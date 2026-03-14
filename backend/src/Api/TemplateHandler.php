<?php

declare(strict_types=1);

namespace Stolpersteine\Api;

use Stolpersteine\Auth\Auth;
use Stolpersteine\Repository\TemplateRepository;

class TemplateHandler extends BaseHandler
{
    private TemplateRepository $repo;

    public function __construct()
    {
        $this->repo = new TemplateRepository();
    }

    /**
     * GET /templates?zielsystem=wikipedia
     */
    public function index(array $params): void
    {
        Auth::requireAdmin();
        $zielsystem = $this->queryParam('zielsystem', '');
        if ($zielsystem === '') {
            Response::error('zielsystem ist erforderlich.', 422);
        }
        Response::success($this->repo->findAlleAktiv($zielsystem));
    }

    /**
     * GET /templates/{id}
     */
    public function show(array $params): void
    {
        Auth::requireAdmin();
        $row = $this->repo->findById((int) $params['id']);
        if (!$row) {
            Response::error('Template nicht gefunden.', 404);
        }
        Response::success($row);
    }

    /**
     * PUT /templates/{id}
     */
    public function update(array $params): void
    {
        Auth::requireAdmin();
        $body = $this->jsonBody();
        if (!isset($body['inhalt'])) {
            Response::error('inhalt ist erforderlich.', 422);
        }
        $row = $this->repo->findById((int) $params['id']);
        if (!$row) {
            Response::error('Template nicht gefunden.', 404);
        }
        $user  = Auth::user();
        $neueId = $this->repo->update((int) $params['id'], $body['inhalt'], $user['benutzername'] ?? 'system');
        Response::success($this->repo->findById($neueId));
    }
}
