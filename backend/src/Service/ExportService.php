<?php

declare(strict_types=1);

namespace Stolpersteine\Service;

use PDO;
use Stolpersteine\Config\Database;
use Stolpersteine\Repository\TemplateRepository;

class ExportService
{
    private PDO $pdo;
    private TemplateRepository $templateRepo;

    // Deutsche Monatsnamen für Datumsformatierung
    private const MONATE = [
        1  => 'Januar',   2  => 'Februar',  3  => 'März',
        4  => 'April',    5  => 'Mai',      6  => 'Juni',
        7  => 'Juli',     8  => 'August',   9  => 'September',
        10 => 'Oktober',  11 => 'November', 12 => 'Dezember',
    ];

    public function __construct()
    {
        $this->pdo          = Database::connection();
        $this->templateRepo = new TemplateRepository();
    }

    /**
     * Erzeugt den vollständigen Wikitext für eine Wikipedia-Seite eines Stadtteils.
     *
     * @return array{wikitext: string, stadtteil: string, wikipedia_name: string|null, anzahl: int}
     * @throws \InvalidArgumentException wenn kein Template oder Stadtteil gefunden
     */
    public function wikipedia(int $stadtteilId): array
    {
        // Stadtteil laden
        $stadtteil = $this->loadStadtteil($stadtteilId);
        if ($stadtteil === null) {
            throw new \InvalidArgumentException('Stadtteil nicht gefunden.');
        }

        // Templates laden
        $seitenTemplate = $this->templateRepo->findAktiv('wikipedia', 'seite');
        $zeilenTemplate = $this->templateRepo->findAktiv('wikipedia', 'zeile');

        if ($seitenTemplate === null) {
            throw new \RuntimeException('Kein aktives Wikipedia-Seitentemplate gefunden (name="seite", zielsystem="wikipedia").');
        }
        if ($zeilenTemplate === null) {
            throw new \RuntimeException('Kein aktives Wikipedia-Zeilentemplate gefunden (name="zeile", zielsystem="wikipedia").');
        }

        // Alle Stolpersteine des Stadtteils laden (sortiert nach Nachname, Vorname)
        $steine = $this->loadSteineForStadtteil($stadtteilId);

        // Jede Zeile rendern
        $zeilen = [];
        foreach ($steine as $stein) {
            $zeilen[] = $this->renderZeile($zeilenTemplate['inhalt'], $stein);
        }
        $zeilenWikitext = implode("\n", $zeilen);

        // Seite rendern
        $wpStadtteil = $stadtteil['wikipedia_stadtteil'] ?? '';
        $seitenVars = [
            '[[SEITE.STADTTEIL]]'                   => $stadtteil['name'],
            '[[SEITE.STADTTEIL_WIKIDATA]]'          => $stadtteil['wikidata_id']              ?? '',
            '[[SEITE.STADTTEIL_WIKIPEDIA]]'         => $wpStadtteil,
            '[[SEITE.STADTTEIL_WIKIPEDIA_LINK]]'    => $wpStadtteil !== ''
                ? ($wpStadtteil === $stadtteil['name']
                    ? '[[' . $wpStadtteil . ']]'
                    : '[[' . $wpStadtteil . '|' . $stadtteil['name'] . ']]')
                : $stadtteil['name'],
            '[[SEITE.STOLPERSTEINE_WIKIPEDIA]]'     => $stadtteil['wikipedia_stolpersteine'] ?? '',
            '[[SEITE.ZEILEN]]'                      => $zeilenWikitext,
            '[[SEITE.ANZAHL_ZEILEN]]'               => (string) count($steine),
        ];
        $wikitext = strtr($seitenTemplate['inhalt'], $seitenVars);

        return [
            'wikitext'       => $wikitext,
            'stadtteil'      => $stadtteil['name'],
            'wikipedia_name' => $stadtteil['wikipedia_stolpersteine'],
            'anzahl'         => count($steine),
        ];
    }

    /**
     * Erzeugt lokalen Wikitext und holt den Live-Wikitext von Wikipedia zum Vergleich.
     *
     * @return array{lokal: string, live: string|null, seitenname: string, anzahl: int}
     */
    public function wikipediaDiff(int $stadtteilId): array
    {
        $result = $this->wikipedia($stadtteilId);
        $live   = $result['wikipedia_name']
            ? $this->fetchWikipediaLive($result['wikipedia_name'])
            : null;

        return [
            'lokal'      => $result['wikitext'],
            'live'       => $live,
            'seitenname' => $result['wikipedia_name'] ?? '',
            'anzahl'     => $result['anzahl'],
        ];
    }

