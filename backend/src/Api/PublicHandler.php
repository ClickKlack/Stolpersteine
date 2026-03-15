<?php

declare(strict_types=1);

namespace Stolpersteine\Api;

use Stolpersteine\Config\Database;

/**
 * Öffentliche API – keine Authentifizierung erforderlich.
 * Liefert ausschließlich Daten mit status = 'freigegeben'.
 */
class PublicHandler extends BaseHandler
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    // GET /public/statistiken
    public function statistiken(array $params): void
    {
        $steine = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM stolpersteine WHERE status = 'freigegeben'"
        )->fetchColumn();

        $personen = (int) $this->pdo->query(
            "SELECT COUNT(DISTINCT s.person_id)
             FROM stolpersteine s WHERE s.status = 'freigegeben'"
        )->fetchColumn();

        $strassen = (int) $this->pdo->query(
            "SELECT COUNT(DISTINCT al.strasse_id)
             FROM stolpersteine s
             JOIN verlegeorte v ON v.id = s.verlegeort_id
             LEFT JOIN adress_lokationen al ON al.id = v.adress_lokation_id
             WHERE s.status = 'freigegeben' AND al.strasse_id IS NOT NULL"
        )->fetchColumn();

        $stadtteile = (int) $this->pdo->query(
            "SELECT COUNT(DISTINCT al.stadtteil_id)
             FROM stolpersteine s
             JOIN verlegeorte v ON v.id = s.verlegeort_id
             LEFT JOIN adress_lokationen al ON al.id = v.adress_lokation_id
             WHERE s.status = 'freigegeben' AND al.stadtteil_id IS NOT NULL"
        )->fetchColumn();

        Response::success(compact('steine', 'personen', 'strassen', 'stadtteile'));
    }

    // GET /public/stolpersteine
    public function liste(array $params): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                s.id,
                p.nachname,
                p.vorname,
                p.geburtsname,
                p.geburtsdatum,
                p.sterbedatum,
                p.geburtsdatum_genauigkeit,
                p.sterbedatum_genauigkeit,
                str.name                        AS strasse,
                v.hausnummer_aktuell            AS hausnummer,
                st.name                         AS stadtteil,
                COALESCE(s.lat_override, v.lat) AS lat,
                COALESCE(s.lon_override, v.lon) AS lon,
                s.foto_pfad,
                s.foto_eigenes,
                s.wikimedia_commons,
                s.zustand
             FROM stolpersteine s
             JOIN personen     p   ON p.id  = s.person_id
             JOIN verlegeorte  v   ON v.id  = s.verlegeort_id
             LEFT JOIN adress_lokationen al  ON al.id  = v.adress_lokation_id
             LEFT JOIN strassen          str ON str.id = al.strasse_id
             LEFT JOIN stadtteile        st  ON st.id  = al.stadtteil_id
             WHERE s.status = ?
             ORDER BY p.nachname, p.vorname'
        );
        $stmt->execute(['freigegeben']);
        Response::success($stmt->fetchAll());
    }

    // GET /public/stolpersteine/{id}
    public function detail(array $params): void
    {
        $id   = $this->intParam($params, 'id');
        $stmt = $this->pdo->prepare(
            'SELECT
                s.id,
                s.verlegedatum,
                s.inschrift,
                s.zustand,
                s.wikidata_id_stein,
                s.osm_id,
                s.foto_pfad,
                s.foto_eigenes,
                s.wikimedia_commons,
                s.foto_lizenz_autor,
                s.foto_lizenz_name,
                s.foto_lizenz_url,
                COALESCE(s.lat_override, v.lat) AS lat,
                COALESCE(s.lon_override, v.lon) AS lon,
                p.id                            AS person_id,
                p.nachname,
                p.vorname,
                p.geburtsname,
                p.geburtsdatum,
                p.sterbedatum,
                p.geburtsdatum_genauigkeit,
                p.sterbedatum_genauigkeit,
                p.biografie_kurz,
                p.wikipedia_name                AS person_wikipedia,
                p.wikidata_id_person,
                str.name                        AS strasse,
                str.wikipedia_name              AS strasse_wikipedia,
                v.hausnummer_aktuell            AS hausnummer,
                v.beschreibung                  AS verlegeort_beschreibung,
                v.bemerkung_historisch,
                st.name                         AS stadtteil,
                plz.plz,
                dok.quelle_url                  AS biografie_dok_url,
                dok.titel                       AS biografie_dok_titel,
                dok.dateiname                   AS biografie_dok_dateiname,
                dok.groesse_bytes               AS biografie_dok_groesse_bytes,
                dok.quelle                      AS biografie_dok_quelle
             FROM stolpersteine s
             JOIN personen     p   ON p.id  = s.person_id
             JOIN verlegeorte  v   ON v.id  = s.verlegeort_id
             LEFT JOIN adress_lokationen al  ON al.id  = v.adress_lokation_id
             LEFT JOIN strassen          str ON str.id = al.strasse_id
             LEFT JOIN stadtteile        st  ON st.id  = al.stadtteil_id
             LEFT JOIN plz                   ON plz.id = al.plz_id
             LEFT JOIN dokumente dok         ON dok.id = p.biografie_dokument_id
             WHERE s.id = ? AND s.status = ?'
        );
        $stmt->execute([$id, 'freigegeben']);
        $row = $stmt->fetch();

        if (!$row) {
            Response::error('Nicht gefunden.', 404);
        }

        Response::success($row);
    }

    // GET /public/suche?q=
    public function suche(array $params): void
    {
        $q = trim($this->queryParam('q', ''));

        if ($q === '') {
            Response::error('Suchbegriff fehlt.', 422);
        }

        $stmt = $this->pdo->prepare(
            'SELECT
                s.id,
                p.nachname,
                p.vorname,
                str.name                        AS strasse,
                v.hausnummer_aktuell            AS hausnummer,
                st.name                         AS stadtteil,
                COALESCE(s.lat_override, v.lat) AS lat,
                COALESCE(s.lon_override, v.lon) AS lon,
                MATCH(si.personen_anteil, si.lage_anteil, si.dokumente_anteil)
                    AGAINST (? IN BOOLEAN MODE) AS relevanz
             FROM stolpersteine s
             JOIN personen     p   ON p.id  = s.person_id
             JOIN verlegeorte  v   ON v.id  = s.verlegeort_id
             JOIN suchindex    si  ON si.stolperstein_id = s.id
             LEFT JOIN adress_lokationen al  ON al.id  = v.adress_lokation_id
             LEFT JOIN strassen          str ON str.id = al.strasse_id
             LEFT JOIN stadtteile        st  ON st.id  = al.stadtteil_id
             WHERE s.status = ?
               AND MATCH(si.personen_anteil, si.lage_anteil, si.dokumente_anteil)
                   AGAINST (? IN BOOLEAN MODE)
             ORDER BY relevanz DESC
             LIMIT 100'
        );
        $stmt->execute([$q, 'freigegeben', $q]);

        Response::success($stmt->fetchAll());
    }
}
