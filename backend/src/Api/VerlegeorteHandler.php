<?php

declare(strict_types=1);

namespace Stolpersteine\Api;

use Stolpersteine\Auth\Auth;
use Stolpersteine\Config\Logger;
use Stolpersteine\Repository\VerlegeortRepository;
use Stolpersteine\Repository\StolpersteinRepository;
use Stolpersteine\Repository\AuditRepository;
use Stolpersteine\Service\SuchindexService;

class VerlegeorteHandler extends BaseHandler
{
    private VerlegeortRepository $repo;
    private StolpersteinRepository $steinRepo;
    private SuchindexService $suchindex;

    public function __construct()
    {
        $this->repo      = new VerlegeortRepository();
        $this->steinRepo = new StolpersteinRepository();
        $this->suchindex = new SuchindexService();
    }

    // GET /verlegeorte?stadtteil=&strasse=&plz=&status=
    public function index(array $params): void
    {
        Auth::required();

        $filter = array_filter([
            'stadtteil' => $this->queryParam('stadtteil'),
            'strasse'   => $this->queryParam('strasse'),
            'plz'       => $this->queryParam('plz'),
            'status'    => $this->queryParam('status'),
        ]);

        $orte = $this->repo->findAll($filter);
        Logger::get()->debug('Verlegeorte-Liste abgerufen', ['filter' => $filter, 'anzahl' => count($orte)]);
        Response::success($orte);
    }

    // GET /verlegeorte/{id}
    public function show(array $params): void
    {
        Auth::required();

        $id  = $this->intParam($params, 'id');
        $ort = $this->repo->findById($id);

        if ($ort === null) {
            Response::error('Verlegeort nicht gefunden.', 404);
        }

        Response::success($ort);
    }

    // POST /verlegeorte
    public function create(array $params): void
    {
        $user = Auth::required();
        $body = $this->jsonBody();

        $id  = $this->repo->create($body, $user['benutzername']);
        $ort = $this->repo->findById($id);

        AuditRepository::log($user['benutzername'], 'INSERT', 'verlegeorte', $id, null, $ort);
        Logger::get()->info('Verlegeort erstellt', ['id' => $id, 'adresse' => ($ort['strasse_aktuell'] ?? '') . ' ' . ($ort['hausnummer_aktuell'] ?? ''), 'von' => $user['benutzername']]);

        Response::created($ort);
    }

    // PUT /verlegeorte/{id}
    public function update(array $params): void
    {
        $user = Auth::required();
        $id   = $this->intParam($params, 'id');
        $body = $this->jsonBody();

        $alt = $this->repo->findById($id);
        if ($alt === null) {
            Response::error('Verlegeort nicht gefunden.', 404);
        }

        $this->repo->update($id, $body, $user['benutzername']);
        $neu = $this->repo->findById($id);

        // Wenn Verlegeort auf "validierung" gesetzt wird → alle zugehörigen Stolpersteine ebenfalls
        if (($neu['status'] ?? '') === 'validierung') {
            $this->steinRepo->setStatusForVerlegeort($id, 'validierung');
        }

        AuditRepository::log($user['benutzername'], 'UPDATE', 'verlegeorte', $id, $alt, $neu);
        $this->suchindex->updateForLayingLocation($id);
        Logger::get()->info('Verlegeort aktualisiert', ['id' => $id, 'adresse' => ($neu['strasse_aktuell'] ?? '') . ' ' . ($neu['hausnummer_aktuell'] ?? ''), 'von' => $user['benutzername']]);

        Response::success($neu);
    }

    // DELETE /verlegeorte/{id}
    public function delete(array $params): void
    {
        $user = Auth::requireAdmin();
        $id   = $this->intParam($params, 'id');

        $alt = $this->repo->findById($id);
        if ($alt === null) {
            Response::error('Verlegeort nicht gefunden.', 404);
        }

        try {
            $this->repo->delete($id);
        } catch (\RuntimeException $e) {
            Logger::get()->warning('Verlegeort konnte nicht gelöscht werden', ['id' => $id, 'grund' => $e->getMessage(), 'von' => $user['benutzername']]);
            Response::error($e->getMessage(), 409);
        }

        AuditRepository::log($user['benutzername'], 'DELETE', 'verlegeorte', $id, $alt, null);
        Logger::get()->warning('Verlegeort gelöscht', ['id' => $id, 'adresse' => ($alt['strasse_aktuell'] ?? '') . ' ' . ($alt['hausnummer_aktuell'] ?? ''), 'von' => $user['benutzername']]);

        Response::noContent();
    }
}
