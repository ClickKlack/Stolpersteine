<?php

declare(strict_types=1);

namespace Stolpersteine\Service;

use Stolpersteine\Config\Config;

class DateiService
{
    // Erlaubte MIME-Typen
    private const ERLAUBTE_TYPEN = [
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
        'image/tiff'      => 'tif',
        'application/pdf' => 'pdf',
    ];

    private string $uploadDir;

    public function __construct()
    {
        $this->uploadDir = Config::get('app')['upload_dir'];
    }

    // Datei speichern, gibt ['dateiname', 'dateipfad', 'hash', 'groesse_bytes', 'typ'] zurück
    public function save(array $file): array
    {
        $this->validateUpload($file);

        $mime      = mime_content_type($file['tmp_name']);
        $erweiterung = self::ERLAUBTE_TYPEN[$mime];
        $hash      = hash_file('sha256', $file['tmp_name']);
        $groesse   = $file['size'];

        // Verzeichnis nach Jahr/Monat strukturieren
        $unterverzeichnis = date('Y') . '/' . date('m');
        $zielVerzeichnis  = $this->uploadDir . '/' . $unterverzeichnis;

        if (!is_dir($zielVerzeichnis)) {
            mkdir($zielVerzeichnis, 0755, true);
        }

        // Dateiname: Hash-Prefix + Originalnamen (bereinigt)
        $originalName = pathinfo($file['name'], PATHINFO_FILENAME);
        $originalName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $originalName);
        $originalName = substr($originalName, 0, 50);
        $dateiname    = substr($hash, 0, 8) . '_' . $originalName . '.' . $erweiterung;
        $dateipfad    = $unterverzeichnis . '/' . $dateiname;
        $zielPfad     = $this->uploadDir . '/' . $dateipfad;

        if (!move_uploaded_file($file['tmp_name'], $zielPfad)) {
            throw new \RuntimeException('Datei konnte nicht gespeichert werden.');
        }

        return [
            'dateiname'    => $dateiname,
            'dateipfad'    => $dateipfad,
            'hash'         => $hash,
            'groesse_bytes'=> $groesse,
            'typ'          => $this->typeFromMime($mime),
        ];
    }

    // Datei vom Dateisystem löschen
    public function delete(string $dateipfad): void
    {
        $fullPath = $this->uploadDir . '/' . $dateipfad;
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }

    // Absoluten Pfad zur Datei liefern
    public function fullPath(string $dateipfad): string
    {
        return $this->uploadDir . '/' . $dateipfad;
    }

    private function validateUpload(array $file): void
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $fehler = match($file['error']) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Datei ist zu groß.',
                UPLOAD_ERR_PARTIAL   => 'Datei wurde nur teilweise übertragen.',
                UPLOAD_ERR_NO_FILE   => 'Keine Datei übermittelt.',
                default              => 'Upload-Fehler.',
            };
            throw new \InvalidArgumentException($fehler);
        }

        $mime = mime_content_type($file['tmp_name']);
        if (!isset(self::ERLAUBTE_TYPEN[$mime])) {
            throw new \InvalidArgumentException("Dateityp nicht erlaubt: $mime");
        }

        // Max. 20 MB
        if ($file['size'] > 20 * 1024 * 1024) {
            throw new \InvalidArgumentException('Datei überschreitet die maximale Größe von 20 MB.');
        }
    }

    private function typeFromMime(string $mime): string
    {
        return match($mime) {
            'image/jpeg', 'image/png', 'image/webp', 'image/tiff' => 'foto',
            'application/pdf'                                       => 'pdf',
            default                                                 => 'sonstig',
        };
    }
}
