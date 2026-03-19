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

    // =========================================================================
    // OSM-Export & Diff
    // =========================================================================

    /**
     * Rendert pro Stein ein Tag-Set aus dem aktiven Tag-Mapping-Template.
     * Lädt nur freigegeben-Steine des Stadtteils (oder aller Stadtteile bei null).
     *
     * @return array{steine: list<array>, stadtteil: string|null, anzahl: int}
     */
    public function osmExport(?int $stadtteilId): array
    {
        $tplTags = $this->templateRepo->findAktiv('osm', 'tags');
        if ($tplTags === null) {
            throw new \RuntimeException('Kein aktives OSM-Tags-Template gefunden (name="tags", zielsystem="osm").');
        }

        $steine = $stadtteilId !== null
            ? $this->loadSteineForStadtteil($stadtteilId)
            : $this->loadAlleSteineFreigegeben();

        $stadtteilName = null;
        if ($stadtteilId !== null) {
            $st = $this->loadStadtteil($stadtteilId);
            $stadtteilName = $st['name'] ?? null;
        }

        $mapping = json_decode($tplTags['inhalt'], true);
        if (!is_array($mapping)) {
            throw new \RuntimeException('OSM-Tags-Template enthält kein gültiges JSON.');
        }

        $result = [];
        foreach ($steine as $stein) {
            $tags = [];
            foreach ($mapping as $tag => $platzhalter) {
                $wert = $this->renderOsmWert((string) $platzhalter, $stein);
                if ($wert !== '') {
                    $tags[$tag] = $wert;
                }
            }
            $result[] = [
                'lokal_id'    => (int) $stein['id'],
                'person'      => trim(($stein['nachname'] ?? '') . ', ' . ($stein['vorname'] ?? '')),
                'adresse'     => trim(($stein['strasse'] ?? '') . ' ' . ($stein['hausnummer'] ?? '')),
                'osm_id'      => $stein['osm_id'] ? (int) $stein['osm_id'] : null,
                'stadtteil'   => $stein['stadtteil'] ?? null,
                'koordinaten' => [
                    'lat' => $stein['lat'] !== null ? (float) $stein['lat'] : null,
                    'lon' => $stein['lon'] !== null ? (float) $stein['lon'] : null,
                ],
                'tags'        => $tags,
            ];
        }

        return [
            'steine'    => $result,
            'stadtteil' => $stadtteilName,
            'anzahl'    => count($result),
        ];
    }

    /**
     * Ruft Overpass ab und vergleicht mit lokalen Daten.
     * $stadtteilId = null → stadtweiter Vergleich.
     *
     * @return array mit zusammenfassung, gematchte, nur_lokal, nur_osm, in_osm_nicht_freigegeben
     * @throws \RuntimeException bei Overpass-Fehler oder fehlenden Templates
     */
    public function osmDiff(?int $stadtteilId): array
    {
        $tplAbfrage = $this->templateRepo->findAktiv('osm', 'abfrage');
        if ($tplAbfrage === null) {
            throw new \RuntimeException('Kein aktives OSM-Abfrage-Template gefunden (name="abfrage", zielsystem="osm").');
        }
        $tplTags = $this->templateRepo->findAktiv('osm', 'tags');
        if ($tplTags === null) {
            throw new \RuntimeException('Kein aktives OSM-Tags-Template gefunden (name="tags", zielsystem="osm").');
        }
        $mapping = json_decode($tplTags['inhalt'], true);
        if (!is_array($mapping)) {
            throw new \RuntimeException('OSM-Tags-Template enthält kein gültiges JSON.');
        }

        $stadtName = $this->loadKonfigurationswert('stadt_name') ?? '';
        $query = strtr($tplAbfrage['inhalt'], ['[[STADT.NAME]]' => $stadtName]);
        $osmNodes = $this->fetchOverpass($query);

        // ALLE Steine laden (alle Status) und Tags für jeden rendern
        $rohdaten = $stadtteilId !== null
            ? $this->loadSteineAlleStatusFuerStadtteil($stadtteilId)
            : $this->loadSteineAlleStatus();

        $alleSteineVerarbeitet = [];
        foreach ($rohdaten as $stein) {
            $tags = [];
            foreach ($mapping as $tag => $platzhalter) {
                $wert = $this->renderOsmWert((string) $platzhalter, $stein);
                if ($wert !== '') {
                    $tags[$tag] = $wert;
                }
            }
            $alleSteineVerarbeitet[] = [
                'lokal_id'    => (int) $stein['id'],
                'person'      => trim(($stein['nachname'] ?? '') . ', ' . ($stein['vorname'] ?? '')),
                'adresse'     => trim(($stein['strasse'] ?? '') . ' ' . ($stein['hausnummer'] ?? '')),
                'osm_id'      => $stein['osm_id'] ? (int) $stein['osm_id'] : null,
                'stadtteil'   => $stein['stadtteil'] ?? null,
                'status'      => $stein['status'] ?? '',
                'koordinaten' => [
                    'lat' => $stein['lat'] !== null ? (float) $stein['lat'] : null,
                    'lon' => $stein['lon'] !== null ? (float) $stein['lon'] : null,
                ],
                'tags' => $tags,
            ];
        }

        $matching = $this->matchOsmNodes($alleSteineVerarbeitet, $osmNodes);
        return $this->buildOsmDiff($matching, $osmNodes);
    }

    /**
     * Generiert eine .osm XML-Datei (JOSM-kompatibel).
     */
    public function osmDatei(?int $stadtteilId): string
    {
        $exportData = $this->osmExport($stadtteilId);
        $steine = $exportData['steine'];

        $xml  = "<?xml version='1.0' encoding='UTF-8'?>\n";
        $xml .= "<osm version='0.6' generator='Stolpersteine-Verwaltungssystem'>\n";

        $negId = -1;
        foreach ($steine as $stein) {
            $lat = $stein['koordinaten']['lat'];
            $lon = $stein['koordinaten']['lon'];
            if ($lat === null || $lon === null) {
                continue; // Kein Koordinaten → überspringen
            }

            $hasOsmId = $stein['osm_id'] !== null;
            $id     = $hasOsmId ? $stein['osm_id'] : $negId--;
            $action = $hasOsmId ? 'modify' : 'create';

            $xml .= sprintf(
                "  <node id='%d' action='%s' visible='true' lat='%.8f' lon='%.8f'>\n",
                $id, $action, $lat, $lon
            );
            foreach ($stein['tags'] as $k => $v) {
                $xml .= sprintf(
                    "    <tag k='%s' v='%s'/>\n",
                    htmlspecialchars($k, ENT_XML1 | ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8')
                );
            }
            $xml .= "  </node>\n";
        }
        $xml .= "</osm>\n";

        return $xml;
    }

    /**
     * POST an Overpass API, gibt Array von Nodes zurück.
     *
     * @return list<array{id: int, lat: float, lon: float, tags: array<string,string>}>
     * @throws \RuntimeException bei Netzwerkfehler oder ungültiger Antwort
     */
    private function fetchOverpass(string $query): array
    {
        $ch = curl_init('https://overpass-api.de/api/interpreter');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => 'data=' . urlencode($query),
            CURLOPT_TIMEOUT        => 90,
            CURLOPT_HTTPHEADER     => [
                'User-Agent: Stolpersteine-Verwaltung/1.0 (https://github.com/stolpersteine)',
            ],
        ]);
        $body = curl_exec($ch);
        $http  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($errno || $body === false) {
            throw new \RuntimeException('Overpass API nicht erreichbar (cURL-Fehler).');
        }
        if ($http !== 200) {
            throw new \RuntimeException("Overpass API antwortete mit HTTP $http.");
        }

        $data = json_decode($body, true);
        if (!isset($data['elements'])) {
            throw new \RuntimeException('Overpass-Antwort enthält kein "elements"-Feld.');
        }

        $nodes = [];
        foreach ($data['elements'] as $el) {
            if ($el['type'] !== 'node') continue;
            $nodes[] = [
                'id'   => (int) $el['id'],
                'lat'  => (float) $el['lat'],
                'lon'  => (float) $el['lon'],
                'tags' => $el['tags'] ?? [],
            ];
        }
        return $nodes;
    }

    /**
     * Matched lokale Steine gegen OSM-Nodes.
     * Pass 1: OSM-ID-Match (alle Status)
     * Pass 2: Koordinaten-Match (nur freigegeben, ≤ 15 m)
     *
     * @param array $alleSteineVerarbeitet Vereinheitlichtes Array mit lokal_id, status, koordinaten, tags, …
     */
    private function matchOsmNodes(array $alleSteineVerarbeitet, array $osmNodes): array
    {
        $osmById = [];
        foreach ($osmNodes as $node) {
            $osmById[$node['id']] = $node;
        }
        $gematchteOsmIds   = [];
        $gematchteLokalIds = [];

        $gematchte             = [];
        $inOsmNichtFreigegeben = [];

        // Pass 1: OSM-ID-Match für alle Status
        foreach ($alleSteineVerarbeitet as $stein) {
            if (empty($stein['osm_id'])) continue;
            $osmId = (int) $stein['osm_id'];
            if (!isset($osmById[$osmId])) continue;

            $gematchteOsmIds[$osmId]               = true;
            $gematchteLokalIds[$stein['lokal_id']] = true;
            $osmNode = $osmById[$osmId];

            if ($stein['status'] !== 'freigegeben') {
                $inOsmNichtFreigegeben[] = [
                    'lokal'     => $stein,
                    'osm'       => $osmNode,
                    'match_typ' => 'osm_id',
                ];
            } else {
                $gematchte[] = [
                    'lokal'     => $stein,
                    'osm'       => $osmNode,
                    'match_typ' => 'osm_id',
                ];
            }
        }

        // Pass 2: Koordinaten-Match für freigegeben-Steine ohne bisherigen Match
        foreach ($alleSteineVerarbeitet as $stein) {
            if ($stein['status'] !== 'freigegeben') continue;
            if (isset($gematchteLokalIds[$stein['lokal_id']])) continue;
            $lat = $stein['koordinaten']['lat'];
            $lon = $stein['koordinaten']['lon'];
            if ($lat === null || $lon === null) continue;

            $naechsterNode    = null;
            $naechsterAbstand = PHP_FLOAT_MAX;

            foreach ($osmNodes as $node) {
                if (isset($gematchteOsmIds[$node['id']])) continue;
                $abstand = $this->haversine($lat, $lon, $node['lat'], $node['lon']);
                if ($abstand < $naechsterAbstand) {
                    $naechsterAbstand = $abstand;
                    $naechsterNode    = $node;
                }
            }

            if ($naechsterNode !== null && $naechsterAbstand <= 15.0) {
                $gematchteOsmIds[$naechsterNode['id']]   = true;
                $gematchteLokalIds[$stein['lokal_id']]   = true;
                $gematchte[] = [
                    'lokal'     => $stein,
                    'osm'       => $naechsterNode,
                    'match_typ' => 'koordinaten',
                ];
            }
        }

        // Nur-lokal: freigegeben ohne Match
        $nurLokal = [];
        foreach ($alleSteineVerarbeitet as $stein) {
            if ($stein['status'] !== 'freigegeben') continue;
            if (!isset($gematchteLokalIds[$stein['lokal_id']])) {
                $nurLokal[] = $stein;
            }
        }

        // Nur-OSM: OSM-Nodes ohne Match
        $nurOsm = [];
        foreach ($osmNodes as $node) {
            if (!isset($gematchteOsmIds[$node['id']])) {
                $nurOsm[] = $node;
            }
        }

        return compact('gematchte', 'nurLokal', 'nurOsm', 'inOsmNichtFreigegeben');
    }

    /**
     * Baut die vollständige Diff-Datenstruktur.
     */
    private function buildOsmDiff(array $matching, array $osmNodes): array
    {
        $gematchteErgebnis         = [];
        $inOsmNichtFreigErgebnis   = [];
        $matchedOsmId              = 0;
        $matchedKoord              = 0;

        // Hilfsfunktion: Diff-Eintrag für ein Paar bauen
        $buildPaarDiff = function(array $paar) use (&$matchedOsmId, &$matchedKoord): array {
            $lokalStein = $paar['lokal'];
            $osmNode    = $paar['osm'];

            if ($paar['match_typ'] === 'osm_id') {
                $matchedOsmId++;
            } else {
                $matchedKoord++;
            }

            $lokalLat = $lokalStein['koordinaten']['lat'];
            $lokalLon = $lokalStein['koordinaten']['lon'];
            $osmLat   = (float) $osmNode['lat'];
            $osmLon   = (float) $osmNode['lon'];
            $abstand  = ($lokalLat !== null && $lokalLon !== null)
                ? round($this->haversine($lokalLat, $lokalLon, $osmLat, $osmLon), 1)
                : null;

            // Koordinaten-Einträge am Anfang der Tag-Diffs
            $tagDiffs        = [];
            $hatUnterschiede = false;

            $lokalLatStr = $lokalLat !== null ? (string) round($lokalLat, 6) : null;
            $lokalLonStr = $lokalLon !== null ? (string) round($lokalLon, 6) : null;
            $osmLatStr   = (string) round($osmLat, 6);
            $osmLonStr   = (string) round($osmLon, 6);

            $latGleich = $lokalLatStr !== null && abs($lokalLat - $osmLat) < 0.000001;
            $lonGleich = $lokalLonStr !== null && abs($lokalLon - $osmLon) < 0.000001;

            $tagDiffs[] = [
                'tag'      => 'lat',
                'lokal'    => $lokalLatStr,
                'osm'      => $osmLatStr,
                'in_lokal' => $lokalLatStr !== null,
                'in_osm'   => true,
                'gleich'   => $latGleich,
                'abstand_m' => $abstand,
            ];
            $tagDiffs[] = [
                'tag'      => 'lon',
                'lokal'    => $lokalLonStr,
                'osm'      => $osmLonStr,
                'in_lokal' => $lokalLonStr !== null,
                'in_osm'   => true,
                'gleich'   => $lonGleich,
                'abstand_m' => $abstand,
            ];

            // Tag-Vergleich
            $alleTags = array_unique(array_merge(
                array_keys($lokalStein['tags']),
                array_keys($osmNode['tags'])
            ));
            sort($alleTags);

            foreach ($alleTags as $tag) {
                $lokalWert = $lokalStein['tags'][$tag] ?? null;
                $osmWert   = $osmNode['tags'][$tag] ?? null;
                $gleich    = $lokalWert === $osmWert;
                if (!$gleich) {
                    $hatUnterschiede = true;
                }
                $tagDiffs[] = [
                    'tag'      => $tag,
                    'lokal'    => $lokalWert,
                    'osm'      => $osmWert,
                    'in_lokal' => $lokalWert !== null,
                    'in_osm'   => $osmWert !== null,
                    'gleich'   => $gleich,
                ];
            }

            return [
                'lokal_id'         => $lokalStein['lokal_id'],
                'osm_id'           => $osmNode['id'],
                'person'           => $lokalStein['person'],
                'adresse'          => $lokalStein['adresse'],
                'status'           => $lokalStein['status'],
                'match_typ'        => $paar['match_typ'],
                'koordinaten'      => [
                    'lokal'     => ['lat' => $lokalLat, 'lon' => $lokalLon],
                    'osm'       => ['lat' => $osmLat,   'lon' => $osmLon],
                    'abstand_m' => $abstand,
                ],
                'tag_diffs'        => $tagDiffs,
                'hat_unterschiede' => $hatUnterschiede,
            ];
        };

        foreach ($matching['gematchte'] as $paar) {
            $gematchteErgebnis[] = $buildPaarDiff($paar);
        }

        foreach ($matching['inOsmNichtFreigegeben'] as $paar) {
            $inOsmNichtFreigErgebnis[] = $buildPaarDiff($paar);
        }

        $mitTagUnterschieden  = count(array_filter($gematchteErgebnis, fn($g) => $g['hat_unterschiede']));
        $mitKoordAbweichung   = count(array_filter($gematchteErgebnis, fn($g) => ($g['koordinaten']['abstand_m'] ?? 0) > 5));

        return [
            'zusammenfassung' => [
                'lokal_gesamt'               => count($matching['gematchte']) + count($matching['nurLokal']) + count($matching['inOsmNichtFreigegeben']),
                'osm_gesamt'                 => count($osmNodes),
                'gematched_osm_id'           => $matchedOsmId,
                'gematched_koordinaten'      => $matchedKoord,
                'nur_lokal'                  => count($matching['nurLokal']),
                'nur_osm'                    => count($matching['nurOsm']),
                'in_osm_nicht_freigegeben'   => count($matching['inOsmNichtFreigegeben']),
                'mit_tag_unterschieden'      => $mitTagUnterschieden,
                'mit_koordinaten_abweichung' => $mitKoordAbweichung,
            ],
            'gematchte'                 => $gematchteErgebnis,
            'nur_lokal'                 => $matching['nurLokal'],
            'nur_osm'                   => $matching['nurOsm'],
            'in_osm_nicht_freigegeben'  => $inOsmNichtFreigErgebnis,
        ];
    }

    /**
     * Haversine-Distanz in Metern zwischen zwei Koordinaten.
     */
    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r  = 6371000; // Erdradius in Metern
        $p  = M_PI / 180;
        $a  = 0.5 - cos(($lat2 - $lat1) * $p) / 2
            + cos($lat1 * $p) * cos($lat2 * $p) * (1 - cos(($lon2 - $lon1) * $p)) / 2;
        return 2 * $r * asin(sqrt($a));
    }

    /**
     * Rendert einen einzelnen OSM-Tag-Wert mit Platzhaltern.
     */
    private function renderOsmWert(string $vorlage, array $stein): string
    {
        $vars = $this->buildRenderVars($stein);
        $result = strtr($vorlage, $vars);
        // Wenn keine Platzhalter enthalten waren (Literal-Wert), direkt zurückgeben
        return trim($result);
    }

    /**
     * Baut das vollständige Variablen-Array für Platzhalter-Substitution.
     * Wird von renderZeile() und renderOsmWert() genutzt.
     */
    private function buildRenderVars(array $stein): array
    {
        return [
            '[[PERSON.VORNAME]]'          => $stein['vorname']              ?? '',
            '[[PERSON.NACHNAME]]'         => $stein['nachname']             ?? '',
            '[[PERSON.GEBURTSNAME]]'      => $stein['geburtsname']          ?? '',
            '[[PERSON.GEBURTSDATUM]]'     => $this->formatDatum($stein['geburtsdatum'], $stein['geburtsdatum_genauigkeit']),
            '[[PERSON.STERBEDATUM]]'      => $this->formatDatum($stein['sterbedatum'],  $stein['sterbedatum_genauigkeit']),
            '[[PERSON.WIKIPEDIA_NAME]]'   => $stein['person_wikipedia_name'] ?? '',
            '[[PERSON.WIKIDATA_ID]]'      => $stein['wikidata_id_person']   ?? '',
            '[[PERSON.BIOGRAFIE_KURZ]]'   => $stein['biografie_kurz']       ?? '',
            '[[PERSON.NAME_VOLL]]'        => trim(
                ($stein['nachname'] ?? '') . ', ' . ($stein['vorname'] ?? '')
                . (($stein['geburtsname'] ?? '') !== '' ? ' geb. ' . $stein['geburtsname'] : '')
            ),
            // OSM-spezifisch: Vorname Nachname (geb. Geburtsname)
            '[[PERSON.NAME_OSM]]'         => trim(
                ($stein['vorname'] ?? '') . ' ' . ($stein['nachname'] ?? '')
                . (($stein['geburtsname'] ?? '') !== '' ? ' (geb. ' . $stein['geburtsname'] . ')' : '')
            ),

            '[[ORT.STRASSE]]'              => $stein['strasse']              ?? '',
            '[[ORT.STRASSE_WIKIPEDIA]]'    => $stein['strasse_wikipedia_name'] ?? '',
            '[[ORT.HAUSNUMMER]]'           => $stein['hausnummer']           ?? '',
            '[[ORT.STADTTEIL]]'            => $stein['stadtteil']            ?? '',
            '[[ORT.STADT]]'               => $stein['stadt']               ?? '',
            '[[ORT.PLZ]]'                  => $stein['plz']                  ?? '',
            '[[ORT.BEMERKUNG_HISTORISCH]]' => $stein['bemerkung_historisch'] ?? '',
            '[[ORT.BESCHREIBUNG]]'         => $stein['verlegeort_beschreibung'] ?? '',
            '[[ORT.ADRESSE]]'              => trim(
                ($stein['strasse'] ?? '')
                . (($stein['hausnummer'] ?? '') !== '' ? ' ' . $stein['hausnummer'] : '')
                . (($stein['verlegeort_beschreibung'] ?? '') !== '' ? ', ' . $stein['verlegeort_beschreibung'] : '')
            ),

            '[[STEIN.VERLEGEDATUM]]'          => $this->formatDatumKurz($stein['verlegedatum']),
            '[[STEIN.VERLEGEDATUM_ISO]]'      => $stein['verlegedatum'] ?? '',
            '[[STEIN.INSCHRIFT]]'             => mb_strtoupper($stein['inschrift']  ?? '', 'UTF-8'),
            '[[STEIN.INSCHRIFT_BR]]'          => str_replace("\n", "<br />", mb_strtoupper($stein['inschrift'] ?? '', 'UTF-8')),
            // OSM-spezifisch: Inschrift wie in der DB, Zeilenumbrüche als " | "
            '[[STEIN.INSCHRIFT_ORIGINAL]]'    => str_replace("\n", ' | ', $stein['inschrift'] ?? ''),
            '[[STEIN.LAT]]'               => $stein['lat'] !== null ? rtrim(rtrim((string)$stein['lat'], '0'), '.') : '',
            '[[STEIN.LON]]'               => $stein['lon'] !== null ? rtrim(rtrim((string)$stein['lon'], '0'), '.') : '',
            '[[STEIN.WIKIMEDIA_COMMONS]]' => $stein['wikimedia_commons']    ?? '',
            '[[STEIN.FOTO_AUTOR]]'        => $stein['foto_lizenz_autor']    ?? '',
            '[[STEIN.FOTO_LIZENZ]]'       => $stein['foto_lizenz_name']     ?? '',
            '[[STEIN.FOTO_LIZENZ_URL]]'   => $stein['foto_lizenz_url']      ?? '',
            '[[STEIN.WIKIDATA_ID]]'       => $stein['wikidata_id_stein']    ?? '',
            '[[STEIN.OSM_ID]]'            => $stein['osm_id'] !== null ? (string)$stein['osm_id'] : '',
            '[[STEIN.STATUS]]'            => $stein['status']               ?? '',
            '[[STEIN.ZUSTAND]]'           => $stein['zustand']              ?? '',

            '[[DOK.URL]]'         => $stein['dok_url']                      ?? '',
            '[[DOK.DATEINAME]]'   => $stein['dok_dateiname']                ?? '',
            '[[DOK.LIZENZ]]'      => $stein['dok_lizenz']                   ?? '',
            '[[DOK.TYP_GROESSE]]' => $this->formatDokTypGroesse(
                $stein['dok_url']           ?? null,
                isset($stein['dok_groesse_bytes']) ? (int) $stein['dok_groesse_bytes'] : null
            ),
            '[[DOK.GROESSE_KB]]'  => $this->formatGroesseKb(
                isset($stein['dok_groesse_bytes']) ? (int) $stein['dok_groesse_bytes'] : null
            ),
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
        return $this->loadSteineQuery('WHERE st.id = ? AND s.status = ?', [$stadtteilId, 'freigegeben']);
    }

    private function loadAlleSteineFreigegeben(): array
    {
        return $this->loadSteineQuery('WHERE s.status = ?', ['freigegeben']);
    }

    /**
     * Alle Steine (alle Status) für ein bestimmtes Stadtteil – für OSM-ID-Matching.
     */
    private function loadSteineAlleStatusFuerStadtteil(int $stadtteilId): array
    {
        return $this->loadSteineQuery('WHERE st.id = ?', [$stadtteilId], allStatus: true);
    }

    /**
     * Alle Steine (alle Status) der gesamten Stadt – für OSM-ID-Matching.
     */
    private function loadSteineAlleStatus(): array
    {
        return $this->loadSteineQuery('', [], allStatus: true);
    }

    private function loadSteineQuery(string $where, array $params, bool $allStatus = false): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                s.id,
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
                sta.name                        AS stadt,
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
                s.zustand,
                dok.quelle_url          AS dok_url,
                dok.titel               AS dok_titel,
                dok.dateiname           AS dok_dateiname,
                dok.quelle              AS dok_lizenz,
                dok.typ                 AS dok_typ,
                dok.groesse_bytes       AS dok_groesse_bytes
             FROM stolpersteine s
             JOIN personen     p   ON p.id  = s.person_id
             JOIN verlegeorte  v   ON v.id  = s.verlegeort_id
             LEFT JOIN adress_lokationen al  ON al.id  = v.adress_lokation_id
             LEFT JOIN strassen          str ON str.id = al.strasse_id
             LEFT JOIN stadtteile        st  ON st.id  = al.stadtteil_id
             LEFT JOIN staedte           sta ON sta.id = st.stadt_id
             LEFT JOIN plz                   ON plz.id = al.plz_id
             LEFT JOIN dokumente dok         ON dok.id = p.biografie_dokument_id
             ' . $where . '
             ORDER BY p.nachname, p.vorname'
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function loadKonfigurationswert(string $schluessel): ?string
    {
        $stmt = $this->pdo->prepare('SELECT wert FROM konfiguration WHERE schluessel = ?');
        $stmt->execute([$schluessel]);
        $row = $stmt->fetch();
        return $row ? $row['wert'] : null;
    }

    private function renderZeile(string $vorlage, array $stein): string
    {
        $vars = $this->buildRenderVars($stein);

        // [[PERSON.BIOGRAFIE_LANG]] nur im Wikipedia-Kontext (aufwändige Berechnung)
        $vars['[[PERSON.BIOGRAFIE_LANG]]'] = $this->formatBiografieLang(
            $stein['biografie_kurz']  ?? null,
            $stein['dok_url']         ?? null,
            $stein['dok_titel']       ?? null,
            $stein['dok_dateiname']   ?? null,
            $stein['dok_lizenz']      ?? null,
            $stein['dok_typ']         ?? null,
            isset($stein['dok_groesse_bytes']) ? (int) $stein['dok_groesse_bytes'] : null
        );

        return strtr($vorlage, $vars);
    }

    /**
     * Formatiert Typ und Größe eines Dokuments: "(PDF; 173,1 kB)" oder "(PDF)"
     */
    private function formatDokTypGroesse(?string $url, ?int $bytes): string
    {
        if ($url === null) {
            return '';
        }
        $ext = strtoupper(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        $typ = $ext !== '' ? $ext : 'PDF';

        $groesse = $this->formatGroesseKb($bytes);
        return $groesse !== '' ? '(' . $typ . '; ' . $groesse . ')' : '(' . $typ . ')';
    }

    /**
     * Formatiert Bytes als "173,1 kB" (deutsches Dezimalzeichen).
     */
    private function formatGroesseKb(?int $bytes): string
    {
        if ($bytes === null || $bytes <= 0) {
            return '';
        }
        return number_format($bytes / 1024.0, 1, ',', '.') . ' kB';
    }

    /**
     * Baut [[PERSON.BIOGRAFIE_LANG]]:
     * "Biografie: " + kurz + <br/><br/> (nur wenn beide) + [URL ''Titel''] (Quelle; TYP; Größe)
     */
    private function formatBiografieLang(
        ?string $kurz,
        ?string $url,
        ?string $titel,
        ?string $dateiname,
        ?string $quelle,
        ?string $typ,
        ?int    $bytes
    ): string {
        $kurz = ($kurz !== null && $kurz !== '') ? $kurz : null;
        $url  = ($url  !== null && $url  !== '') ? $url  : null;

        if ($kurz === null && $url === null) {
            return '';
        }

        $parts = ['Biografie: '];

        if ($kurz !== null) {
            $parts[] = $kurz;
        }

        if ($kurz !== null && $url !== null) {
            $parts[] = '<br /><br />';
        }

        if ($url !== null) {
            $anzeige = $titel ?: $dateiname ?: $url;
            $link    = "[{$url} ''{$anzeige}'']";

            $suffix = [];
            if ($quelle !== null && $quelle !== '') {
                $suffix[] = $quelle;
            }
            if ($typ !== null && $typ !== '') {
                $suffix[] = strtoupper($typ);
            }
            if ($bytes !== null && $bytes > 0) {
                $suffix[] = number_format($bytes / 1024.0, 1, ',', '.') . '&nbsp;kB';
            }

            if ($suffix !== []) {
                $link .= ' (' . implode('; ', $suffix) . ')';
            }

            $parts[] = $link;
        }

        return implode('', $parts);
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
