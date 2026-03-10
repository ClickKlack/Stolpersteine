<?php

declare(strict_types=1);

namespace Stolpersteine\Api;

use Stolpersteine\Auth\Auth;
use Stolpersteine\Config\Database;

class SucheHandler extends BaseHandler
{
    // GET /suche?q=&status=&zustand=&stadtteil=&strasse=&ohne_wikidata=1
    public function search(array $params): void
    {
        Auth::required();

        $q           = trim($this->queryParam('q', ''));
        $status      = $this->queryParam('status');
        $zustand     = $this->queryParam('zustand');
        $stadtteil   = $this->queryParam('stadtteil');
        $strasse     = $this->queryParam('strasse');
        $ohneWikidata= $this->queryParam('ohne_wikidata');

        $hatVolltext = $q !== '';
        $hatFilter   = $status || $zustand || $stadtteil || $strasse || $ohneWikidata;

        if (!$hatVolltext && !$hatFilter) {
            Response::error('Mindestens ein Suchbegriff oder Filter erforderlich.', 422);
        }

        $pdo    = Database::connection();
        $select = 's.id, s.status, s.zustand, s.verlegedatum,
                   s.wikidata_id_stein, s.osm_id,
                   p.vorname, p.nachname,
                   v.strasse_aktuell, v.hausnummer_aktuell, v.stadtteil';
        $joins  = 'JOIN personen    p ON p.id = s.person_id
                   JOIN verlegeorte v ON v.id = s.verlegeort_id';
        $where  = [];
        $params = [];

        // Volltext-Suche über Suchindex
        if ($hatVolltext) {
            $select  .= ', MATCH(si.personen_anteil, si.lage_anteil, si.dokumente_anteil)
                          AGAINST (? IN BOOLEAN MODE) AS relevanz';
            $joins   .= ' JOIN suchindex si ON si.stolperstein_id = s.id';
            $where[]  = 'MATCH(si.personen_anteil, si.lage_anteil, si.dokumente_anteil)
                         AGAINST (? IN BOOLEAN MODE)';
            $params[] = $q; // für SELECT
            $params[] = $q; // für WHERE
        }

        // Einfache Filter
        if ($status) {
            $where[]  = 's.status = ?';
            $params[] = $status;
        }
        if ($zustand) {
            $where[]  = 's.zustand = ?';
            $params[] = $zustand;
        }
        if ($stadtteil) {
            $where[]  = 'v.stadtteil LIKE ?';
            $params[] = '%' . $stadtteil . '%';
        }
        if ($strasse) {
            $where[]  = 'v.strasse_aktuell LIKE ?';
            $params[] = '%' . $strasse . '%';
        }
        if ($ohneWikidata) {
            $where[] = 's.wikidata_id_stein IS NULL';
        }

        $sql = "SELECT $select FROM stolpersteine s $joins";
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= $hatVolltext ? ' ORDER BY relevanz DESC' : ' ORDER BY v.stadtteil, p.nachname';
        $sql .= ' LIMIT 200';

        // Bei Volltext: q kommt zweimal (SELECT + WHERE), Rest danach
        $bindParams = $hatVolltext
            ? array_merge([$q, $q], array_slice($params, 2))
            : $params;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($bindParams);
        $ergebnisse = $stmt->fetchAll();

        Response::success([
            'treffer' => count($ergebnisse),
            'daten'   => $ergebnisse,
        ]);
    }
}
