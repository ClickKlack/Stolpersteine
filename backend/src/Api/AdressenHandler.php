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
}
