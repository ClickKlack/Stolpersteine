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
- `geburtsdatum_genauigkeit` ENUM('tag','monat','jahr')
- `sterbedatum` DATE
- `sterbedatum_genauigkeit` ENUM('tag','monat','jahr')
- `biografie_kurz` TEXT
- `wikipedia_name` VARCHAR(255) — Artikelname auf Wikipedia
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
- `foto_eigenes` TINYINT(1) (1 = eigenes Foto, 0 = fremdes)
- `foto_lizenz_autor` VARCHAR(255)
- `foto_lizenz_name` VARCHAR(100)
- `foto_lizenz_url` VARCHAR(255)
- `wikimedia_commons` VARCHAR(255) (Dateiname, z. B. `Stolperstein_Berlin.jpg`)
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

- `staedte` – `id`, `name` (UNIQUE), `wikidata_id`
- `stadtteile` – `id`, `name`, `wikidata_id`, `wikipedia_stadtteil`, `wikipedia_stolpersteine`, `stadt_id` FK
- `strassen` – `id`, `name`, `wikipedia_name`, `wikidata_id`, `stadt_id` FK (UNIQUE `name + stadt_id`)
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
│   │   └── .htaccess        # Alle Requests → index.php; schützt src/, vendor/, config.php
│   ├── src/
│   │   ├── Api/
│   │   │   ├── BaseHandler.php       # Basisklasse (jsonBody, intParam, queryParam)
│   │   │   ├── Router.php            # URL-Matching mit {id}-Parametern
│   │   │   ├── Response.php          # Einheitliche JSON-Antworten
│   │   │   ├── AuthHandler.php       # POST /auth/login|logout, GET /auth/me
│   │   │   ├── PersonenHandler.php   # CRUD /api/personen
│   │   │   ├── VerlegeorteHandler.php
│   │   │   ├── StolpersteineHandler.php
│   │   │   ├── DokumenteHandler.php  # CRUD + URL-Check + Spiegelung + Biografie
│   │   │   ├── AdressenHandler.php   # GET /adressen/strassen|stadtteile, POST /adressen/lokationen
│   │   │   ├── FotoHandler.php       # Upload, Commons-Import, Löschen, Vergleich
│   │   │   ├── KonfigurationHandler.php  # GET /konfiguration
│   │   │   ├── SucheHandler.php
│   │   │   ├── ImportHandler.php     # POST /api/import/analyze|preview|execute
│   │   │   ├── ExportHandler.php     # Wikipedia + OSM Export & Diff
│   │   │   ├── TemplateHandler.php   # GET/PUT /templates, GET /templates/{id}
│   │   │   └── PublicHandler.php     # GET /public/* (kein Auth, nur freigegeben)
│   │   ├── Repository/
│   │   │   ├── AuditRepository.php   # Zentrales Audit-Log
│   │   │   ├── PersonRepository.php
│   │   │   ├── VerlegeortRepository.php  # JOINs auf adress_lokationen
│   │   │   ├── AdresseRepository.php     # find-or-create für Adress-Normalisierung
│   │   │   ├── StolpersteinRepository.php
│   │   │   ├── DokumentRepository.php
│   │   │   └── TemplateRepository.php    # Templates mit Versionierung
│   │   ├── Service/
│   │   │   ├── DateiService.php      # Datei-Upload, Duplikat-Check via SHA-256
│   │   │   ├── DokumentService.php   # URL-Check, PDF-Mirroring, Metadaten, Biografie-Zuweisung
│   │   │   ├── ImportService.php     # Excel/CSV-Import, Dry-Run, Duplikat-Erkennung
│   │   │   ├── SuchindexService.php  # Suchindex aufbauen/aktualisieren
│   │   │   └── ExportService.php     # Wikitext + OSM-Export, MediaWiki-API-Diff
│   │   ├── Auth/
│   │   │   └── Auth.php     # Session, Login, Rollen-Guards
│   │   └── Config/
│   │       ├── Config.php   # Konfigurationslader
│   │       ├── Logger.php   # Monolog-Singleton (log_dir aus Config, rotierende Logs)
│   │       └── Database.php # PDO-Singleton, setzt UTC + utf8mb4
│   ├── storage/             # Persistenter Dateispeicher (nicht web-zugänglich)
│   │   ├── .htaccess        # Zugriff verweigert (Apache 2.2 kompatibel)
│   │   ├── logs/            # App-Logs (app-*.log) + PHP-Fehlerlog – nicht im Git
│   │   └── spiegel/         # Gespiegelte externe PDFs – nicht im Git
│   ├── db/
│   │   ├── schema.sql       # Vollständiges DB-Schema v1.0
│   │   └── migrations/      # Schema-Änderungen
│   ├── composer.json        # PSR-4 Autoloading (Stolpersteine\)
│   └── config.example.php  # Vorlage für config.php (nicht im Git)
│
├── frontend/                # Verwaltungsoberfläche – Alpine.js + Pico CSS (kein Build-Schritt)
│   ├── index.html           # App-Shell: Login, Navigation, Router-Outlet
│   ├── css/
│   │   └── app.css          # Custom Styles (ergänzt Pico CSS)
│   └── js/
│       ├── config.js        # API-Basis-URL
│       ├── api.js           # fetch-Client (get/post/put/delete/upload)
│       ├── app.js           # Stores (auth, notify, router) + Haupt-Komponente
│       └── pages/
│           ├── login.js         # Login-Formular
│           ├── dashboard.js     # Übersichts-Statistiken (klickbare Stat-Karten)
│           ├── personen.js      # Personen-CRUD (Liste, Filter, Modal, Löschen)
│           ├── verlegeorte.js   # Verlegeorte-CRUD + Adress-Lookup + Karte + Grid-Konfig
│           ├── stolpersteine.js # Stolpersteine-CRUD + Grid-Picker + Foto + Karte
│           ├── adressen.js      # Adress-CRUD (Städte, Stadtteile, Straßen, PLZ, Lokationen)
│           ├── dokumente.js     # Dokument-Verwaltung (Upload, URL, Spiegelung, Biografie)
│           └── export.js        # Export-Seite: Wikipedia + OSM, Templates, Diff-Ansicht
│
├── website/                 # Öffentliche Website – Alpine.js + Leaflet (kein Build-Schritt)
│   ├── index.html           # SPA: Karte / Personenliste / Detailansicht
│   ├── css/
│   │   └── app.css          # Eigenes Design (würdevoll, kein Pico CSS, responsive)
│   └── js/
│       ├── config.js        # API-Basis-URL
│       ├── api.js           # fetch-Client (ohne credentials)
│       ├── app.js           # Router-Store (#karte, #liste, #stein/{id}) + Stats-Store
│       └── pages/
│           ├── karte.js         # Leaflet-Karte mit Marker-Clustern und Popups
│           ├── liste.js         # Gefilterte, paginierte Personenliste
│           └── detail.js        # Detailansicht: Foto, Biografie, Dokument-Link, externe Links
│
├── bruno/                   # Bruno API-Collection (versioniert)
│   ├── bruno.json
│   ├── environments/
│   │   └── local.bru        # Lokale Umgebungsvariablen (baseUrl etc.)
│   ├── auth/
│   ├── personen/
│   ├── verlegeorte/
│   ├── stolpersteine/
│   │   └── foto/            # Upload, Commons-Import, Löschen, Vergleich
│   ├── dokumente/
│   ├── suche/
│   ├── import/
│   ├── export/
│   ├── templates/
│   └── public/              # Öffentliche Endpunkte (kein Login)
│
├── scripts/
│   └── deploy.sh            # rsync-Deployment auf Shared Hosting per SSH
│
├── uploads/                 # Fotos, PDFs (außerhalb public/, nicht im Git)
├── backend/API.md           # Vollständige Endpunkt-Dokumentation
├── projekt_solptersteine.md
├── .gitignore
└── README.md
```

**Deployment-Struktur beim Hoster** (`stst/`):
```
stst/
├── storage/          ← Persistenter Speicher (nie per rsync gelöscht)
│   ├── logs/         ← PHP-Fehlerlog (php.log) + App-Logs (app-*.log)
│   └── spiegel/      ← Gespiegelte PDFs
├── api/              ← PHP-Backend (public/ + src/ + vendor/)
│   └── config.php    ← Produktionskonfiguration (nicht deployt)
├── verwaltung/       ← Verwaltungsoberfläche (frontend/)
└── (root)            ← Öffentliche Website (website/)
```

---

## 3. Suche & Filterung

### 3.1 Einfache Filter
- Personen: Nachname, Geburtsname, Geburtsjahr
- Steine: Status, Zustand, Verlegedatum, Ort, Verlegeort, Foto-Status, ohne_wikidata
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

### 4.1 Wikipedia-Export und Abgleich

**Grundprinzip:** Das interne System ist die „Source of Truth". Wikipedia ist das Ausgabemedium. Der Abgleich dient dazu, externe Änderungen in Wikipedia zu erkennen und selektiv zu übernehmen — nicht umgekehrt.

#### Export
- Für jeden **Stadtteil** wird eine eigene Wikipedia-Seite erzeugt
- Innerhalb der Seite sind die Einträge nach **Nachname, Vorname** sortiert
- Das Ausgabeformat orientiert sich an der Wikipedia-Vorlage für Stolpersteine (wikitable)
- Ein konfigurierbares Template (gespeichert in der Tabelle `templates`, `zielsystem = 'wikipedia'`) besteht aus zwei Teilen:
  - **Seitenvorlage**: umschließender Wikitext der gesamten Seite (Einleitung, Tabellenrahmen, Abschluss) mit Platzhaltern
  - **Zeilenvorlage**: Markup für genau eine Datenzeile (eine Person / ein Stein) mit Platzhaltern
- Der Export erzeugt vollständigen Wikitext, der direkt in eine Wikipedia-Seite übernommen werden kann

#### Abgleich (Diff-Funktion)
Ziel: Erkennen, ob seit dem letzten Export sinnvolle Änderungen in Wikipedia stattgefunden haben (z. B. neue Fotos, korrigierte Daten, ergänzte Wikidata-IDs), die in die internen Daten übernommen werden sollten.

Ablauf:
1. Die aktuelle Wikipedia-Seite wird per **MediaWiki API** eingelesen und geparst
2. Die exportierten Daten aus dem internen System werden mit dem Wikipedia-Ist-Stand **zeilenweise verglichen**
3. Unterschiede werden **feldweise hervorgehoben** (ähnlich einem Diff-Tool: alt/neu nebeneinander)
4. Der Benutzer entscheidet pro Unterschied, ob er den Wikipedia-Wert ins System übernimmt, verwirft oder ignoriert

Technische Randbedingungen:
- Wikipedia-Tabellen haben keine stabilen IDs; die Zuordnung erfolgt über **Nachname + Vorname** der Person
- Felder, die im internen System als „kanonisch" gelten (z. B. Koordinaten, Status), werden beim Abgleich nur angezeigt, nicht automatisch überschrieben
- Der Abgleich ist rein lesend; Schreibzugriffe auf Wikipedia sind nicht vorgesehen

#### Konfiguration
- Der Wikipedia-Seitenname für den Abgleich wird am Stadtteil-Datensatz (`stadtteile.wikipedia_stolpersteine`) hinterlegt; der allgemeine Stadtteil-Artikel unter `wikipedia_stadtteil`

### 4.2 Wikidata/OSM-Validierung
*(Vorgesehen, noch nicht implementiert)*

Mehrstufig:

- **Syntax-Check** (Format korrekt?)
- **Existenz-Check** (Item/Node existiert?)
- **Semantik-Check** (Typ passend?)

Ergebnisse werden in `validierungen` gespeichert.

---

## 5. Template-System

### 5.1 Templates (`templates`)
- `id` INT PK AI
- `name` VARCHAR(100) — `seite` oder `zeile`
- `version` INT
- `zielsystem` ENUM('wikipedia','osm','json','csv')
- `inhalt` LONGTEXT
- `aktiv` TINYINT(1) — 1 = aktuelle Version
- `erstellt_von`, `geaendert_von` VARCHAR(100)

### 5.2 Platzhalter

**Seite (Seitenebene)**
- `[[SEITE.STADTTEIL]]` – Stadtteilname
- `[[SEITE.STADTTEIL_WIKIDATA]]` – Wikidata-ID des Stadtteils
- `[[SEITE.STADTTEIL_WIKIPEDIA]]` – Wikipedia-Artikeltitel des Stadtteils
- `[[SEITE.STADTTEIL_WIKIPEDIA_LINK]]` – Wikipedia-Markup-Link: `[[Titel|Name]]` wenn Titel ≠ Name, `[[Titel]]` wenn gleich
- `[[SEITE.STOLPERSTEINE_WIKIPEDIA]]` – Wikipedia-Seite der Stolpersteinliste
- `[[SEITE.ZEILEN]]` – alle gerenderten Tabellenzeilen
- `[[SEITE.ANZAHL_ZEILEN]]` – Anzahl der Stolpersteine

**Person (Zeilenebene)**
- `[[PERSON.NAME_VOLL]]` – Nachname, Vorname (geb. Geburtsname)
- `[[PERSON.VORNAME]]`
- `[[PERSON.NACHNAME]]`
- `[[PERSON.GEBURTSNAME]]`
- `[[PERSON.GEBURTSDATUM]]` – auf Deutsch formatiert (z. B. „15. März 1910"), respektiert Genauigkeit (tag/monat/jahr)
- `[[PERSON.STERBEDATUM]]` – wie Geburtsdatum
- `[[PERSON.BIOGRAFIE_KURZ]]`
- `[[PERSON.WIKIPEDIA_NAME]]`, `[[PERSON.WIKIDATA_ID]]`

**Ort (Zeilenebene)**
- `[[ORT.ADRESSE]]` – Straße + Hausnummer + Beschreibung (kombiniert)
- `[[ORT.STRASSE]]`, `[[ORT.HAUSNUMMER]]`, `[[ORT.STRASSE_WIKIPEDIA]]`
- `[[ORT.STADTTEIL]]`, `[[ORT.PLZ]]`
- `[[ORT.BESCHREIBUNG]]`, `[[ORT.BEMERKUNG_HISTORISCH]]`

**Stein (Zeilenebene)**
- `[[STEIN.INSCHRIFT_BR]]` – Inschrift Großschrift, Zeilenumbrüche als `<br />`
- `[[STEIN.INSCHRIFT]]` – Inschrift Großschrift
- `[[STEIN.VERLEGEDATUM]]` – Format DD.MM.YYYY
- `[[STEIN.LAT]]`, `[[STEIN.LON]]`
- `[[STEIN.WIKIMEDIA_COMMONS]]`, `[[STEIN.FOTO_AUTOR]]`, `[[STEIN.FOTO_LIZENZ]]`, `[[STEIN.FOTO_LIZENZ_URL]]`
- `[[STEIN.WIKIDATA_ID]]`, `[[STEIN.OSM_ID]]`, `[[STEIN.STATUS]]`, `[[STEIN.ZUSTAND]]`

---

## 6. Technische Architektur

### 6.1 Schichtenmodell
- **Repository-Schicht**: Datenzugriff (PDO, prepared statements)
- **Service-Schicht**: Import, Sync, Export, Suche
- **REST-API**: `/api/personen`, `/api/stolpersteine`, `/api/verlegeorte`, `/api/dokumente`, `/api/suche`, `/api/export`, `/api/konfiguration`
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
- Logging: Monolog mit `RotatingFileHandler` (7 Tage), Log-Pfad per `app.log_dir` in config.php konfigurierbar
- Deployment: `storage/` liegt eine Ebene über `api/` auf dem Server (`stst/storage/`), wird nie per rsync gelöscht; `vendor/` und `src/` liegen direkt unter `api/`

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
- URL-Prüfung: `GET /dokumente/url-pruefung` (Batch-Status aller Dokument-URLs), `POST /dokumente/url-check`, `POST /dokumente/url-info`
- PDF-Spiegelung: `POST /dokumente/{id}/spiegel` (lokal cachen), `GET /dokumente/{id}/spiegel` (Download)
- Biografie-Zuweisung: `POST /dokumente/{id}/biografie` – verknüpft Dokument als Biografie einer Person

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
- PhpSpreadsheet `RichText`-Objekte werden per `getPlainText()` entladen
- HTML-Tags werden aus Freitextfeldern (`biografie_kurz`, `bemerkung_historisch`, `beschreibung`) entfernt
- Wikidata-IDs für Straße und Stadtteil werden beim Import direkt in die normalisierten Tabellen geschrieben

### ✅ Phase 5: Wikipedia-Export & Abgleich
- `stadtteile.wikipedia_name` aufgeteilt in `wikipedia_stadtteil` (Stadtteil-Artikel) und `wikipedia_stolpersteine` (Stolpersteinliste für Export/Diff)
- Template-System: Tabelle `templates` mit Versionierung (neue Version nur bei Inhaltsänderung, ältere Versionen werden deaktiviert)
- Zwei Templates je Format: `name="seite"` (Seitenrahmen) + `name="zeile"` (eine Tabellenzeile)
- Vollständiger Platzhalter-Satz für Person, Ort, Stein und Seitenkontext
- `ExportService::wikipedia()` – generiert vollständigen Wikitext per `strtr()` mit Platzhaltern
- `ExportService::wikipediaDiff()` – lädt Live-Wikitext per MediaWiki Action-API (`action=query&prop=revisions`)
- `ExportHandler` / `TemplateHandler` – `/api/export/wikipedia`, `/api/export/wikipedia/diff`, `/api/templates`
- Frontend Export-Seite: Kategorie-Tabs (Wikipedia / OSM / Wikidata), Wikipedia Sub-Tabs (Export / Templates)
- Export-Tab: Stadtteil-Auswahl, zwei Textfelder nebeneinander (lokal ↔ live), zeilenweiser Diff mit Zeichen-Hervorhebung (jsdiff)
- Template-Editor: Platzhalter-Sidebar zum Anklicken (mit Undo-Unterstützung via execCommand), Versionsnummer-Anzeige
- Alle Export- und Template-Endpunkte nur für Admins zugänglich

### ✅ Phase 5b: OSM-Export
- `ExportService::osmDiff()` – vergleicht interne Daten mit OSM-Ist-Stand
- `ExportService::osmDatei()` – erzeugt OSM-kompatible Exportdatei
- `GET /export/osm/diff` und `GET /export/osm/datei` (nur Admin)
- Frontend Export-Seite: OSM-Tab mit Diff-Ansicht und Download

### Phase 6: Externe Validierung (Wikidata/OSM)
*(Vorgesehen, noch nicht implementiert)*
- Wikidata/OSM-Checks (Syntax, Existenz, Semantik)
- Speicherung der Ergebnisse in `validierungen`

### ✅ Phase 7: Frontend
- **Alpine.js** (kein Build-Schritt, CDN), **Pico CSS** für Basis-Styling
- Hash-basiertes Routing via `Alpine.store('router')`
- `js/api.js` als zentraler HTTP-Client (fetch-basiert, Cookie-Auth)
- `js/app.js` mit Stores: `auth`, `notify`, `router`, `config` (lädt Stadtkonfiguration nach Login)
- Jede Seite ist eine eigene `Alpine.data()`-Komponente in `js/pages/`

Implementiert:
- ✅ Login-Seite + Session-Handling
- ✅ Dashboard mit Statistiken
- ✅ Personen-Verwaltung (Liste mit Name-Filter, Modal Anlegen/Bearbeiten, Lösch-Bestätigung, `wikipedia_name`)
- ✅ Verlegeorte-Verwaltung:
  - Normalisierte Adress-Eingabe mit Autocomplete-Lookup
  - Inline-Formular „Neue Adresse" (`POST /adressen/lokationen`)
  - Anzeige gewählter Adresse mit Wikidata-Buttons
  - Leaflet-Karte zur Koordinateneingabe
  - Grid-Konfiguration (Rasterbreite/-höhe + Beschreibung)
- ✅ Stolpersteine-Verwaltung:
  - Namenssuche (Vorname/Nachname/Geburtsname), Straße, Stadtteil, Status, Zustand, Foto-Filter
  - Verlegedatum einheitlich als DD.MM.YYYY formatiert
  - Personen-Lookup mit `Vorname Nachname (geb. Geburtsname)` + Geburtsdatum
  - Verlegeort-Lookup mit Beschreibung
  - Visueller Grid-Picker mit Anzeige belegter Positionen
  - Foto-Upload (lokal) und Wikimedia-Commons-Import
  - SHA1-Vergleich lokal ↔ Commons mit Lizenzanzeige
  - Koordinaten-Overrides (lat/lon) mit Leaflet-Karte
  - Einfüge-Handler: entfernt umschließende Anführungszeichen in Inschrift-Feld
- ✅ Adress-Verwaltung (`adressen.js`):
  - Unterseiten: Städte, Stadtteile, Straßen, PLZ, Lokationen
  - Vollständiges CRUD für alle Adressentitäten
  - Straßen mit `wikipedia_name` und `wikidata_id`
  - Stadtteile mit `wikidata_id` (Magdeburg: 40 Stadtteile vorgeseedet)
- ✅ Import-Wizard:
  - Datei-Upload → Spaltenvorschau → Feld-Mapping → Dry-Run → Ausführen
  - Fortschrittsanzeige, Zeilen-Status-Tabelle
- ✅ Klickbare Tabellenzeilen in allen Listen (Klick = Bearbeiten, dezenter Hover-Effekt)
- ✅ Export-Seite: Wikipedia-Export, Template-Verwaltung, Diff-Ansicht (nur Admin)

- ✅ Dokument-Verwaltung: Upload, URL-Dokumente, Spiegelung, Biografie-Zuweisung, URL-Prüfung

Ausstehend: Suche, Benutzerverwaltung

### ✅ Phase 8: Öffentliche Website

- Neue Backend-Endpunkte `/public/*` (kein Auth, nur `freigegeben`)
  - `GET /public/statistiken`
  - `GET /public/stolpersteine` (Liste für Karte + Suche)
  - `GET /public/stolpersteine/{id}` (Detailansicht)
  - `GET /public/suche?q=` (Volltext-Suche)
- Neues Frontend-Verzeichnis `website/` (eigenständig, kein Pico CSS)
  - Leaflet-Karte mit MarkerCluster-Unterstützung und Popup-Links
  - Paginierte Personenliste mit clientseitiger Filterung
  - Detailansicht: Foto (lokal oder Wikimedia Commons), Biografie, Dokument-Link, externe Links (Wikidata, Wikipedia, OpenStreetMap)
  - Hash-Routing: `#karte`, `#liste`, `#stein/{id}`
- Deployment-Skript `scripts/deploy.sh` (rsync über SSH)
- CORS-Konfiguration für Entwicklung (`localhost:8002`)

### Phase 9: Feinschliff & Erweiterungen
- Wikidata/OSM-Validierung (Phase 6)
- Benutzerverwaltung im Frontend
- Optimierungen und erweiterte Filter
