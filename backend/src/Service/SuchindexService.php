<?php

declare(strict_types=1);

namespace Stolpersteine\Service;

use PDO;
use Stolpersteine\Config\Database;

class SuchindexService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    // Index für einen Stolperstein neu aufbauen
    public function update(int $stolpersteinId): void
    {
        $stein = $this->loadStoneData($stolpersteinId);
        if ($stein === null) {
            return;
        }

        $personenAnteil  = $this->buildPersonSection($stein);
        $lageAnteil      = $this->buildLocationSection($stein);
        $dokumenteAnteil = $this->buildDocumentsSection($stolpersteinId);

        $this->pdo->prepare(
            'INSERT INTO suchindex (stolperstein_id, personen_anteil, lage_anteil, dokumente_anteil)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                personen_anteil  = VALUES(personen_anteil),
                lage_anteil      = VALUES(lage_anteil),
                dokumente_anteil = VALUES(dokumente_anteil),
                aktualisiert_am  = NOW()'
        )->execute([$stolpersteinId, $personenAnteil, $lageAnteil, $dokumenteAnteil]);
    }

    // Index für alle Steine einer Person aktualisieren (nach Personen-Update)
    public function updateForPerson(int $personId): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM stolpersteine WHERE person_id = ?'
        );
        $stmt->execute([$personId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $steinId) {
            $this->update((int) $steinId);
        }
    }

    // Index für alle Steine an einem Verlegeort aktualisieren
    public function updateForLayingLocation(int $verlegeortId): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM stolpersteine WHERE verlegeort_id = ?'
        );
        $stmt->execute([$verlegeortId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $steinId) {
            $this->update((int) $steinId);
        }
    }

    // Index-Eintrag entfernen (nach Stein-Löschung — normalerweise via ON DELETE CASCADE)
    public function remove(int $stolpersteinId): void
    {
        $this->pdo->prepare(
            'DELETE FROM suchindex WHERE stolperstein_id = ?'
        )->execute([$stolpersteinId]);
    }

    // --- private Aufbau-Methoden ---

    private function loadStoneData(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.id, s.inschrift, s.verlegedatum,
                    p.vorname, p.nachname, p.geburtsname, p.geburtsdatum, p.sterbedatum, p.biografie_kurz,
                    v.strasse_aktuell, v.hausnummer_aktuell, v.stadtteil, v.plz_aktuell,
                    v.beschreibung AS ort_beschreibung, v.bemerkung_historisch
             FROM stolpersteine s
             JOIN personen    p ON p.id = s.person_id
             JOIN verlegeorte v ON v.id = s.verlegeort_id
             WHERE s.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function buildPersonSection(array $stein): string
    {
        $teile = array_filter([
            $stein['vorname'],
            $stein['nachname'],
            $stein['geburtsname'] ? 'geb. ' . $stein['geburtsname'] : null,
            $stein['geburtsdatum'],
            $stein['sterbedatum'],
            $stein['biografie_kurz'],
            $stein['inschrift'],
        ]);
        return implode(' ', $teile);
    }

    private function buildLocationSection(array $stein): string
    {
        $teile = array_filter([
            $stein['strasse_aktuell'],
            $stein['hausnummer_aktuell'],
            $stein['plz_aktuell'],
            $stein['stadtteil'],
            $stein['ort_beschreibung'],
            $stein['bemerkung_historisch'],
        ]);
        return implode(' ', $teile);
    }

    private function buildDocumentsSection(int $steinId): string
    {
        $stmt = $this->pdo->prepare(
            'SELECT titel, beschreibung_kurz FROM dokumente
             WHERE stolperstein_id = ? OR person_id IN (
                 SELECT person_id FROM stolpersteine WHERE id = ?
             )'
        );
        $stmt->execute([$steinId, $steinId]);
        $zeilen = $stmt->fetchAll();

        $teile = [];
        foreach ($zeilen as $z) {
            if ($z['titel'])            $teile[] = $z['titel'];
            if ($z['beschreibung_kurz']) $teile[] = $z['beschreibung_kurz'];
        }
        return implode(' ', $teile);
    }
}
