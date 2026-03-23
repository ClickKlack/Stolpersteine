<?php

declare(strict_types=1);

namespace Stolpersteine\Auth;

use PDO;
use Stolpersteine\Config\Config;
use Stolpersteine\Config\Database;
use Stolpersteine\Config\Logger;
use Stolpersteine\Api\Response;
use Stolpersteine\Repository\BenutzerRepository;
use Stolpersteine\Service\MailService;

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
            Logger::get()->warning('Login fehlgeschlagen (Benutzer unbekannt oder inaktiv)', [
                'benutzername' => $benutzername,
            ]);
            return null;
        }

        if (!password_verify($passwort, $benutzer['passwort_hash'])) {
            Logger::get()->warning('Login fehlgeschlagen (falsches Passwort)', [
                'benutzername' => $benutzername,
            ]);
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

        Logger::get()->info('Benutzer eingeloggt', [
            'benutzername' => $sessionData['benutzername'],
            'rolle'        => $sessionData['rolle'],
        ]);

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

    /**
     * Passwort-Reset anfordern: generiert Token, speichert ihn und sendet Reset-Mail.
     * Gibt immer true zurück (kein Unterschied ob Benutzer gefunden oder nicht).
     */
    public static function requestPasswordReset(string $benutzernameOderEmail): bool
    {
        $repo     = new BenutzerRepository();
        $benutzer = $repo->findByBenutzernameOrEmail($benutzernameOderEmail);

        if ($benutzer === null) {
            Logger::get()->info('Passwort-Reset angefordert – Benutzer nicht gefunden (kein Detail zur Sicherheit)');
            return true;
        }

        if (empty($benutzer['email'])) {
            Logger::get()->info('Passwort-Reset angefordert – Benutzer hat keine E-Mail-Adresse', [
                'benutzer_id' => $benutzer['id'],
            ]);
            return true;
        }

        $token     = bin2hex(random_bytes(32));
        $ablaufSql = date('Y-m-d H:i:s', time() + 1800); // 30 Minuten

        $repo->setResetToken($benutzer['id'], $token, $ablaufSql);

        // Frontend-URL aus dem Referer ableiten, damit der Link in allen
        // Deployment-Varianten (Root vs. Unterverzeichnis) korrekt landet.
        // Sicherheitsprüfung: Referer-Origin muss in cors_origins erlaubt sein.
        $baseUrl  = rtrim(Config::get('app')['base_url'] ?? '', '/');
        $referer  = $_SERVER['HTTP_REFERER'] ?? '';
        if ($referer !== '') {
            $parts     = parse_url($referer);
            $refOrigin = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');
            if (!empty($parts['port'])) {
                $refOrigin .= ':' . $parts['port'];
            }
            $allowedOrigins = Config::get('app')['cors_origins'] ?? [];
            if (in_array($refOrigin, $allowedOrigins, true)) {
                $baseUrl = $refOrigin . rtrim($parts['path'] ?? '', '/');
            }
        }
        $resetUrl = $baseUrl . '/#passwort-reset?token=' . $token;

        try {
            MailService::sendPasswordReset(
                $benutzer['email'],
                $benutzer['benutzername'],
                $resetUrl
            );
        } catch (\Throwable $e) {
            Logger::get()->error('Reset-Mail-Versand fehlgeschlagen', [
                'benutzer_id' => $benutzer['id'],
                'error'       => $e->getMessage(),
            ]);
            // Trotzdem true zurückgeben (Enumeration-Schutz)
        }

        return true;
    }

    /**
     * Passwort via Token zurücksetzen.
     * Gibt true bei Erfolg, false bei ungültigem/abgelaufenem Token zurück.
     */
    public static function resetPassword(string $token, string $neuesPasswort): bool
    {
        $repo     = new BenutzerRepository();
        $benutzer = $repo->findByResetToken($token);

        if ($benutzer === null) {
            Logger::get()->warning('Passwort-Reset mit ungültigem oder abgelaufenem Token versucht');
            return false;
        }

        $hash = self::hashPassword($neuesPasswort);
        $repo->setPasswort($benutzer['id'], $hash);
        $repo->clearResetToken($benutzer['id']);

        Logger::get()->info('Passwort erfolgreich zurückgesetzt', [
            'benutzer_id' => $benutzer['id'],
        ]);

        return true;
    }
}