    /**
     * Holt den aktuellen Wikitext einer Wikipedia-Seite via REST-API.
     * Gibt null zurück wenn die Seite nicht gefunden wird oder ein Fehler auftritt.
     */
    private function fetchWikipediaLive(string $seitenname): ?string
    {
        $url = 'https://de.wikipedia.org/w/api.php?' . http_build_query([
            'action'  => 'query',
            'prop'    => 'revisions',
            'titles'  => $seitenname,
            'rvprop'  => 'content',
            'rvslots' => 'main',
            'format'  => 'json',
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'User-Agent: Stolpersteine-Verwaltung/1.0 (https://github.com/stolpersteine)',
                'Accept: application/json',
            ],
        ]);
        $body = curl_exec($ch);
        $http  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $http !== 200) {
            return null;
        }

        $data  = json_decode($body, true);
        $pages = $data['query']['pages'] ?? [];
        $page  = reset($pages);

        if (!$page || isset($page['missing'])) {
            return null;
        }

        return $page['revisions'][0]['slots']['main']['*'] ?? null;
    }

    // -------------------------------------------------------------------------
    // Private Hilfsmethoden
    // -------------------------------------------------------------------------

    private function loadStadtteil(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, wikidata_id, wikipedia_stadtteil, wikipedia_stolpersteine FROM stadtteile WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function loadSteineForStadtteil(int $stadtteilId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                p.vorname,                      p.nachname,             p.geburtsname,
                p.geburtsdatum,                 p.geburtsdatum_genauigkeit,
                p.sterbedatum,                  p.sterbedatum_genauigkeit,
                p.wikipedia_name                AS person_wikipedia_name,
                p.wikidata_id_person,
                p.biografie_kurz,
                str.name                        AS strasse,
                str.wikipedia_name              AS strasse_wikipedia_name,
                v.hausnummer_aktuell            AS hausnummer,
                st.name                         AS stadtteil,
                plz.plz,
                v.bemerkung_historisch,
                v.beschreibung          AS verlegeort_beschreibung,
                s.verlegedatum,
                s.inschrift,
                COALESCE(s.lat_override, v.lat) AS lat,
                COALESCE(s.lon_override, v.lon) AS lon,
                s.wikimedia_commons,
                s.foto_lizenz_autor,
                s.foto_lizenz_name,
                s.foto_lizenz_url,
                s.wikidata_id_stein,
                s.osm_id,
                s.status,
                s.zustand
             FROM stolpersteine s
             JOIN personen     p   ON p.id  = s.person_id
             JOIN verlegeorte  v   ON v.id  = s.verlegeort_id
             LEFT JOIN adress_lokationen al  ON al.id  = v.adress_lokation_id
             LEFT JOIN strassen          str ON str.id = al.strasse_id
             LEFT JOIN stadtteile        st  ON st.id  = al.stadtteil_id
             LEFT JOIN plz                   ON plz.id = al.plz_id
             WHERE st.id = ?
             ORDER BY p.nachname, p.vorname'
        );
        $stmt->execute([$stadtteilId]);
        return $stmt->fetchAll();
    }

    private function renderZeile(string $vorlage, array $stein): string
    {
        $vars = [
            // Person
            '[[PERSON.VORNAME]]'          => $stein['vorname']              ?? '',
            '[[PERSON.NACHNAME]]'         => $stein['nachname']             ?? '',
            '[[PERSON.GEBURTSNAME]]'      => $stein['geburtsname']          ?? '',
            '[[PERSON.GEBURTSDATUM]]'     => $this->formatDatum($stein['geburtsdatum'], $stein['geburtsdatum_genauigkeit']),
            '[[PERSON.STERBEDATUM]]'      => $this->formatDatum($stein['sterbedatum'],  $stein['sterbedatum_genauigkeit']),
            '[[PERSON.WIKIPEDIA_NAME]]'   => $stein['person_wikipedia_name'] ?? '',
            '[[PERSON.WIKIDATA_ID]]'      => $stein['wikidata_id_person']   ?? '',

            // Ort
            '[[ORT.STRASSE]]'              => $stein['strasse']              ?? '',
            '[[ORT.STRASSE_WIKIPEDIA]]'    => $stein['strasse_wikipedia_name'] ?? '',
            '[[ORT.HAUSNUMMER]]'           => $stein['hausnummer']           ?? '',
            '[[ORT.STADTTEIL]]'            => $stein['stadtteil']            ?? '',
            '[[ORT.PLZ]]'                  => $stein['plz']                  ?? '',
            '[[ORT.BEMERKUNG_HISTORISCH]]' => $stein['bemerkung_historisch'] ?? '',
            '[[ORT.BESCHREIBUNG]]'         => $stein['verlegeort_beschreibung'] ?? '',
            '[[ORT.ADRESSE]]'              => trim(
                ($stein['strasse'] ?? '')
                . (($stein['hausnummer'] ?? '') !== '' ? ' ' . $stein['hausnummer'] : '')
                . (($stein['verlegeort_beschreibung'] ?? '') !== '' ? ', ' . $stein['verlegeort_beschreibung'] : '')
            ),

            // Stein
            '[[STEIN.VERLEGEDATUM]]'      => $this->formatDatumKurz($stein['verlegedatum']),
            '[[STEIN.INSCHRIFT]]'         => mb_strtoupper($stein['inschrift']  ?? '', 'UTF-8'),
            '[[STEIN.INSCHRIFT_BR]]'      => str_replace("\n", "<br />", mb_strtoupper($stein['inschrift'] ?? '', 'UTF-8')),
            '[[STEIN.LAT]]'               => $stein['lat'] !== null ? rtrim(rtrim((string)$stein['lat'], '0'), '.') : '',
            '[[STEIN.LON]]'               => $stein['lon'] !== null ? rtrim(rtrim((string)$stein['lon'], '0'), '.') : '',
            '[[STEIN.WIKIMEDIA_COMMONS]]' => $stein['wikimedia_commons']    ?? '',
            '[[STEIN.FOTO_AUTOR]]'        => $stein['foto_lizenz_autor']    ?? '',
            '[[STEIN.FOTO_LIZENZ]]'       => $stein['foto_lizenz_name']     ?? '',
            '[[STEIN.FOTO_LIZENZ_URL]]'   => $stein['foto_lizenz_url']      ?? '',
            '[[STEIN.WIKIDATA_ID]]'       => $stein['wikidata_id_stein']    ?? '',
            '[[STEIN.OSM_ID]]'            => $stein['osm_id']               ?? '',
            '[[STEIN.STATUS]]'            => $stein['status']               ?? '',
            '[[STEIN.ZUSTAND]]'           => $stein['zustand']              ?? '',

            // Person (zusammengesetzt)
            '[[PERSON.BIOGRAFIE_KURZ]]'   => $stein['biografie_kurz']       ?? '',
            '[[PERSON.NAME_VOLL]]'        => trim(
                $stein['nachname'] . ', ' . $stein['vorname']
                . (($stein['geburtsname'] ?? '') !== '' ? ' geb. ' . $stein['geburtsname'] : '')
            ),
        ];

        return strtr($vorlage, $vars);
    }

    /**
     * Formatiert ein ISO-Datum (YYYY-MM-DD) als DD.MM.YYYY (für Wikipedia-Vorlagen).
     */
    private function formatDatumKurz(?string $iso): string
    {
        if ($iso === null || $iso === '') {
            return '';
        }
        $parts = explode('-', $iso);
        if (count($parts) !== 3) {
            return $iso;
        }
        return sprintf('%02d.%02d.%04d', (int)$parts[2], (int)$parts[1], (int)$parts[0]);
    }

    /**
     * Formatiert ein ISO-Datum (YYYY-MM-DD) nach Genauigkeit auf Deutsch.
     * Genauigkeit: 'tag' → "15. März 1910", 'monat' → "März 1910", 'jahr' → "1910"
     */
    private function formatDatum(?string $iso, ?string $genauigkeit): string
    {
        if ($iso === null || $iso === '') {
            return '';
        }

        $parts = explode('-', $iso);
        $year  = (int) ($parts[0] ?? 0);
        $month = (int) ($parts[1] ?? 0);
        $day   = (int) ($parts[2] ?? 0);

        if ($year === 0) {
            return $iso;
        }

        return match ($genauigkeit) {
            'jahr'  => (string) $year,
            'monat' => (self::MONATE[$month] ?? '') . ' ' . $year,
            default => $day . '. ' . (self::MONATE[$month] ?? '') . ' ' . $year,
        };
    }
}
