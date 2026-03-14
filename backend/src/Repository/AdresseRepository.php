<?php

declare(strict_types=1);

namespace Stolpersteine\Repository;

use PDO;
use Stolpersteine\Config\Database;

class AdresseRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    /**
     * Straßen nach Name suchen – gibt jede Straße mit ihren Lokationen zurück.
     */
    public function searchStrassen(string $q): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.id, s.name, s.wikidata_id,
                    sta.id AS stadt_id, sta.name AS stadt_name, sta.wikidata_id AS wikidata_id_ort
             FROM strassen s
             JOIN staedte sta ON sta.id = s.stadt_id
             WHERE s.name LIKE ?
             ORDER BY s.name
             LIMIT 20'
        );
        $stmt->execute(['%' . $q . '%']);
        $strassen = $stmt->fetchAll();

        $stmtLok = $this->pdo->prepare(
            'SELECT al.id, al.strasse_id,
                    st.name AS stadtteil_name, st.wikidata_id AS wikidata_id_stadtteil,
                    p.plz
             FROM adress_lokationen al
             LEFT JOIN stadtteile st ON st.id = al.stadtteil_id
             LEFT JOIN plz p ON p.id = al.plz_id
             WHERE al.strasse_id = ?
             ORDER BY st.name, p.plz'
        );

        foreach ($strassen as &$strasse) {
            $stmtLok->execute([$strasse['id']]);
            $strasse['lokationen'] = $stmtLok->fetchAll();
        }

        return $strassen;
    }

    /**
     * Stadtteile nach Name suchen – gibt nur Name zurück (ohne Stadt/PLZ-Kontext).
     */
    public function searchStadtteile(string $q): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT DISTINCT name
             FROM stadtteile
             WHERE name LIKE ?
             ORDER BY name
             LIMIT 20'
        );
        $stmt->execute(['%' . $q . '%']);
        return array_column($stmt->fetchAll(), 'name');
    }

    public function findOrCreateStadt(string $name, ?string $wikidataId): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM staedte WHERE LOWER(name) = LOWER(?) LIMIT 1');
        $stmt->execute([$name]);
        $row = $stmt->fetch();

        if ($row) {
            if ($wikidataId) {
                $this->pdo->prepare('UPDATE staedte SET wikidata_id = ? WHERE id = ? AND wikidata_id IS NULL')
                          ->execute([$wikidataId, $row['id']]);
            }
            return (int) $row['id'];
        }

        $this->pdo->prepare('INSERT INTO staedte (name, wikidata_id) VALUES (?, ?)')
                  ->execute([$name, $wikidataId]);
        return (int) $this->pdo->lastInsertId();
    }

    public function findOrCreateStadtteil(string $name, int $stadtId, ?string $wikidataId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM stadtteile WHERE LOWER(name) = LOWER(?) AND stadt_id = ? LIMIT 1'
        );
        $stmt->execute([$name, $stadtId]);
        $row = $stmt->fetch();

        if ($row) {
            if ($wikidataId) {
                $this->pdo->prepare('UPDATE stadtteile SET wikidata_id = ? WHERE id = ? AND wikidata_id IS NULL')
                          ->execute([$wikidataId, $row['id']]);
            }
            return (int) $row['id'];
        }

        $this->pdo->prepare('INSERT INTO stadtteile (name, stadt_id, wikidata_id) VALUES (?, ?, ?)')
                  ->execute([$name, $stadtId, $wikidataId]);
        return (int) $this->pdo->lastInsertId();
    }

    public function findOrCreateStrasse(string $name, int $stadtId, ?string $wikidataId, ?string $wikipediaName = null): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM strassen WHERE LOWER(name) = LOWER(?) AND stadt_id = ? LIMIT 1'
        );
        $stmt->execute([$name, $stadtId]);
        $row = $stmt->fetch();

        if ($row) {
            if ($wikidataId) {
                $this->pdo->prepare('UPDATE strassen SET wikidata_id = ? WHERE id = ? AND wikidata_id IS NULL')
                          ->execute([$wikidataId, $row['id']]);
            }
            return (int) $row['id'];
        }

        $this->pdo->prepare('INSERT INTO strassen (name, stadt_id, wikidata_id, wikipedia_name) VALUES (?, ?, ?, ?)')
                  ->execute([$name, $stadtId, $wikidataId, $wikipediaName]);
        return (int) $this->pdo->lastInsertId();
    }

    public function findOrCreatePlz(string $plz, int $stadtId): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM plz WHERE plz = ? AND stadt_id = ? LIMIT 1');
        $stmt->execute([$plz, $stadtId]);
        $row = $stmt->fetch();

        if ($row) {
            return (int) $row['id'];
        }

        $this->pdo->prepare('INSERT INTO plz (plz, stadt_id) VALUES (?, ?)')
                  ->execute([$plz, $stadtId]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Lokation finden oder anlegen. Nutzt NULL-sicheren Vergleich (<=>) für optionale Felder.
     */
    public function findOrCreateLokation(int $strasseId, ?int $stadtteilId, ?int $plzId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM adress_lokationen
             WHERE strasse_id = ?
               AND stadtteil_id <=> ?
               AND plz_id <=> ?
             LIMIT 1'
        );
        $stmt->execute([$strasseId, $stadtteilId, $plzId]);
        $row = $stmt->fetch();

        if ($row) {
            return (int) $row['id'];
        }

        $this->pdo->prepare(
            'INSERT INTO adress_lokationen (strasse_id, stadtteil_id, plz_id) VALUES (?, ?, ?)'
        )->execute([$strasseId, $stadtteilId, $plzId]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Vollständige Auflösung einer Adresse zu einer Lokation (find-or-create).
     * Erwartet: strasse_name, stadt_name (Pflicht); stadtteil_name, plz, wikidata_ids (optional).
     */
    public function resolveOrCreate(array $data): array
    {
        $stadtId = $this->findOrCreateStadt(
            $data['stadt_name'],
            $data['wikidata_id_ort'] ?? null
        );

        $strasseId = $this->findOrCreateStrasse(
            $data['strasse_name'],
            $stadtId,
            $data['wikidata_id_strasse'] ?? null
        );

        $stadtteilId = null;
        if (!empty($data['stadtteil_name'])) {
            $stadtteilId = $this->findOrCreateStadtteil(
                $data['stadtteil_name'],
                $stadtId,
                $data['wikidata_id_stadtteil'] ?? null
            );
        }

        $plzId = null;
        if (!empty($data['plz'])) {
            $plzId = $this->findOrCreatePlz($data['plz'], $stadtId);
        }

        $lokationId = $this->findOrCreateLokation($strasseId, $stadtteilId, $plzId);

        return $this->getLokation($lokationId);
    }

    public function getLokation(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT al.id AS lokation_id,
                    s.id AS strasse_id,   s.name AS strasse_name,   s.wikidata_id AS wikidata_id_strasse,
                    st.id AS stadtteil_id, st.name AS stadtteil_name, st.wikidata_id AS wikidata_id_stadtteil,
                    p.id AS plz_id,       p.plz,
                    sta.id AS stadt_id,   sta.name AS stadt_name,   sta.wikidata_id AS wikidata_id_ort
             FROM adress_lokationen al
             JOIN strassen s ON s.id = al.strasse_id
             JOIN staedte sta ON sta.id = s.stadt_id
             LEFT JOIN stadtteile st ON st.id = al.stadtteil_id
             LEFT JOIN plz p ON p.id = al.plz_id
             WHERE al.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // -------------------------------------------------------------------------
    // Städte CRUD
    // -------------------------------------------------------------------------

    public function findAllStaedte(): array
    {
        return $this->pdo->query(
            'SELECT s.id, s.name, s.wikidata_id,
                    (SELECT COUNT(*) FROM strassen WHERE stadt_id = s.id) AS anzahl_strassen,
                    (SELECT COUNT(*) FROM stadtteile WHERE stadt_id = s.id) AS anzahl_stadtteile,
                    (SELECT COUNT(*) FROM plz WHERE stadt_id = s.id) AS anzahl_plz
             FROM staedte s
             ORDER BY s.name'
        )->fetchAll();
    }

    public function findStadtById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, wikidata_id FROM staedte WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function updateStadt(int $id, string $name, ?string $wikidataId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE staedte SET name = ?, wikidata_id = ? WHERE id = ?');
        $stmt->execute([$name, $wikidataId ?: null, $id]);
        return $stmt->rowCount() > 0;
    }

    public function deleteStadt(int $id): void
    {
        $checks = [
            ['strassen',   'stadt_id', 'Straßen'],
            ['stadtteile', 'stadt_id', 'Stadtteile'],
            ['plz',        'stadt_id', 'PLZ-Einträge'],
        ];
        foreach ($checks as [$table, $col, $label]) {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM $table WHERE $col = ?");
            $stmt->execute([$id]);
            if ((int) $stmt->fetchColumn() > 0) {
                throw new \RuntimeException(
                    "Stadt kann nicht gelöscht werden – es gibt noch zugeordnete $label."
                );
            }
        }
        $this->pdo->prepare('DELETE FROM staedte WHERE id = ?')->execute([$id]);
    }

    // -------------------------------------------------------------------------
    // Stadtteile CRUD
    // -------------------------------------------------------------------------

    public function findAllStadtteile(array $filter = []): array
    {
        $where  = [];
        $params = [];

        if (!empty($filter['stadt_id'])) {
            $where[]  = 'st.stadt_id = ?';
            $params[] = (int) $filter['stadt_id'];
        }

        $sql = 'SELECT st.id, st.name, st.wikidata_id, st.wikipedia_stadtteil, st.wikipedia_stolpersteine,
                       sta.id AS stadt_id, sta.name AS stadt_name,
                       (SELECT COUNT(*) FROM adress_lokationen WHERE stadtteil_id = st.id) AS anzahl_lokationen
                FROM stadtteile st
                JOIN staedte sta ON sta.id = st.stadt_id';

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY sta.name, st.name';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findStadtteilById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT st.id, st.name, st.wikidata_id, st.wikipedia_stadtteil, st.wikipedia_stolpersteine, st.stadt_id, sta.name AS stadt_name
             FROM stadtteile st
             JOIN staedte sta ON sta.id = st.stadt_id
             WHERE st.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function updateStadtteil(int $id, string $name, int $stadtId, ?string $wikidataId, ?string $wikipediaStadtteil = null, ?string $wikipediaStolpersteine = null): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE stadtteile SET name = ?, stadt_id = ?, wikidata_id = ?, wikipedia_stadtteil = ?, wikipedia_stolpersteine = ? WHERE id = ?'
        );
        $stmt->execute([$name, $stadtId, $wikidataId ?: null, $wikipediaStadtteil ?: null, $wikipediaStolpersteine ?: null, $id]);
        return $stmt->rowCount() > 0;
    }

    public function deleteStadtteil(int $id): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM adress_lokationen WHERE stadtteil_id = ?'
        );
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new \RuntimeException(
                'Stadtteil kann nicht gelöscht werden – es gibt noch zugeordnete Adress-Lokationen.'
            );
        }
        $this->pdo->prepare('DELETE FROM stadtteile WHERE id = ?')->execute([$id]);
    }

    // -------------------------------------------------------------------------
    // Straßen CRUD
    // -------------------------------------------------------------------------

    public function findAllStrassen(array $filter = []): array
    {
        $where  = [];
        $params = [];

        if (!empty($filter['stadt_id'])) {
            $where[]  = 's.stadt_id = ?';
            $params[] = (int) $filter['stadt_id'];
        }
        if (!empty($filter['q'])) {
            $where[]  = 's.name LIKE ?';
            $params[] = '%' . $filter['q'] . '%';
        }

        $sql = 'SELECT s.id, s.name, s.wikipedia_name, s.wikidata_id,
                       sta.id AS stadt_id, sta.name AS stadt_name,
                       (SELECT COUNT(*) FROM adress_lokationen WHERE strasse_id = s.id) AS anzahl_lokationen
                FROM strassen s
                JOIN staedte sta ON sta.id = s.stadt_id';

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY sta.name, s.name';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findStrasseById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.id, s.name, s.wikipedia_name, s.wikidata_id, s.stadt_id, sta.name AS stadt_name
             FROM strassen s
             JOIN staedte sta ON sta.id = s.stadt_id
             WHERE s.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function updateStrasse(int $id, string $name, int $stadtId, ?string $wikidataId, ?string $wikipediaName): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE strassen SET name = ?, stadt_id = ?, wikidata_id = ?, wikipedia_name = ? WHERE id = ?'
        );
        $stmt->execute([$name, $stadtId, $wikidataId ?: null, $wikipediaName ?: null, $id]);
        return $stmt->rowCount() > 0;
    }

    public function deleteStrasse(int $id): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM adress_lokationen WHERE strasse_id = ?'
        );
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new \RuntimeException(
                'Straße kann nicht gelöscht werden – es gibt noch zugeordnete Adress-Lokationen.'
            );
        }
        $this->pdo->prepare('DELETE FROM strassen WHERE id = ?')->execute([$id]);
    }

    // -------------------------------------------------------------------------
    // PLZ CRUD
    // -------------------------------------------------------------------------

    public function findAllPlzEintraege(array $filter = []): array
    {
        $where  = [];
        $params = [];

        if (!empty($filter['stadt_id'])) {
            $where[]  = 'p.stadt_id = ?';
            $params[] = (int) $filter['stadt_id'];
        }

        $sql = 'SELECT p.id, p.plz, p.stadt_id, sta.name AS stadt_name,
                       (SELECT COUNT(*) FROM adress_lokationen WHERE plz_id = p.id) AS anzahl_lokationen
                FROM plz p
                JOIN staedte sta ON sta.id = p.stadt_id';

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY sta.name, p.plz';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findPlzById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.id, p.plz, p.stadt_id, sta.name AS stadt_name
             FROM plz p
             JOIN staedte sta ON sta.id = p.stadt_id
             WHERE p.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function updatePlz(int $id, string $plz, int $stadtId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE plz SET plz = ?, stadt_id = ? WHERE id = ?');
        $stmt->execute([$plz, $stadtId, $id]);
        return $stmt->rowCount() > 0;
    }

    public function deletePlz(int $id): void
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM adress_lokationen WHERE plz_id = ?');
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new \RuntimeException(
                'PLZ kann nicht gelöscht werden – es gibt noch zugeordnete Adress-Lokationen.'
            );
        }
        $this->pdo->prepare('DELETE FROM plz WHERE id = ?')->execute([$id]);
    }

    // -------------------------------------------------------------------------
    // Lokationen CRUD
    // -------------------------------------------------------------------------

    public function findAllLokationen(array $filter = []): array
    {
        $where  = [];
        $params = [];

        if (!empty($filter['stadtteil_id'])) {
            $where[]  = 'al.stadtteil_id = ?';
            $params[] = (int) $filter['stadtteil_id'];
        }
        if (!empty($filter['strasse_id'])) {
            $where[]  = 'al.strasse_id = ?';
            $params[] = (int) $filter['strasse_id'];
        }
        if (!empty($filter['plz_id'])) {
            $where[]  = 'al.plz_id = ?';
            $params[] = (int) $filter['plz_id'];
        }

        $sql = 'SELECT al.id AS lokation_id,
                       s.id AS strasse_id,   s.name AS strasse_name,
                       st.id AS stadtteil_id, st.name AS stadtteil_name,
                       p.id AS plz_id,       p.plz,
                       sta.id AS stadt_id,   sta.name AS stadt_name,
                       (SELECT COUNT(*) FROM verlegeorte WHERE adress_lokation_id = al.id) AS anzahl_verlegeorte
                FROM adress_lokationen al
                JOIN strassen s ON s.id = al.strasse_id
                JOIN staedte sta ON sta.id = s.stadt_id
                LEFT JOIN stadtteile st ON st.id = al.stadtteil_id
                LEFT JOIN plz p ON p.id = al.plz_id';

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY sta.name, s.name, st.name, p.plz';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function updateLokation(int $id, int $strasseId, ?int $stadtteilId, ?int $plzId): void
    {
        $this->pdo->prepare(
            'UPDATE adress_lokationen SET strasse_id = ?, stadtteil_id = ?, plz_id = ? WHERE id = ?'
        )->execute([$strasseId, $stadtteilId, $plzId, $id]);
    }

    public function deleteLokation(int $id): void
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM verlegeorte WHERE adress_lokation_id = ?');
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new \RuntimeException(
                'Lokation kann nicht gelöscht werden – es gibt noch zugeordnete Verlegeorte.'
            );
        }
        $this->pdo->prepare('DELETE FROM adress_lokationen WHERE id = ?')->execute([$id]);
    }
}
