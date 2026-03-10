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

    public function findOrCreateStrasse(string $name, int $stadtId, ?string $wikidataId): int
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

        $this->pdo->prepare('INSERT INTO strassen (name, stadt_id, wikidata_id) VALUES (?, ?, ?)')
                  ->execute([$name, $stadtId, $wikidataId]);
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
}
