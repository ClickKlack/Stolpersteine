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
| `name` | Teilsuche (LIKE) in Vorname, Nachname und Geburtsname (OR) |
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
      "wikipedia_name": null,
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
  "geburtsdatum_genauigkeit": "tag",
  "sterbedatum": "1942-03-15",
  "sterbedatum_genauigkeit": "tag",
  "biografie_kurz": "Kurze Biografie.",
  "wikipedia_name": null,
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

Adressdaten sind normalisiert: Straße, Stadtteil, PLZ und Stadt werden in eigenen Tabellen
geführt und über eine `adress_lokation_id` referenziert. Die Antwortfelder (`strasse_aktuell`,
`stadtteil`, `plz_aktuell`, `stadt`, `wikidata_id_*`) werden per JOIN befüllt und sind
abwärtskompatibel benannt.

### `GET /api/verlegeorte`
Liste aller Verlegeorte. Optional filterbar.

**Query-Parameter:**

| Parameter | Beschreibung |
|---|---|
| `stadtteil` | Teilsuche (LIKE) auf `stadtteile.name` |
| `strasse` | Teilsuche (LIKE) auf `strassen.name` |
| `plz` | Exakt auf `plz.plz` |

**Antwort `200`:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "adress_lokation_id": 3,
      "hausnummer_aktuell": "42",
      "beschreibung": null,
      "lat": 52.12345678,
      "lon": 11.12345678,
      "strasse_aktuell": "Hegelstraße",
      "wikidata_id_strasse": "Q12345",
      "stadtteil": "Altstadt",
      "wikidata_id_stadtteil": null,
      "plz_aktuell": "39104",
      "stadt": "Magdeburg",
      "wikidata_id_ort": "Q1733",
      "bemerkung_historisch": null,
      "adresse_alt": null,
      "raster_beschreibung": null,
      "grid_n": null,
      "grid_m": null,
      "erstellt_am": "2025-01-01 10:00:00",
      "geaendert_am": "2025-01-01 10:00:00"
    }
  ]
}
```

---

### `POST /api/verlegeorte`
Neuen Verlegeort anlegen. Erfordert Login.

Die Adresse wird vorab über `POST /api/adressen/lokationen` aufgelöst (find-or-create).
Die zurückgegebene `lokation_id` wird als `adress_lokation_id` übergeben.

**Body (JSON):**
```json
{
  "adress_lokation_id": 3,
  "hausnummer_aktuell": "42",
  "lat": 52.12345678,
  "lon": 11.12345678,
  "beschreibung": null,
  "bemerkung_historisch": null,
  "adresse_alt": null,
  "grid_n": null,
  "grid_m": null,
  "raster_beschreibung": null
}
```

**Antwort `201`:** vollständiger Datensatz (wie `GET /api/verlegeorte`)

---

### `GET /api/verlegeorte/{id}`
Einen Verlegeort per ID abrufen.

**Antwort `200`:** vollständiger Datensatz inkl. `adresse_alt` (JSON), `bemerkung_historisch`,
`raster_beschreibung`, `erstellt_von`, `geaendert_von`

**Fehler:** `404` – nicht gefunden

---

### `PUT /api/verlegeorte/{id}`
Verlegeort aktualisieren. Erfordert Login. Body wie `POST`.

**Antwort `200`:** aktualisierter Datensatz

---

### `DELETE /api/verlegeorte/{id}`
Verlegeort löschen. Erfordert **Admin**.

**Antwort:** `204 No Content`

**Fehler:** `409` – Verlegeort hat zugeordnete Stolpersteine

---

## Adressen (Lookup & Normalisierung)

Hilfendpunkte zur Auflösung normalisierter Adressdaten. Werden vom Frontend beim Anlegen /
Bearbeiten eines Verlegeorts genutzt.

### `GET /api/adressen/strassen?q=`
Straßen nach Name suchen (mindestens 2 Zeichen). Erfordert Login.

**Query-Parameter:**

| Parameter | Beschreibung |
|---|---|
| `q` | Suchbegriff, Teilsuche (LIKE), min. 2 Zeichen |

**Antwort `200`:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Hegelstraße",
      "wikidata_id": "Q12345",
      "stadt_id": 1,
      "stadt_name": "Magdeburg",
      "wikidata_id_ort": "Q1733",
      "lokationen": [
        {
          "id": 3,
          "strasse_id": 1,
          "stadtteil_name": "Altstadt",
          "wikidata_id_stadtteil": null,
          "plz": "39104"
        }
      ]
    }
  ]
}
```

---

### `GET /api/adressen/stadtteile?q=`
Stadtteile nach Name suchen. Erfordert Login.

