<?php

declare(strict_types=1);

namespace Stolpersteine\Api;

use Stolpersteine\Auth\Auth;
use Stolpersteine\Repository\PersonRepository;
use Stolpersteine\Repository\AuditRepository;
use Stolpersteine\Service\SuchindexService;

class PersonenHandler extends BaseHandler
{
    private PersonRepository $repo;
    private SuchindexService $suchindex;

    public function __construct()
    {
        $this->repo      = new PersonRepository();
        $this->suchindex = new SuchindexService();
    }

    // GET /personen?nachname=&geburtsname=&geburtsjahr=
    public function index(array $params): void
    {
        Auth::required();

        $filter = array_filter([
            'nachname'    => $this->queryParam('nachname'),
            'geburtsname' => $this->queryParam('geburtsname'),
            'geburtsjahr' => $this->queryParam('geburtsjahr'),
        ]);

        $personen = $this->repo->findAll($filter);
        Response::success($personen);
    }

    // GET /personen/{id}
    public function show(array $params): void
    {
        Auth::required();

        $id     = $this->intParam($params, 'id');
        $person = $this->repo->findById($id);

        if ($person === null) {
            Response::error('Person nicht gefunden.', 404);
        }

        Response::success($person);
    }

    // POST /personen
    public function create(array $params): void
    {
        $user = Auth::required();
        $body = $this->jsonBody();

        $this->validateRequiredFields($body);

        $id     = $this->repo->create($body, $user['benutzername']);
        $person = $this->repo->findById($id);

        AuditRepository::log($user['benutzername'], 'INSERT', 'personen', $id, null, $person);

        Response::created($person);
    }

    // PUT /personen/{id}
    public function update(array $params): void
    {
        $user = Auth::required();
        $id   = $this->intParam($params, 'id');
        $body = $this->jsonBody();

        $alt = $this->repo->findById($id);
        if ($alt === null) {
            Response::error('Person nicht gefunden.', 404);
        }

        $this->validateRequiredFields($body);

        $this->repo->update($id, $body, $user['benutzername']);
        $neu = $this->repo->findById($id);

        AuditRepository::log($user['benutzername'], 'UPDATE', 'personen', $id, $alt, $neu);
        $this->suchindex->updateForPerson($id);

        Response::success($neu);
    }

    // DELETE /personen/{id}
    public function delete(array $params): void
    {
        $user = Auth::requireAdmin();
        $id   = $this->intParam($params, 'id');

        $alt = $this->repo->findById($id);
        if ($alt === null) {
            Response::error('Person nicht gefunden.', 404);
        }

        try {
            $this->repo->delete($id);
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), 409);
        }

        AuditRepository::log($user['benutzername'], 'DELETE', 'personen', $id, $alt, null);

        Response::noContent();
    }

    private function validateRequiredFields(array $body): void
    {
        if (empty($body['nachname'])) {
            Response::error('Nachname ist erforderlich.', 422);
        }
    }
}
