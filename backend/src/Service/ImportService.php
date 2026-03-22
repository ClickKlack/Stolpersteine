<?php

declare(strict_types=1);

namespace Stolpersteine\Service;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Stolpersteine\Repository\PersonRepository;
use Stolpersteine\Repository\VerlegeortRepository;
use Stolpersteine\Repository\StolpersteinRepository;
use Stolpersteine\Repository\AdresseRepository;
use Stolpersteine\Repository\DokumentRepository;
use Stolpersteine\Repository\AuditRepository;
use Stolpersteine\Config\Logger;
use Stolpersteine\Service\DokumentService;

class ImportService
{
    // Felder die aus der Excel-Datei gemappt werden können
    private const PERSON_FIELDS = [
        'nachname', 'vorname', 'geburtsname',
        'geburtsdatum', 'sterbedatum', 'biografie_kurz',
        'wikipedia_name', 'wikidata_id_person',
    ];

    private const VERLEGEORT_FIELDS = [
        'strasse_aktuell', 'hausnummer_aktuell', 'stadtteil', 'plz_aktuell',
        'wikidata_id_strasse', 'wikidata_id_stadtteil',
        'lat', 'lon', 'beschreibung', 'bemerkung_historisch', 'grid_n', 'grid_m',
    ];

    // Freitext-Felder, aus denen HTML-Tags entfernt werden
    private const HTML_STRIP_FIELDS = ['biografie_kurz', 'bemerkung_historisch', 'beschreibung'];

    private const STEIN_FIELDS = [
        'verlegedatum', 'inschrift', 'wikidata_id_stein', 'osm_id',
        'pos_x', 'pos_y', 'lat_override', 'lon_override',
        'wikimedia_commons', 'foto_lizenz_autor', 'foto_lizenz_name', 'foto_lizenz_url',
        'zustand',
    ];

    private const DOKUMENT_FIELDS = [
        'dokument_url',
        'dokument_ist_biografie',
    ];

    private const DATE_FIELDS = ['geburtsdatum', 'sterbedatum', 'verlegedatum'];

    private PersonRepository $personRepo;
    private VerlegeortRepository $ortRepo;
    private StolpersteinRepository $steinRepo;
    private AdresseRepository $adresseRepo;
    private DokumentRepository $dokumentRepo;
    private DokumentService $dokumentService;

    // Stadtname aus konfiguration (gecacht)
    private ?string $stadtName = null;

    public function __construct()
    {
        $this->personRepo      = new PersonRepository();
        $this->ortRepo         = new VerlegeortRepository();
        $this->steinRepo       = new StolpersteinRepository();
        $this->adresseRepo     = new AdresseRepository();
        $this->dokumentRepo    = new DokumentRepository();
        $this->dokumentService = new DokumentService();
    }

    private function stadtName(): string
    {
        if ($this->stadtName === null) {
            $pdo  = \Stolpersteine\Config\Database::connection();
            $stmt = $pdo->prepare("SELECT wert FROM konfiguration WHERE schluessel = 'stadt_name' LIMIT 1");
            $stmt->execute();
            $this->stadtName = (string) ($stmt->fetchColumn() ?: 'Magdeburg');
        }
        return $this->stadtName;
    }

    // Extrahiert den reinen Dateinamen aus einer Wikimedia-Commons-URL oder File:-Angabe
    // z.B. "https://commons.wikimedia.org/wiki/File:Foo.jpg" → "Foo.jpg"
    //      "File:Foo.jpg"                                    → "Foo.jpg"
    //      "Foo.jpg"                                         → "Foo.jpg"
    private function normalizeCommonsDateiname(?string $wert): ?string
    {
        if ($wert === null || $wert === '') {
            return null;
        }

        // URL-Form: .../File:Dateiname
        if (str_contains($wert, '/File:')) {
            $wert = substr($wert, strrpos($wert, '/File:') + 6);
        }

        // Präfix "File:" oder "Datei:"
        if (preg_match('/^(?:File|Datei):/i', $wert)) {
            $wert = preg_replace('/^(?:File|Datei):/i', '', $wert);
        }

        return trim($wert) ?: null;
    }

    // Löst Adressfelder zu einer adress_lokation_id auf (find-or-create)
    private function resolveAdressLokation(array $data): ?int
    {
        if (empty($data['strasse_aktuell'])) {
            return null;
        }

        $lokation = $this->adresseRepo->resolveOrCreate([
            'strasse_name'         => $data['strasse_aktuell'],
            'stadt_name'           => $this->stadtName(),
            'stadtteil_name'       => $data['stadtteil']            ?? null,
            'plz'                  => $data['plz_aktuell']          ?? null,
            'wikidata_id_strasse'  => $data['wikidata_id_strasse']  ?? null,
            'wikidata_id_stadtteil'=> $data['wikidata_id_stadtteil'] ?? null,
        ]);

        return $lokation ? (int) $lokation['lokation_id'] : null;
    }

