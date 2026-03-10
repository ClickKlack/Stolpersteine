# Stolperstein‑Verwaltungssystem Magdeburg
## Umsetzungskonzept (überarbeitet)

## 1. Projektdefinition
Internes Verwaltungssystem zur Pflege von Personen, Stolpersteinen, Verlegeorten und Dokumenten, mit Export- und Vergleichsfunktionen für Wikipedia, OSM und Wikidata.
Plattform: PHP 8.x, MariaDB, Shared Hosting.
Architektur: REST‑API zwischen Backend und Frontend.
Rollen: `editor` (Datenpflege), `admin` (Sync, Benutzerverwaltung).

### 1.1 Zusätzliche Festlegung
Das Projekt ist für die Stadt Magdeburg erstellt, soll aber grundsätzlich auch für andere Städte nutzbar sein. Das bedeutet, die Stadt muss konfigurierbar sein (Tabelle `konfiguration`).

### 1.2 Entwicklungskonventionen
- Alle Bezeichner (Klassen, Methoden, Variablen) in **Englisch**
- Ausnahme: Datenbankspaltennamen und API-Feldnamen (Domänensprache bleibt Deutsch, z. B. `nachname`, `stadtteil`)
- Handler-Methoden folgen REST-Konvention: `index`, `show`, `create`, `update`, `delete`

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
- `adress_lokation_id` INT FK → `adress_lokationen.id` (nullable)
- `hausnummer_aktuell` VARCHAR(50)
- `beschreibung` TEXT
- `lat` DECIMAL(10,8)
- `lon` DECIMAL(11,8)
- ~~`geo_point` POINT~~ → entfernt (SPATIAL INDEX nicht nullable auf Shared Hosting)
- `adresse_alt` JSON
- `bemerkung_historisch` TEXT
- `grid_n` INT
- `grid_m` INT
- `raster_beschreibung` TEXT
  - Raster ist **1/1-basiert**, Ursprung **links oben**

Straße, PLZ, Stadtteil und Stadt werden normalisiert in eigenen Tabellen geführt (→ 2.7).
Die API-Antworten liefern diese Felder als JOIN-Aliase (`strasse_aktuell`, `plz_aktuell`, `stadtteil`,
`stadt`, `wikidata_id_strasse`, `wikidata_id_stadtteil`, `wikidata_id_ort`) — abwärtskompatibel benannt.

### 2.3 Stolpersteine (`stolpersteine`)
- `id` INT PK AI
- `person_id` INT FK → personen.id
- `verlegeort_id` INT FK → verlegeorte.id
- `verlegedatum` DATE
- `inschrift` TEXT
- `pos_x` INT (Spalte, 1-basiert)
- `pos_y` INT (Zeile, 1-basiert)
- ~~`geo_override` POINT~~ → ersetzt durch `lat_override` DECIMAL(10,8) / `lon_override` DECIMAL(11,8)
- `foto_pfad` VARCHAR(255)
- `wikidata_id_stein` VARCHAR(50)
- `osm_id` BIGINT
- `status` ENUM('neu', 'validierung', 'freigegeben', 'archiviert', 'fehlerhaft', 'abgleich_wikipedia', 'abgleich_osm', 'abgleich_wikidata')
- `zustand` ENUM('verfuegbar', 'stein_fehlend', 'kein_stein', 'beschaedigt', 'unleserlich')

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

### 2.6 Weitere Tabellen
- `benutzer` — Login, Rollen (editor/admin)
- `konfiguration` — Stadtname und externe IDs konfigurierbar
- `templates` — Exportvorlagen mit Versionierung
- `audit_log` — Lückenlose Protokollierung aller Änderungen
- `validierungen` — Ergebnisse der Wikidata/OSM-Checks

### 2.7 Adress-Normalisierung
Straßen, Stadtteile, PLZ und Städte werden in eigenen Tabellen verwaltet und über eine
Bridge-Entität `adress_lokationen` verknüpft. `verlegeorte` hält nur eine FK darauf.

- `staedte` – `id`, `name`, `wikidata_id`
- `stadtteile` – `id`, `name`, `wikidata_id`, `stadt_id` FK
- `strassen` – `id`, `name`, `wikidata_id`, `stadt_id` FK
- `plz` – `id`, `plz`, `stadt_id` FK (UNIQUE `plz + stadt_id`)
- `adress_lokationen` – `id`, `strasse_id` FK, `stadtteil_id` FK (nullable), `plz_id` FK (nullable)

**find-or-create-Muster:** `POST /api/adressen/lokationen` löst eine vollständige Adresse auf —
legt alle fehlenden Einträge an und gibt die Lokation zurück. Wikidata-IDs werden beim ersten
Vorkommen gesetzt und danach nie überschrieben. NULL-sichere Eindeutigkeit via MySQL `<=>` Operator.

---

