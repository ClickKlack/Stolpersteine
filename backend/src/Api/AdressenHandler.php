<?php

declare(strict_types=1);

namespace Stolpersteine\Api;

use Stolpersteine\Auth\Auth;
use Stolpersteine\Repository\AdresseRepository;

class AdressenHandler extends BaseHandler
{
    private AdresseRepository $repo;

    public function __construct()
    {
        $this->repo = new AdresseRepository();
    }

    // GET /adressen/strassen?q=
    public function strassen(array $params): void
    {
        Auth::required();

        $q = trim($this->queryParam('q') ?? '');
        if (mb_strlen($q) < 2) {
            Response::success([]);
            return;
        }

        Response::success($this->repo->searchStrassen($q));
    }

    // GET /adressen/stadtteile?q=
    public function stadtteile(array $params): void
    {
        Auth::required();

        $q = trim($this->queryParam('q') ?? '');
        if (mb_strlen($q) < 2) {
            Response::success([]);
            return;
        }

        Response::success($this->repo->searchStadtteile($q));
    }

    // POST /adressen/lokationen
    public function createLokation(array $params): void
    {
        Auth::required();
        $body = $this->jsonBody();

        if (empty($body['strasse_name'])) {
            Response::error('strasse_name ist erforderlich.', 422);
        }
        if (empty($body['stadt_name'])) {
            Response::error('stadt_name ist erforderlich.', 422);
        }

        $lokation = $this->repo->resolveOrCreate($body);
        Response::success($lokation);
    }

    // -------------------------------------------------------------------------
    // Städte
    // -------------------------------------------------------------------------

    // GET /adressen/staedte
    public function staedte(array $params): void
    {
        Auth::required();
        Response::success($this->repo->findAllStaedte());
    }

    // POST /adressen/staedte
    public function createStadt(array $params): void
    {
        Auth::requireAdmin();
        $body = $this->jsonBody();

        if (empty($body['name'])) {
            Response::error('name ist erforderlich.', 422);
        }

        $id = $this->repo->findOrCreateStadt(trim($body['name']), $body['wikidata_id'] ?? null);
        Response::success($this->repo->findStadtById($id));
    }

    // GET /adressen/staedte/{id}
    public function showStadt(array $params): void
    {
        Auth::required();
        $row = $this->repo->findStadtById((int) $params['id']);
        if (!$row) {
            Response::error('Stadt nicht gefunden.', 404);
        }
        Response::success($row);
    }

    // PUT /adressen/staedte/{id}
    public function updateStadt(array $params): void
    {
        Auth::requireAdmin();
        $body = $this->jsonBody();

        if (empty($body['name'])) {
            Response::error('name ist erforderlich.', 422);
        }

        $this->repo->updateStadt(
            (int) $params['id'],
            trim($body['name']),
            $body['wikidata_id'] ?? null
        );
        Response::success($this->repo->findStadtById((int) $params['id']));
    }

