<?php

declare(strict_types=1);

namespace Stolpersteine\Repository;

use PDO;
use Stolpersteine\Config\Database;

class StolpersteinRepository
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

        if (!empty($filter['status'])) {
            $where[]  = 's.status = ?';
            $params[] = $filter['status'];
        }

        if (!empty($filter['zustand'])) {
            $where[]  = 's.zustand = ?';
            $params[] = $filter['zustand'];
        }

        if (!empty($filter['stadtteil'])) {
            $where[]  = 'st.name LIKE ?';
            $params[] = '%' . $filter['stadtteil'] . '%';
        }

        if (!empty($filter['strasse'])) {
            $where[]  = 's.name LIKE ?';
            $params[] = '%' . $filter['strasse'] . '%';
        }

        if (!empty($filter['person_id'])) {
            $where[]  = 's.person_id = ?';
            $params[] = (int) $filter['person_id'];
        }

        if (!empty($filter['ohne_wikidata'])) {
            $where[] = 's.wikidata_id_stein IS NULL';
        }

        $sql = 'SELECT s.id, s.person_id, s.verlegeort_id,
                       p.vorname, p.nachname,
                       str.name AS strasse_aktuell, v.hausnummer_aktuell,
                       st.name  AS stadtteil,
                       s.verlegedatum, s.status, s.zustand,
                       s.wikidata_id_stein, s.osm_id,
                       s.erstellt_am, s.geaendert_am
                FROM stolpersteine s
                JOIN personen     p   ON p.id  = s.person_id
                JOIN verlegeorte  v   ON v.id  = s.verlegeort_id
                LEFT JOIN adress_lokationen al  ON al.id  = v.adress_lokation_id
                LEFT JOIN strassen          str ON str.id = al.strasse_id
                LEFT JOIN stadtteile        st  ON st.id  = al.stadtteil_id';

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY st.name, str.name, p.nachname';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.*,
                    p.vorname, p.nachname, p.geburtsdatum, p.sterbedatum,
                    str.name AS strasse_aktuell, v.hausnummer_aktuell,
                    st.name  AS stadtteil,
                    v.lat, v.lon,
                    si.aktualisiert_am AS suchindex_aktualisiert_am
             FROM stolpersteine s
             JOIN personen    p   ON p.id  = s.person_id
             JOIN verlegeorte v   ON v.id  = s.verlegeort_id
             LEFT JOIN adress_lokationen al  ON al.id  = v.adress_lokation_id
             LEFT JOIN strassen          str ON str.id = al.strasse_id
             LEFT JOIN stadtteile        st  ON st.id  = al.stadtteil_id
             LEFT JOIN suchindex si ON si.stolperstein_id = s.id
             WHERE s.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data, string $benutzer): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO stolpersteine
                (person_id, verlegeort_id, verlegedatum, inschrift,
                 pos_x, pos_y, lat_override, lon_override,
                 foto_pfad, wikidata_id_stein, osm_id,
                 status, zustand, erstellt_von, geaendert_von)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            (int) $data['person_id'],
            (int) $data['verlegeort_id'],
            $data['verlegedatum']       ?? null,
            $data['inschrift']          ?? null,
            isset($data['pos_x'])       ? (int) $data['pos_x'] : null,
            isset($data['pos_y'])       ? (int) $data['pos_y'] : null,
            $data['lat_override']       ?? null,
            $data['lon_override']       ?? null,
            $data['foto_pfad']          ?? null,
            $data['wikidata_id_stein']  ?? null,
            isset($data['osm_id'])      ? (int) $data['osm_id'] : null,
            $data['status']             ?? 'neu',
            $data['zustand']            ?? 'verfuegbar',
            $benutzer,
            $benutzer,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data, string $benutzer): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE stolpersteine SET
                person_id         = ?,
                verlegeort_id     = ?,
                verlegedatum      = ?,
                inschrift         = ?,
                pos_x             = ?,
                pos_y             = ?,
                lat_override      = ?,
                lon_override      = ?,
                foto_pfad         = ?,
                wikidata_id_stein = ?,
                osm_id            = ?,
                status            = ?,
                zustand           = ?,
                geaendert_von     = ?
             WHERE id = ?'
        );

        $stmt->execute([
            (int) $data['person_id'],
            (int) $data['verlegeort_id'],
            $data['verlegedatum']       ?? null,
            $data['inschrift']          ?? null,
            isset($data['pos_x'])       ? (int) $data['pos_x'] : null,
            isset($data['pos_y'])       ? (int) $data['pos_y'] : null,
            $data['lat_override']       ?? null,
            $data['lon_override']       ?? null,
            $data['foto_pfad']          ?? null,
            $data['wikidata_id_stein']  ?? null,
            isset($data['osm_id'])      ? (int) $data['osm_id'] : null,
            $data['status']             ?? 'neu',
            $data['zustand']            ?? 'verfuegbar',
            $benutzer,
            $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        // Suchindex wird via ON DELETE CASCADE automatisch entfernt
        $stmt = $this->pdo->prepare('DELETE FROM stolpersteine WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}