    // Gibt die ersten Zeilen zurück, damit das Frontend das Spalten-Mapping anbieten kann
    public function analyze(array $file): array
    {
        $sheet = $this->loadSheet($file);

        $maxCol  = $sheet->getHighestColumn();
        $maxRow  = $sheet->getHighestRow();
        $preview = [];

        $previewRows = min($maxRow, 5);
        for ($row = 1; $row <= $previewRows; $row++) {
            $cols = [];
            $colIndex = 1;
            $lastCol  = Coordinate::columnIndexFromString($maxCol);
            for ($colIndex = 1; $colIndex <= $lastCol; $colIndex++) {
                $letter       = Coordinate::stringFromColumnIndex($colIndex);
                $cols[$letter] = (string) ($sheet->getCell($letter . $row)->getValue() ?? '');
            }
            $preview[] = ['zeile' => $row, 'spalten' => $cols];
        }

        return [
            'zeilenanzahl'  => $maxRow,
            'spaltenanzahl' => Coordinate::columnIndexFromString($maxCol),
            'vorschau'      => $preview,
            'felder'        => [
                'person'     => self::PERSON_FIELDS,
                'verlegeort' => self::VERLEGEORT_FIELDS,
                'stein'      => self::STEIN_FIELDS,
                'dokument'   => self::DOKUMENT_FIELDS,
            ],
        ];
    }

    // Dry-Run: analysiert alle Zeilen ohne zu schreiben
    public function preview(array $file, array $mapping, int $startRow = 2, string $dokIstBiografieGlobal = 'spalte'): array
    {
        $sheet = $this->loadSheet($file);
        $rows  = $this->readRows($sheet, $mapping, $startRow);
        return $this->analyzeRows($rows, $dokIstBiografieGlobal);
    }

    // Tatsächlicher Import in einer Transaktion
    public function execute(array $file, array $mapping, int $startRow, string $benutzer, string $dokIstBiografieGlobal = 'spalte'): array
    {
        Logger::get()->info('Import gestartet', [
            'datei'    => $file['name'],
            'benutzer' => $benutzer,
            'startRow' => $startRow,
        ]);

        $sheet = $this->loadSheet($file);
        $rows  = $this->readRows($sheet, $mapping, $startRow);

        $pdo = \Stolpersteine\Config\Database::connection();
        $pdo->beginTransaction();

        try {
            $result = $this->importRows($rows, $benutzer, $dokIstBiografieGlobal);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Logger::get()->error('Import fehlgeschlagen, Transaktion zurückgerollt', [
                'benutzer'  => $benutzer,
                'exception' => get_class($e),
                'message'   => $e->getMessage(),
            ]);
            throw $e;
        }

        Logger::get()->info('Import abgeschlossen', [
            'benutzer'         => $benutzer,
            'gesamt'           => $result['gesamt'],
            'neue_personen'    => $result['neue_personen'],
            'neue_steine'      => $result['neue_steine'],
            'neue_verlegeorte' => $result['neue_verlegeorte'],
            'fehler'           => $result['fehler'],
        ]);

        return $result;
    }

    // --- Private Hilfsmethoden ---

