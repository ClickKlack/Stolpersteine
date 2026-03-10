# API-Dokumentation

**Base URL:** `/api`
**Format:** Alle Anfragen und Antworten sind JSON, außer bei Datei-Uploads (multipart/form-data).
**Auth:** Session-Cookie (`stolpersteine_sess`) nach erfolgreichem Login.

---

## Antwortformat

Alle Endpunkte antworten einheitlich:

```json
{ "success": true, "data": { ... } }
{ "success": false, "error": "Fehlermeldung.", "details": { ... } }
```

| HTTP-Code | Bedeutung |
|---|---|
| `200` | OK |
| `201` | Ressource erstellt |
| `204` | Erfolg, kein Body |
| `400` | Ungültige Anfrage (z. B. kein gültiger JSON-Body) |
| `401` | Nicht eingeloggt |
| `403` | Keine Berechtigung (nur Admin) |
| `404` | Nicht gefunden |
| `409` | Konflikt (z. B. Duplikat) |
| `422` | Validierungsfehler |
| `500` | Interner Fehler |

---

## Auth

### `POST /api/auth/login`
Einloggen und Session starten.

**Body (JSON):**
```json
{ "benutzername": "admin", "passwort": "geheim" }
```

**Antwort `200`:**
```json
{ "success": true, "data": { "benutzername": "admin", "rolle": "admin" } }
```

**Fehler:**
- `422` – Benutzername oder Passwort fehlt
- `401` – Ungültige Anmeldedaten

---

### `POST /api/auth/logout`
Session beenden.

**Antwort:** `204 No Content`

---

### `GET /api/auth/me`
Gibt den aktuell eingeloggten Benutzer zurück.

**Antwort `200`:**
```json
{ "success": true, "data": { "benutzername": "admin", "rolle": "admin" } }
```

**Fehler:** `401` – nicht eingeloggt

---

## Personen

### `GET /api/personen`
Liste aller Personen. Optional filterbar.

**Query-Parameter:**

| Parameter | Beschreibung |
|---|---|
| `nachname` | Teilsuche (LIKE) |
| `geburtsname` | Teilsuche (LIKE) |
| `geburtsjahr` | Exakt (Jahr als Zahl) |

**Antwort `200`:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "vorname": "Anna",
      "nachname": "Schmidt",
      "geburtsname": null,
      "geburtsdatum": "1898-05-12",
      "sterbedatum": "1942-03-15",
      "biografie_kurz": null,
      "wikidata_id_person": null,
      "erstellt_am": "2025-01-01 10:00:00",
      "geaendert_am": "2025-01-01 10:00:00"
    }
  ]
}
```

---

### `POST /api/personen`
Neue Person anlegen. Erfordert Login.

**Body (JSON):**
```json
{
  "nachname": "Schmidt",
  "vorname": "Anna",
  "geburtsname": null,
  "geburtsdatum": "1898-05-12",
  "sterbedatum": "1942-03-15",
  "biografie_kurz": "Kurze Biografie.",
  "wikidata_id_person": "Q12345"
}
```

**Pflichtfelder:** `nachname`

**Antwort `201`:** vollständiger Datensatz

**Fehler:** `422` – Nachname fehlt

---

### `GET /api/personen/{id}`
Eine Person per ID abrufen.

**Antwort `200`:** vollständiger Datensatz inkl. `erstellt_von`, `geaendert_von`

**Fehler:** `404` – nicht gefunden

---

### `PUT /api/personen/{id}`
Person aktualisieren. Erfordert Login.

**Body:** wie `POST`, alle Felder werden ersetzt.

**Antwort `200`:** aktualisierter Datensatz

---

### `DELETE /api/personen/{id}`
Person löschen. Erfordert **Admin**.

**Antwort:** `204 No Content`

**Fehler:** `409` – Person hat zugeordnete Stolpersteine

---

## Verlegeorte

### `GET /api/verlegeorte`
Liste aller Verlegeorte. Optional filterbar.

**Query-Parameter:**

| Parameter | Beschreibung |
|---|---|
| `stadtteil` | Teilsuche (LIKE) |
| `strasse` | Teilsuche (LIKE) |
| `plz` | Exakt |

**Antwort `200`:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "beschreibung": null,
      "lat": 52.12345678,
      "lon": 11.12345678,
      "stadtteil": "Altstadt",
      "strasse_aktuell": "Hegelstraße",
      "hausnummer_aktuell": "42",
      "plz_aktuell": "39104",
      "grid_n": null,
      "grid_m": null,
      "wikidata_id_strasse": null,
      "erstellt_am": "2025-01-01 10:00:00",
      "geaendert_am": "2025-01-01 10:00:00"
    }
  ]
}
```