**Query-Parameter:**

| Parameter | Beschreibung |
|---|---|
| `q` | Suchbegriff, Teilsuche (LIKE), min. 2 Zeichen |

**Antwort `200`:** Array von Stadtteil-Namen (Strings)

---

### `POST /api/adressen/lokationen`
Adresslokation auflösen oder anlegen (find-or-create). Erfordert Login.

Legt bei Bedarf Stadt, Straße, Stadtteil, PLZ und die Lokation an. Gibt immer eine bestehende
oder neu angelegte Lokation zurück.

**Body (JSON):**
```json
{
  "strasse_name": "Hegelstraße",
  "wikidata_id_strasse": "Q12345",
  "stadtteil_name": "Altstadt",
  "wikidata_id_stadtteil": null,
  "plz": "39104",
  "stadt_name": "Magdeburg",
  "wikidata_id_ort": "Q1733"
}
```

**Pflichtfelder:** `strasse_name`, `stadt_name`

**Antwort `200`:**
```json
{
  "success": true,
  "data": {
    "lokation_id": 3,
    "strasse_id": 1,
    "strasse_name": "Hegelstraße",
    "wikidata_id_strasse": "Q12345",
    "stadtteil_id": 2,
    "stadtteil_name": "Altstadt",
    "wikidata_id_stadtteil": null,
    "plz_id": 5,
    "plz": "39104",
    "stadt_id": 1,
    "stadt_name": "Magdeburg",
    "wikidata_id_ort": "Q1733"
  }
}
```

**Fehler:** `422` – `strasse_name` oder `stadt_name` fehlt

---

### `GET /api/adressen/staedte`
Liste aller Städte. Erfordert Login.

**Antwort `200`:** Array mit `{ id, name, wikidata_id }`

---

### `POST /api/adressen/staedte`  ·  `GET /api/adressen/staedte/{id}`  ·  `PUT /api/adressen/staedte/{id}`  ·  `DELETE /api/adressen/staedte/{id}`
CRUD für Städte. Anlegen/Ändern/Löschen erfordert **Admin**.

**Body (JSON):**
```json
{ "name": "Magdeburg", "wikidata_id": "Q1733" }
```

**Fehler DELETE:** `409` – Stadt hat verknüpfte Straßen/Stadtteile/PLZ

---

### `GET /api/adressen/alle-stadtteile?stadt_id=`
Liste aller Stadtteile, optional nach `stadt_id` gefiltert. Erfordert Login.

**Antwort `200`:** Array mit `{ id, name, wikidata_id, stadt_id, stadt_name }`

---

### `POST /api/adressen/alle-stadtteile`  ·  `GET /api/adressen/alle-stadtteile/{id}`  ·  `PUT /api/adressen/alle-stadtteile/{id}`  ·  `DELETE /api/adressen/alle-stadtteile/{id}`
CRUD für Stadtteile. Anlegen/Ändern/Löschen erfordert **Admin**.

**Body (JSON):**
```json
{
  "name": "Altstadt",
  "stadt_id": 1,
  "wikidata_id": "Q445520",
  "wikipedia_stadtteil": "Altstadt (Magdeburg)",
  "wikipedia_stolpersteine": "Liste der Stolpersteine in Magdeburg-Altstadt"
}
```

**Pflichtfelder:** `name`, `stadt_id`

---

### `GET /api/adressen/alle-strassen?stadt_id=&q=`
Liste aller Straßen. Optional nach `stadt_id` und Suchbegriff `q` filterbar. Erfordert Login.

**Antwort `200`:** Array mit `{ id, name, wikipedia_name, wikidata_id, stadt_id, stadt_name }`

---

### `POST /api/adressen/alle-strassen`  ·  `GET /api/adressen/alle-strassen/{id}`  ·  `PUT /api/adressen/alle-strassen/{id}`  ·  `DELETE /api/adressen/alle-strassen/{id}`
CRUD für Straßen. Anlegen/Ändern/Löschen erfordert **Admin**.

**Body (JSON):**
```json
{ "name": "Hegelstraße", "stadt_id": 1, "wikidata_id": "Q12345", "wikipedia_name": "Hegelstraße (Magdeburg)" }
```

**Pflichtfelder:** `name`, `stadt_id`

---

### `GET /api/adressen/alle-plz?stadt_id=`
Liste aller PLZ-Einträge, optional nach `stadt_id` gefiltert. Erfordert Login.

**Antwort `200`:** Array mit `{ id, plz, stadt_id, stadt_name }`

---

