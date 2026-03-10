<?php

declare(strict_types=1);

namespace Stolpersteine\Repository;

use PDO;
use Stolpersteine\Config\Database;

class DokumentRepository
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

        if (!empty($filter['person_id'])) {
            $where[]  = 'person_id = ?';
            $params[] = (int) $filter['person_id'];
        }

        if (!empty($filter['stolperstein_id'])) {
            $where[]  = 'stolperstein_id = ?';
            $params[] = (int) $filter['stolperstein_id'];
        }

        if (!empty($filter['typ'])) {
            $where[]  = 'typ = ?';
            $params[] = $filter['typ'];
        }

        $sql = 'SELECT id, person_id, stolperstein_id, titel, beschreibung_kurz,
                       typ, dateiname, dateipfad, quelle_url, groesse_bytes,
                       erstellt_am, erstellt_von
                FROM dokumente';

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY erstellt_am DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, person_id, stolperstein_id, titel, beschreibung_kurz,
                    typ, dateiname, dateipfad, quelle_url, hash, groesse_bytes,
                    erstellt_am, erstellt_von, geaendert_am, geaendert_von
             FROM dokumente
             WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByHash(string $hash): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, titel, dateipfad FROM dokumente WHERE hash = ? LIMIT 1'
        );
        $stmt->execute([$hash]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data, string $benutzer): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO dokumente
                (person_id, stolperstein_id, titel, beschreibung_kurz,
                 typ, dateiname, dateipfad, quelle_url, hash, groesse_bytes,
                 erstellt_von, geaendert_von)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            isset($data['person_id'])       ? (int) $data['person_id']       : null,
            isset($data['stolperstein_id']) ? (int) $data['stolperstein_id'] : null,
            $data['titel'],
            $data['beschreibung_kurz'] ?? null,
            $data['typ']               ?? null,
            $data['dateiname']         ?? null,
            $data['dateipfad']         ?? null,
            $data['quelle_url']        ?? null,
            $data['hash']              ?? null,
            $data['groesse_bytes']     ?? null,
            $benutzer,
            $benutzer,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM dokumente WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}
