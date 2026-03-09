# Stolperstein‑Verwaltungssystem Magdeburg  
## Umsetzungskonzept (überarbeitet)

## 1. Projektdefinition
Internes Verwaltungssystem zur Pflege von Personen, Stolpersteinen, Verlegeorten und Dokumenten, mit Export- und Vergleichsfunktionen für Wikipedia, OSM und Wikidata.  
Plattform: PHP 8.x, MariaDB, Shared Hosting.  
Architektur: REST‑API zwischen Backend und Frontend.  
Rollen: `editor` (Datenpflege), `admin` (Sync, Benutzerverwaltung).

### 1.1 Zusätzliche Festlegung
Das Projekt ist für die Stadt Magdeburg erstellt, soll aber grundsätzlich auch für andere Städte nutzbar sein. Das bedeutet, die Stadt muss konfiurierbar sein.

---

## 2. Datenmodell

Alle Tabellen enthalten:
- `erstellt_am` DATETIME  
- `erstellt_von` VARCHAR(100)  
- `geaendert_am` DATETIME  
- `geaendert_von` VARCHAR(100)

### 2.1 Personen (`personen`)
- `id` INT PK AI  
- `vorname` VARCHAR(255)  
- `nachname` VARCHAR(255)  
- `geburtsname` VARCHAR(255)  
- `geburtsdatum` DATE  
- `sterbedatum` DATE  
- `biografie_kurz` TEXT  
- `wikidata_id_person` VARCHAR(50)

### 2.2 Verlegeorte (`verlegeorte`)
- `id` INT PK AI  
- `beschreibung` TEXT  
- `lat` DECIMAL(10,8)  
- `lon` DECIMAL(10,8)  
- `geo_point` POINT  
- `stadtteil` VARCHAR(100)  
- `strasse_aktuell` VARCHAR(255)  
- `hausnummer_aktuell` VARCHAR(50)  
- `plz_aktuell` VARCHAR(10)  
- `adresse_alt` JSON  
- `bemerkung_historisch` TEXT  
- `wikidata_id_strasse` VARCHAR(50)  
- `grid_n` INT  
- `grid_m` INT  
- `raster_beschreibung` TEXT  
  - Raster ist **1/1-basiert**, Ursprung **links oben**

### 2.3 Stolpersteine (`stolpersteine`)
- `id` INT PK AI  
- `person_id` INT FK → personen.id  
- `verlegeort_id` INT FK → verlegeorte.id  
- `verlegedatum` DATE  
- `inschrift` TEXT  
- `pos_x` INT (Spalte, 1-basiert)  
- `pos_y` INT (Zeile, 1-basiert)  
- `geo_override` POINT (optional)  
- `foto_pfad` VARCHAR(255)  
- `wikidata_id_stein` VARCHAR(50)  
- `osm_id` BIGINT  
- `status` ENUM(
  'neu',
  'validierung',
  'freigegeben',
  'archiviert',
  'fehlerhaft',
  'abgleich_wikipedia',
  'abgleich_osm',
  'abgleich_wikidata'
  )  
- `zustand` ENUM(
  'verfuegbar',
  'stein_fehlend',
  'kein_stein',
  'beschaedigt',
  'unleserlich'
  )

### 2.4 Dokumente (`dokumente`)
- `id` INT PK AI  
- `person_id` INT FK nullable  
- `stolperstein_id` INT FK nullable  
- `titel` VARCHAR(255)  
- `beschreibung_kurz` TEXT  
- `typ` VARCHAR(20)  
- `dateiname` VARCHAR(255)  
- `dateipfad` VARCHAR(255)  
- `quelle_url` VARCHAR(255)  
- `hash` CHAR(64) UNIQUE  
- `groesse_bytes` INT

### 2.5 Suchindex (`suchindex`)
Volltextindex getrennt von Kerntabellen.

- `id` INT PK AI  
- `stolperstein_id` INT FK  
- `personen_anteil` TEXT  
- `lage_anteil` TEXT  
- `dokumente_anteil` LONGTEXT  
- FULLTEXT-Index auf allen drei Feldern

---

## 3. Suche & Filterung

### 3.1 Einfache Filter
- Personen: Nachname, Geburtsname, Geburtsjahr  
- Steine: Status, Zustand, Verlegedatum, Ort  
- Orte: Stadtteil, Straße, PLZ  

Beispiele:
- „Steine ohne Wikidata-ID“  
- „Steine im Stadtteil Sudenburg“  
- „Steine mit Zustand ≠ verfügbar“

