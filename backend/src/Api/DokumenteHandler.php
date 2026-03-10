<?php

declare(strict_types=1);

namespace Stolpersteine\Api;

use Stolpersteine\Auth\Auth;
use Stolpersteine\Repository\DokumentRepository;
use Stolpersteine\Repository\AuditRepository;
use Stolpersteine\Service\DateiService;

class DokumenteHandler extends BaseHandler
{
    private DokumentRepository $repo;
    private DateiService $dateiService;

    public function __construct()
    {
        $this->repo         = new DokumentRepository();
        $this->dateiService = new DateiService();
    }

    // GET /dokumente?person_id=&stolperstein_id=&typ=
    public function index(array $params): void
    {
        Auth::required();

        $filter = array_filter([
            'person_id'       => $this->queryParam('person_id'),
            'stolperstein_id' => $this->queryParam('stolperstein_id'),
            'typ'             => $this->queryParam('typ'),
        ]);

        Response::success($this->repo->findAll($filter));
    }

    // GET /dokumente/{id}
    public function show(array $params): void
    {
        Auth::required();

        $id  = $this->intParam($params, 'id');
        $dok = $this->repo->findById($id);

        if ($dok === null) {
            Response::error('Dokument nicht gefunden.', 404);
        }

        Response::success($dok);
    }

    // POST /dokumente — multipart/form-data oder JSON (für URL-Dokumente)
    public function create(array $params): void
    {
        $user = Auth::required();

        // Datei-Upload
        if (!empty($_FILES['datei'])) {
            $this->createWithFile($user);
            return;
        }

        // Nur URL / Metadaten (kein Datei-Upload)
        $this->createWithoutFile($user);
    }

    // DELETE /dokumente/{id}
    public function delete(array $params): void
    {
        $user = Auth::required();
        $id   = $this->intParam($params, 'id');

        $dok = $this->repo->findById($id);
        if ($dok === null) {
            Response::error('Dokument nicht gefunden.', 404);
        }

        // Datei vom Dateisystem entfernen
        if (!empty($dok['dateipfad'])) {
            $this->dateiService->delete($dok['dateipfad']);
        }

        $this->repo->delete($id);

        AuditRepository::log($user['benutzername'], 'DELETE', 'dokumente', $id, $dok, null);

        Response::noContent();
    }

    // --- private Hilfsmethoden ---

    private function createWithFile(array $user): void
    {
        $titel = trim($_POST['titel'] ?? '');
        if ($titel === '') {
            Response::error('Titel ist erforderlich.', 422);
        }

        try {
            $dateiInfo = $this->dateiService->save($_FILES['datei']);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), 500);
        }

        // Duplikat-Check
        $duplikat = $this->repo->findByHash($dateiInfo['hash']);
        if ($duplikat !== null) {
            Response::error(
                'Datei bereits vorhanden.',
                409,
                ['vorhandenes_dokument_id' => $duplikat['id'], 'titel' => $duplikat['titel']]
            );
        }

        $data = array_merge($dateiInfo, [
            'titel'            => $titel,
            'beschreibung_kurz'=> $_POST['beschreibung_kurz'] ?? null,
            'person_id'        => $_POST['person_id']       ?? null,
            'stolperstein_id'  => $_POST['stolperstein_id'] ?? null,
            'quelle_url'       => null,
        ]);

        $id  = $this->repo->create($data, $user['benutzername']);
        $dok = $this->repo->findById($id);

        AuditRepository::log($user['benutzername'], 'INSERT', 'dokumente', $id, null, $dok);

        Response::created($dok);
    }

    private function createWithoutFile(array $user): void
    {
        $body  = $this->jsonBody();
        $titel = trim($body['titel'] ?? '');

        if ($titel === '') {
            Response::error('Titel ist erforderlich.', 422);
        }

        if (empty($body['quelle_url'])) {
            Response::error('Entweder eine Datei oder eine quelle_url ist erforderlich.', 422);
        }

        $data = [
            'titel'            => $titel,
            'beschreibung_kurz'=> $body['beschreibung_kurz'] ?? null,
            'person_id'        => $body['person_id']         ?? null,
            'stolperstein_id'  => $body['stolperstein_id']   ?? null,
            'quelle_url'       => $body['quelle_url'],
            'typ'              => $body['typ']                ?? 'url',
            'dateiname'        => null,
            'dateipfad'        => null,
            'hash'             => null,
            'groesse_bytes'    => null,
        ];

        $id  = $this->repo->create($data, $user['benutzername']);
        $dok = $this->repo->findById($id);

        AuditRepository::log($user['benutzername'], 'INSERT', 'dokumente', $id, null, $dok);

        Response::created($dok);
    }
}