### `POST /api/adressen/alle-plz`  ·  `GET /api/adressen/alle-plz/{id}`  ·  `PUT /api/adressen/alle-plz/{id}`  ·  `DELETE /api/adressen/alle-plz/{id}`
CRUD für PLZ. Anlegen/Ändern/Löschen erfordert **Admin**.

**Body (JSON):**
```json
{ "plz": "39104", "stadt_id": 1 }
```

**Pflichtfelder:** `plz`, `stadt_id`

---

### `GET /api/adressen/alle-lokationen?strasse_id=&stadtteil_id=&plz_id=`
Liste aller Adress-Lokationen (Verknüpfung Straße + Stadtteil + PLZ). Erfordert Login.

---

### `POST /api/adressen/alle-lokationen`  ·  `PUT /api/adressen/alle-lokationen/{id}`  ·  `DELETE /api/adressen/alle-lokationen/{id}`
CRUD für Lokationen. Erfordert **Admin**.

**Body (JSON):**
```json
{ "strasse_id": 1, "stadtteil_id": 2, "plz_id": 5 }
```

**Pflichtfelder:** `strasse_id`

**Fehler DELETE:** `409` – Lokation wird von einem Verlegeort referenziert

---

## Stolpersteine

### `GET /api/stolpersteine`
Liste aller Stolpersteine. Optional filterbar.

**Query-Parameter:**

| Parameter | Beschreibung |
|---|---|
| `name` | Teilsuche (LIKE) in Vorname, Nachname und Geburtsname der verknüpften Person (OR) |
| `status` | Exakt: `neu`, `validierung`, `freigegeben`, `archiviert`, `fehlerhaft`, `abgleich_wikipedia`, `abgleich_osm`, `abgleich_wikidata` |
| `zustand` | Exakt: `verfuegbar`, `stein_fehlend`, `kein_stein`, `beschaedigt`, `unleserlich` |
| `stadtteil` | Teilsuche (LIKE) |
| `strasse` | Teilsuche (LIKE) |
| `person_id` | Exakt |
| `ohne_wikidata` | `1` = nur Steine ohne Wikidata-ID |
| `verlegeort_id` | Exakt – nur Steine an diesem Verlegeort |
| `foto_status` | `ohne_foto`, `ohne_commons`, `foto_ohne_commons` |

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
      "inschrift": "HIER WOHNTE ...",
      "foto_pfad": null,
      "wikimedia_commons": null,
      "foto_lizenz_autor": null,
      "foto_lizenz_name": null,
      "foto_lizenz_url": null,
      "foto_eigenes": 0,
      "pos_x": null,
      "pos_y": null,
      "grid_n": null,
      "grid_m": null,
      "lat_override": null,
      "lon_override": null,
      "verlegeort_lat": 52.12345678,
      "verlegeort_lon": 11.12345678,
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

## Fotos

### `GET /api/stolpersteine/{id}/foto/vergleich`
Vergleicht lokales Foto und Commons-Foto per SHA1-Hash. Gibt immer Commons-Metadaten zurück, auch ohne lokales Foto. Erfordert Login.

**Voraussetzung:** `wikimedia_commons` muss am Stolperstein gesetzt sein.

**Antwort `200`:**
```json
{
  "success": true,
  "data": {
    "identisch": true,
    "hash_lokal": "a1b2c3d4e5f6...",
    "hash_commons": "a1b2c3d4e5f6...",
    "commons_autor": "Max Mustermann",
    "commons_lizenz": "CC BY-SA 4.0",
    "commons_lizenz_url": "https://creativecommons.org/licenses/by-sa/4.0/"
  }
}
```

> `identisch` ist `null` wenn kein lokales Foto vorhanden (nur Commons-Metadaten).
> `hash_lokal` ist `null` wenn keine lokale Datei gefunden.

**Fehler:**
- `422` – kein Commons-Link gesetzt
- `404` – Commons-Datei nicht gefunden
- `502` – Wikimedia Commons API nicht erreichbar

---

### `POST /api/stolpersteine/{id}/foto/upload`
Lädt ein Foto für einen Stolperstein hoch. Erfordert Login.

**Request (multipart/form-data):**

| Feld | Beschreibung |
|---|---|
| `foto` | Bilddatei (JPEG, PNG, WEBP, TIFF; max. 20 MB) |
| `foto_eigenes` | `1` = eigenes Foto (kein Lizenzhinweis nötig), `0` = Fremdmaterial |

**Antwort `200`:**
```json
{
  "success": true,
  "data": {
    "foto_pfad": "2025/01/abc12345.jpg",
    "foto_lizenz_autor": null,
    "foto_lizenz_name": null,
    "foto_lizenz_url": null
  }
}
```

**Fehler:** `422` – keine Datei übermittelt, `400` – ungültiger Dateityp