---

### `POST /api/verlegeorte`
Neuen Verlegeort anlegen. Erfordert Login.

**Body (JSON):**
```json
{
  "strasse_aktuell": "Hegelstraße",
  "hausnummer_aktuell": "42",
  "stadtteil": "Altstadt",
  "plz_aktuell": "39104",
  "lat": 52.12345678,
  "lon": 11.12345678,
  "beschreibung": null,
  "bemerkung_historisch": null,
  "adresse_alt": null,
  "wikidata_id_strasse": null,
  "grid_n": null,
  "grid_m": null,
  "raster_beschreibung": null
}
```

**Antwort `201`:** vollständiger Datensatz

---

### `GET /api/verlegeorte/{id}`
Einen Verlegeort per ID abrufen.

**Antwort `200`:** vollständiger Datensatz inkl. `adresse_alt` (JSON), `raster_beschreibung`, `erstellt_von`, `geaendert_von`

**Fehler:** `404` – nicht gefunden

---

### `PUT /api/verlegeorte/{id}`
Verlegeort aktualisieren. Erfordert Login.

**Antwort `200`:** aktualisierter Datensatz

---

### `DELETE /api/verlegeorte/{id}`
Verlegeort löschen. Erfordert **Admin**.

**Antwort:** `204 No Content`

**Fehler:** `409` – Verlegeort hat zugeordnete Stolpersteine

---

## Stolpersteine

### `GET /api/stolpersteine`
Liste aller Stolpersteine. Optional filterbar.

**Query-Parameter:**

| Parameter | Beschreibung |
|---|---|
| `status` | Exakt: `neu`, `validierung`, `freigegeben`, `archiviert`, `fehlerhaft`, `abgleich_wikipedia`, `abgleich_osm`, `abgleich_wikidata` |
| `zustand` | Exakt: `verfuegbar`, `stein_fehlend`, `kein_stein`, `beschaedigt`, `unleserlich` |
| `stadtteil` | Teilsuche (LIKE) |
| `strasse` | Teilsuche (LIKE) |
| `person_id` | Exakt |
| `ohne_wikidata` | `1` = nur Steine ohne Wikidata-ID |

**Antwort `200`:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "person_id": 1,
      "verlegeort_id": 1,
      "vorname": "Anna",
      "nachname": "Schmidt",
      "strasse_aktuell": "Hegelstraße",
      "hausnummer_aktuell": "42",
      "stadtteil": "Altstadt",
      "verlegedatum": "2005-11-10",
      "status": "freigegeben",
      "zustand": "verfuegbar",
      "wikidata_id_stein": null,
      "osm_id": null,
      "erstellt_am": "2025-01-01 10:00:00",
      "geaendert_am": "2025-01-01 10:00:00"
    }
  ]
}
```

---

### `POST /api/stolpersteine`
Neuen Stolperstein anlegen. Erfordert Login.

**Body (JSON):**
```json
{
  "person_id": 1,
  "verlegeort_id": 1,
  "verlegedatum": "2005-11-10",
  "inschrift": "HIER WOHNTE ...",
  "status": "neu",
  "zustand": "verfuegbar",
  "pos_x": null,
  "pos_y": null,
  "lat_override": null,
  "lon_override": null,
  "wikidata_id_stein": null,
  "osm_id": null
}
```

**Pflichtfelder:** `person_id`, `verlegeort_id`

**Antwort `201`:** vollständiger Datensatz. Suchindex wird automatisch aufgebaut.

---

### `GET /api/stolpersteine/{id}`
Einen Stolperstein per ID abrufen.

**Antwort `200`:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "person_id": 1,
    "verlegeort_id": 1,
    "vorname": "Anna",
    "nachname": "Schmidt",
    "geburtsdatum": "1898-05-12",
    "sterbedatum": "1942-03-15",
    "strasse_aktuell": "Hegelstraße",
    "hausnummer_aktuell": "42",
    "stadtteil": "Altstadt",
    "lat": 52.12345678,
    "lon": 11.12345678,
    "verlegedatum": "2005-11-10",
    "inschrift": null,
    "status": "freigegeben",
    "zustand": "verfuegbar",
    "pos_x": null,
    "pos_y": null,
    "lat_override": null,
    "lon_override": null,
    "wikidata_id_stein": null,
    "osm_id": null,
    "suchindex_aktualisiert_am": "2025-01-01 10:05:00",
    "erstellt_am": "2025-01-01 10:00:00",
    "erstellt_von": "admin",
    "geaendert_am": "2025-01-01 10:00:00",
    "geaendert_von": "admin"
  }
}
```

