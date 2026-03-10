<?php

declare(strict_types=1);

namespace Stolpersteine\Repository;

use PDO;
use Stolpersteine\Config\Database;

class VerlegeortRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function findAll(array $filter = []): array
    {
        $where  = [];
        $params = [];

        if (!empty($filter['stadtteil'])) {
            $where[]  = 'stadtteil LIKE ?';
            $params[] = '%' . $filter['stadtteil'] . '%';
        }

        if (!empty($filter['strasse'])) {
            $where[]  = 'strasse_aktuell LIKE ?';
            $params[] = '%' . $filter['strasse'] . '%';
        }

        if (!empty($filter['plz'])) {
            $where[]  = 'plz_aktuell = ?';
            $params[] = $filter['plz'];
        }

        $sql = 'SELECT id, beschreibung, lat, lon, stadtteil,
                       strasse_aktuell, hausnummer_aktuell, plz_aktuell,
                       grid_n, grid_m, wikidata_id_strasse, erstellt_am, geaendert_am
                FROM verlegeorte';

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY stadtteil, strasse_aktuell, hausnummer_aktuell';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findByAddress(string $strasse, string $hausnummer): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, strasse_aktuell, hausnummer_aktuell, stadtteil, plz_aktuell, lat, lon
             FROM verlegeorte
             WHERE LOWER(strasse_aktuell) = LOWER(?)
               AND LOWER(hausnummer_aktuell) = LOWER(?)
             LIMIT 1'
        );
        $stmt->execute([$strasse, $hausnummer]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, beschreibung, lat, lon, stadtteil,
                    strasse_aktuell, hausnummer_aktuell, plz_aktuell,
                    adresse_alt, bemerkung_historisch, wikidata_id_strasse,
                    grid_n, grid_m, raster_beschreibung,
                    erstellt_am, erstellt_von, geaendert_am, geaendert_von
             FROM verlegeorte
             WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        // JSON-Feld dekodieren
        if ($row['adresse_alt'] !== null) {
            $row['adresse_alt'] = json_decode($row['adresse_alt'], true);
        }

        return $row;
    }

    public function create(array $data, string $benutzer): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO verlegeorte
                (beschreibung, lat, lon, stadtteil,
                 strasse_aktuell, hausnummer_aktuell, plz_aktuell,
                 adresse_alt, bemerkung_historisch, wikidata_id_strasse,
                 grid_n, grid_m, raster_beschreibung,
                 erstellt_von, geaendert_von)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $data['beschreibung']         ?? null,
            $data['lat']                  ?? null,
            $data['lon']                  ?? null,
            $data['stadtteil']            ?? null,
            $data['strasse_aktuell']      ?? null,
            $data['hausnummer_aktuell']   ?? null,
            $data['plz_aktuell']          ?? null,
            isset($data['adresse_alt']) ? json_encode($data['adresse_alt'], JSON_UNESCAPED_UNICODE) : null,
            $data['bemerkung_historisch'] ?? null,
            $data['wikidata_id_strasse']  ?? null,
            isset($data['grid_n']) ? (int) $data['grid_n'] : null,
            isset($data['grid_m']) ? (int) $data['grid_m'] : null,
            $data['raster_beschreibung']  ?? null,
            $benutzer,
            $benutzer,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data, string $benutzer): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE verlegeorte SET
                beschreibung         = ?,
                lat                  = ?,
                lon                  = ?,
                stadtteil            = ?,
                strasse_aktuell      = ?,
                hausnummer_aktuell   = ?,
                plz_aktuell          = ?,
                adresse_alt          = ?,
                bemerkung_historisch = ?,
                wikidata_id_strasse  = ?,
                grid_n               = ?,
                grid_m               = ?,
                raster_beschreibung  = ?,
                geaendert_von        = ?
             WHERE id = ?'
        );

        $stmt->execute([
            $data['beschreibung']         ?? null,
            $data['lat']                  ?? null,
            $data['lon']                  ?? null,
            $data['stadtteil']            ?? null,
            $data['strasse_aktuell']      ?? null,
            $data['hausnummer_aktuell']   ?? null,
            $data['plz_aktuell']          ?? null,
            isset($data['adresse_alt']) ? json_encode($data['adresse_alt'], JSON_UNESCAPED_UNICODE) : null,
            $data['bemerkung_historisch'] ?? null,
            $data['wikidata_id_strasse']  ?? null,
            isset($data['grid_n']) ? (int) $data['grid_n'] : null,
            isset($data['grid_m']) ? (int) $data['grid_m'] : null,
            $data['raster_beschreibung']  ?? null,
            $benutzer,
            $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $check = $this->pdo->prepare(
            'SELECT COUNT(*) FROM stolpersteine WHERE verlegeort_id = ?'
        );
        $check->execute([$id]);
        if ((int) $check->fetchColumn() > 0) {
            throw new \RuntimeException('Verlegeort hat zugeordnete Stolpersteine und kann nicht gelöscht werden.');
        }

        $stmt = $this->pdo->prepare('DELETE FROM verlegeorte WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}
