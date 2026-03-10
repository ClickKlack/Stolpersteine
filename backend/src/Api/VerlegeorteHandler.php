<?php

declare(strict_types=1);

namespace Stolpersteine\Api;

use Stolpersteine\Auth\Auth;
use Stolpersteine\Repository\VerlegeortRepository;
use Stolpersteine\Repository\AuditRepository;
use Stolpersteine\Service\SuchindexService;

class VerlegeorteHandler extends BaseHandler
{
    private VerlegeortRepository $repo;
    private SuchindexService $suchindex;

    public function __construct()
    {
        $this->repo      = new VerlegeortRepository();
        $this->suchindex = new SuchindexService();
    }

    // GET /verlegeorte?stadtteil=&strasse=&plz=
    public function index(array $params): void
    {
        Auth::required();

        $filter = array_filter([
            'stadtteil' => $this->queryParam('stadtteil'),
            'strasse'   => $this->queryParam('strasse'),
            'plz'       => $this->queryParam('plz'),
        ]);

        Response::success($this->repo->findAll($filter));
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

        AuditRepository::log($user['benutzername'], 'UPDATE', 'verlegeorte', $id, $alt, $neu);
        $this->suchindex->updateForLayingLocation($id);

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
            Response::error($e->getMessage(), 409);
        }

        AuditRepository::log($user['benutzername'], 'DELETE', 'verlegeorte', $id, $alt, null);

        Response::noContent();
    }
}
