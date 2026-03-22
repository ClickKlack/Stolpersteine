<?php

declare(strict_types=1);

namespace Stolpersteine\Api;

use Stolpersteine\Auth\Auth;
use Stolpersteine\Repository\DokumentRepository;
use Stolpersteine\Repository\AuditRepository;
use Stolpersteine\Service\DateiService;
use Stolpersteine\Service\DokumentService;

class DokumenteHandler extends BaseHandler
{
    private DokumentRepository $repo;
    private DateiService $dateiService;
    private DokumentService $dokumentService;

    public function __construct()
    {
        $this->repo            = new DokumentRepository();
        $this->dateiService    = new DateiService();
        $this->dokumentService = new DokumentService();
    }

    // GET /dokumente?person_id=&stolperstein_id=&typ=
    public function index(array $params): void
    {
        Auth::required();

        $filter = array_filter([
            'person_id'       => $this->queryParam('person_id'),
            'stolperstein_id' => $this->queryParam('stolperstein_id'),
            'typ'             => $this->queryParam('typ'),
        ]);

        Response::success($this->repo->findAll($filter));
    }

    // GET /dokumente/url-pruefung
    public function urlPruefung(array $params): void
    {
        Auth::required();
        Response::success($this->repo->findAllWithUrl());
    }

    // POST /dokumente/url-info — Metadaten einer externen URL abrufen
    public function urlInfo(array $params): void
    {
        Auth::required();

        $body = $this->jsonBody();
        $url  = trim($body['url'] ?? '');

        if ($url === '') {
            Response::error('URL ist erforderlich.', 422);
        }

        $info = $this->dokumentService->fetchUrlInfo($url);
        Response::success($info);
    }

    // GET /dokumente/{id}
    public function show(array $params): void
    {
        Auth::required();

        $id  = $this->intParam($params, 'id');
        $dok = $this->repo->findById($id);

        if ($dok === null) {
            Response::error('Dokument nicht gefunden.', 404);
        }

        Response::success($dok);
    }

    // POST /dokumente — multipart/form-data oder JSON (für URL-Dokumente)
    public function create(array $params): void
    {
        $user = Auth::required();

        if (!empty($_FILES['datei'])) {
            $this->createWithFile($user);
            return;
        }

        $this->createWithoutFile($user);
    }

    // PUT /dokumente/{id}
    public function update(array $params): void
    {
        $user = Auth::required();
        $id   = $this->intParam($params, 'id');

        $dok = $this->repo->findById($id);
        if ($dok === null) {
            Response::error('Dokument nicht gefunden.', 404);
        }

        $body      = $this->jsonBody();
        $titel     = trim($body['titel'] ?? '');
        $quelleUrl = trim($body['quelle_url'] ?? '');

        if ($titel === '') {
            Response::error('Titel ist erforderlich.', 422);
        }

        // URL-Deduplizierung: nur prüfen wenn URL geändert wurde
        if ($quelleUrl !== '' && $quelleUrl !== ($dok['quelle_url'] ?? '')) {
            $duplikat = $this->repo->findByQuelleUrl($quelleUrl);
            if ($duplikat !== null && (int) $duplikat['id'] !== $id) {
                Response::error(
                    'URL bereits vorhanden.',
                    409,
                    ['vorhandenes_dokument_id' => $duplikat['id'], 'titel' => $duplikat['titel']]
                );
            }
        }

        $personIds = $this->parsePersonIds(
            isset($body['person_ids']) ? json_encode($body['person_ids']) : null,
            $body['person_id'] ?? null
        );

        $data = [
            'titel'            => $titel,
            'beschreibung_kurz'=> $body['beschreibung_kurz'] ?? null,
            'quelle_url'       => $quelleUrl ?: null,
            'typ'              => $body['typ']                ?? $dok['typ'],
            'dateiname'        => trim($body['dateiname']     ?? '') ?: $dok['dateiname'],
            'groesse_bytes'    => $body['groesse_bytes']      ?? null,
            'quelle'    => trim($body['quelle'] ?? '') ?: null,
            'stolperstein_id'  => $body['stolperstein_id']    ?? null,
            'person_ids'       => $personIds,
        ];

        $this->repo->update($id, $data, $user['benutzername']);

        $updated = $this->repo->findById($id);

        AuditRepository::log($user['benutzername'], 'UPDATE', 'dokumente', $id, $dok, $updated);

        Response::success($updated);
    }