### 3.2 Volltextsuche
- Durchsucht `suchindex`  
- Kombination aus Volltext + Filtern  
- Relevanzsortierung  
- Inhalte: Personen, Lage, Dokumente

---

## 4. Externe Systeme & Synchronisation

### 4.1 Wikipedia
**Source of Truth: internes System**

- Export von Wikipedia-Markup (Tabellenzeilen)  
- Halbmanuelle Übernahme durch Admin  
- Diff-Funktion:
  - Einlesen der bestehenden Seite  
  - Feldweiser Vergleich  
  - Selektive Übernahme einzelner Werte

### 4.2 Wikidata/OSM-Validierung
Mehrstufig:

- **Syntax-Check** (Format korrekt?)  
- **Existenz-Check** (Item/Node existiert?)  
- **Semantik-Check** (Typ passend?)  

Ergebnisse werden gespeichert.

---

## 5. Template-System

### 5.1 Templates (`templates`)
- `id` INT PK AI  
- `name` VARCHAR(100)  
- `version` INT  
- `zielsystem` ENUM('wikipedia','osm','json','csv')  
- `inhalt` LONGTEXT

### 5.2 Platzhalter
**Person**
- `[[PERSON.VORNAME]]`  
- `[[PERSON.NACHNAME]]`  
- `[[PERSON.GEBURTSNAME]]`  
- `[[PERSON.GEBURTSDATUM]]`  
- `[[PERSON.STERBEDATUM]]`

**Stein**
- `[[STEIN.VERLEGEDATUM]]`  
- `[[STEIN.INSCHRIFT]]`  
- `[[STEIN.STATUS]]`  
- `[[STEIN.ZUSTAND]]`  
- `[[STEIN.LAT]]`  
- `[[STEIN.LON]]`

**Ort**
- `[[ORT.STADTTEIL]]`  
- `[[ORT.STRASSE]]`  
- `[[ORT.HAUSNUMMER]]`  
- `[[ORT.PLZ]]`  
- `[[ORT.BEMERKUNG_HISTORISCH]]`

---

## 6. Technische Architektur

### 6.1 Schichtenmodell
- **Repository-Schicht**: Datenzugriff  
- **Service-Schicht**: Import, Sync, Export, Suche  
- **REST-API**: `/api/personen`, `/api/stolpersteine`, `/api/verlegeorte`, `/api/suche`, `/api/export`  
- **Frontend**: nutzt ausschließlich REST-API

### 6.2 Dateisystem statt BLOB
- Alle Dateien (Fotos, PDFs) im Dateisystem  
- DB speichert nur Pfade  
- Hash zur Duplikaterkennung

### 6.3 Rollen & Rechte
- **editor**: Datenpflege, Exporte  
- **admin**: zusätzlich Sync, Benutzerverwaltung, Template-Verwaltung

### 6.4 Audit-Log (`audit_log`)
- `id` INT PK AI  
- `user` VARCHAR(100)  
- `aktion` VARCHAR(50)  
- `tabelle` VARCHAR(50)  
- `datensatz_id` INT  
- `altwert` JSON  
- `neuwert` JSON  
- `zeitpunkt` DATETIME

---

## 7. Import

### 7.1 Manueller Import
- Excel-Upload  
- Spalten-Mapping  
- Keine automatischen Jobs

### 7.2 Dry-Run
- Analyse ohne Speicherung  
- Anzeige:
  - neue Personen  
  - neue Steine  
  - Konflikte  
  - fehlerhafte Zeilen  
- Erst nach Bestätigung wird importiert

---

## 8. Roadmap

### Phase 1: Fundament
- DB-Schema  
- REST-API-Basis  
- Login, Rollen  
- CRUD für Personen, Steine, Orte  
- Audit-Log

### Phase 2: Dateien & Dokumente
- Dateisystem-Anbindung  
- Dokumentenverwaltung  
- API-Erweiterung

### Phase 3: Suche
- Einfache Filter  
- Suchindex  
- Volltextsuche

### Phase 4: Import
- Excel-Mapping  
- Dry-Run  
- Manueller Import

### Phase 5: Templates & Exporte
- Template-Versionierung  
- Platzhalter-Engine  
- Exporte (Wikipedia, OSM, JSON)

### Phase 6: Externe Validierung & Wikipedia-Diff
- Wikidata/OSM-Checks  
- Speicherung der Ergebnisse  
- Wikipedia-Diff

### Phase 7: Feinschliff & Erweiterungen
- Optimierungen  
- Erweiterte Filter  
- Vorbereitung öffentliches Frontend

