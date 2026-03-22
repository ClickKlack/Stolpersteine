<?php

declare(strict_types=1);

namespace Stolpersteine\Service;

use Stolpersteine\Config\Config;
use Stolpersteine\Config\Logger;

class DokumentService
{
    private string $spiegelDir;

    public function __construct()
    {
        $app = Config::get('app');
        $this->spiegelDir = $app['spiegel_dir']
            ?? dirname($app['upload_dir'] ?? (__DIR__ . '/../../uploads')) . '/spiegel';
    }

    public function getSpiegelDir(): string
    {
        return $this->spiegelDir;
    }

    /**
     * Extrahiert den Hostnamen aus einer URL (ohne "www.") als Standard-Lizenzhinweis.
     * Gibt leeren String zurück wenn die URL nicht parsebar ist.
     */
    public function extractDomain(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if ($host === null || $host === false || $host === '') {
            return '';
        }
        return (string) preg_replace('/^www\./i', '', $host);
    }

    /**
     * Generiert einen sanitizierten Dateinamen aus URL und optionalem Titel.
     * Beispiel: ('https://example.de/files/doc.pdf', 'Melderegister') → 'melderegister.pdf'
     */
    public function generateFilename(string $url, string $titel = ''): string
    {
        $path     = parse_url($url, PHP_URL_PATH) ?? '';
        $basename = pathinfo($path, PATHINFO_BASENAME);
        $ext      = strtolower(pathinfo($basename, PATHINFO_EXTENSION));

        if ($titel !== '') {
            $sanitized = (string) preg_replace('/[^a-zA-Z0-9_\-]/', '_', $titel);
            $sanitized = strtolower(substr($sanitized, 0, 50));
            $sanitized = trim($sanitized, '_');
            return $ext !== '' ? $sanitized . '.' . $ext : $sanitized;
        }

        if ($basename !== '') {
            return $basename;
        }

        // Fallback: kurzer Hash der URL
        return substr(md5($url), 0, 12) . ($ext !== '' ? '.' . $ext : '');
    }

    /**
     * Holt Metadaten einer URL per HEAD-Request:
     * dateiname (aus URL-Pfad), typ (aus Content-Type), groesse_bytes (aus Content-Length), url_status.
     */
    public function fetchUrlInfo(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY         => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_USERAGENT      => 'Stolpersteine-Verwaltung/1.0',
        ]);
        $headerStr = curl_exec($ch);
        $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $headers       = $this->parseResponseHeaders((string) ($headerStr ?: ''));
        $contentType   = strtolower($headers['content-type'] ?? '');
        $contentLength = isset($headers['content-length']) ? (int) $headers['content-length'] : null;

        // Typ aus Content-Type ableiten
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        $typ = 'url';
        if (str_contains($contentType, 'pdf') || $ext === 'pdf') {
            $typ = 'pdf';
        } elseif (str_contains($contentType, 'image/') || in_array($ext, ['jpg','jpeg','png','webp','tiff','tif'], true)) {
            $typ = 'foto';
        }

        return [
            'dateiname'    => $this->generateFilename($url),
            'typ'          => $typ,
            'groesse_bytes'=> ($contentLength !== null && $contentLength > 0) ? $contentLength : null,
            'url_status'   => $httpCode,
            'quelle'=> $this->extractDomain($url) ?: null,
        ];
    }

    private function parseResponseHeaders(string $raw): array
    {
        $headers = [];
        foreach (explode("\r\n", $raw) as $line) {
            $pos = strpos($line, ':');
            if ($pos !== false) {
                $name            = strtolower(trim(substr($line, 0, $pos)));
                $headers[$name]  = trim(substr($line, $pos + 1));
            }
        }
        return $headers;
    }

    /**
     * Prüft eine URL per HTTP HEAD-Request.
     * Gibt ['status' => int, 'geprueft_am' => string (ISO)] zurück.
     * Bei Netzwerkfehler: status = 0.
     */
    public function checkUrl(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY         => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_USERAGENT      => 'Stolpersteine-Verwaltung/1.0',
        ]);
        $headerStr = curl_exec($ch);
        $status    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $headers       = $this->parseResponseHeaders((string) ($headerStr ?: ''));
        $contentLength = isset($headers['content-length']) ? (int) $headers['content-length'] : null;

        return [
            'status'        => $status,
            'geprueft_am'   => date('Y-m-d H:i:s'),
            'groesse_bytes' => ($contentLength !== null && $contentLength > 0) ? $contentLength : null,
        ];
    }

    /**
     * Lädt eine externe PDF-Datei in das lokale Spiegel-Verzeichnis.
     * Gibt ['pfad' => string (relativ zu spiegel_dir), 'groesse_bytes' => int] zurück.
     *
     * @throws \RuntimeException bei Netzwerk- oder Dateisystemfehlern
     */
    public function mirrorPdf(string $url, int $dokId): array
    {
        if (!is_dir($this->spiegelDir) && !mkdir($this->spiegelDir, 0750, true)) {
            throw new \RuntimeException('Spiegel-Verzeichnis konnte nicht erstellt werden.');
        }

        $ext      = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        $filename = 'dok_' . $dokId . '_' . date('Ymd') . ($ext !== '' ? '.' . $ext : '.pdf');
        $zielPfad = $this->spiegelDir . '/' . $filename;

        $fh = fopen($zielPfad, 'wb');
        if ($fh === false) {
            throw new \RuntimeException('Zieldatei konnte nicht geöffnet werden.');
        }

        Logger::get()->info('PDF-Spiegel wird heruntergeladen', ['url' => $url, 'dok_id' => $dokId]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fh,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_USERAGENT      => 'Stolpersteine-Verwaltung/1.0',
        ]);
        curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);
        fclose($fh);

        if ($curlErr !== '') {
            Logger::get()->error('PDF-Download fehlgeschlagen (cURL-Fehler)', [
                'url'    => $url,
                'dok_id' => $dokId,
                'error'  => $curlErr,
            ]);
            @unlink($zielPfad);
            throw new \RuntimeException('Download fehlgeschlagen: ' . $curlErr);
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            Logger::get()->warning('PDF-Download fehlgeschlagen (HTTP-Status)', [
                'url'       => $url,
                'dok_id'    => $dokId,
                'http_code' => $httpCode,
            ]);
            @unlink($zielPfad);
            throw new \RuntimeException('Download fehlgeschlagen: HTTP ' . $httpCode);
        }

        $groesse = filesize($zielPfad);
        if ($groesse === false || $groesse === 0) {
            @unlink($zielPfad);
            throw new \RuntimeException('Heruntergeladene Datei ist leer.');
        }

        Logger::get()->info('PDF-Spiegel erfolgreich gespeichert', [
            'dok_id'        => $dokId,
            'datei'         => $filename,
            'groesse_bytes' => $groesse,
        ]);

        return [
            'pfad'          => $filename,
            'groesse_bytes' => $groesse,
            'http_status'   => $httpCode,
            'geprueft_am'   => date('Y-m-d H:i:s'),
        ];
    }
}
