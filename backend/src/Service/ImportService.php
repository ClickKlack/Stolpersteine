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
use Stolpersteine\Repository\AuditRepository;

class ImportService
{
    // Felder die aus der Excel-Datei gemappt werden können
    private const PERSON_FIELDS = [
        'nachname', 'vorname', 'geburtsname',
        'geburtsdatum', 'sterbedatum', 'biografie_kurz', 'wikidata_id_person',
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
        'status', 'zustand',
    ];

    private PersonRepository $personRepo;
    private VerlegeortRepository $ortRepo;
    private StolpersteinRepository $steinRepo;
    private AdresseRepository $adresseRepo;

    // Stadtname aus konfiguration (gecacht)
    private ?string $stadtName = null;

    public function __construct()
    {
        $this->personRepo  = new PersonRepository();
        $this->ortRepo     = new VerlegeortRepository();
        $this->steinRepo   = new StolpersteinRepository();
        $this->adresseRepo = new AdresseRepository();
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
            ],
        ];
    }

    // Dry-Run: analysiert alle Zeilen ohne zu schreiben
    public function preview(array $file, array $mapping, int $startRow = 2): array
    {
        $sheet = $this->loadSheet($file);
        $rows  = $this->readRows($sheet, $mapping, $startRow);

        $result = $this->analyzeRows($rows);
        return $result;
    }

    // Tatsächlicher Import in einer Transaktion
    public function execute(array $file, array $mapping, int $startRow, string $benutzer): array
    {
        $sheet = $this->loadSheet($file);
        $rows  = $this->readRows($sheet, $mapping, $startRow);

        $pdo = \Stolpersteine\Config\Database::connection();
        $pdo->beginTransaction();

        try {
            $result = $this->importRows($rows, $benutzer);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

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
        $allFields = array_merge(self::PERSON_FIELDS, self::VERLEGEORT_FIELDS, self::STEIN_FIELDS);
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

    private function analyzeRows(array $rows): array
    {
        $summary = [
            'gesamt'            => count($rows),
            'neue_personen'     => 0,
            'neue_verlegeorte'  => 0,
            'neue_steine'       => 0,
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

            $summary['neue_steine']++;
            $summary['zeilen'][] = [
                'zeile'          => $rowNum,
                'status'         => 'ok',
                'person_status'  => $personStatus,
                'ort_status'     => $ortStatus,
                'person'         => $this->extractFields($data, self::PERSON_FIELDS),
                'verlegeort'     => $this->extractFields($data, self::VERLEGEORT_FIELDS),
                'stein'          => $this->extractFields($data, self::STEIN_FIELDS),
                'meldungen'      => [],
            ];
        }

        return $summary;
    }

    private function importRows(array $rows, string $benutzer): array
    {
        $summary = [
            'gesamt'            => count($rows),
            'neue_personen'     => 0,
            'neue_verlegeorte'  => 0,
            'neue_steine'       => 0,
            'fehler'            => 0,
            'zeilen'            => [],
        ];

        $suchindex       = new SuchindexService();
        $personCache     = [];
        $locationCache   = [];

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
                    $personId = $this->personRepo->create(
                        $this->extractFields($data, self::PERSON_FIELDS),
                        $benutzer
                    );
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

            // ENUM-Werte absichern: ungültige Werte → null → Repository-Default greift
            static $gueltigeStatus   = ['neu', 'validierung', 'freigegeben', 'archiviert',
                                         'fehlerhaft', 'abgleich_wikipedia', 'abgleich_osm', 'abgleich_wikidata'];
            static $gueltigeZustaende = ['verfuegbar', 'stein_fehlend', 'kein_stein', 'beschaedigt', 'unleserlich'];

            if (!in_array($steinData['status']  ?? null, $gueltigeStatus,    true)) {
                $steinData['status']  = null;
            }
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

            $summary['zeilen'][] = [
                'zeile'          => $rowNum,
                'status'         => 'importiert',
                'person_id'      => $personId,
                'verlegeort_id'  => $ortId,
                'stolperstein_id'=> $steinId,
                'meldungen'      => [],
            ];
        }

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

    private function locationKey(array $data): string
    {
        return strtolower(trim($data['strasse_aktuell'] ?? ''))
            . '|'
            . strtolower(trim($data['hausnummer_aktuell'] ?? ''));
    }
}
