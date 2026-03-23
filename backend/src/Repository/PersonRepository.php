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

        // Kombinierte Namenssuche: Vor-, Nach- und Geburtsname (auch als Vollname)
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

        if (!empty($filter['geburtsjahr'])) {
            $where[]  = 'YEAR(p.geburtsdatum) = ?';
            $params[] = (int) $filter['geburtsjahr'];
        }

        if (!empty($filter['status'])) {
            $where[]  = 'p.status = ?';
            $params[] = $filter['status'];
        }

        $sql = 'SELECT p.id, p.vorname, p.nachname, p.geburtsname,
                       p.geburtsdatum, p.geburtsdatum_genauigkeit,
                       p.sterbedatum, p.sterbedatum_genauigkeit,
                       p.biografie_kurz, p.bemerkung, p.biografie_dokument_id,
                       dok.titel      AS biografie_dok_titel,
                       dok.quelle_url AS biografie_dok_url,
                       p.wikipedia_name, p.wikidata_id_person, p.status, p.erstellt_am, p.geaendert_am,
                       COALESCE(sc.anzahl, 0) AS stolpersteine_anzahl
                FROM personen p
                LEFT JOIN dokumente dok ON dok.id = p.biografie_dokument_id
                LEFT JOIN (SELECT person_id, COUNT(*) AS anzahl FROM stolpersteine GROUP BY person_id) sc
                    ON sc.person_id = p.id';

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY p.nachname, p.vorname';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findByName(string $nachname, ?string $vorname): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, vorname, nachname, geburtsname, geburtsdatum, sterbedatum,
                    biografie_kurz, wikipedia_name, wikidata_id_person
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
            'SELECT p.id, p.vorname, p.nachname, p.geburtsname,
                    p.geburtsdatum, p.geburtsdatum_genauigkeit,
                    p.sterbedatum, p.sterbedatum_genauigkeit,
                    p.biografie_kurz, p.bemerkung, p.biografie_dokument_id,
                    dok.titel      AS biografie_dok_titel,
                    dok.quelle_url AS biografie_dok_url,
                    p.wikipedia_name, p.wikidata_id_person, p.status,
                    p.erstellt_am, p.erstellt_von, p.geaendert_am, p.geaendert_von
             FROM personen p
             LEFT JOIN dokumente dok ON dok.id = p.biografie_dokument_id
             WHERE p.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data, string $benutzer): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO personen
                (vorname, nachname, geburtsname,
                 geburtsdatum, geburtsdatum_genauigkeit,
                 sterbedatum, sterbedatum_genauigkeit,
                 biografie_kurz, bemerkung, wikipedia_name, wikidata_id_person,
                 status, erstellt_von, geaendert_von)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $data['vorname']                     ?? null,
            $data['nachname'],
            $data['geburtsname']                 ?? null,
            $data['geburtsdatum']                ?? null,
            $data['geburtsdatum_genauigkeit']    ?? null,
            $data['sterbedatum']                 ?? null,
            $data['sterbedatum_genauigkeit']     ?? null,
            $data['biografie_kurz']              ?? null,
            $data['bemerkung']                   ?? null,
            $data['wikipedia_name']              ?? null,
            $data['wikidata_id_person']          ?? null,
            $data['status']                      ?? 'validierung',
            $benutzer,
            $benutzer,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data, string $benutzer): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE personen SET
                vorname                     = ?,
                nachname                    = ?,
                geburtsname                 = ?,
                geburtsdatum                = ?,
                geburtsdatum_genauigkeit    = ?,
                sterbedatum                 = ?,
                sterbedatum_genauigkeit     = ?,
                biografie_kurz              = ?,
                bemerkung                   = ?,
                wikipedia_name              = ?,
                wikidata_id_person          = ?,
                status                      = ?,
                geaendert_von               = ?
             WHERE id = ?'
        );

        $stmt->execute([
            $data['vorname']                     ?? null,
            $data['nachname'],
            $data['geburtsname']                 ?? null,
            $data['geburtsdatum']                ?? null,
            $data['geburtsdatum_genauigkeit']    ?? null,
            $data['sterbedatum']                 ?? null,
            $data['sterbedatum_genauigkeit']     ?? null,
            $data['biografie_kurz']              ?? null,
            $data['bemerkung']                   ?? null,
            $data['wikipedia_name']              ?? null,
            $data['wikidata_id_person']          ?? null,
            in_array($data['status'] ?? '', ['ok', 'validierung'], true) ? $data['status'] : 'validierung',
            $benutzer,
            $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function setBiografie(int $personId, int $dokId): void
    {
        $this->pdo->prepare(
            'UPDATE personen SET biografie_dokument_id = ? WHERE id = ?'
        )->execute([$dokId, $personId]);
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
