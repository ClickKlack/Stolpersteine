<?php

declare(strict_types=1);

namespace Stolpersteine\Api;

use Stolpersteine\Auth\Auth;
use Stolpersteine\Config\Database;
use Stolpersteine\Config\Logger;

class DashboardHandler extends BaseHandler
{
    public function statistiken(): void
    {
        $user = Auth::required();

        Logger::get()->debug('Dashboard-Statistiken abgerufen', ['von' => $user['benutzername']]);

        $pdo = Database::connection();

        // --- Personen nach Status ---
        $rows = $pdo->query('SELECT status, COUNT(*) AS n FROM personen GROUP BY status')->fetchAll();
        $personenStatus = [];
        foreach ($rows as $r) {
            $personenStatus[$r['status']] = (int) $r['n'];
        }
        $personenGesamt = array_sum($personenStatus);

        // --- Verlegeorte nach Status ---
        $rows = $pdo->query('SELECT status, COUNT(*) AS n FROM verlegeorte GROUP BY status')->fetchAll();
        $verlegeorteStatus = [];
        foreach ($rows as $r) {
            $verlegeorteStatus[$r['status']] = (int) $r['n'];
        }
        $verlegeorteGesamt = array_sum($verlegeorteStatus);

        // --- Verlegeorte nach Stadtteil + Status ---
        $rows = $pdo->query(
            'SELECT st.name AS stadtteil, v.status, COUNT(*) AS n
             FROM verlegeorte v
             JOIN adress_lokationen al ON al.id  = v.adress_lokation_id
             JOIN stadtteile st        ON st.id  = al.stadtteil_id
             GROUP BY st.name, v.status
             ORDER BY st.name'
        )->fetchAll();

        $verlegeorteStadtteile = [];
        foreach ($rows as $r) {
            $name = $r['stadtteil'];
            if (!isset($verlegeorteStadtteile[$name])) {
                $verlegeorteStadtteile[$name] = ['name' => $name, 'gesamt' => 0, 'status' => []];
            }
            $verlegeorteStadtteile[$name]['status'][$r['status']] = (int) $r['n'];
            $verlegeorteStadtteile[$name]['gesamt'] += (int) $r['n'];
        }

        // --- Stolpersteine nach Status ---
        $rows = $pdo->query('SELECT status, COUNT(*) AS n FROM stolpersteine GROUP BY status')->fetchAll();
        $steineStatus = [];
        foreach ($rows as $r) {
            $steineStatus[$r['status']] = (int) $r['n'];
        }
        $steineGesamt = array_sum($steineStatus);

        // --- Stolpersteine nach Zustand ---
        $rows = $pdo->query('SELECT zustand, COUNT(*) AS n FROM stolpersteine GROUP BY zustand')->fetchAll();
        $steineZustand = [];
        foreach ($rows as $r) {
            $steineZustand[$r['zustand']] = (int) $r['n'];
        }

        // --- Stolpersteine nach Stadtteil + Status + Zustand ---
        $rows = $pdo->query(
            'SELECT st.name AS stadtteil, s.status, s.zustand, COUNT(*) AS n
             FROM stolpersteine s
             JOIN verlegeorte v         ON v.id  = s.verlegeort_id
             JOIN adress_lokationen al  ON al.id = v.adress_lokation_id
             JOIN stadtteile st         ON st.id = al.stadtteil_id
             GROUP BY st.name, s.status, s.zustand
             ORDER BY st.name'
        )->fetchAll();

        $steineStadtteile = [];
        foreach ($rows as $r) {
            $name = $r['stadtteil'];
            if (!isset($steineStadtteile[$name])) {
                $steineStadtteile[$name] = ['name' => $name, 'gesamt' => 0, 'status' => [], 'zustand' => []];
            }
            $steineStadtteile[$name]['gesamt'] += (int) $r['n'];
            $steineStadtteile[$name]['status'][$r['status']]  = ($steineStadtteile[$name]['status'][$r['status']]  ?? 0) + (int) $r['n'];
            $steineStadtteile[$name]['zustand'][$r['zustand']] = ($steineStadtteile[$name]['zustand'][$r['zustand']] ?? 0) + (int) $r['n'];
        }

        // --- Dokumente nach URL-Status ---
        $row = $pdo->query(
            "SELECT
                SUM(CASE WHEN quelle_url IS NULL                                      THEN 1 ELSE 0 END) AS ohne_url,
                SUM(CASE WHEN quelle_url IS NOT NULL AND url_status IS NULL           THEN 1 ELSE 0 END) AS ungeprueft,
                SUM(CASE WHEN url_status = 200                                        THEN 1 ELSE 0 END) AS ok,
                SUM(CASE WHEN url_status >= 300 AND url_status < 400                 THEN 1 ELSE 0 END) AS umleitung,
                SUM(CASE WHEN url_status = 0 OR url_status >= 400                    THEN 1 ELSE 0 END) AS fehler
             FROM dokumente"
        )->fetch();

        $dokUrlStatus = [
            'ohne_url'  => (int) ($row['ohne_url']  ?? 0),
            'ungeprueft'=> (int) ($row['ungeprueft'] ?? 0),
            'ok'        => (int) ($row['ok']         ?? 0),
            'umleitung' => (int) ($row['umleitung']  ?? 0),
            'fehler'    => (int) ($row['fehler']     ?? 0),
        ];
        $dokGesamt = array_sum($dokUrlStatus);

        Response::success([
            'personen' => [
                'gesamt' => $personenGesamt,
                'status' => $personenStatus,
            ],
            'verlegeorte' => [
                'gesamt'     => $verlegeorteGesamt,
                'status'     => $verlegeorteStatus,
                'stadtteile' => array_values($verlegeorteStadtteile),
            ],
            'stolpersteine' => [
                'gesamt'     => $steineGesamt,
                'status'     => $steineStatus,
                'zustand'    => $steineZustand,
                'stadtteile' => array_values($steineStadtteile),
            ],
            'dokumente' => [
                'gesamt'     => $dokGesamt,
                'url_status' => $dokUrlStatus,
            ],
        ]);
    }
}
