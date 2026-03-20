# Stolperstein-Verwaltungssystem Magdeburg

Internes Verwaltungssystem zur Pflege von Personen, Stolpersteinen, Verlegeorten und Dokumenten – mit Import, Volltextsuche und Export für Wikipedia, OSM und Wikidata.

Entwickelt für Magdeburg, konfigurierbar für andere Städte.

---

## Voraussetzungen

- PHP 8.1+
- MariaDB / MySQL 8+
- Composer
- Node.js (optional, für Bruno CLI)

## PHP-Abhängigkeiten

Die folgenden Pakete werden über [Composer](https://getcomposer.org/) installiert (`composer install` im Verzeichnis `backend/`):

| Paket | Version | Zweck |
|---|---|---|
| `phpoffice/phpspreadsheet` | ^5.5 | Excel/CSV-Import (`.xlsx`, `.csv`) |
| `monolog/monolog` | ^3.0 | Strukturiertes Logging (rotierende Log-Dateien) |

---

## Installation

```bash
# 1. Abhängigkeiten installieren
cd backend
composer install

# 2. Konfiguration anlegen
cp config.example.php config.php
# config.php bearbeiten: DB-Zugangsdaten, log_dir, upload_dir, debug-Flag

# 3. Datenbank einrichten
mysql -u root -p stolpersteine < db/schema.sql

# 4. Entwicklungsserver starten
cd ..
./dev.sh
```

---

## Projektstruktur

```
Stolpersteine/
├── backend/         PHP-REST-API
│   ├── public/      Document Root (php -S ... -t public/)
│   ├── src/         Quellcode (PSR-4, Namespace Stolpersteine\)
│   ├── storage/     Dateispeicher (nicht web-zugänglich)
│   │   ├── logs/    App-Logs + PHP-Fehlerlog (nicht im Git)
│   │   └── spiegel/ Gespiegelte PDFs (nicht im Git)
│   ├── db/          schema.sql + migrations/
│   ├── API.md       Vollständige Endpunkt-Dokumentation
│   └── config.php   Nicht im Git (siehe config.example.php)
├── frontend/        Verwaltungsoberfläche (Alpine.js + Pico CSS, kein Build-Schritt)
│   ├── index.html   App-Shell (Login, Navigation, Router-Outlet)
│   ├── css/app.css
│   └── js/          Stores, API-Client, Seiten-Komponenten
├── website/         Öffentliche Website (Alpine.js + Leaflet, kein Build-Schritt)
│   ├── index.html   SPA (Karte, Personenliste, Detailansicht)
│   ├── css/app.css
│   └── js/          Router-Store, API-Client, Seiten-Komponenten
├── bruno/           API-Collection für Bruno
├── scripts/         Hilfsskripte (deploy.sh)
└── uploads/         Hochgeladene Dateien (nicht im Git)
```

---

## API

Basis-URL: `http://localhost:8080/api`

Vollständige Dokumentation aller Endpunkte: [backend/API.md](backend/API.md)

**Verwaltungs-Endpunkte** (erfordern Login):

| Ressource | Endpunkte |
|---|---|
| Auth | `POST /auth/login`, `POST /auth/logout`, `GET /auth/me` |
| Personen | `GET/POST /personen`, `GET/PUT/DELETE /personen/{id}` |
| Verlegeorte | `GET/POST /verlegeorte`, `GET/PUT/DELETE /verlegeorte/{id}` |
| Adressen (Lookup) | `GET /adressen/strassen`, `GET /adressen/stadtteile`, `POST /adressen/lokationen` |
| Adressen (CRUD) | `GET/POST /adressen/staedte`, `…/alle-stadtteile`, `…/alle-strassen`, `…/alle-plz`, `…/alle-lokationen` |
| Stolpersteine | `GET/POST /stolpersteine`, `GET/PUT/DELETE /stolpersteine/{id}` |
| Fotos | `POST /stolpersteine/{id}/foto/upload`, `POST /stolpersteine/{id}/foto/commons-import`, `DELETE /stolpersteine/{id}/foto`, `GET /stolpersteine/{id}/foto/vergleich` |
| Dokumente | `GET/POST /dokumente`, `GET/PUT/DELETE /dokumente/{id}`, `GET /dokumente/url-pruefung`, `POST /dokumente/url-check`, `POST /dokumente/url-info`, `GET/POST /dokumente/{id}/spiegel`, `POST /dokumente/{id}/biografie` |
| Suche | `GET /suche` |
| Import | `POST /import/analyze`, `POST /import/preview`, `POST /import/execute` |
| Export | `GET /export/wikipedia`, `GET /export/wikipedia/diff`, `GET /export/osm/diff`, `GET /export/osm/datei`, `GET /export/{format}` |
| Templates | `GET /templates`, `GET/PUT /templates/{id}` |
| Konfiguration | `GET /konfiguration` |

**Öffentliche Endpunkte** (kein Login, nur `status = freigegeben`):

| Ressource | Endpunkte |
|---|---|
| Statistiken | `GET /public/statistiken` |
| Stolpersteine | `GET /public/stolpersteine`, `GET /public/stolpersteine/{id}` |
| Suche | `GET /public/suche?q=` |

---

## API testen mit Bruno

Die Collection unter `bruno/` enthält fertige Requests für alle Endpunkte.

**Voraussetzung:** [Bruno](https://www.usebruno.com/) installieren (kostenlos, open-source).

**Einrichten:**

1. Bruno öffnen → *Open Collection* → Ordner `bruno/` auswählen
2. Oben rechts Environment `local` aktivieren
3. *Preferences → Enable Cookie Jar* aktivieren (für Session-Handling)

**Testen:**

```
auth/login              → einloggen
personen/erstellen      → personId wird automatisch gesetzt
verlegeorte/erstellen   → verlegeortId wird gesetzt
stolpersteine/erstellen → nutzt beide IDs
suche/volltext          → Suche testen
...
auth/logout
```

**CLI (automatisierter Testlauf):**

```bash
npm install -g @usebruno/cli
bru run bruno/ --env local --recursive
```

---

## Rollen

| Aktion | editor | admin |
|---|---|---|
| Lesen | ✅ | ✅ |
| Erstellen / Aktualisieren | ✅ | ✅ |
| Löschen (Personen, Orte, Steine) | ❌ | ✅ |
| Löschen (Dokumente) | ✅ | ✅ |
| Export & Templates | ❌ | ✅ |

---

## Deployment

Für Shared Hosting mit SSH-Zugang:

```bash
# 1. SSH-Host in scripts/deploy.sh eintragen
# 2. Trockenübung
bash scripts/deploy.sh --dry-run
# 3. Deployen
bash scripts/deploy.sh
```

**Verzeichnisstruktur beim Hoster:**

```
stst/
├── storage/          ← Persistenter Speicher (nie per rsync gelöscht)
│   ├── logs/         ← PHP-Fehlerlog (php.log) + App-Logs (app-*.log)
│   └── spiegel/      ← Gespiegelte PDFs (extern verlinkter Bestand)
├── api/              ← PHP-Backend
│   ├── index.php
│   ├── .htaccess
│   ├── src/
│   ├── vendor/
│   └── config.php    ← Produktionskonfiguration (nicht deployt)
├── verwaltung/       ← Verwaltungsoberfläche (frontend/)
└── (root)            ← Öffentliche Website (website/)
```

**Einmalig auf dem Server anlegen** (nicht per Skript deployt):
- `stst/api/config.php` – Produktionskonfiguration (DB, `log_dir`, kein Debug)
- `stst/api/uploads/` – Verzeichnis für Foto-Uploads
- `stst/storage/logs/` – wird automatisch per SSH-Schritt im Deploy angelegt

**Wichtige config.php-Einträge für Produktion:**
```php
'log_dir' => '/usr/www/users/.../stst/storage/logs',
'spiegel_dir' => '/usr/www/users/.../stst/storage/spiegel',
```

---

## Entwicklungsstand

Siehe [projekt_solptersteine.md](projekt_solptersteine.md) für die vollständige Roadmap.

- ✅ Phase 1 – Fundament (Auth, CRUD, Audit-Log)
- ✅ Phase 2 – Dateien & Dokumente (inkl. externe Dokumente, URL-Prüfung, PDF-Spiegelung)
- ✅ Phase 3 – Volltextsuche & Filter
- ✅ Phase 4 – Excel/CSV-Import (inkl. RichText, HTML-Stripping, vollständiges Feld-Mapping)
- ✅ Phase 5 – Wikipedia-Export & Abgleich (Templates, Wikitext-Generierung, Live-Diff, Zeichen-Hervorhebung)
- ✅ Phase 5b – OSM-Export (Diff-Ansicht, Datei-Download)
- ⬜ Phase 6 – Externe Validierung (Wikidata/OSM)
- ✅ Phase 7 – Frontend (Verwaltungsoberfläche vollständig)
- ✅ Phase 8 – Öffentliche Website (Karte, Personenliste, Detailansicht)