## Projektstruktur
```
Stolpersteine/
├── backend/
│   ├── public/              # Document Root des Webservers
│   │   ├── index.php        # Einziger Einstiegspunkt (Router)
│   │   └── .htaccess        # Alle Requests → index.php
│   ├── src/
│   │   ├── Api/
│   │   │   ├── BaseHandler.php       # Basisklasse (jsonBody, intParam, queryParam)
│   │   │   ├── Router.php            # URL-Matching mit {id}-Parametern
│   │   │   ├── Response.php          # Einheitliche JSON-Antworten
│   │   │   ├── AuthHandler.php       # POST /auth/login|logout, GET /auth/me
│   │   │   ├── PersonenHandler.php   # CRUD /api/personen
│   │   │   ├── VerlegeorteHandler.php
│   │   │   ├── StolpersteineHandler.php
│   │   │   ├── DokumenteHandler.php
│   │   │   ├── AdressenHandler.php   # GET /adressen/strassen, POST /adressen/lokationen
│   │   │   ├── SucheHandler.php
│   │   │   ├── ImportHandler.php     # POST /api/import/analyze|preview|execute
│   │   │   └── ExportHandler.php     # (Phase 5, noch nicht implementiert)
│   │   ├── Repository/
│   │   │   ├── AuditRepository.php   # Zentrales Audit-Log
│   │   │   ├── PersonRepository.php
│   │   │   ├── VerlegeortRepository.php  # JOINs auf adress_lokationen
│   │   │   ├── AdresseRepository.php     # find-or-create für Adress-Normalisierung
│   │   │   ├── StolpersteinRepository.php
│   │   │   └── DokumentRepository.php
│   │   ├── Service/
│   │   │   ├── DateiService.php      # Datei-Upload, Duplikat-Check via SHA-256
│   │   │   ├── ImportService.php     # Excel/CSV-Import, Dry-Run, Duplikat-Erkennung
│   │   │   └── SuchindexService.php  # Suchindex aufbauen/aktualisieren
│   │   ├── Auth/
│   │   │   └── Auth.php     # Session, Login, Rollen-Guards
│   │   └── Config/
│   │       ├── Config.php   # Konfigurationslader
│   │       └── Database.php # PDO-Singleton, setzt UTC + utf8mb4
│   ├── db/
│   │   ├── schema.sql       # Vollständiges DB-Schema v1.0
│   │   └── migrations/      # Zukünftige Schema-Änderungen
│   ├── composer.json        # PSR-4 Autoloading (Stolpersteine\)
│   └── config.example.php  # Vorlage für config.php (nicht im Git)
│
├── frontend/                # Alpine.js + Pico CSS (kein Build-Schritt)
│   ├── index.html           # App-Shell: Login, Navigation, Router-Outlet
│   ├── css/
│   │   └── app.css          # Custom Styles (ergänzt Pico CSS)
│   └── js/
│       ├── config.js        # API-Basis-URL
│       ├── api.js           # fetch-Client (get/post/put/delete/upload)
│       ├── app.js           # Stores (auth, notify, router) + Haupt-Komponente
│       └── pages/
│           ├── login.js        # Login-Formular
│           ├── dashboard.js    # Übersichts-Statistiken
│           ├── personen.js     # Personen-CRUD (Liste, Filter, Modal, Löschen)
│           └── verlegeorte.js  # Verlegeorte-CRUD + Adress-Lookup-Widget
│
├── bruno/                   # Bruno API-Collection (versioniert)
│   ├── bruno.json
│   ├── environments/
│   │   └── local.bru        # Lokale Umgebungsvariablen (baseUrl etc.)
│   ├── auth/
│   ├── personen/
│   ├── verlegeorte/
│   ├── stolpersteine/
│   ├── dokumente/
│   ├── suche/
│   └── import/
│
├── uploads/                 # Fotos, PDFs (außerhalb public/, nicht im Git)
├── backend/API.md           # Vollständige Endpunkt-Dokumentation
├── projekt_solptersteine.md
├── .gitignore
└── README.md
```

---

## 3. Suche & Filterung

### 3.1 Einfache Filter
- Personen: Nachname, Geburtsname, Geburtsjahr
- Steine: Status, Zustand, Verlegedatum, Ort, ohne_wikidata
- Orte: Stadtteil, Straße, PLZ

Beispiele:
- „Steine ohne Wikidata-ID"
- „Steine im Stadtteil Sudenburg"
- „Steine mit Zustand ≠ verfügbar"

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

Ergebnisse werden in `validierungen` gespeichert.

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
- **Repository-Schicht**: Datenzugriff (PDO, prepared statements)
- **Service-Schicht**: Import, Sync, Export, Suche
- **REST-API**: `/api/personen`, `/api/stolpersteine`, `/api/verlegeorte`, `/api/dokumente`, `/api/suche`, `/api/export`
- **Frontend**: nutzt ausschließlich REST-API

### 6.2 Dateisystem statt BLOB
- Alle Dateien (Fotos, PDFs) im Dateisystem (`uploads/`)
- DB speichert nur Pfade
- SHA-256-Hash zur Duplikaterkennung
- Erlaubte MIME-Typen: `image/jpeg`, `image/png`, `image/webp`, `image/tiff`, `application/pdf`
- Max. Dateigröße: 20 MB
- Verzeichnisstruktur nach Jahr/Monat: `uploads/2025/03/`

