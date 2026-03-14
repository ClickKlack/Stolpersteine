<?php

declare(strict_types=1);

namespace Stolpersteine\Repository;

use PDO;
use Stolpersteine\Config\Database;

class TemplateRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    /**
     * Liefert das aktive Template für ein gegebenes Zielsystem und einen Namen.
     * Bei mehreren Versionen wird die höchste aktive Version verwendet.
     */
    public function findAktiv(string $zielsystem, string $name): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, version, zielsystem, inhalt
             FROM templates
             WHERE zielsystem = ? AND name = ? AND aktiv = 1
             ORDER BY version DESC
             LIMIT 1'
        );
        $stmt->execute([$zielsystem, $name]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Alle aktiven Templates für ein Zielsystem.
     */
    public function findAlleAktiv(string $zielsystem): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, version, zielsystem, inhalt, erstellt_am, geaendert_am
             FROM templates
             WHERE zielsystem = ? AND aktiv = 1
             ORDER BY name, version DESC'
        );
        $stmt->execute([$zielsystem]);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, version, zielsystem, inhalt, aktiv, erstellt_am, geaendert_am
             FROM templates WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Legt eine neue Version an (alte Version wird deaktiviert).
     * Gibt die ID der neuen Version zurück.
     */
    public function update(int $id, string $inhalt, string $geaendertVon): int
    {
        $alte = $this->findById($id);
        if ($alte === null) {
            throw new \InvalidArgumentException('Template nicht gefunden.');
        }

        // Kein neues Version wenn Inhalt unverändert
        if ($alte['inhalt'] === $inhalt) {
            return $id;
        }

        // Höchste existierende Versionsnummer für diesen Namen/Zielsystem ermitteln
        $stmt = $this->pdo->prepare(
            'SELECT MAX(version) FROM templates WHERE zielsystem = ? AND name = ?'
        );
        $stmt->execute([$alte['zielsystem'], $alte['name']]);
        $neueVersion = ((int) $stmt->fetchColumn()) + 1;

        // Neue Version einfügen
        $stmt = $this->pdo->prepare(
            'INSERT INTO templates (name, version, zielsystem, inhalt, aktiv, erstellt_von, geaendert_von)
             VALUES (?, ?, ?, ?, 1, ?, ?)'
        );
        $stmt->execute([$alte['name'], $neueVersion, $alte['zielsystem'], $inhalt, $geaendertVon, $geaendertVon]);
        $neueId = (int) $this->pdo->lastInsertId();

        // Alle älteren Versionen deaktivieren
        $this->pdo->prepare(
            'UPDATE templates SET aktiv = 0 WHERE zielsystem = ? AND name = ? AND id != ?'
        )->execute([$alte['zielsystem'], $alte['name'], $neueId]);

        return $neueId;
    }
}
