<?php

declare(strict_types=1);

namespace Stolpersteine\Api;

use Stolpersteine\Auth\Auth;
use Stolpersteine\Config\Database;

class AuthHandler extends BaseHandler
{
    public function login(array $params): void
    {
        $body = $this->jsonBody();

        $benutzername = trim($body['benutzername'] ?? '');
        $passwort     = $body['passwort'] ?? '';

        if ($benutzername === '' || $passwort === '') {
            Response::error('Benutzername und Passwort erforderlich.', 422);
        }

        $benutzer = Auth::login($benutzername, $passwort);

        if ($benutzer === null) {
            // Bewusst keine Unterscheidung zwischen "unbekannt" und "falsches Passwort"
            Response::error('Ungültige Anmeldedaten.', 401);
        }

        // Login im Audit-Log festhalten
        $pdo = Database::connection();
        $pdo->prepare(
            'INSERT INTO audit_log (benutzer, aktion, zeitpunkt)
             VALUES (?, \'LOGIN\', NOW())'
        )->execute([$benutzer['benutzername']]);

        Response::success([
            'benutzername' => $benutzer['benutzername'],
            'rolle'        => $benutzer['rolle'],
        ]);
    }

    public function logout(array $params): void
    {
        $user = Auth::user();
        Auth::logout();

        if ($user) {
            $pdo = Database::connection();
            $pdo->prepare(
                'INSERT INTO audit_log (benutzer, aktion, zeitpunkt)
                 VALUES (?, \'LOGOUT\', NOW())'
            )->execute([$user['benutzername']]);
        }

        Response::noContent();
    }

    public function me(array $params): void
    {
        $user = Auth::required();

        Response::success([
            'benutzername' => $user['benutzername'],
            'rolle'        => $user['rolle'],
        ]);
    }

}
