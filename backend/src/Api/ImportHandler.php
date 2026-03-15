<?php

declare(strict_types=1);

namespace Stolpersteine\Api;

use Stolpersteine\Auth\Auth;
use Stolpersteine\Service\ImportService;

class ImportHandler extends BaseHandler
{
    private ImportService $service;

    public function __construct()
    {
        $this->service = new ImportService();
    }

    // POST /import/analyze — gibt Spaltenvorschau zurück, damit das Frontend das Mapping anbieten kann
    public function analyze(array $params): void
    {
        Auth::required();

        if (empty($_FILES['datei'])) {
            Response::error('Keine Datei übermittelt.', 422);
        }

        try {
            $result = $this->service->analyze($_FILES['datei']);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        }

        Response::success($result);
    }

    // POST /import/preview — Dry-Run mit Spalten-Mapping, keine DB-Schreibzugriffe
    public function preview(array $params): void
    {
        Auth::required();

        if (empty($_FILES['datei'])) {
            Response::error('Keine Datei übermittelt.', 422);
        }

        $mapping                = $this->parseMapping();
        $startRow               = max(1, (int) ($_POST['startzeile'] ?? 2));
        $dokIstBiografieGlobal  = $this->parseDokBiografieGlobal();

        try {
            $result = $this->service->preview($_FILES['datei'], $mapping, $startRow, $dokIstBiografieGlobal);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        }

        Response::success($result);
    }

    // POST /import/execute — tatsächlicher Import in einer DB-Transaktion
    public function execute(array $params): void
    {
        $user = Auth::required();

        if (empty($_FILES['datei'])) {
            Response::error('Keine Datei übermittelt.', 422);
        }

        $mapping                = $this->parseMapping();
        $startRow               = max(1, (int) ($_POST['startzeile'] ?? 2));
        $dokIstBiografieGlobal  = $this->parseDokBiografieGlobal();

        try {
            $result = $this->service->execute($_FILES['datei'], $mapping, $startRow, $user['benutzername'], $dokIstBiografieGlobal);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), 500);
        }

        Response::success($result);
    }

    private function parseDokBiografieGlobal(): string
    {
        $val = $_POST['dok_ist_biografie_global'] ?? 'spalte';
        return in_array($val, ['spalte', 'ja', 'nein'], true) ? $val : 'spalte';
    }

    private function parseMapping(): array
    {
        $raw = $_POST['mapping'] ?? '';
        if (empty($raw)) {
            Response::error('Kein Spalten-Mapping übermittelt.', 422);
        }

        $mapping = json_decode($raw, true);
        if (!is_array($mapping) || $mapping === []) {
            Response::error('Spalten-Mapping ist kein gültiges JSON-Objekt.', 422);
        }

        return $mapping;
    }
}