    // POST /dokumente/url-check — bulk URL-Prüfung
    public function urlCheck(array $params): void
    {
        $user = Auth::required();

        $body = $this->jsonBody();
        $ids  = array_map('intval', array_filter($body['ids'] ?? []));

        if ($ids === []) {
            Response::error('Keine IDs übergeben.', 422);
        }

        $results = [];
        foreach ($ids as $id) {
            $dok = $this->repo->findById($id);
            if ($dok === null || empty($dok['quelle_url'])) {
                continue;
            }

            $check = $this->dokumentService->checkUrl($dok['quelle_url']);
            $this->repo->updateUrlCheck($id, $check['status'], $check['geprueft_am'], $check['groesse_bytes'] ?? null);

            AuditRepository::log($user['benutzername'], 'URL_CHECK', 'dokumente', $id, null,
                ['url_status' => $check['status'], 'url_geprueft_am' => $check['geprueft_am']]);

            $results[] = [
                'id'             => $id,
                'url_status'     => $check['status'],
                'url_geprueft_am'=> $check['geprueft_am'],
                'groesse_bytes'  => $check['groesse_bytes'] ?? null,
            ];
        }

        Response::success($results);
    }

    // POST /dokumente/{id}/spiegel — lokale PDF-Spiegelung
    public function spiegel(array $params): void
    {
        $user = Auth::required();
        $id   = $this->intParam($params, 'id');

        $dok = $this->repo->findById($id);
        if ($dok === null) {
            Response::error('Dokument nicht gefunden.', 404);
        }

        if (empty($dok['quelle_url'])) {
            Response::error('Dieses Dokument hat keine externe URL.', 422);
        }

        try {
            $result = $this->dokumentService->mirrorPdf($dok['quelle_url'], $id);
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), 502);
        }

        $this->repo->updateSpiegel($id, $result['pfad'], $result['groesse_bytes']);
        $this->repo->updateUrlCheck($id, $result['http_status'], $result['geprueft_am'], $result['groesse_bytes']);

        AuditRepository::log($user['benutzername'], 'SPIEGEL', 'dokumente', $id, null, [
            'spiegel_pfad'        => $result['pfad'],
            'spiegel_groesse_bytes' => $result['groesse_bytes'],
            'url_status'          => $result['http_status'],
            'url_geprueft_am'     => $result['geprueft_am'],
        ]);

        $updated = $this->repo->findById($id);
        Response::success($updated);
    }

    // GET /dokumente/{id}/spiegel-download — geschützte Dateiauslieferung
    public function spiegelDownload(array $params): void
    {
        Auth::requireAdmin();
        $id = $this->intParam($params, 'id');

        $dok = $this->repo->findById($id);
        if ($dok === null) {
            Response::error('Dokument nicht gefunden.', 404);
        }

        if (empty($dok['spiegel_pfad'])) {
            Response::error('Kein lokaler Spiegel vorhanden.', 404);
        }

        $pfad = $this->dokumentService->getSpiegelDir() . '/' . basename($dok['spiegel_pfad']);

        if (!is_file($pfad) || !is_readable($pfad)) {
            Response::error('Datei nicht lesbar.', 404);
        }

        $ext         = strtolower(pathinfo($pfad, PATHINFO_EXTENSION));
        $mimeTypes   = [
            'pdf'  => 'application/pdf',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'webp' => 'image/webp',
        ];
        $contentType = $mimeTypes[$ext] ?? 'application/octet-stream';
        $dateiname   = $dok['dateiname'] ?: basename($pfad);

        // JSON-Header überschreiben und Datei streamen
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: inline; filename="' . addslashes($dateiname) . '"');
        header('Content-Length: ' . filesize($pfad));
        header('Cache-Control: private, no-store');
        readfile($pfad);
        exit;
    }

    // POST /dokumente/{id}/biografie  – setzt oder entfernt das Biografie-Dokument für eine Person (Toggle)
    public function setBiografie(array $params): void
    {
        Auth::required();
        $dokId    = $this->intParam($params, 'id');
        $body     = $this->jsonBody();
        $personId = (int) ($body['person_id'] ?? 0);

        if (!$personId) {
            Response::error('person_id fehlt.', 422);
        }

        $dok = $this->repo->findById($dokId);
        if ($dok === null) {
            Response::error('Dokument nicht gefunden.', 404);
        }

        // Toggle: wenn diese Person dieses Dokument bereits als Biografie hat → entfernen
        $aktuell = array_filter($dok['personen'], fn($p) => (int) $p['id'] === $personId);
        $person  = array_values($aktuell)[0] ?? null;

        if ($person && (bool) $person['ist_biografie']) {
            $this->repo->clearBiografieForPerson($personId);
            Response::success(['ist_biografie' => false]);
        } else {
            $this->repo->setBiografieForPerson($dokId, $personId);
            Response::success(['ist_biografie' => true]);
        }
    }

    // DELETE /dokumente/{id}
    public function delete(array $params): void
    {
        $user = Auth::required();
        $id   = $this->intParam($params, 'id');

        $dok = $this->repo->findById($id);
        if ($dok === null) {
            Response::error('Dokument nicht gefunden.', 404);
        }

        if (!empty($dok['dateipfad'])) {
            $this->dateiService->delete($dok['dateipfad']);
        }

        $this->repo->delete($id);

        AuditRepository::log($user['benutzername'], 'DELETE', 'dokumente', $id, $dok, null);

        Response::noContent();
    }

    // --- private Hilfsmethoden ---

    private function createWithFile(array $user): void
    {
        $titel = trim($_POST['titel'] ?? '');
        if ($titel === '') {
            Response::error('Titel ist erforderlich.', 422);
        }

        try {
            $dateiInfo = $this->dateiService->save($_FILES['datei']);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), 500);
        }

        $duplikat = $this->repo->findByHash($dateiInfo['hash']);
        if ($duplikat !== null) {
            Response::error(
                'Datei bereits vorhanden.',
                409,
                ['vorhandenes_dokument_id' => $duplikat['id'], 'titel' => $duplikat['titel']]
            );
        }

        $personIds = $this->parsePersonIds($_POST['person_ids'] ?? null, $_POST['person_id'] ?? null);

        $data = array_merge($dateiInfo, [
            'titel'            => $titel,
            'beschreibung_kurz'=> $_POST['beschreibung_kurz'] ?? null,
            'person_ids'       => $personIds,
            'stolperstein_id'  => $_POST['stolperstein_id']   ?? null,
            'quelle_url'       => null,
            'quelle'    => $_POST['quelle']      ?? null,
        ]);

        $id  = $this->repo->create($data, $user['benutzername']);
        $dok = $this->repo->findById($id);

        AuditRepository::log($user['benutzername'], 'INSERT', 'dokumente', $id, null, $dok);

        Response::created($dok);
    }

    private function createWithoutFile(array $user): void
    {
        $body      = $this->jsonBody();
        $titel     = trim($body['titel'] ?? '');
        $quelleUrl = trim($body['quelle_url'] ?? '');

        if ($titel === '') {
            Response::error('Titel ist erforderlich.', 422);
        }

        if ($quelleUrl === '') {
            Response::error('Entweder eine Datei oder eine quelle_url ist erforderlich.', 422);
        }

        // URL-Deduplizierung
        $duplikat = $this->repo->findByQuelleUrl($quelleUrl);
        if ($duplikat !== null) {
            Response::error(
                'URL bereits vorhanden.',
                409,
                ['vorhandenes_dokument_id' => $duplikat['id'], 'titel' => $duplikat['titel']]
            );
        }

        // Lizenzhinweis: Domain als Default
        $quelle = trim($body['quelle'] ?? '');
        if ($quelle === '') {
            $quelle = $this->dokumentService->extractDomain($quelleUrl) ?: null;
        }

        // Dateiname generieren wenn nicht angegeben
        $dateiname = trim($body['dateiname'] ?? '');
        if ($dateiname === '') {
            $dateiname = $this->dokumentService->generateFilename($quelleUrl, $titel);
        }

        $personIds = $this->parsePersonIds(
            isset($body['person_ids']) ? json_encode($body['person_ids']) : null,
            $body['person_id'] ?? null
        );

        $data = [
            'titel'            => $titel,
            'beschreibung_kurz'=> $body['beschreibung_kurz'] ?? null,
            'person_ids'       => $personIds,
            'stolperstein_id'  => $body['stolperstein_id']   ?? null,
            'quelle_url'       => $quelleUrl,
            'typ'              => $body['typ']                ?? 'url',
            'dateiname'        => $dateiname,
            'dateipfad'        => null,
            'hash'             => null,
            'groesse_bytes'    => $body['groesse_bytes']      ?? null,
            'quelle'    => $quelle,
            'url_status'       => isset($body['url_status']) ? (int) $body['url_status'] : null,
        ];

        $id  = $this->repo->create($data, $user['benutzername']);
        $dok = $this->repo->findById($id);

        AuditRepository::log($user['benutzername'], 'INSERT', 'dokumente', $id, null, $dok);

        Response::created($dok);
    }

    /**
     * Normalisiert person_ids aus Form-Data (JSON-String) oder einzelner ID.
     * @return int[]
     */
    private function parsePersonIds(?string $personIdsJson, mixed $personIdSingle): array
    {
        if ($personIdsJson !== null) {
            $decoded = json_decode($personIdsJson, true);
            if (is_array($decoded)) {
                return array_values(array_map('intval', array_filter($decoded)));
            }
        }
        if ($personIdSingle !== null && $personIdSingle !== '') {
            return [(int) $personIdSingle];
        }
        return [];
    }
}
