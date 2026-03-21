<?php

declare(strict_types=1);

namespace Stolpersteine\Api;

use Stolpersteine\Auth\Auth;
use Stolpersteine\Config\Database;
use Stolpersteine\Config\Logger;
use Stolpersteine\Repository\BenutzerRepository;

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

        if ($user) {
            Logger::get()->info('Benutzer ausgeloggt', ['benutzername' => $user['benutzername']]);
        }

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

    public function profil(array $params): void
    {
        $user = Auth::required();
        $repo = new BenutzerRepository();
        $data = $repo->findById($user['id']);

        if ($data === null) {
            Response::error('Benutzer nicht gefunden.', 404);
        }

        Response::success($data);
    }

    public function profilAktualisieren(array $params): void
    {
        $user = Auth::required();
        $body = $this->jsonBody();
        $repo = new BenutzerRepository();

        $updates = [];

        // E-Mail ändern
        if (array_key_exists('email', $body)) {
            $updates['email'] = trim($body['email'] ?? '');
        }

        // Passwort ändern
        if (!empty($body['neues_passwort'])) {
            $aktuelles = $body['aktuelles_passwort'] ?? '';

            if ($aktuelles === '') {
                Response::error('Aktuelles Passwort ist erforderlich.', 422);
            }

            if (!$repo->verifyPassword($user['id'], $aktuelles)) {
                Logger::get()->warning('Profil-Passwortänderung fehlgeschlagen – falsches aktuelles Passwort', [
                    'benutzername' => $user['benutzername'],
                ]);
                Response::error('Das aktuelle Passwort ist falsch.', 403);
            }

            if (strlen($body['neues_passwort']) < 8) {
                Response::error('Das neue Passwort muss mindestens 8 Zeichen lang sein.', 422);
            }

            $updates['passwort'] = $body['neues_passwort'];
        }

        if (empty($updates)) {
            Response::error('Keine Änderungen übermittelt.', 422);
        }

        $repo->update($user['id'], $updates, $user['benutzername']);

        Logger::get()->info('Profil aktualisiert', ['benutzername' => $user['benutzername']]);

        Response::success($repo->findById($user['id']));
    }

    public function passwortVergessen(array $params): void
    {
        $body = $this->jsonBody();

        $eingabe = trim($body['benutzername_oder_email'] ?? '');

        if ($eingabe === '') {
            Response::error('Benutzername oder E-Mail-Adresse erforderlich.', 422);
        }

        Auth::requestPasswordReset($eingabe);

        // Immer gleiche Antwort – kein Hinweis ob Benutzer existiert
        Response::success([
            'message' => 'Falls die Adresse bekannt ist, wurde eine E-Mail versandt.',
        ]);
    }

    public function passwortReset(array $params): void
    {
        $body = $this->jsonBody();

        $token        = trim($body['token'] ?? '');
        $neuesPasswort = $body['neues_passwort'] ?? '';

        if ($token === '') {
            Response::error('Token erforderlich.', 422);
        }

        if (strlen($neuesPasswort) < 8) {
            Response::error('Das neue Passwort muss mindestens 8 Zeichen lang sein.', 422);
        }

        $erfolg = Auth::resetPassword($token, $neuesPasswort);

        if (!$erfolg) {
            Response::error('Der Link ist ungültig oder abgelaufen.', 400);
        }

        Response::success([
            'message' => 'Passwort erfolgreich geändert.',
        ]);
    }

}
