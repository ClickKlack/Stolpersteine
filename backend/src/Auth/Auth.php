<?php

declare(strict_types=1);

namespace Stolpersteine\Auth;

use PDO;
use Stolpersteine\Config\Config;
use Stolpersteine\Config\Database;
use Stolpersteine\Api\Response;

class Auth
{
    private static bool $sessionStarted = false;

    // Session starten (einmalig)
    private static function start(): void
    {
        if (self::$sessionStarted) {
            return;
        }

        $cfg = Config::get('session');

        session_name($cfg['name'] ?? 'stolpersteine_sess');

        session_set_cookie_params([
            'lifetime' => 0,                    // Cookie gilt bis Browser-Ende
            'path'     => '/',
            'secure'   => !(($host = explode(':', $_SERVER['HTTP_HOST'])[0]) === 'localhost' || str_starts_with($host, '127.')),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        session_start();
        self::$sessionStarted = true;
    }

    // Gibt den eingeloggten Benutzer zurück oder null
    public static function user(): ?array
    {
        self::start();
        return $_SESSION['benutzer'] ?? null;
    }

    // Gibt die Rolle des eingeloggten Benutzers zurück
    public static function role(): ?string
    {
        return self::user()['rolle'] ?? null;
    }

    // Prüft ob eingeloggt, bricht sonst mit 401 ab
    public static function required(): array
    {
        $user = self::user();
        if ($user === null) {
            Response::error('Nicht authentifiziert.', 401);
        }
        return $user;
    }

    // Prüft ob Admin, bricht sonst mit 403 ab
    public static function requireAdmin(): array
    {
        $user = self::required();
        if ($user['rolle'] !== 'admin') {
            Response::error('Keine Berechtigung.', 403);
        }
        return $user;
    }

    // Login: Passwort prüfen, Session setzen
    public static function login(string $benutzername, string $passwort): ?array
    {
        $pdo  = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT id, benutzername, passwort_hash, rolle, aktiv
             FROM benutzer
             WHERE benutzername = ?
             LIMIT 1'
        );
        $stmt->execute([$benutzername]);
        $benutzer = $stmt->fetch();

        if (!$benutzer || !$benutzer['aktiv']) {
            return null;
        }

        if (!password_verify($passwort, $benutzer['passwort_hash'])) {
            return null;
        }

        self::start();

        // Session-ID erneuern nach Login (Session Fixation verhindern)
        session_regenerate_id(true);

        $sessionData = [
            'id'          => $benutzer['id'],
            'benutzername'=> $benutzer['benutzername'],
            'rolle'       => $benutzer['rolle'],
        ];

        $_SESSION['benutzer']      = $sessionData;
        $_SESSION['eingeloggt_am'] = time();

        return $sessionData;
    }

    // Logout: Session zerstören
    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        session_destroy();
    }

    // Passwort-Hash erzeugen (für Benutzeranlage)
    public static function hashPassword(string $passwort): string
    {
        return password_hash($passwort, PASSWORD_BCRYPT, ['cost' => 12]);
    }
}
