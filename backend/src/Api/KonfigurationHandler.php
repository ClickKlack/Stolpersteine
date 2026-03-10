<?php

declare(strict_types=1);

namespace Stolpersteine\Api;

use Stolpersteine\Auth\Auth;
use Stolpersteine\Config\Database;

class KonfigurationHandler extends BaseHandler
{
    // GET /konfiguration  – gibt alle Schlüssel/Wert-Paare zurück
    public function index(array $params): void
    {
        Auth::required();

        $pdo  = Database::connection();
        $stmt = $pdo->query('SELECT schluessel, wert FROM konfiguration ORDER BY schluessel');
        $rows = $stmt->fetchAll();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['schluessel']] = $row['wert'];
        }

        Response::success($result);
    }
}
