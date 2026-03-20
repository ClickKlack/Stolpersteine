<?php

declare(strict_types=1);

namespace Stolpersteine\Api;

use Stolpersteine\Auth\Auth;
use Stolpersteine\Repository\StolpersteinRepository;
use Stolpersteine\Repository\AuditRepository;
use Stolpersteine\Service\DateiService;
use Stolpersteine\Config\Config;

class FotoHandler extends BaseHandler
{
    private StolpersteinRepository $repo;
    private DateiService $dateiService;

    public function __construct()
    {
        $this->repo         = new StolpersteinRepository();
        $this->dateiService = new DateiService();
    }

    // POST /stolpersteine/{id}/foto/upload
    // Alle eingeloggten Nutzer dürfen hochladen
    public function upload(array $params): void
    {
        $user  = Auth::required();
        $id    = $this->intParam($params, 'id');
        $stein = $this->steinOder404($id);

        if (empty($_FILES['foto'])) {
            Response::error('Keine Datei übermittelt.', 422);
        }

        try {
            $dateiInfo = $this->dateiService->save($_FILES['foto']);
        } catch (\InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), 500);
        }

        // Altes lokales Foto löschen
        if (!empty($stein['foto_pfad'])) {
            $this->dateiService->delete($stein['foto_pfad']);
        }

        $this->repo->updateFoto($id, [
            'foto_pfad'         => $dateiInfo['dateipfad'],
            'foto_eigenes'      => (int) ($_POST['foto_eigenes'] ?? 0),
            'foto_lizenz_autor' => null,
            'foto_lizenz_name'  => null,
            'foto_lizenz_url'   => null,
        ], $user['benutzername']);

        $neu = $this->repo->findById($id);
        AuditRepository::log($user['benutzername'], 'UPDATE', 'stolpersteine', $id, $stein, $neu);

        Response::success($neu);
    }

    // POST /stolpersteine/{id}/foto/commons-import
    // Nur Admins
    public function commonsImport(array $params): void
    {
        $user  = Auth::requireAdmin();
        $id    = $this->intParam($params, 'id');
        $stein = $this->steinOder404($id);

        $body     = $this->jsonBody();
        $dateiname = $this->parseCommonsDateiname($body['commons_datei'] ?? '');

        if ($dateiname === '') {
            Response::error('commons_datei ist erforderlich.', 422);
        }

        // Lizenz- und Download-URL von Commons-API holen
        $commonsInfo = $this->fetchCommonsInfo($dateiname);

        // Bild lokal herunterladen
        $localPfad = $this->downloadCommonsImage($commonsInfo['url'], $dateiname);

        // Altes lokales Foto löschen
        if (!empty($stein['foto_pfad'])) {
            $this->dateiService->delete($stein['foto_pfad']);
        }

        $this->repo->updateFoto($id, [
            'foto_pfad'         => $localPfad,
            'wikimedia_commons' => $dateiname,
            'foto_lizenz_autor' => $commonsInfo['autor'],
            'foto_lizenz_name'  => $commonsInfo['lizenz_name'],
            'foto_lizenz_url'   => $commonsInfo['lizenz_url'],
            'foto_eigenes'      => 0,
        ], $user['benutzername']);

        $neu = $this->repo->findById($id);
        AuditRepository::log($user['benutzername'], 'UPDATE', 'stolpersteine', $id, $stein, $neu);

        Response::success($neu);
    }

    // DELETE /stolpersteine/{id}/foto
    // Alle eingeloggten Nutzer
    public function delete(array $params): void
    {
        $user  = Auth::required();
        $id    = $this->intParam($params, 'id');
        $stein = $this->steinOder404($id);

        if (!empty($stein['foto_pfad'])) {
            $this->dateiService->delete($stein['foto_pfad']);
        }

        $this->repo->updateFoto($id, [
            'foto_pfad'         => null,
            'foto_lizenz_autor' => null,
            'foto_lizenz_name'  => null,
            'foto_lizenz_url'   => null,
            // wikimedia_commons und foto_eigenes bleiben erhalten
        ], $user['benutzername']);

        $neu = $this->repo->findById($id);
        AuditRepository::log($user['benutzername'], 'UPDATE', 'stolpersteine', $id, $stein, $neu);

        Response::success($neu);
    }

    // GET /stolpersteine/{id}/foto/vergleich
    public function vergleich(array $params): void
    {
        Auth::required();
        $id    = $this->intParam($params, 'id');
        $stein = $this->steinOder404($id);

        if (empty($stein['wikimedia_commons'])) {
            Response::error('Kein Commons-Link vorhanden.', 422);
        }

        // Lokalen Hash nur berechnen wenn Datei vorhanden
        $hashLokal = null;
        if (!empty($stein['foto_pfad'])) {
            $lokalPfad = Config::get('app')['upload_dir'] . '/' . $stein['foto_pfad'];
            if (is_file($lokalPfad)) {
                $hashLokal = sha1_file($lokalPfad);
            }
        }

        // SHA1-Hash + Lizenzdaten der Commons-Datei per API abrufen
        $url = 'https://commons.wikimedia.org/w/api.php?' . http_build_query([
            'action'  => 'query',
            'titles'  => 'File:' . $stein['wikimedia_commons'],
            'prop'    => 'imageinfo',
            'iiprop'  => 'sha1|extmetadata',
            'iiextmetadatalanguage' => 'de',
            'format'  => 'json',
        ]);

        $json = $this->httpGet($url);
        if ($json === false) {
            Response::error('Wikimedia Commons API nicht erreichbar.', 502);
        }

        $data  = json_decode($json, true);
        $pages = $data['query']['pages'] ?? [];
        $page  = reset($pages);

        if (isset($page['missing'])) {
            Response::error('Commons-Datei nicht gefunden: ' . $stein['wikimedia_commons'], 404);
        }

        $info        = $page['imageinfo'][0] ?? [];
        $meta        = $info['extmetadata'] ?? [];
        $hashCommons = $info['sha1'] ?? null;

        if ($hashCommons === null) {
            Response::error('Hash der Commons-Datei konnte nicht ermittelt werden.', 502);
        }

        Response::success([
            'identisch'         => $hashLokal !== null ? ($hashLokal === $hashCommons) : null,
            'hash_lokal'        => $hashLokal,
            'hash_commons'      => $hashCommons,
            'commons_autor'     => strip_tags($meta['Artist']['value']         ?? ''),
            'commons_lizenz'    => $meta['LicenseShortName']['value']          ?? null,
            'commons_lizenz_url'=> $meta['LicenseUrl']['value']                ?? null,
        ]);
    }

    // --- Hilfsmethoden ---

    /**
     * Führt einen HTTP-GET-Request aus (cURL, Fallback auf file_get_contents).
     * Gibt den Body als String zurück oder false bei Fehler.
     */
    private function httpGet(string $url, int $timeout = 10): string|false
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_USERAGENT      => 'Stolpersteine-App/1.0',
                CURLOPT_FOLLOWLOCATION => true,
            ]);
            $body = curl_exec($ch);
            $err  = curl_errno($ch);
            curl_close($ch);
            return $err === 0 ? $body : false;
        }

        $context = stream_context_create(['http' => [
            'timeout'       => $timeout,
            'user_agent'    => 'Stolpersteine-App/1.0',
            'ignore_errors' => true,
        ]]);
        return @file_get_contents($url, false, $context);
    }


    private function steinOder404(int $id): array
    {
        $stein = $this->repo->findById($id);
        if ($stein === null) {
            Response::error('Stolperstein nicht gefunden.', 404);
        }
        return $stein;
    }

    /**
     * Normalisiert Commons-Eingaben auf reinen Dateinamen:
     *   "Stolperstein_Berlin.jpg"
     *   "File:Stolperstein_Berlin.jpg"
     *   "https://commons.wikimedia.org/wiki/File:Stolperstein_Berlin.jpg"
     */
    private function parseCommonsDateiname(string $eingabe): string
    {
        $eingabe = trim($eingabe);
        if ($eingabe === '') {
            return '';
        }

        // URL-Pfad: letztes Segment nach dem letzten /
        if (str_contains($eingabe, '/')) {
            $eingabe = basename(parse_url($eingabe, PHP_URL_PATH) ?? $eingabe);
        }

        // "File:" / "Datei:" Prefix entfernen
        $eingabe = preg_replace('/^(File|Datei):/i', '', $eingabe);

        return trim($eingabe);
    }

    /**
     * Fragt die Wikimedia Commons API ab und gibt URL + Lizenzdaten zurück.
     */
    private function fetchCommonsInfo(string $dateiname): array
    {
        $url = 'https://commons.wikimedia.org/w/api.php?' . http_build_query([
            'action'  => 'query',
            'titles'  => 'File:' . $dateiname,
            'prop'    => 'imageinfo',
            'iiprop'  => 'url|extmetadata',
            'iiextmetadatalanguage' => 'de',
            'format'  => 'json',
        ]);

        $json = $this->httpGet($url);
        if ($json === false) {
            Response::error('Wikimedia Commons API nicht erreichbar.', 502);
        }

        $data  = json_decode($json, true);
        $pages = $data['query']['pages'] ?? [];
        $page  = reset($pages);

        if (isset($page['missing'])) {
            Response::error("Datei nicht gefunden: $dateiname", 404);
        }

        $info = $page['imageinfo'][0] ?? [];
        $meta = $info['extmetadata'] ?? [];

        return [
            'url'         => $info['url'] ?? null,
            'autor'       => strip_tags($meta['Artist']['value']          ?? ''),
            'lizenz_name' => $meta['LicenseShortName']['value']           ?? null,
            'lizenz_url'  => $meta['LicenseUrl']['value']                 ?? null,
        ];
    }

    /**
     * Lädt ein Bild von einer URL herunter und speichert es in uploads/.
     * Gibt den relativen Pfad zurück.
     */
    private function downloadCommonsImage(string $sourceUrl, string $dateiname): string
    {
        $uploadDir = Config::get('app')['upload_dir'];

        $bildDaten = $this->httpGet($sourceUrl, 30);
        if ($bildDaten === false) {
            Response::error('Bild konnte nicht von Commons heruntergeladen werden.', 502);
        }

        // Temporäre Datei anlegen für MIME-Erkennung
        $tmpFile = tempnam(sys_get_temp_dir(), 'commons_');
        file_put_contents($tmpFile, $bildDaten);

        $mime = mime_content_type($tmpFile);
        $erlaubteTypen = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/tiff' => 'tif',
        ];

        if (!isset($erlaubteTypen[$mime])) {
            unlink($tmpFile);
            Response::error("Nicht erlaubter Bildtyp: $mime", 422);
        }

        $erweiterung = $erlaubteTypen[$mime];
        $hash        = hash_file('sha256', $tmpFile);

        $unterverz = date('Y') . '/' . date('m');
        $zielDir   = $uploadDir . '/' . $unterverz;

        if (!is_dir($zielDir)) {
            mkdir($zielDir, 0755, true);
        }

        $basisname  = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($dateiname, PATHINFO_FILENAME));
        $basisname  = substr($basisname, 0, 50);
        $zieldatei  = substr($hash, 0, 8) . '_' . $basisname . '.' . $erweiterung;
        $zielPfad   = $zielDir . '/' . $zieldatei;
        $relativPfad = $unterverz . '/' . $zieldatei;

        if (!copy($tmpFile, $zielPfad)) {
            unlink($tmpFile);
            Response::error('Bild konnte nicht gespeichert werden.', 500);
        }
        unlink($tmpFile);

        return $relativPfad;
    }
}
