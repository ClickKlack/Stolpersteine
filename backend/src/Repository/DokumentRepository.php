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
            $where[]  = 'EXISTS (SELECT 1 FROM dokument_personen dp WHERE dp.dokument_id = d.id AND dp.person_id = ?)';
            $params[] = (int) $filter['person_id'];
        }

        if (!empty($filter['stolperstein_id'])) {
            $where[]  = 'd.stolperstein_id = ?';
            $params[] = (int) $filter['stolperstein_id'];
        }

        if (!empty($filter['typ'])) {
            $where[]  = 'd.typ = ?';
            $params[] = $filter['typ'];
        }

        if (!empty($filter['url_fehler'])) {
            // Dokumente mit URL, deren Status nicht 200 ist (inkl. noch nicht geprüft)
            $where[] = 'd.quelle_url IS NOT NULL AND (d.url_status IS NULL OR d.url_status != 200)';
        }

        $sql = 'SELECT d.id, d.stolperstein_id, d.titel, d.beschreibung_kurz,
                       d.typ, d.dateiname, d.dateipfad, d.quelle_url,
                       d.groesse_bytes, d.quelle,
                       d.spiegel_pfad, d.spiegel_groesse_bytes,
                       d.url_geprueft_am, d.url_status,
                       d.erstellt_am, d.erstellt_von
                FROM dokumente d';

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY d.erstellt_am DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // Personen-IDs je Dokument nachladen
        foreach ($rows as &$row) {
            $row['person_ids'] = $this->getPersonIds((int) $row['id']);
        }
        unset($row);

        return $rows;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT d.id, d.stolperstein_id, d.titel, d.beschreibung_kurz,
                    d.typ, d.dateiname, d.dateipfad, d.quelle_url, d.hash,
                    d.groesse_bytes, d.quelle,
                    d.spiegel_pfad, d.spiegel_groesse_bytes,
                    d.url_geprueft_am, d.url_status,
                    d.erstellt_am, d.erstellt_von, d.geaendert_am, d.geaendert_von
             FROM dokumente d
             WHERE d.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $row['person_ids'] = $this->getPersonIds($id);
        $row['personen']   = $this->getPersonen($id);
        return $row;
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

    public function findByQuelleUrl(string $url): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, titel, quelle_url FROM dokumente WHERE quelle_url = ? LIMIT 1'
        );
        $stmt->execute([$url]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findAllWithUrl(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT d.id, d.titel, d.typ, d.quelle_url, d.quelle,
                    d.url_status, d.url_geprueft_am, d.dateiname
             FROM dokumente d
             WHERE d.quelle_url IS NOT NULL
             ORDER BY d.url_status ASC, d.titel ASC'
        );
        $stmt->execute();
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $row['person_ids'] = $this->getPersonIds((int) $row['id']);
        }
        unset($row);

        return $rows;
    }

    public function create(array $data, string $benutzer): int
    {
        $urlStatus    = isset($data['url_status']) ? (int) $data['url_status'] : null;
        $geprueftAm   = $urlStatus !== null ? date('Y-m-d H:i:s') : null;

        $stmt = $this->pdo->prepare(
            'INSERT INTO dokumente
                (stolperstein_id, titel, beschreibung_kurz,
                 typ, dateiname, dateipfad, quelle_url, hash, groesse_bytes,
                 quelle, url_status, url_geprueft_am, erstellt_von, geaendert_von)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            isset($data['stolperstein_id']) ? (int) $data['stolperstein_id'] : null,
            $data['titel'],
            $data['beschreibung_kurz'] ?? null,
            $data['typ']               ?? null,
            $data['dateiname']         ?? null,
            $data['dateipfad']         ?? null,
            $data['quelle_url']        ?? null,
            $data['hash']              ?? null,
            $data['groesse_bytes']     ?? null,
            $data['quelle']     ?? null,
            $urlStatus,
            $geprueftAm,
            $benutzer,
            $benutzer,
        ]);

        $id = (int) $this->pdo->lastInsertId();

        // Personen verknüpfen
        foreach ($data['person_ids'] ?? [] as $personId) {
            $this->addPerson($id, (int) $personId);
        }

        return $id;
    }

    public function update(int $id, array $data, string $benutzer): void
    {
        $urlStatus  = array_key_exists('url_status', $data) && $data['url_status'] !== null
            ? (int) $data['url_status'] : null;
        $geprueftAm = $urlStatus !== null ? date('Y-m-d H:i:s') : null;

        $sql = 'UPDATE dokumente SET
                titel             = ?,
                beschreibung_kurz = ?,
                typ               = ?,
                dateiname         = ?,
                quelle_url        = ?,
                groesse_bytes     = ?,
                quelle     = ?,
                stolperstein_id   = ?,
                geaendert_am      = NOW(),
                geaendert_von     = ?';

        $params = [
            $data['titel'],
            $data['beschreibung_kurz'] ?? null,
            $data['typ']               ?? null,
            $data['dateiname']         ?? null,
            $data['quelle_url']        ?? null,
            $data['groesse_bytes']     ?? null,
            $data['quelle']     ?? null,
            isset($data['stolperstein_id']) ? (int) $data['stolperstein_id'] : null,
            $benutzer,
        ];

        if ($urlStatus !== null) {
            $sql      .= ', url_status = ?, url_geprueft_am = ?';
            $params[]  = $urlStatus;
            $params[]  = $geprueftAm;
        }

        $sql     .= ' WHERE id = ?';
        $params[] = $id;

        $this->pdo->prepare($sql)->execute($params);

        // Personenverknüpfungen synchronisieren
        $this->pdo->prepare('DELETE FROM dokument_personen WHERE dokument_id = ?')->execute([$id]);
        foreach ($data['person_ids'] ?? [] as $personId) {
            $this->addPerson($id, (int) $personId);
        }
    }

    public function addPerson(int $dokId, int $personId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO dokument_personen (dokument_id, person_id) VALUES (?, ?)'
        );
        $stmt->execute([$dokId, $personId]);
    }

    public function removePerson(int $dokId, int $personId): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM dokument_personen WHERE dokument_id = ? AND person_id = ?'
        );
        $stmt->execute([$dokId, $personId]);
    }

    public function updateUrlCheck(int $id, int $status, string $geprueftAm, ?int $groesseBytes = null): void
    {
        if ($groesseBytes !== null) {
            $stmt = $this->pdo->prepare(
                'UPDATE dokumente SET url_status = ?, url_geprueft_am = ?, groesse_bytes = ?, geaendert_am = NOW() WHERE id = ?'
            );
            $stmt->execute([$status, $geprueftAm, $groesseBytes, $id]);
        } else {
            $stmt = $this->pdo->prepare(
                'UPDATE dokumente SET url_status = ?, url_geprueft_am = ?, geaendert_am = NOW() WHERE id = ?'
            );
            $stmt->execute([$status, $geprueftAm, $id]);
        }
    }

    public function updateDateiname(int $id, string $dateiname, ?string $neuerTitel): void
    {
        if ($neuerTitel !== null) {
            $stmt = $this->pdo->prepare(
                'UPDATE dokumente SET dateiname = ?, titel = ?, geaendert_am = NOW() WHERE id = ?'
            );
            $stmt->execute([$dateiname, $neuerTitel, $id]);
        } else {
            $stmt = $this->pdo->prepare(
                'UPDATE dokumente SET dateiname = ?, geaendert_am = NOW() WHERE id = ?'
            );
            $stmt->execute([$dateiname, $id]);
        }
    }

    public function updateSpiegel(int $id, string $pfad, int $groesse): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE dokumente SET spiegel_pfad = ?, spiegel_groesse_bytes = ?, geaendert_am = NOW() WHERE id = ?'
        );
        $stmt->execute([$pfad, $groesse, $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM dokumente WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    // -------------------------------------------------------------------------

    private function getPersonIds(int $dokId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT person_id FROM dokument_personen WHERE dokument_id = ? ORDER BY person_id'
        );
        $stmt->execute([$dokId]);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'person_id');
    }

    public function setBiografieForPerson(int $dokId, int $personId): void
    {
        $this->pdo->prepare(
            'UPDATE personen SET biografie_dokument_id = ? WHERE id = ?'
        )->execute([$dokId, $personId]);
    }

    public function clearBiografieForPerson(int $personId): void
    {
        $this->pdo->prepare(
            'UPDATE personen SET biografie_dokument_id = NULL WHERE id = ?'
        )->execute([$personId]);
    }

    private function getPersonen(int $dokId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.id, p.vorname, p.nachname, p.geburtsname, p.geburtsdatum,
                    (p.biografie_dokument_id = ?) AS ist_biografie
             FROM dokument_personen dp
             JOIN personen p ON p.id = dp.person_id
             WHERE dp.dokument_id = ?
             ORDER BY p.nachname, p.vorname'
        );
        $stmt->execute([$dokId, $dokId]);
        return $stmt->fetchAll();
    }
}
