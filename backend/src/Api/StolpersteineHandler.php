<?php

declare(strict_types=1);

namespace Stolpersteine\Api;

use Stolpersteine\Auth\Auth;
use Stolpersteine\Repository\StolpersteinRepository;
use Stolpersteine\Repository\AuditRepository;
use Stolpersteine\Service\SuchindexService;

class StolpersteineHandler extends BaseHandler
{
    private StolpersteinRepository $repo;
    private SuchindexService $suchindex;

    public function __construct()
    {
        $this->repo      = new StolpersteinRepository();
        $this->suchindex = new SuchindexService();
    }

    // GET /stolpersteine?status=&zustand=&stadtteil=&strasse=&person_id=&ohne_wikidata=1
    public function index(array $params): void
    {
        Auth::required();

        $filter = array_filter([
            'status'         => $this->queryParam('status'),
            'zustand'        => $this->queryParam('zustand'),
            'stadtteil'      => $this->queryParam('stadtteil'),
            'strasse'        => $this->queryParam('strasse'),
            'person_id'      => $this->queryParam('person_id'),
            'ohne_wikidata'  => $this->queryParam('ohne_wikidata'),
        ]);

        Response::success($this->repo->findAll($filter));
    }

    // GET /stolpersteine/{id}
    public function show(array $params): void
    {
        Auth::required();

        $id    = $this->intParam($params, 'id');
        $stein = $this->repo->findById($id);

        if ($stein === null) {
            Response::error('Stolperstein nicht gefunden.', 404);
        }

        Response::success($stein);
    }

    // POST /stolpersteine
    public function create(array $params): void
    {
        $user = Auth::required();
        $body = $this->jsonBody();

        $this->validateRequiredFields($body);

        $id    = $this->repo->create($body, $user['benutzername']);
        $stein = $this->repo->findById($id);

        AuditRepository::log($user['benutzername'], 'INSERT', 'stolpersteine', $id, null, $stein);
        $this->suchindex->update($id);

        Response::created($stein);
    }

    // PUT /stolpersteine/{id}
    public function update(array $params): void
    {
        $user = Auth::required();
        $id   = $this->intParam($params, 'id');
        $body = $this->jsonBody();

        $alt = $this->repo->findById($id);
        if ($alt === null) {
            Response::error('Stolperstein nicht gefunden.', 404);
        }

        $this->validateRequiredFields($body);

        $this->repo->update($id, $body, $user['benutzername']);
        $neu = $this->repo->findById($id);

        AuditRepository::log($user['benutzername'], 'UPDATE', 'stolpersteine', $id, $alt, $neu);
        $this->suchindex->update($id);

        Response::success($neu);
    }

    // DELETE /stolpersteine/{id}
    public function delete(array $params): void
    {
        $user = Auth::requireAdmin();
        $id   = $this->intParam($params, 'id');

        $alt = $this->repo->findById($id);
        if ($alt === null) {
            Response::error('Stolperstein nicht gefunden.', 404);
        }

        $this->repo->delete($id);

        AuditRepository::log($user['benutzername'], 'DELETE', 'stolpersteine', $id, $alt, null);

        Response::noContent();
    }

    private function validateRequiredFields(array $body): void
    {
        if (empty($body['person_id'])) {
            Response::error('person_id ist erforderlich.', 422);
        }
        if (empty($body['verlegeort_id'])) {
            Response::error('verlegeort_id ist erforderlich.', 422);
        }
    }
}
