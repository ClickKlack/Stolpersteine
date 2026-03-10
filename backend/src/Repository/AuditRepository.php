<?php

declare(strict_types=1);

namespace Stolpersteine\Repository;

use Stolpersteine\Config\Database;

class AuditRepository
{
    public static function log(
        string  $benutzer,
        string  $aktion,
        string  $tabelle,
        int     $datensatzId,
        ?array  $altwert = null,
        ?array  $neuwert = null
    ): void {
        $pdo = Database::connection();
        $pdo->prepare(
            'INSERT INTO audit_log (benutzer, aktion, tabelle, datensatz_id, altwert, neuwert, zeitpunkt)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        )->execute([
            $benutzer,
            $aktion,
            $tabelle,
            $datensatzId,
            $altwert !== null ? json_encode($altwert, JSON_UNESCAPED_UNICODE) : null,
            $neuwert !== null ? json_encode($neuwert, JSON_UNESCAPED_UNICODE) : null,
        ]);
    }
}
