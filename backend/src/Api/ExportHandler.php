<?php

declare(strict_types=1);

namespace Stolpersteine\Api;

use Stolpersteine\Auth\Auth;
use Stolpersteine\Service\ExportService;

class ExportHandler extends BaseHandler
{
    private ExportService $service;

    public function __construct()
    {
        $this->service = new ExportService();
    }

    /**
     * GET /api/export/wikipedia?stadtteil_id=
     * GET /api/export/wikipedia?stadtteil_id=&raw=1  → Wikitext als text/plain
     *
     * Weitere Formate (osm, csv, …) noch nicht implementiert.
     */
    public function export(array $params): void
    {
        Auth::requireAdmin();

        $format = $params['format'] ?? '';

        if ($format !== 'wikipedia') {
            Response::error("Export-Format '$format' wird noch nicht unterstützt.", 501);
        }

        $stadtteilId = $this->queryParam('stadtteil_id');
        if ($stadtteilId === null || !ctype_digit((string) $stadtteilId)) {
            Response::error('stadtteil_id ist erforderlich.', 422);
        }

        try {
            $result = $this->service->wikipedia((int) $stadtteilId);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), 409);
        }

        // Mit ?raw=1 wird reiner Wikitext als text/plain ausgegeben
        if ($this->queryParam('raw') === '1') {
            header('Content-Type: text/plain; charset=utf-8');
            echo $result['wikitext'];
            exit;
        }

        Response::success($result);
    }

    /**
     * GET /api/export/wikipedia/diff?stadtteil_id=
     * Gibt lokal generierten und live Wikipedia-Wikitext zurück.
     */
    public function diff(array $params): void
    {
        Auth::requireAdmin();

        $stadtteilId = $this->queryParam('stadtteil_id');
        if ($stadtteilId === null || !ctype_digit((string) $stadtteilId)) {
            Response::error('stadtteil_id ist erforderlich.', 422);
        }

        try {
            $result = $this->service->wikipediaDiff((int) $stadtteilId);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), 409);
        }

        Response::success($result);
    }
}