    private function loadSheet(array $file): \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('Upload-Fehler.');
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls', 'csv', 'ods'], true)) {
            throw new \InvalidArgumentException("Nicht unterstütztes Dateiformat: .$ext");
        }

        $spreadsheet = IOFactory::load($file['tmp_name']);
        return $spreadsheet->getActiveSheet();
    }

    private function readRows(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        array $mapping,
        int $startRow
    ): array {
        $maxRow    = $sheet->getHighestRow();
        $allFields = array_merge(self::PERSON_FIELDS, self::VERLEGEORT_FIELDS, self::STEIN_FIELDS, self::DOKUMENT_FIELDS);
        $rows      = [];

        for ($rowNum = $startRow; $rowNum <= $maxRow; $rowNum++) {
            $data = [];
            foreach ($allFields as $field) {
                if (!isset($mapping[$field])) {
                    continue;
                }
                $col        = strtoupper(trim($mapping[$field]));
                $cell      = $sheet->getCell($col . $rowNum);
                $cellValue = $cell->getValue();
                if ($cellValue instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
                    $cellValue = $cellValue->getPlainText();
                }
                if (is_numeric($cellValue) && ExcelDate::isDateTime($cell)) {
                    $cellValue = ExcelDate::excelToDateTimeObject((float) $cellValue)
                                          ->format('Y-m-d');
                } else {
                    $cellValue = $cellValue !== null ? trim((string) $cellValue) : null;
                    if ($cellValue !== null && in_array($field, self::HTML_STRIP_FIELDS, true)) {
                        $cellValue = trim(strip_tags($cellValue)) ?: null;
                    }
                    if ($cellValue !== null && in_array($field, self::DATE_FIELDS, true)) {
                        $cellValue = $this->normalizeDate($cellValue);
                    }
                }
                $data[$field] = $cellValue;
            }

            // Leerzeilen überspringen (kein Nachname vorhanden)
            if (empty($data['nachname'])) {
                continue;
            }

            $rows[] = ['zeile' => $rowNum, 'data' => $data];
        }

        return $rows;
    }

    private function analyzeRows(array $rows, string $dokIstBiografieGlobal = 'spalte'): array
    {
        $summary = [
            'gesamt'            => count($rows),
            'neue_personen'     => 0,
            'neue_verlegeorte'  => 0,
            'neue_steine'       => 0,
            'neue_dokumente'    => 0,
            'fehler'            => 0,
            'zeilen'            => [],
        ];

        // Neue Personen/Orte innerhalb dieser Vorschau tracken (keine DB-Schreibzugriffe)
        $knownPersons    = [];
        $knownLocations  = [];

        foreach ($rows as ['zeile' => $rowNum, 'data' => $data]) {
            $fehler = $this->validateRow($data);
            if ($fehler !== []) {
                $summary['fehler']++;
                $summary['zeilen'][] = [
                    'zeile'    => $rowNum,
                    'status'   => 'fehler',
                    'meldungen'=> $fehler,
                ];
                continue;
            }

            $personKey   = $this->personKey($data);
            $locationKey = $this->locationKey($data);

            // Person prüfen
            if (array_key_exists($personKey, $knownPersons)) {
                $personStatus = 'neu_in_import';
            } else {
                $existing = $this->personRepo->findByName(
                    $data['nachname'],
                    $data['vorname'] ?: null
                );
                if ($existing) {
                    $personStatus              = 'vorhanden';
                    $knownPersons[$personKey]  = $existing['id'];
                } else {
                    $personStatus              = 'neu';
                    $knownPersons[$personKey]  = null;
                    $summary['neue_personen']++;
                }
            }

            // Verlegeort prüfen
            if (array_key_exists($locationKey, $knownLocations)) {
                $ortStatus = 'neu_in_import';
            } else {
                $existing = $this->ortRepo->findByAddress(
                    $data['strasse_aktuell'],
                    $data['hausnummer_aktuell'] ?? ''
                );
                if ($existing) {
                    $ortStatus                    = 'vorhanden';
                    $knownLocations[$locationKey] = $existing['id'];
                } else {
                    $ortStatus                    = 'neu';
                    $knownLocations[$locationKey] = null;
                    $summary['neue_verlegeorte']++;
                }
            }

            $dokUrl = trim($data['dokument_url'] ?? '');
            if ($dokUrl !== '') {
                $summary['neue_dokumente']++;
            }

            // Zeige effektiven Biografie-Status in Vorschau
            $istBiografieEffektiv = match ($dokIstBiografieGlobal) {
                'ja'   => $dokUrl !== '',
                'nein' => false,
                default => $dokUrl !== '' && !empty($data['dokument_ist_biografie'])
                    && strtolower(trim($data['dokument_ist_biografie'])) !== 'nein'
                    && $data['dokument_ist_biografie'] !== '0',
            };

            $summary['neue_steine']++;
            $summary['zeilen'][] = [
                'zeile'                  => $rowNum,
                'status'                 => 'ok',
                'person_status'          => $personStatus,
                'ort_status'             => $ortStatus,
                'person'                 => $this->extractFields($data, self::PERSON_FIELDS),
                'verlegeort'             => $this->extractFields($data, self::VERLEGEORT_FIELDS),
                'stein'                  => $this->extractFields($data, self::STEIN_FIELDS),
                'dokument_url'           => $dokUrl ?: null,
                'dokument_ist_biografie' => $istBiografieEffektiv,
                'meldungen'              => [],
            ];
        }

        return $summary;
    }

    private function importRows(array $rows, string $benutzer, string $dokIstBiografieGlobal = 'spalte'): array
    {
        $summary = [
            'gesamt'            => count($rows),
            'neue_personen'     => 0,
            'neue_verlegeorte'  => 0,
            'neue_steine'       => 0,
            'neue_dokumente'    => 0,
            'fehler'            => 0,
            'zeilen'            => [],
            'dokumente'         => [],
        ];

        $suchindex       = new SuchindexService();
        $personCache     = [];
        $locationCache   = [];
        $dokumentDetail  = [];

        foreach ($rows as ['zeile' => $rowNum, 'data' => $data]) {
            $fehler = $this->validateRow($data);
            if ($fehler !== []) {
                $summary['fehler']++;
                $summary['zeilen'][] = [
                    'zeile'    => $rowNum,
                    'status'   => 'fehler',
                    'meldungen'=> $fehler,
                ];
                continue;
            }

            // Person anlegen oder wiederverwenden
            $personKey = $this->personKey($data);
            if (!isset($personCache[$personKey])) {
                $existing = $this->personRepo->findByName(
                    $data['nachname'],
                    $data['vorname'] ?: null
                );
                if ($existing) {
                    $personCache[$personKey] = $existing['id'];
                } else {
                    $personData = $this->extractFields($data, self::PERSON_FIELDS);
                    $personData['status'] = 'validierung';
                    $personId = $this->personRepo->create($personData, $benutzer);
                    $personCache[$personKey] = $personId;
                    $summary['neue_personen']++;
                    AuditRepository::log($benutzer, 'INSERT', 'personen', $personId, null,
                        $this->personRepo->findById($personId));
                }
            }
            $personId = $personCache[$personKey];

            // Verlegeort anlegen oder wiederverwenden
            $locationKey = $this->locationKey($data);
            if (!isset($locationCache[$locationKey])) {
                $existing = $this->ortRepo->findByAddress(
                    $data['strasse_aktuell'],
                    $data['hausnummer_aktuell'] ?? ''
                );
                if ($existing) {
                    $locationCache[$locationKey] = $existing['id'];
                } else {
                    // Adresse normalisieren → adress_lokation_id ermitteln
                    $adressLokationId = $this->resolveAdressLokation($data);

                    $ortDaten = $this->extractFields($data, self::VERLEGEORT_FIELDS);
                    // Felder entfernen, die VerlegeortRepository nicht kennt
                    unset(
                        $ortDaten['strasse_aktuell'],    $ortDaten['stadtteil'],
                        $ortDaten['plz_aktuell'],        $ortDaten['wikidata_id_strasse'],
                        $ortDaten['wikidata_id_stadtteil']
                    );
                    $ortDaten['adress_lokation_id'] = $adressLokationId;
                    $ortDaten['status'] = 'validierung';

                    $ortId = $this->ortRepo->create($ortDaten, $benutzer);
                    $locationCache[$locationKey] = $ortId;
                    $summary['neue_verlegeorte']++;
                    AuditRepository::log($benutzer, 'INSERT', 'verlegeorte', $ortId, null,
                        $this->ortRepo->findById($ortId));
                }
            }
            $ortId = $locationCache[$locationKey];

            // Stolperstein anlegen
            $steinData = $this->extractFields($data, self::STEIN_FIELDS);
            $steinData['wikimedia_commons'] = $this->normalizeCommonsDateiname(
                $steinData['wikimedia_commons'] ?? null
            );

            // Status immer auf "validierung" setzen — nicht aus Importdatei lesen
            $steinData['status'] = 'validierung';

            // ENUM-Wert absichern: ungültige Zustandswerte → null → Repository-Default greift
            static $gueltigeZustaende = ['verfuegbar', 'stein_fehlend', 'kein_stein', 'beschaedigt', 'unleserlich'];

            if (!in_array($steinData['zustand'] ?? null, $gueltigeZustaende, true)) {
                $steinData['zustand'] = null;
            }

            $steinData['person_id']     = $personId;
            $steinData['verlegeort_id'] = $ortId;

            $steinId = $this->steinRepo->create($steinData, $benutzer);
            $summary['neue_steine']++;
            $suchindex->update($steinId);

            AuditRepository::log($benutzer, 'INSERT', 'stolpersteine', $steinId, null,
                $this->steinRepo->findById($steinId));

            // Dokument-URL verknüpfen (find-or-create)
            $dokUrl = trim($data['dokument_url'] ?? '');
            $istBiografie = match ($dokIstBiografieGlobal) {
                'ja'   => true,
                'nein' => false,
                default => !empty($data['dokument_ist_biografie'])
                    && strtolower(trim($data['dokument_ist_biografie'])) !== 'nein'
                    && $data['dokument_ist_biografie'] !== '0',
            };
            $dokId = null;
            if ($dokUrl !== '') {
                $isNew = ($this->dokumentRepo->findByQuelleUrl($dokUrl) === null);
                $dokId = $this->findOrCreateDokumentUrl($dokUrl, $personId, $benutzer);
                if ($istBiografie) {
                    $this->personRepo->setBiografie($personId, $dokId);
                }
                if (!isset($dokumentDetail[$dokId])) {
                    if ($isNew) {
                        $summary['neue_dokumente']++;
                    }
                    $d = $this->dokumentRepo->findById($dokId);
                    $dokumentDetail[$dokId] = [
                        'id'              => $dokId,
                        'titel'           => $d['titel'],
                        'quelle_url'      => $d['quelle_url'],
                        'typ'             => $d['typ'],
                        'groesse_bytes'   => $d['groesse_bytes'] ?? null,
                        'quelle'          => $d['quelle'] ?? null,
                        'url_status'      => $d['url_status'] ?? null,
                        'url_geprueft_am' => $d['url_geprueft_am'] ?? null,
                    ];
                }
            }

            $summary['zeilen'][] = [
                'zeile'          => $rowNum,
                'status'         => 'importiert',
                'person_id'      => $personId,
                'verlegeort_id'  => $ortId,
                'stolperstein_id'=> $steinId,
                'dokument_id'    => $dokId,
                'meldungen'      => [],
            ];
        }

        $summary['dokumente'] = array_values($dokumentDetail);
        return $summary;
    }

    private function validateRow(array $data): array
    {
        $fehler = [];
        if (empty($data['nachname'])) {
            $fehler[] = 'Nachname fehlt.';
        }
        if (empty($data['strasse_aktuell'])) {
            $fehler[] = 'Straße (strasse_aktuell) fehlt.';
        }
        return $fehler;
    }

    private function extractFields(array $data, array $fields): array
    {
        $result = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $result[$field] = $data[$field] !== '' ? $data[$field] : null;
            }
        }
        return $result;
    }

    private function personKey(array $data): string
    {
        return strtolower(trim($data['nachname'] ?? ''))
            . '|'
            . strtolower(trim($data['vorname'] ?? ''));
    }

    /**
     * Normalisiert ein Datum auf Y-m-d für MySQL.
     * Unterstützt: DD.MM.YYYY, DD.MM.YY, YYYY-MM-DD, MM/DD/YYYY.
     * Gibt den Originalwert zurück, wenn kein bekanntes Format erkannt wird.
     */
    private function normalizeDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return $value;
        }

        // Bereits Y-m-d oder Y-m-d H:i:s
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            return substr($value, 0, 10);
        }

        // DD.MM.YYYY oder DD.MM.YY
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{2,4})$/', $value, $m)) {
            $year  = strlen($m[3]) === 2 ? (((int) $m[3] > 30 ? 1900 : 2000) + (int) $m[3]) : (int) $m[3];
            return sprintf('%04d-%02d-%02d', $year, (int) $m[2], (int) $m[1]);
        }

        // MM/DD/YYYY
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $value, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[1], (int) $m[2]);
        }

        return $value;
    }

    private function locationKey(array $data): string
    {
        return strtolower(trim($data['strasse_aktuell'] ?? ''))
            . '|'
            . strtolower(trim($data['hausnummer_aktuell'] ?? ''));
    }

    /**
     * Legt ein Dokument per URL an oder verknüpft die Person mit einem vorhandenen.
     * Gibt die Dokument-ID zurück.
     */
    private function findOrCreateDokumentUrl(string $url, int $personId, string $benutzer): int
    {
        $existing = $this->dokumentRepo->findByQuelleUrl($url);

        if ($existing !== null) {
            // Idempotent: Person mit vorhandenem Dokument verknüpfen
            $this->dokumentRepo->addPerson($existing['id'], $personId);
            return (int) $existing['id'];
        }

        $quelle = $this->dokumentService->extractDomain($url) ?: null;
        $dateiname     = $this->dokumentService->generateFilename($url);
        $ext           = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        $typ           = $ext === 'pdf' ? 'pdf' : 'url';

        $dokData = [
            'titel'        => $dateiname ?: (parse_url($url, PHP_URL_HOST) ?? $url),
            'quelle_url'   => $url,
            'typ'          => $typ,
            'dateiname'    => $dateiname,
            'quelle'=> $quelle,
            'person_ids'   => [$personId],
        ];

        $dokId = $this->dokumentRepo->create($dokData, $benutzer);
        AuditRepository::log($benutzer, 'INSERT', 'dokumente', $dokId, null,
            $this->dokumentRepo->findById($dokId));

        return $dokId;
    }
}
