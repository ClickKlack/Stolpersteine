<?php

declare(strict_types=1);

namespace Stolpersteine\Repository;

use Stolpersteine\Auth\Auth;
use Stolpersteine\Config\Database;

class BenutzerRepository
{
    private const FIELDS = 'id, benutzername, email, rolle, aktiv, erstellt_am, erstellt_von, geaendert_am, geaendert_von';

    public function findAll(array $filter = []): array
    {
        $pdo  = Database::connection();
        $sql  = 'SELECT ' . self::FIELDS . ' FROM benutzer WHERE 1=1';
        $bind = [];

        if (!empty($filter['benutzername'])) {
            $sql    .= ' AND benutzername LIKE ?';
            $bind[]  = '%' . $filter['benutzername'] . '%';
        }

        if (isset($filter['rolle']) && $filter['rolle'] !== '') {
            $sql    .= ' AND rolle = ?';
            $bind[]  = $filter['rolle'];
        }

        if (isset($filter['aktiv']) && $filter['aktiv'] !== '') {
            $sql    .= ' AND aktiv = ?';
            $bind[]  = (int) $filter['aktiv'];
        }

        $sql .= ' ORDER BY benutzername ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($bind);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $pdo  = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT ' . self::FIELDS . ' FROM benutzer WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByBenutzernameOrEmail(string $q): ?array
    {
        $pdo  = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT id, benutzername, email, rolle, aktiv
             FROM benutzer
             WHERE aktiv = 1 AND (benutzername = ? OR email = ?)
             LIMIT 1'
        );
        $stmt->execute([$q, $q]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function verifyPassword(int $id, string $passwort): bool
    {
        $pdo  = Database::connection();
        $stmt = $pdo->prepare('SELECT passwort_hash FROM benutzer WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }
        return password_verify($passwort, $row['passwort_hash']);
    }

    public function findByResetToken(string $token): ?array
    {
        $pdo  = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT id, benutzername, email
             FROM benutzer
             WHERE passwort_reset_token = ? AND passwort_reset_ablauf > NOW()
             LIMIT 1'
        );
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data, string $byUser): int
    {
        $pdo = Database::connection();

        // Kein Passwort vom Admin – zufälligen, nicht einlogbaren Hash setzen
        $hash = Auth::hashPassword(bin2hex(random_bytes(32)));

        $stmt = $pdo->prepare(
            'INSERT INTO benutzer
                (benutzername, passwort_hash, email, rolle, aktiv, erstellt_von, geaendert_von)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['benutzername'],
            $hash,
            $data['email'] ?? null,
            $data['rolle'] ?? 'editor',
            isset($data['aktiv']) ? (int) $data['aktiv'] : 1,
            $byUser,
            $byUser,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public function update(int $id, array $data, string $byUser): void
    {
        $pdo    = Database::connection();
        $fields = [];
        $bind   = [];

        if (isset($data['email'])) {
            $fields[] = 'email = ?';
            $bind[]   = $data['email'];
        }
        if (isset($data['rolle'])) {
            $fields[] = 'rolle = ?';
            $bind[]   = $data['rolle'];
        }
        if (isset($data['aktiv'])) {
            $fields[] = 'aktiv = ?';
            $bind[]   = (int) $data['aktiv'];
        }
        if (empty($fields)) {
            return;
        }

        $fields[] = 'geaendert_von = ?';
        $bind[]   = $byUser;

        $bind[] = $id;

        $pdo->prepare(
            'UPDATE benutzer SET ' . implode(', ', $fields) . ' WHERE id = ?'
        )->execute($bind);
    }

    public function delete(int $id): void
    {
        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM benutzer WHERE id = ?')->execute([$id]);
    }

    public function setResetToken(int $id, string $token, string $ablaufSql): void
    {
        $pdo = Database::connection();
        $pdo->prepare(
            'UPDATE benutzer SET passwort_reset_token = ?, passwort_reset_ablauf = ? WHERE id = ?'
        )->execute([$token, $ablaufSql, $id]);
    }

    public function clearResetToken(int $id): void
    {
        $pdo = Database::connection();
        $pdo->prepare(
            'UPDATE benutzer SET passwort_reset_token = NULL, passwort_reset_ablauf = NULL WHERE id = ?'
        )->execute([$id]);
    }

    public function setPasswort(int $id, string $passwortHash): void
    {
        $pdo = Database::connection();
        $pdo->prepare(
            'UPDATE benutzer SET passwort_hash = ? WHERE id = ?'
        )->execute([$passwortHash, $id]);
    }
}