---

### `POST /api/stolpersteine/{id}/foto/commons-import`
Lädt ein Bild von Wikimedia Commons herunter und speichert es lokal. Lizenzmetadaten werden automatisch von der Commons-API übernommen. Erfordert **Admin**.

**Body (JSON):**
```json
{ "commons_datei": "Stolperstein_Anna_Schmidt.jpg" }
```

**Antwort `200`:**
```json
{
  "success": true,
  "data": {
    "foto_pfad": "2025/01/abc12345.jpg",
    "wikimedia_commons": "Stolperstein_Anna_Schmidt.jpg",
    "foto_lizenz_autor": "Max Mustermann",
    "foto_lizenz_name": "CC BY-SA 4.0",
    "foto_lizenz_url": "https://creativecommons.org/licenses/by-sa/4.0/"
  }
}
```

**Fehler:** `422` – `commons_datei` fehlt, `404` – Datei nicht auf Commons, `502` – Download fehlgeschlagen

---

### `DELETE /api/stolpersteine/{id}/foto`
Löscht das lokale Foto eines Stolpersteins (Datei + DB-Felder). Erfordert Login.

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
      "verlegeort": ["strasse_aktuell", "hausnummer_aktuell", "stadtteil", "plz_aktuell", "wikidata_id_strasse", "wikidata_id_stadtteil", "lat", "lon", "beschreibung", "bemerkung_historisch", "grid_n", "grid_m"],
      "stein":      ["verlegedatum", "inschrift", "wikidata_id_stein", "osm_id", "pos_x", "pos_y", "lat_override", "lon_override", "wikimedia_commons", "foto_lizenz_autor", "foto_lizenz_name", "foto_lizenz_url", "status", "zustand"]
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

## Export

Alle Export-Endpunkte erfordern **Admin**-Rolle.

### `GET /api/export/wikipedia?stadtteil_id={id}`
Erzeugt den vollständigen Wikitext für die Wikipedia-Stolpersteinliste eines Stadtteils.

**Query-Parameter:**

| Parameter | Pflicht | Beschreibung |
|---|---|---|
| `stadtteil_id` | ✅ | ID des Stadtteils |
| `raw` | – | `1` → Antwort als `text/plain` (Wikitext direkt, kein JSON-Wrapper) |

**Antwort `200`:**
```json
{
  "success": true,
  "data": {
    "wikitext": "Die '''Liste der Stolpersteine...",
    "stadtteil": "Neue Neustadt",
    "wikipedia_name": "Liste der Stolpersteine in Magdeburg-Neue Neustadt",
    "anzahl": 16
  }
}
```

**Fehler:**
- `404` – Stadtteil nicht gefunden
- `409` – Kein aktives Template vorhanden

---

### `GET /api/export/wikipedia/diff?stadtteil_id={id}`
Gibt lokal generierten Wikitext und den aktuellen Live-Wikitext von Wikipedia zurück (für Diff-Anzeige im Frontend).

**Query-Parameter:**

| Parameter | Pflicht | Beschreibung |
|---|---|---|
| `stadtteil_id` | ✅ | ID des Stadtteils |

**Antwort `200`:**
```json
{
  "success": true,
  "data": {
    "lokal": "Die '''Liste der Stolpersteine...",
    "live": "Die '''Liste der Stolpersteine...",
    "seitenname": "Liste der Stolpersteine in Magdeburg-Neue Neustadt",
    "anzahl": 16
  }
}
```

`live` ist `null` wenn die Wikipedia-Seite nicht abgerufen werden konnte (Seite nicht vorhanden, Netzwerkfehler).

**Fehler:**
- `404` – Stadtteil nicht gefunden
- `409` – Kein aktives Template vorhanden

---

## Templates

Alle Template-Endpunkte erfordern **Admin**-Rolle.

Templates steuern das Ausgabeformat des Exports. Pro Zielsystem gibt es zwei Templates:
- `name = "seite"` – Rahmen der gesamten Seite (Einleitung, Tabellenstart/-ende, Fußnoten)
- `name = "zeile"` – Markup für genau einen Datensatz (eine Tabellenzeile)

Jede Änderung erzeugt eine neue Versionszeile; ältere Versionen werden deaktiviert (`aktiv = 0`). Wenn der Inhalt unverändert ist, wird keine neue Version erstellt.

### `GET /api/templates?zielsystem={system}`
Gibt alle aktiven Templates für ein Zielsystem zurück.

**Query-Parameter:**

| Parameter | Beschreibung |
|---|---|
| `zielsystem` | z. B. `wikipedia` |

