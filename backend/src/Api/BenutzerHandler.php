<?php

declare(strict_types=1);

namespace Stolpersteine\Api;

use Stolpersteine\Auth\Auth;
use Stolpersteine\Config\Logger;
use Stolpersteine\Repository\AuditRepository;
use Stolpersteine\Repository\BenutzerRepository;

class BenutzerHandler extends BaseHandler
{
    private BenutzerRepository $repo;

    public function __construct()
    {
        $this->repo = new BenutzerRepository();
    }

    public function index(array $params): void
    {
        Auth::requireAdmin();

        $filter = array_filter([
            'benutzername' => $this->queryParam('benutzername'),
            'rolle'        => $this->queryParam('rolle'),
            'aktiv'        => $this->queryParam('aktiv'),
        ], fn($v) => $v !== null && $v !== '');

        Logger::get()->debug('Benutzer-Liste abgerufen', ['filter' => $filter]);

        Response::success($this->repo->findAll($filter));
    }

    public function show(array $params): void
    {
        Auth::requireAdmin();

        $id       = $this->intParam($params, 'id');
        $benutzer = $this->repo->findById($id);

        if ($benutzer === null) {
            Response::error('Benutzer nicht gefunden.', 404);
        }

        Response::success($benutzer);
    }

    public function create(array $params): void
    {
        $admin = Auth::requireAdmin();
        $body  = $this->jsonBody();

        $benutzername = trim($body['benutzername'] ?? '');
        $email        = trim($body['email'] ?? '');

        if ($benutzername === '') {
            Response::error('Benutzername ist erforderlich.', 422);
        }
        if ($email === '') {
            Response::error('E-Mail-Adresse ist erforderlich (wird für die Einladungs-Mail benötigt).', 422);
        }

        try {
            $id  = $this->repo->create($body, $admin['benutzername']);
            $neu = $this->repo->findById($id);

            AuditRepository::log($admin['benutzername'], 'INSERT', 'benutzer', $id, null, $neu);

            Logger::get()->info('Benutzer erstellt', [
                'neu_benutzername' => $benutzername,
                'von'              => $admin['benutzername'],
            ]);

            // Einladungs-Mail mit Reset-Link senden
            $mailGesendet = Auth::requestPasswordReset($email);
            if (!$mailGesendet) {
                Logger::get()->warning('Einladungs-Mail nach Benutzeranlage nicht gesendet', [
                    'benutzer_id' => $id,
                ]);
            }

            Response::created($neu);
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                Response::error('Benutzername bereits vergeben.', 409);
            }
            Logger::get()->error('Fehler beim Erstellen des Benutzers', ['error' => $e->getMessage()]);
            Response::error('Fehler beim Erstellen des Benutzers.', 500);
        }
    }

    public function passwortResetSenden(array $params): void
    {
        $admin = Auth::requireAdmin();
        $id    = $this->intParam($params, 'id');

        $benutzer = $this->repo->findById($id);
        if ($benutzer === null) {
            Response::error('Benutzer nicht gefunden.', 404);
        }

        if (empty($benutzer['email'])) {
            Response::error('Dieser Benutzer hat keine E-Mail-Adresse hinterlegt.', 422);
        }

        Auth::requestPasswordReset($benutzer['email']);

        Logger::get()->info('Admin hat Passwort-Reset ausgelöst', [
            'benutzer_id' => $id,
            'von'         => $admin['benutzername'],
        ]);

        Response::success(['message' => 'Passwort-Reset-Mail wurde gesendet.']);
    }

    public function update(array $params): void
    {
        $admin = Auth::requireAdmin();
        $id    = $this->intParam($params, 'id');
        $body  = $this->jsonBody();

        $benutzer = $this->repo->findById($id);
        if ($benutzer === null) {
            Response::error('Benutzer nicht gefunden.', 404);
        }

        $email = trim($body['email'] ?? '');
        if ($email === '') {
            Response::error('E-Mail-Adresse ist erforderlich.', 422);
        }

        // Admin darf sich nicht selbst deaktivieren
        if (
            $benutzer['benutzername'] === $admin['benutzername']
            && isset($body['aktiv'])
            && !(bool) $body['aktiv']
        ) {
            Logger::get()->warning('Admin versuchte, sich selbst zu deaktivieren', [
                'benutzername' => $admin['benutzername'],
            ]);
            Response::error('Du kannst deinen eigenen Account nicht deaktivieren.', 403);
        }

        if (!empty($body['passwort']) && strlen($body['passwort']) < 8) {
            Response::error('Das Passwort muss mindestens 8 Zeichen lang sein.', 422);
        }

        $alt = $benutzer;
        $this->repo->update($id, $body, $admin['benutzername']);
        $neu = $this->repo->findById($id);

        AuditRepository::log($admin['benutzername'], 'UPDATE', 'benutzer', $id, $alt, $neu);

        Logger::get()->info('Benutzer aktualisiert', [
            'benutzer_id'  => $id,
            'benutzername' => $benutzer['benutzername'],
            'von'          => $admin['benutzername'],
        ]);

        Response::success($neu);
    }

    public function delete(array $params): void
    {
        $admin = Auth::requireAdmin();
        $id    = $this->intParam($params, 'id');

        $benutzer = $this->repo->findById($id);
        if ($benutzer === null) {
            Response::error('Benutzer nicht gefunden.', 404);
        }

        // Admin darf sich nicht selbst löschen
        if ($benutzer['benutzername'] === $admin['benutzername']) {
            Logger::get()->warning('Admin versuchte, sich selbst zu löschen', [
                'benutzername' => $admin['benutzername'],
            ]);
            Response::error('Du kannst deinen eigenen Account nicht löschen.', 403);
        }

        $this->repo->delete($id);

        AuditRepository::log($admin['benutzername'], 'DELETE', 'benutzer', $id, $benutzer, null);

        Logger::get()->warning('Benutzer gelöscht', [
            'benutzer_id'  => $id,
            'benutzername' => $benutzer['benutzername'],
            'von'          => $admin['benutzername'],
        ]);

        Response::noContent();
    }

    public function audit(array $params): void
    {
        Auth::requireAdmin();

        $id       = $this->intParam($params, 'id');
        $benutzer = $this->repo->findById($id);

        if ($benutzer === null) {
            Response::error('Benutzer nicht gefunden.', 404);
        }

        $eintraege = AuditRepository::findByBenutzer($benutzer['benutzername']);

        Response::success($eintraege);
    }
}
