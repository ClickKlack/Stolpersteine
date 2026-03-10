<?php

declare(strict_types=1);

namespace Stolpersteine\Repository;

use PDO;
use Stolpersteine\Config\Database;

class PersonRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    // Liste mit optionalen Filtern
    public function findAll(array $filter = []): array
    {
        $where  = [];
        $params = [];

        if (!empty($filter['nachname'])) {
            $where[]  = 'nachname LIKE ?';
            $params[] = '%' . $filter['nachname'] . '%';
        }

        if (!empty($filter['geburtsname'])) {
            $where[]  = 'geburtsname LIKE ?';
            $params[] = '%' . $filter['geburtsname'] . '%';
        }

        if (!empty($filter['geburtsjahr'])) {
            $where[]  = 'YEAR(geburtsdatum) = ?';
            $params[] = (int) $filter['geburtsjahr'];
        }

        $sql = 'SELECT id, vorname, nachname, geburtsname, geburtsdatum, sterbedatum,
                       biografie_kurz, wikidata_id_person, erstellt_am, geaendert_am
                FROM personen';

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY nachname, vorname';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findByName(string $nachname, ?string $vorname): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, vorname, nachname, geburtsname, geburtsdatum, sterbedatum,
                    biografie_kurz, wikidata_id_person
             FROM personen
             WHERE LOWER(nachname) = LOWER(?)
               AND (? IS NULL OR LOWER(vorname) = LOWER(?))
             LIMIT 1'
        );
        $stmt->execute([$nachname, $vorname, $vorname]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, vorname, nachname, geburtsname, geburtsdatum, sterbedatum,
                    biografie_kurz, wikidata_id_person,
                    erstellt_am, erstellt_von, geaendert_am, geaendert_von
             FROM personen
             WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data, string $benutzer): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO personen
                (vorname, nachname, geburtsname, geburtsdatum, sterbedatum,
                 biografie_kurz, wikidata_id_person, erstellt_von, geaendert_von)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $data['vorname']            ?? null,
            $data['nachname'],
            $data['geburtsname']        ?? null,
            $data['geburtsdatum']       ?? null,
            $data['sterbedatum']        ?? null,
            $data['biografie_kurz']     ?? null,
            $data['wikidata_id_person'] ?? null,
            $benutzer,
            $benutzer,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data, string $benutzer): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE personen SET
                vorname            = ?,
                nachname           = ?,
                geburtsname        = ?,
                geburtsdatum       = ?,
                sterbedatum        = ?,
                biografie_kurz     = ?,
                wikidata_id_person = ?,
                geaendert_von      = ?
             WHERE id = ?'
        );

        $stmt->execute([
            $data['vorname']            ?? null,
            $data['nachname'],
            $data['geburtsname']        ?? null,
            $data['geburtsdatum']       ?? null,
            $data['sterbedatum']        ?? null,
            $data['biografie_kurz']     ?? null,
            $data['wikidata_id_person'] ?? null,
            $benutzer,
            $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        // Prüfen ob Stolpersteine verknüpft sind
        $check = $this->pdo->prepare(
            'SELECT COUNT(*) FROM stolpersteine WHERE person_id = ?'
        );
        $check->execute([$id]);
        if ((int) $check->fetchColumn() > 0) {
            throw new \RuntimeException('Person hat zugeordnete Stolpersteine und kann nicht gelöscht werden.');
        }

        $stmt = $this->pdo->prepare('DELETE FROM personen WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}
