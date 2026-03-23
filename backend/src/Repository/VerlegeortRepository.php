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

        $sql = 'SELECT v.id, v.hausnummer_aktuell, v.beschreibung, v.lat, v.lon,
                       v.adress_lokation_id,
                       v.bemerkung_historisch, v.adresse_alt, v.raster_beschreibung,
                       s.name  AS strasse_aktuell,  s.wikidata_id  AS wikidata_id_strasse,
                       st.name AS stadtteil,        st.wikidata_id AS wikidata_id_stadtteil,
                       p.plz   AS plz_aktuell,
                       sta.name AS stadt,           sta.wikidata_id AS wikidata_id_ort,
                       v.grid_n, v.grid_m,
                       v.status, v.erstellt_am, v.geaendert_am,
                       COALESCE(sc.anzahl, 0) AS stolpersteine_anzahl
                FROM verlegeorte v
                LEFT JOIN adress_lokationen al ON al.id  = v.adress_lokation_id
                LEFT JOIN strassen s            ON s.id  = al.strasse_id
                LEFT JOIN stadtteile st         ON st.id = al.stadtteil_id
                LEFT JOIN plz p                 ON p.id  = al.plz_id
                LEFT JOIN staedte sta           ON sta.id = s.stadt_id
                LEFT JOIN (SELECT verlegeort_id, COUNT(*) AS anzahl FROM stolpersteine GROUP BY verlegeort_id) sc
                    ON sc.verlegeort_id = v.id';

        if (!empty($filter['stadtteil'])) {
            $where[]  = 'st.name LIKE ?';
            $params[] = '%' . $filter['stadtteil'] . '%';
        }

        if (!empty($filter['strasse'])) {
            $where[]  = 's.name LIKE ?';
            $params[] = '%' . $filter['strasse'] . '%';
        }

        if (!empty($filter['plz'])) {
            $where[]  = 'p.plz = ?';
            $params[] = $filter['plz'];
        }

        if (!empty($filter['status'])) {
            $where[]  = 'v.status = ?';
            $params[] = $filter['status'];
        }

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY st.name, s.name, v.hausnummer_aktuell';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findByAddress(string $strasse, string $hausnummer): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT v.id, s.name AS strasse_aktuell, v.hausnummer_aktuell,
                    st.name AS stadtteil, p.plz AS plz_aktuell, v.lat, v.lon
             FROM verlegeorte v
             LEFT JOIN adress_lokationen al ON al.id  = v.adress_lokation_id
             LEFT JOIN strassen s            ON s.id  = al.strasse_id
             LEFT JOIN stadtteile st         ON st.id = al.stadtteil_id
             LEFT JOIN plz p                 ON p.id  = al.plz_id
             WHERE LOWER(s.name) = LOWER(?)
               AND LOWER(v.hausnummer_aktuell) = LOWER(?)
             LIMIT 1'
        );
        $stmt->execute([$strasse, $hausnummer]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT v.id, v.hausnummer_aktuell, v.beschreibung, v.lat, v.lon,
                    v.adress_lokation_id, v.adresse_alt, v.bemerkung_historisch,
                    s.name  AS strasse_aktuell,  s.wikidata_id  AS wikidata_id_strasse,
                    st.name AS stadtteil,        st.wikidata_id AS wikidata_id_stadtteil,
                    p.plz   AS plz_aktuell,
                    sta.name AS stadt,           sta.wikidata_id AS wikidata_id_ort,
                    v.grid_n, v.grid_m, v.raster_beschreibung,
                    v.status, v.erstellt_am, v.erstellt_von, v.geaendert_am, v.geaendert_von
             FROM verlegeorte v
             LEFT JOIN adress_lokationen al ON al.id  = v.adress_lokation_id
             LEFT JOIN strassen s            ON s.id  = al.strasse_id
             LEFT JOIN stadtteile st         ON st.id = al.stadtteil_id
             LEFT JOIN plz p                 ON p.id  = al.plz_id
             LEFT JOIN staedte sta           ON sta.id = s.stadt_id
             WHERE v.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        if ($row['adresse_alt'] !== null) {
            $row['adresse_alt'] = json_decode($row['adresse_alt'], true);
        }

        return $row;
    }

    public function create(array $data, string $benutzer): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO verlegeorte
                (adress_lokation_id, hausnummer_aktuell, beschreibung, lat, lon,
                 adresse_alt, bemerkung_historisch,
                 grid_n, grid_m, raster_beschreibung,
                 status, erstellt_von, geaendert_von)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            isset($data['adress_lokation_id']) ? (int) $data['adress_lokation_id'] : null,
            $data['hausnummer_aktuell']   ?? null,
            $data['beschreibung']         ?? null,
            $data['lat']                  ?? null,
            $data['lon']                  ?? null,
            isset($data['adresse_alt']) ? json_encode($data['adresse_alt'], JSON_UNESCAPED_UNICODE) : null,
            $data['bemerkung_historisch'] ?? null,
            isset($data['grid_n']) ? (int) $data['grid_n'] : null,
            isset($data['grid_m']) ? (int) $data['grid_m'] : null,
            $data['raster_beschreibung']  ?? null,
            $data['status']               ?? 'validierung',
            $benutzer,
            $benutzer,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data, string $benutzer): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE verlegeorte SET
                adress_lokation_id   = ?,
                hausnummer_aktuell   = ?,
                beschreibung         = ?,
                lat                  = ?,
                lon                  = ?,
                adresse_alt          = ?,
                bemerkung_historisch = ?,
                grid_n               = ?,
                grid_m               = ?,
                raster_beschreibung  = ?,
                status               = ?,
                geaendert_von        = ?
             WHERE id = ?'
        );

        $stmt->execute([
            isset($data['adress_lokation_id']) ? (int) $data['adress_lokation_id'] : null,
            $data['hausnummer_aktuell']   ?? null,
            $data['beschreibung']         ?? null,
            $data['lat']                  ?? null,
            $data['lon']                  ?? null,
            isset($data['adresse_alt']) ? json_encode($data['adresse_alt'], JSON_UNESCAPED_UNICODE) : null,
            $data['bemerkung_historisch'] ?? null,
            isset($data['grid_n']) ? (int) $data['grid_n'] : null,
            isset($data['grid_m']) ? (int) $data['grid_m'] : null,
            $data['raster_beschreibung']  ?? null,
            in_array($data['status'] ?? '', ['ok', 'validierung'], true) ? $data['status'] : 'validierung',
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