    // DELETE /adressen/staedte/{id}
    public function deleteStadt(array $params): void
    {
        Auth::requireAdmin();
        try {
            $this->repo->deleteStadt((int) $params['id']);
            Response::success(['deleted' => true]);
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), 409);
        }
    }

    // -------------------------------------------------------------------------
    // Stadtteile
    // -------------------------------------------------------------------------

    // POST /adressen/alle-stadtteile
    public function createStadtteil(array $params): void
    {
        Auth::requireAdmin();
        $body = $this->jsonBody();

        if (empty($body['name'])) {
            Response::error('name ist erforderlich.', 422);
        }
        if (empty($body['stadt_id'])) {
            Response::error('stadt_id ist erforderlich.', 422);
        }

        $id = $this->repo->findOrCreateStadtteil(
            trim($body['name']),
            (int) $body['stadt_id'],
            $body['wikidata_id'] ?? null
        );
        Response::success($this->repo->findStadtteilById($id));
    }

    // GET /adressen/alle-stadtteile?stadt_id=
    public function alleStadtteile(array $params): void
    {
        Auth::required();
        $filter = [];
        if ($this->queryParam('stadt_id')) {
            $filter['stadt_id'] = (int) $this->queryParam('stadt_id');
        }
        Response::success($this->repo->findAllStadtteile($filter));
    }

    // GET /adressen/alle-stadtteile/{id}
    public function showStadtteil(array $params): void
    {
        Auth::required();
        $row = $this->repo->findStadtteilById((int) $params['id']);
        if (!$row) {
            Response::error('Stadtteil nicht gefunden.', 404);
        }
        Response::success($row);
    }

    // PUT /adressen/alle-stadtteile/{id}
    public function updateStadtteil(array $params): void
    {
        Auth::requireAdmin();
        $body = $this->jsonBody();

        if (empty($body['name'])) {
            Response::error('name ist erforderlich.', 422);
        }
        if (empty($body['stadt_id'])) {
            Response::error('stadt_id ist erforderlich.', 422);
        }

        $this->repo->updateStadtteil(
            (int) $params['id'],
            trim($body['name']),
            (int) $body['stadt_id'],
            $body['wikidata_id'] ?? null
        );
        Response::success($this->repo->findStadtteilById((int) $params['id']));
    }

    // DELETE /adressen/alle-stadtteile/{id}
    public function deleteStadtteil(array $params): void
    {
        Auth::requireAdmin();
        try {
            $this->repo->deleteStadtteil((int) $params['id']);
            Response::success(['deleted' => true]);
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), 409);
        }
    }

    // -------------------------------------------------------------------------
    // Straßen
    // -------------------------------------------------------------------------

    // POST /adressen/alle-strassen
    public function createStrasse(array $params): void
    {
        Auth::requireAdmin();
        $body = $this->jsonBody();

        if (empty($body['name'])) {
            Response::error('name ist erforderlich.', 422);
        }
        if (empty($body['stadt_id'])) {
            Response::error('stadt_id ist erforderlich.', 422);
        }

        $id = $this->repo->findOrCreateStrasse(
            trim($body['name']),
            (int) $body['stadt_id'],
            $body['wikidata_id'] ?? null,
            !empty($body['wikipedia_name']) ? trim($body['wikipedia_name']) : null
        );
        Response::success($this->repo->findStrasseById($id));
    }

    // GET /adressen/alle-strassen?stadt_id=&q=
    public function alleStrassen(array $params): void
    {
        Auth::required();
        $filter = [];
        if ($this->queryParam('stadt_id')) {
            $filter['stadt_id'] = (int) $this->queryParam('stadt_id');
        }
        if ($this->queryParam('q')) {
            $filter['q'] = $this->queryParam('q');
        }
        Response::success($this->repo->findAllStrassen($filter));
    }

    // GET /adressen/alle-strassen/{id}
    public function showStrasse(array $params): void
    {
        Auth::required();
        $row = $this->repo->findStrasseById((int) $params['id']);
        if (!$row) {
            Response::error('Straße nicht gefunden.', 404);
        }
        Response::success($row);
    }

    // PUT /adressen/alle-strassen/{id}
    public function updateStrasse(array $params): void
    {
        Auth::requireAdmin();
        $body = $this->jsonBody();

        if (empty($body['name'])) {
            Response::error('name ist erforderlich.', 422);
        }
        if (empty($body['stadt_id'])) {
            Response::error('stadt_id ist erforderlich.', 422);
        }

        $this->repo->updateStrasse(
            (int) $params['id'],
            trim($body['name']),
            (int) $body['stadt_id'],
            $body['wikidata_id'] ?? null,
            !empty($body['wikipedia_name']) ? trim($body['wikipedia_name']) : null
        );
        Response::success($this->repo->findStrasseById((int) $params['id']));
    }

    // DELETE /adressen/alle-strassen/{id}
    public function deleteStrasse(array $params): void
    {
        Auth::requireAdmin();
        try {
            $this->repo->deleteStrasse((int) $params['id']);
            Response::success(['deleted' => true]);
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), 409);
        }
    }

    // -------------------------------------------------------------------------
    // PLZ
    // -------------------------------------------------------------------------

    // POST /adressen/alle-plz
    public function createPlz(array $params): void
    {
        Auth::requireAdmin();
        $body = $this->jsonBody();

        if (empty($body['plz'])) {
            Response::error('plz ist erforderlich.', 422);
        }
        if (empty($body['stadt_id'])) {
            Response::error('stadt_id ist erforderlich.', 422);
        }

        $id = $this->repo->findOrCreatePlz(trim($body['plz']), (int) $body['stadt_id']);
        Response::success($this->repo->findPlzById($id));
    }

    // GET /adressen/alle-plz?stadt_id=
    public function allePlz(array $params): void
    {
        Auth::required();
        $filter = [];
        if ($this->queryParam('stadt_id')) {
            $filter['stadt_id'] = (int) $this->queryParam('stadt_id');
        }
        Response::success($this->repo->findAllPlzEintraege($filter));
    }

    // GET /adressen/alle-plz/{id}
    public function showPlz(array $params): void
    {
        Auth::required();
        $row = $this->repo->findPlzById((int) $params['id']);
        if (!$row) {
            Response::error('PLZ nicht gefunden.', 404);
        }
        Response::success($row);
    }

    // PUT /adressen/alle-plz/{id}
    public function updatePlz(array $params): void
    {
        Auth::requireAdmin();
        $body = $this->jsonBody();

        if (empty($body['plz'])) {
            Response::error('plz ist erforderlich.', 422);
        }
        if (empty($body['stadt_id'])) {
            Response::error('stadt_id ist erforderlich.', 422);
        }

        $this->repo->updatePlz(
            (int) $params['id'],
            trim($body['plz']),
            (int) $body['stadt_id']
        );
        Response::success($this->repo->findPlzById((int) $params['id']));
    }

    // DELETE /adressen/alle-plz/{id}
    public function deletePlz(array $params): void
    {
        Auth::requireAdmin();
        try {
            $this->repo->deletePlz((int) $params['id']);
            Response::success(['deleted' => true]);
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), 409);
        }
    }

    // -------------------------------------------------------------------------
    // Lokationen
    // -------------------------------------------------------------------------

    // POST /adressen/alle-lokationen
    public function createLokationDirect(array $params): void
    {
        Auth::requireAdmin();
        $body = $this->jsonBody();

        if (empty($body['strasse_id'])) {
            Response::error('strasse_id ist erforderlich.', 422);
        }

        $id = $this->repo->findOrCreateLokation(
            (int) $body['strasse_id'],
            !empty($body['stadtteil_id']) ? (int) $body['stadtteil_id'] : null,
            !empty($body['plz_id'])       ? (int) $body['plz_id']       : null
        );
        Response::success($this->repo->getLokation($id));
    }

    // PUT /adressen/alle-lokationen/{id}
    public function updateLokation(array $params): void
    {
        Auth::requireAdmin();
        $body = $this->jsonBody();

        if (empty($body['strasse_id'])) {
            Response::error('strasse_id ist erforderlich.', 422);
        }

        $this->repo->updateLokation(
            (int) $params['id'],
            (int) $body['strasse_id'],
            !empty($body['stadtteil_id']) ? (int) $body['stadtteil_id'] : null,
            !empty($body['plz_id'])       ? (int) $body['plz_id']       : null
        );
        Response::success($this->repo->getLokation((int) $params['id']));
    }

    // GET /adressen/alle-lokationen?stadtteil_id=&strasse_id=&plz_id=
    public function alleLokationen(array $params): void
    {
        Auth::required();
        $filter = [];
        foreach (['stadtteil_id', 'strasse_id', 'plz_id'] as $key) {
            if ($this->queryParam($key)) {
                $filter[$key] = (int) $this->queryParam($key);
            }
        }
        Response::success($this->repo->findAllLokationen($filter));
    }

    // DELETE /adressen/alle-lokationen/{id}
    public function deleteLokation(array $params): void
    {
        Auth::requireAdmin();
        try {
            $this->repo->deleteLokation((int) $params['id']);
            Response::success(['deleted' => true]);
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), 409);
        }
    }
}