### 6.3 Rollen & Rechte
- **editor**: Datenpflege (CRUD), Exporte
- **admin**: zusätzlich Löschen, Sync, Benutzerverwaltung, Template-Verwaltung

### 6.4 Technische Entscheidungen
- Zeitzone: `SET time_zone = '+00:00'` bei jedem DB-Connect → alle DATETIME-Felder in UTC
- Kein SPATIAL INDEX (Shared Hosting, nullable POINT nicht erlaubt) → lat/lon als DECIMAL
- Session-basierte Auth mit `session_regenerate_id()` nach Login
- Secure-Cookie-Flag: wird automatisch deaktiviert für `localhost` und `127.*` (inkl. Port-Erkennung)
- Fehlerdetails nur im Debug-Modus (`app.debug = true`) nach außen

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

### ✅ Phase 1: Fundament
- DB-Schema (`backend/db/schema.sql`)
- Composer / PSR-4 Autoloading
- Config-Klasse + PDO-Singleton (UTC, utf8mb4)
- Router + Response-Klassen
- Auth: Login, Logout, Session, Rollen-Guards
- CRUD für Personen, Verlegeorte, Stolpersteine
- Audit-Log (zentral via AuditRepository)

### ✅ Phase 2: Dateien & Dokumente
- `DateiService`: Upload, SHA-256-Duplikatprüfung, Verzeichnisstruktur nach Jahr/Monat
- `DokumentRepository` + `DokumenteHandler`: CRUD `/api/dokumente`
- Unterstützt Datei-Upload (multipart) und URL-Dokumente (JSON)

### ✅ Phase 3: Suche
- `SuchindexService`: Index aufbauen, bei Personen-/Verlegeort-Updates automatisch aktualisieren
- `SucheHandler`: Volltextsuche + einfache Filter kombinierbar (`/api/suche`)
- Relevanzsortierung bei Volltextsuche
- Filter `ohne_wikidata=1` in `/api/stolpersteine` implementiert und getestet
- `GET /api/stolpersteine/{id}` liefert `suchindex_aktualisiert_am` (null = kein Indexeintrag)

### ✅ Phase 4: Import
- `ImportService`: Excel/XLSX/ODS/CSV einlesen via PhpSpreadsheet
- `POST /api/import/analyze` – Spaltenvorschau für Mapping-UI (erste 5 Zeilen + Feldliste)
- `POST /api/import/preview` – Dry-Run mit Mapping: zeigt neue/vorhandene Personen & Orte, Fehlerzeilen
- `POST /api/import/execute` – tatsächlicher Import in einer DB-Transaktion mit Audit-Log
- Duplikat-Erkennung: Person per Nachname+Vorname, Verlegeort per Straße+Hausnummer
- Innerhalb einer Datei werden mehrere Steine am selben Ort korrekt zusammengeführt
- `PersonRepository::findByName()` und `VerlegeortRepository::findByAddress()` als Lookup-Methoden

### Phase 5: Templates & Exporte
- `ExportHandler` implementieren (`/api/export/{format}`)
- Template-Versionierung
- Platzhalter-Engine
- Exporte: Wikipedia-Markup, OSM, JSON, CSV

### Phase 6: Externe Validierung & Wikipedia-Diff
- Wikidata/OSM-Checks
- Speicherung der Ergebnisse in `validierungen`
- Wikipedia-Diff (seitenweises Einlesen, feldweiser Vergleich)

### 🔄 Phase 7: Frontend
- **Alpine.js** (kein Build-Schritt, CDN), **Pico CSS** für Basis-Styling
- Hash-basiertes Routing via `Alpine.store('router')`
- `js/api.js` als zentraler HTTP-Client (fetch-basiert, Cookie-Auth)
- `js/app.js` mit Stores: `auth`, `notify`, `router`, `config` (lädt Stadtkonfiguration nach Login)
- Jede Seite ist eine eigene `Alpine.data()`-Komponente in `js/pages/`

Implementiert:
- ✅ Login-Seite + Session-Handling
- ✅ Dashboard mit Statistiken
- ✅ Personen-Verwaltung (Liste, Filter, Modal Anlegen/Bearbeiten, Lösch-Bestätigung)
- ✅ Verlegeorte-Verwaltung mit normalisierter Adress-Eingabe:
  - Autocomplete-Lookup (`GET /adressen/strassen`)
  - Inline-Formular „Neue Adresse" (`POST /adressen/lokationen`)
  - Anzeige gewählter Adresse mit Wikidata-Buttons je Straße / Stadtteil / Stadt

Ausstehend: Stolpersteine, Dokumente, Suche, Import, Export, Benutzerverwaltung

### Phase 8: Feinschliff & Erweiterungen
- Optimierungen
- Erweiterte Filter
- Vorbereitung öffentliches Frontend
