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

    /**
     * GET /api/export/osm/diff
     * GET /api/export/osm/diff?stadtteil_id=N
     * Stadtweiter Overpass-Abruf + feldweiser Vergleich mit lokalen Daten.
     */
    public function osmDiff(array $params): void
    {
        Auth::requireAdmin();

        $stadtteilIdRaw = $this->queryParam('stadtteil_id');
        $stadtteilId    = null;
        if ($stadtteilIdRaw !== null) {
            if (!ctype_digit((string) $stadtteilIdRaw)) {
                Response::error('stadtteil_id muss eine Zahl sein.', 422);
            }
            $stadtteilId = (int) $stadtteilIdRaw;
        }

        try {
            $result = $this->service->osmDiff($stadtteilId);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 404);
        } catch (\RuntimeException $e) {
            // Unterscheide Overpass-Fehler von Template-Fehler
            $msg = $e->getMessage();
            if (str_contains($msg, 'Overpass') || str_contains($msg, 'cURL')) {
                Response::error($msg, 502);
            }
            Response::error($msg, 409);
        }

        Response::success($result);
    }

    /**
     * GET /api/export/osm/datei
     * GET /api/export/osm/datei?stadtteil_id=N
     * Download einer JOSM-kompatiblen .osm XML-Datei.
     */
    public function osmDatei(array $params): void
    {
        Auth::requireAdmin();

        $stadtteilIdRaw = $this->queryParam('stadtteil_id');
        $stadtteilId    = null;
        $dateiSuffix    = 'gesamt';

        if ($stadtteilIdRaw !== null) {
            if (!ctype_digit((string) $stadtteilIdRaw)) {
                Response::error('stadtteil_id muss eine Zahl sein.', 422);
            }
            $stadtteilId = (int) $stadtteilIdRaw;
            $dateiSuffix = 'stadtteil-' . $stadtteilId;
        }

        try {
            $xml = $this->service->osmDatei($stadtteilId);
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), 409);
        }

        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: attachment; filename="stolpersteine-' . $dateiSuffix . '.osm"');
        echo $xml;
        exit;
    }
}
