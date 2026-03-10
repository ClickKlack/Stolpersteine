# Stolperstein-Verwaltungssystem Magdeburg

Internes Verwaltungssystem zur Pflege von Personen, Stolpersteinen, Verlegeorten und Dokumenten – mit Import, Volltextsuche und Export für Wikipedia, OSM und Wikidata.

Entwickelt für Magdeburg, konfigurierbar für andere Städte.

---

## Voraussetzungen

- PHP 8.1+
- MariaDB / MySQL 8+
- Composer
- Node.js (optional, für Bruno CLI)

---

## Installation

```bash
# 1. Abhängigkeiten installieren
cd backend
composer install

# 2. Konfiguration anlegen
cp config.example.php config.php
# config.php bearbeiten: DB-Zugangsdaten, upload_dir, debug-Flag

# 3. Datenbank einrichten
# schema.sql in MariaDB importieren
mysql -u root -p stolpersteine < db/schema.sql

# 4. Entwicklungsserver starten
php -S localhost:8080 -t public/
```

---

## Projektstruktur

```
Stolpersteine/
├── backend/         PHP-REST-API
│   ├── public/      Document Root (php -S ... -t public/)
│   ├── src/         Quellcode (PSR-4, Namespace Stolpersteine\)
│   ├── db/          schema.sql
│   ├── API.md       Vollständige Endpunkt-Dokumentation
│   └── config.php   Nicht im Git (siehe config.example.php)
├── frontend/        Alpine.js + Pico CSS (kein Build-Schritt)
│   ├── index.html   App-Shell (Login, Navigation, Router-Outlet)
│   ├── css/app.css
│   └── js/          Stores, API-Client, Seiten-Komponenten
├── bruno/           API-Collection für Bruno
└── uploads/         Hochgeladene Dateien (nicht im Git)
```

---

## API

Basis-URL: `http://localhost:8080/api`

Vollständige Dokumentation aller Endpunkte: [backend/API.md](backend/API.md)

**Übersicht:**

| Ressource | Endpunkte |
|---|---|
| Auth | `POST /auth/login`, `POST /auth/logout`, `GET /auth/me` |
| Personen | `GET/POST /personen`, `GET/PUT/DELETE /personen/{id}` |
| Verlegeorte | `GET/POST /verlegeorte`, `GET/PUT/DELETE /verlegeorte/{id}` |
| Adressen | `GET /adressen/strassen`, `POST /adressen/lokationen` |
| Stolpersteine | `GET/POST /stolpersteine`, `GET/PUT/DELETE /stolpersteine/{id}` |
| Dokumente | `GET/POST /dokumente`, `GET/DELETE /dokumente/{id}` |
| Suche | `GET /suche` |
| Import | `POST /import/analyze`, `POST /import/preview`, `POST /import/execute` |

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

---

## Entwicklungsstand

Siehe [projekt_solptersteine.md](projekt_solptersteine.md) für die vollständige Roadmap.

- ✅ Phase 1 – Fundament (Auth, CRUD, Audit-Log)
- ✅ Phase 2 – Dateien & Dokumente
- ✅ Phase 3 – Volltextsuche & Filter
- ✅ Phase 4 – Excel/CSV-Import
- ⬜ Phase 5 – Templates & Exporte
- ⬜ Phase 6 – Externe Validierung (Wikidata/OSM)
- 🔄 Phase 7 – Frontend (Personen ✅, Verlegeorte ✅, Adress-Normalisierung ✅)