> `suchindex_aktualisiert_am` ist `null`, wenn kein Suchindexeintrag existiert.

---

### `PUT /api/stolpersteine/{id}`
Stolperstein aktualisieren. Erfordert Login.

**Antwort `200`:** aktualisierter Datensatz. Suchindex wird automatisch aktualisiert.

---

### `DELETE /api/stolpersteine/{id}`
Stolperstein löschen. Erfordert **Admin**.

**Antwort:** `204 No Content`

---

## Dokumente

### `GET /api/dokumente`
Liste der Dokumente. Optional filterbar.

**Query-Parameter:**

| Parameter | Beschreibung |
|---|---|
| `person_id` | Exakt |
| `stolperstein_id` | Exakt |
| `typ` | Exakt: `foto`, `pdf`, `scan`, `url`, `sonstig` |

**Antwort `200`:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "person_id": 1,
      "stolperstein_id": null,
      "titel": "Deportationsliste",
      "beschreibung_kurz": null,
      "typ": "pdf",
      "dateiname": "abc12345_deportation.pdf",
      "dateipfad": "2025/01/abc12345_deportation.pdf",
      "quelle_url": null,
      "groesse_bytes": 204800,
      "erstellt_am": "2025-01-01 10:00:00",
      "erstellt_von": "admin"
    }
  ]
}
```

---

### `POST /api/dokumente`
Neues Dokument anlegen. Erfordert Login. Zwei Varianten:

**Variante A – Datei-Upload (multipart/form-data):**

| Feld | Beschreibung |
|---|---|
| `datei` | Datei (JPEG, PNG, WEBP, TIFF, PDF; max. 20 MB) |
| `titel` | Pflicht |
| `beschreibung_kurz` | Optional |
| `person_id` | Optional |
| `stolperstein_id` | Optional |

**Variante B – URL-Dokument (JSON):**
```json
{
  "titel": "Wikipedia-Artikel",
  "quelle_url": "https://de.wikipedia.org/...",
  "typ": "url",
  "beschreibung_kurz": null,
  "person_id": 1,
  "stolperstein_id": null
}
```

**Antwort `201`:** vollständiger Datensatz

**Fehler:**
- `409` – Datei bereits vorhanden (SHA-256-Duplikat), gibt `vorhandenes_dokument_id` zurück
- `422` – Titel fehlt oder weder Datei noch URL

---

### `GET /api/dokumente/{id}`
Ein Dokument per ID abrufen.

**Antwort `200`:** vollständiger Datensatz inkl. `hash`, `erstellt_von`, `geaendert_von`

---

### `DELETE /api/dokumente/{id}`
Dokument löschen. Erfordert Login. Datei wird vom Dateisystem entfernt.

**Antwort:** `204 No Content`

---

## Suche

### `GET /api/suche`
Volltext- und Filtersuche über Stolpersteine. Erfordert Login.

Mindestens ein Parameter muss angegeben sein.

**Query-Parameter:**

| Parameter | Beschreibung |
|---|---|
| `q` | Volltext-Suchbegriff (BOOLEAN MODE) |
| `status` | Filter: Status-Wert |
| `zustand` | Filter: Zustand-Wert |
| `stadtteil` | Filter: Teilsuche |
| `strasse` | Filter: Teilsuche |
| `ohne_wikidata` | `1` = nur Steine ohne Wikidata-ID |

Volltext und Filter können kombiniert werden. Bei Volltextsuche wird nach Relevanz sortiert, sonst nach Stadtteil + Nachname.

**Antwort `200`:**
```json
{
  "success": true,
  "data": {
    "treffer": 2,
    "daten": [
      {
        "id": 1,
        "status": "freigegeben",
        "zustand": "verfuegbar",
        "verlegedatum": "2005-11-10",
        "wikidata_id_stein": null,
        "osm_id": null,
        "vorname": "Anna",
        "nachname": "Schmidt",
        "strasse_aktuell": "Hegelstraße",
        "hausnummer_aktuell": "42",
        "stadtteil": "Altstadt",
        "relevanz": 0.91
      }
    ]
  }
}
```

> `relevanz` ist nur bei Volltextsuche (`q`) vorhanden. Max. 200 Treffer.

**Fehler:** `422` – kein Suchbegriff und kein Filter angegeben

---

## Import

### `POST /api/import/analyze`
Lädt eine Datei hoch und gibt eine Spaltenvorschau zurück, um das Mapping-UI zu befüllen. Erfordert Login.

**Request (multipart/form-data):**

| Feld | Beschreibung |
|---|---|
| `datei` | Excel-Datei (XLSX, XLS, ODS, CSV) |

**Antwort `200`:**
```json
{
  "success": true,
  "data": {
    "zeilenanzahl": 150,
    "spaltenanzahl": 9,
    "vorschau": [
      { "zeile": 1, "spalten": { "A": "Nachname", "B": "Vorname", "C": "Geburtsdatum" } },
      { "zeile": 2, "spalten": { "A": "Schmidt",  "B": "Anna",   "C": "1898-05-12"   } }
    ],
    "felder": {
      "person":     ["nachname", "vorname", "geburtsname", "geburtsdatum", "sterbedatum", "biografie_kurz", "wikidata_id_person"],
      "verlegeort": ["strasse_aktuell", "hausnummer_aktuell", "stadtteil", "plz_aktuell", "lat", "lon", "bemerkung_historisch", "grid_n", "grid_m"],
      "stein":      ["verlegedatum", "inschrift", "wikidata_id_stein", "osm_id", "pos_x", "pos_y", "status", "zustand"]
    }
  }
}
```

---

### `POST /api/import/preview`
Dry-Run: analysiert alle Zeilen ohne DB-Schreibzugriffe. Erfordert Login.

**Request (multipart/form-data):**

| Feld | Beschreibung |
|---|---|
| `datei` | Excel-Datei (XLSX, XLS, ODS, CSV) |
| `mapping` | JSON-String: Feldname → Spaltenbuchstabe |
| `startzeile` | Erste Datenzeile, Standard: `2` (Zeile 1 = Kopfzeile) |

**Mapping-Beispiel:**
```json
{
  "nachname":          "A",
  "vorname":           "B",
  "geburtsdatum":      "C",
  "sterbedatum":       "D",
  "strasse_aktuell":   "E",
  "hausnummer_aktuell":"F",
  "stadtteil":         "G",
  "plz_aktuell":       "H",
  "verlegedatum":      "I"
}
```

**Antwort `200`:**
```json
{
  "success": true,
  "data": {
    "gesamt": 50,
    "neue_personen": 10,
    "neue_verlegeorte": 8,
    "neue_steine": 50,
    "fehler": 2,
    "zeilen": [
      {
        "zeile": 2,
        "status": "ok",
        "person_status": "neu",
        "ort_status": "neu",
        "person":     { "nachname": "Schmidt", "vorname": "Anna", ... },
        "verlegeort": { "strasse_aktuell": "Hegelstraße", ... },
        "stein":      { "verlegedatum": "2005-11-10", ... },
        "meldungen":  []
      },
      {
        "zeile": 3,
        "status": "ok",
        "person_status": "neu",
        "ort_status": "neu_in_import",
        "meldungen": []
      },
      {
        "zeile": 7,
        "status": "fehler",
        "meldungen": ["Straße (strasse_aktuell) fehlt."]
      }
    ]
  }
}
```

**`person_status` / `ort_status`-Werte:**

| Wert | Bedeutung |
|---|---|
| `neu` | Wird neu angelegt |
| `vorhanden` | Existiert bereits in der DB (wird wiederverwendet) |
| `neu_in_import` | Wird neu angelegt, aber Adresse/Name taucht in dieser Datei mehrfach auf |

---

### `POST /api/import/execute`
Führt den Import in einer DB-Transaktion durch. Erfordert Login.

**Request:** identisch mit `/import/preview`

**Antwort `200`:**
```json
{
  "success": true,
  "data": {
    "gesamt": 50,
    "neue_personen": 10,
    "neue_verlegeorte": 8,
    "neue_steine": 50,
    "fehler": 2,
    "zeilen": [
      {
        "zeile": 2,
        "status": "importiert",
        "person_id": 12,
        "verlegeort_id": 5,
        "stolperstein_id": 42,
        "meldungen": []
      },
      {
        "zeile": 7,
        "status": "fehler",
        "meldungen": ["Straße (strasse_aktuell) fehlt."]
      }
    ]
  }
}
```

Bei einem Datenbankfehler wird die gesamte Transaktion zurückgerollt. Fehlerzeilen werden übersprungen (kein Rollback). Nach dem Import wird der Suchindex für jeden neuen Stein automatisch aufgebaut.

---

## Rollen & Berechtigungen

| Endpunkt | editor | admin |
|---|---|---|
| `GET` alle Ressourcen | ✅ | ✅ |
| `POST` (anlegen) | ✅ | ✅ |
| `PUT` (aktualisieren) | ✅ | ✅ |
| `DELETE` Personen/Verlegeorte/Stolpersteine | ❌ | ✅ |
| `DELETE` Dokumente | ✅ | ✅ |
| Import | ✅ | ✅ |
