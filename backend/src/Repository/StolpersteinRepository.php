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
            $where[]  = 'str.name LIKE ?';
            $params[] = '%' . $filter['strasse'] . '%';
        }

        if (!empty($filter['person_id'])) {
            $where[]  = 's.person_id = ?';
            $params[] = (int) $filter['person_id'];
        }

        if (!empty($filter['verlegeort_id'])) {
            $where[]  = 's.verlegeort_id = ?';
            $params[] = (int) $filter['verlegeort_id'];
        }

        if (!empty($filter['name'])) {
            $term     = '%' . $filter['name'] . '%';
            $where[]  = '(p.vorname LIKE ? OR p.nachname LIKE ? OR p.geburtsname LIKE ?'
                      . ' OR CONCAT(p.vorname, \' \', p.nachname) LIKE ?'
                      . ' OR CONCAT(p.nachname, \' \', p.vorname) LIKE ?)';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        if (!empty($filter['ohne_wikidata'])) {
            $where[] = 's.wikidata_id_stein IS NULL';
        }

        // foto_status: 'ohne_foto' | 'ohne_commons' | 'foto_ohne_commons'
        if (!empty($filter['foto_status'])) {
            match($filter['foto_status']) {
                'ohne_foto'         => $where[] = 's.foto_pfad IS NULL',
                'ohne_commons'      => $where[] = '(s.wikimedia_commons IS NULL OR s.wikimedia_commons = \'\')',
                'foto_ohne_commons' => $where[] = '(s.foto_pfad IS NOT NULL AND (s.wikimedia_commons IS NULL OR s.wikimedia_commons = \'\'))',
                default             => null,
            };
        }

        $sql = 'SELECT s.id, s.person_id, s.verlegeort_id,
                       p.vorname, p.nachname, p.status AS person_status,
                       str.name AS strasse_aktuell, v.hausnummer_aktuell,
                       st.name  AS stadtteil, v.status AS verlegeort_status,
                       s.inschrift, s.verlegedatum, s.status, s.zustand,
                       s.wikidata_id_stein, s.osm_id,
                       s.foto_pfad, s.wikimedia_commons,
                       s.foto_lizenz_autor, s.foto_lizenz_name, s.foto_lizenz_url,
                       s.foto_eigenes,
                       s.pos_x, s.pos_y,
                       v.grid_n, v.grid_m,
                       s.lat_override, s.lon_override,
                       v.lat AS verlegeort_lat, v.lon AS verlegeort_lon,
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
                    p.status  AS person_status,
                    str.name AS strasse_aktuell, v.hausnummer_aktuell,
                    st.name  AS stadtteil,
                    v.lat, v.lon,
                    v.status  AS verlegeort_status,
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
                 foto_pfad, wikimedia_commons,
                 foto_lizenz_autor, foto_lizenz_name, foto_lizenz_url, foto_eigenes,
                 wikidata_id_stein, osm_id,
                 status, zustand, erstellt_von, geaendert_von)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            (int) $data['person_id'],
            (int) $data['verlegeort_id'],
            $data['verlegedatum']        ?? null,
            $data['inschrift']           ?? null,
            isset($data['pos_x'])        ? (int) $data['pos_x'] : null,
            isset($data['pos_y'])        ? (int) $data['pos_y'] : null,
            $data['lat_override']        ?? null,
            $data['lon_override']        ?? null,
            $data['foto_pfad']              ?? null,
            $data['wikimedia_commons']      ?? null,
            $data['foto_lizenz_autor']      ?? null,
            $data['foto_lizenz_name']       ?? null,
            $data['foto_lizenz_url']        ?? null,
            isset($data['foto_eigenes'])    ? (int) $data['foto_eigenes']    : 0,
            $data['wikidata_id_stein']      ?? null,
            isset($data['osm_id'])          ? (int) $data['osm_id'] : null,
            $data['status']                 ?? 'neu',
            $data['zustand']                ?? 'verfuegbar',
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
                wikimedia_commons = ?,
                foto_eigenes      = ?,
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
            $data['verlegedatum']        ?? null,
            $data['inschrift']           ?? null,
            isset($data['pos_x'])        ? (int) $data['pos_x'] : null,
            isset($data['pos_y'])        ? (int) $data['pos_y'] : null,
            $data['lat_override']        ?? null,
            $data['lon_override']        ?? null,
            $data['foto_pfad']               ?? null,
            $data['wikimedia_commons']       ?? null,
            isset($data['foto_eigenes'])     ? (int) $data['foto_eigenes']     : 0,
            $data['wikidata_id_stein']       ?? null,
            isset($data['osm_id'])       ? (int) $data['osm_id'] : null,
            $data['status']              ?? 'neu',
            $data['zustand']             ?? 'verfuegbar',
            $benutzer,
            $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Aktualisiert nur die Foto-bezogenen Felder eines Stolpersteins.
     * Felder die nicht im $data-Array enthalten sind, bleiben unverändert.
     */
    public function updateFoto(int $id, array $data, string $benutzer): bool
    {
        $sets   = ['geaendert_von = ?'];
        $params = [$benutzer];

        $fotoFelder = [
            'foto_pfad', 'wikimedia_commons',
            'foto_lizenz_autor', 'foto_lizenz_name', 'foto_lizenz_url',
            'foto_eigenes',
        ];

        foreach ($fotoFelder as $feld) {
            if (array_key_exists($feld, $data)) {
                $sets[]   = "$feld = ?";
                $params[] = $feld === 'foto_eigenes' && $data[$feld] !== null
                    ? (int) $data[$feld]
                    : $data[$feld];
            }
        }

        $params[] = $id;
        $sql      = 'UPDATE stolpersteine SET ' . implode(', ', $sets) . ' WHERE id = ?';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    // Setzt den Status aller Stolpersteine einer Person
    public function setStatusForPerson(int $personId, string $status): void
    {
        $this->pdo->prepare(
            'UPDATE stolpersteine SET status = ? WHERE person_id = ?'
        )->execute([$status, $personId]);
    }

    // Setzt den Status aller Stolpersteine eines Verlegeorts
    public function setStatusForVerlegeort(int $verlegeortId, string $status): void
    {
        $this->pdo->prepare(
            'UPDATE stolpersteine SET status = ? WHERE verlegeort_id = ?'
        )->execute([$status, $verlegeortId]);
    }

    public function delete(int $id): bool
    {
        // Suchindex wird via ON DELETE CASCADE automatisch entfernt
        $stmt = $this->pdo->prepare('DELETE FROM stolpersteine WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}