**Antwort `200`:**
```json
{
  "success": true,
  "data": [
    {
      "id": 3,
      "name": "seite",
      "version": 2,
      "zielsystem": "wikipedia",
      "inhalt": "Die '''Liste...",
      "erstellt_am": "2025-03-01T10:00:00Z",
      "geaendert_am": "2025-03-10T14:30:00Z"
    },
    {
      "id": 5,
      "name": "zeile",
      "version": 1,
      "zielsystem": "wikipedia",
      "inhalt": "{{Stolpersteinliste Tabellenzeile |...",
      "erstellt_am": "2025-03-01T10:00:00Z",
      "geaendert_am": "2025-03-01T10:00:00Z"
    }
  ]
}
```

---

### `GET /api/templates/{id}`
Gibt ein einzelnes Template zurück.

**Antwort `200`:**
```json
{
  "success": true,
  "data": {
    "id": 3,
    "name": "seite",
    "version": 2,
    "zielsystem": "wikipedia",
    "inhalt": "Die '''Liste...",
    "aktiv": 1,
    "erstellt_am": "2025-03-01T10:00:00Z",
    "geaendert_am": "2025-03-10T14:30:00Z"
  }
}
```

**Fehler:** `404` – Template nicht gefunden

---

### `PUT /api/templates/{id}`
Aktualisiert den Inhalt eines Templates. Erstellt eine neue Version, wenn der Inhalt geändert wurde; andernfalls bleibt die Versionsnummer unverändert.

**Body (JSON):**
```json
{ "inhalt": "Die '''Liste der Stolpersteine..." }
```

**Antwort `200`:**
```json
{
  "success": true,
  "data": {
    "id": 4,
    "version": 3,
    "geaendert_am": "2025-03-14T09:00:00Z"
  }
}
```

Wenn der Inhalt unverändert war, wird die bestehende `id` zurückgegeben und `version` bleibt gleich.

**Fehler:**
- `404` – Template nicht gefunden
- `422` – `inhalt` fehlt

---

## Konfiguration

### `GET /api/konfiguration`
Gibt öffentlich zugängliche Konfigurationswerte zurück (kein Login erforderlich).

**Antwort `200`:**
```json
{
  "success": true,
  "data": {
    "stadt_name": "Magdeburg",
    "wikidata_city_id": "Q1733",
    "map_lat": 52.1317,
    "map_lon": 11.6292
  }
}
```

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
| Export (Wikitext erzeugen, Diff) | ❌ | ✅ |
| Templates lesen/bearbeiten | ❌ | ✅ |

---

## Öffentliche Endpunkte

Kein Login erforderlich. Liefern ausschließlich Daten mit `status = 'freigegeben'`.

---

### `GET /api/public/statistiken`
Aggregatzahlen für die öffentliche Website.

**Antwort `200`:**
```json
{
  "success": true,
  "data": {
    "steine": 512,
    "personen": 498,
    "strassen": 87,
    "stadtteile": 23
  }
}
```

---

### `GET /api/public/stolpersteine`
Liste aller freigegebenen Stolpersteine für Karte und Listenansicht.

**Antwort `200`:** Array mit `id`, `nachname`, `vorname`, `geburtsname`, `geburtsdatum`, `sterbedatum`, `geburtsdatum_genauigkeit`, `sterbedatum_genauigkeit`, `strasse`, `hausnummer`, `stadtteil`, `lat`, `lon`, `foto_pfad`, `foto_eigenes`, `wikimedia_commons`, `zustand`.

---

### `GET /api/public/stolpersteine/{id}`
Detaildaten eines einzelnen freigegebenen Stolpersteins.

Zusätzliche Felder gegenüber der Liste: `biografie_kurz`, `person_wikipedia`, `wikidata_id_person`, `plz`, `foto_lizenz_autor`, `foto_lizenz_name`, `foto_lizenz_url`, `wikidata_id_stein`, `biografie_dok_url`, `biografie_dok_titel`, `biografie_dok_dateiname`, `biografie_dok_groesse_bytes`, `biografie_dok_quelle`.

**Fehler:** `404` – nicht gefunden oder nicht freigegeben

---

### `GET /api/public/suche?q=`
Volltext-Suche über freigegebene Stolpersteine (BOOLEAN MODE, max. 100 Treffer).

**Query-Parameter:**

| Parameter | Beschreibung |
|---|---|
| `q` | Suchbegriff |

**Antwort `200`:** Array mit `id`, `nachname`, `vorname`, `strasse`, `hausnummer`, `stadtteil`, `lat`, `lon`, `relevanz` — absteigend nach Relevanz.

**Fehler:** `422` – Suchbegriff fehlt
