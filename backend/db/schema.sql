-- ============================================================
-- Stolpersteine-Verwaltungssystem
-- Schema v1.0
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ============================================================
-- Hilfsmakro: Audit-Felder werden in jeder Tabelle verwendet
-- (Als Kommentar dokumentiert, manuell eingefügt)
-- erstellt_am, erstellt_von, geaendert_am, geaendert_von
-- ============================================================

-- ------------------------------------------------------------
-- Benutzer & Rollen
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS benutzer (
    id              INT             NOT NULL AUTO_INCREMENT,
    benutzername    VARCHAR(100)    NOT NULL UNIQUE,
    passwort_hash   VARCHAR(255)    NOT NULL,
    email           VARCHAR(255),
    rolle           ENUM('editor', 'admin') NOT NULL DEFAULT 'editor',
    aktiv           TINYINT(1)      NOT NULL DEFAULT 1,
    erstellt_am     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    erstellt_von    VARCHAR(100)    NOT NULL DEFAULT 'system',
    geaendert_am    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    geaendert_von   VARCHAR(100)    NOT NULL DEFAULT 'system',
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- Konfiguration (städteübergreifend konfigurierbar)
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS konfiguration (
    schluessel      VARCHAR(100)    NOT NULL,
    wert            TEXT,
    beschreibung    VARCHAR(255),
    PRIMARY KEY (schluessel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Standardwerte
INSERT INTO konfiguration (schluessel, wert, beschreibung) VALUES
    ('stadt_name',      'Magdeburg',    'Name der Stadt'),
    ('stadt_land',      'Deutschland',  'Land'),
    ('osm_city_id',     NULL,           'OSM-Relation-ID der Stadt'),
    ('wikidata_city_id',NULL,           'Wikidata-ID der Stadt (z.B. Q1733)'),
    ('wikipedia_seite', NULL,           'Titel der Wikipedia-Listenseite');


-- ------------------------------------------------------------
-- Personen
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS personen (
    id                  INT             NOT NULL AUTO_INCREMENT,
    vorname             VARCHAR(255),
    nachname            VARCHAR(255)    NOT NULL,
    geburtsname         VARCHAR(255),
    geburtsdatum                DATE,
    geburtsdatum_genauigkeit    ENUM('tag', 'monat', 'jahr'),
    sterbedatum                 DATE,
    sterbedatum_genauigkeit     ENUM('tag', 'monat', 'jahr'),
    biografie_kurz          TEXT,
    biografie_dokument_id   INT NULL    COMMENT 'Biografie-Dokument (max. 1 je Person)',
    wikipedia_name          VARCHAR(255),
    wikidata_id_person      VARCHAR(50),
    status              ENUM('ok', 'validierung') NOT NULL DEFAULT 'validierung',
    erstellt_am         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    erstellt_von        VARCHAR(100)    NOT NULL,
    geaendert_am        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    geaendert_von       VARCHAR(100)    NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_nachname (nachname),
    INDEX idx_wikidata (wikidata_id_person)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- Adress-Normalisierung
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS staedte (
    id              INT             NOT NULL AUTO_INCREMENT,
    name            VARCHAR(255)    NOT NULL,
    wikidata_id     VARCHAR(50),
    PRIMARY KEY (id),
    UNIQUE KEY uq_stadt_name (name),
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stadtteile (
    id              INT             NOT NULL AUTO_INCREMENT,
    name            VARCHAR(100)    NOT NULL,
    wikidata_id             VARCHAR(50),
    wikipedia_stadtteil     VARCHAR(255)        COMMENT 'Titel des Wikipedia-Artikels über diesen Stadtteil',
    wikipedia_stolpersteine VARCHAR(255)        COMMENT 'Titel der Wikipedia-Stolperstein-Listenseite für diesen Stadtteil',
    stadt_id        INT             NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_stadtteil_stadt FOREIGN KEY (stadt_id) REFERENCES staedte(id) ON UPDATE CASCADE,
    UNIQUE KEY uq_stadtteil_name_stadt (name, stadt_id),
    INDEX idx_name   (name),
    INDEX idx_stadt  (stadt_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS strassen (
    id              INT             NOT NULL AUTO_INCREMENT,
    name            VARCHAR(255)    NOT NULL,
    wikipedia_name  VARCHAR(255),
    wikidata_id     VARCHAR(50),
    stadt_id        INT             NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_strasse_stadt FOREIGN KEY (stadt_id) REFERENCES staedte(id) ON UPDATE CASCADE,
    UNIQUE KEY uq_strasse_name_stadt (name, stadt_id),
    INDEX idx_name   (name),
    INDEX idx_stadt  (stadt_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plz (
    id              INT             NOT NULL AUTO_INCREMENT,
    plz             VARCHAR(10)     NOT NULL,
    stadt_id        INT             NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_plz_stadt FOREIGN KEY (stadt_id) REFERENCES staedte(id) ON UPDATE CASCADE,
    UNIQUE KEY uq_plz_stadt (plz, stadt_id),
    INDEX idx_plz (plz)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS adress_lokationen (
    id              INT             NOT NULL AUTO_INCREMENT,
    strasse_id      INT             NOT NULL,
    stadtteil_id    INT,
    plz_id          INT,
    PRIMARY KEY (id),
    CONSTRAINT fk_lokation_strasse   FOREIGN KEY (strasse_id)   REFERENCES strassen(id)   ON UPDATE CASCADE,
    CONSTRAINT fk_lokation_stadtteil FOREIGN KEY (stadtteil_id) REFERENCES stadtteile(id) ON UPDATE CASCADE,
    CONSTRAINT fk_lokation_plz       FOREIGN KEY (plz_id)       REFERENCES plz(id)        ON UPDATE CASCADE,
    INDEX idx_strasse   (strasse_id),
    INDEX idx_stadtteil (stadtteil_id),
    INDEX idx_plz       (plz_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- Verlegeorte
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS verlegeorte (
    id                      INT             NOT NULL AUTO_INCREMENT,
    adress_lokation_id      INT,
    hausnummer_aktuell      VARCHAR(50),
    beschreibung            TEXT,
    lat                     DECIMAL(10,8),
    lon                     DECIMAL(11,8),
    adresse_alt             JSON,
    bemerkung_historisch    TEXT,
    grid_n                  INT             COMMENT 'Rasterspalte, 1-basiert, Ursprung links oben',
    grid_m                  INT             COMMENT 'Rasterzeile, 1-basiert, Ursprung links oben',
    raster_beschreibung     TEXT,
    status              ENUM('ok', 'validierung') NOT NULL DEFAULT 'validierung',
    erstellt_am             DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    erstellt_von            VARCHAR(100)    NOT NULL,
    geaendert_am            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    geaendert_von           VARCHAR(100)    NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_verlegeort_lokation FOREIGN KEY (adress_lokation_id) REFERENCES adress_lokationen(id) ON UPDATE CASCADE,
    INDEX idx_lokation (adress_lokation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- Stolpersteine
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS stolpersteine (
    id              INT             NOT NULL AUTO_INCREMENT,
    person_id       INT             NOT NULL,
    verlegeort_id   INT             NOT NULL,
    verlegedatum    DATE,
    inschrift       TEXT,
    pos_x           INT             COMMENT 'Spalte am Verlegeort, 1-basiert',
    pos_y           INT             COMMENT 'Zeile am Verlegeort, 1-basiert',
    lat_override    DECIMAL(10,8)   COMMENT 'Überschreibt lat des Verlegeorts',
    lon_override    DECIMAL(11,8)   COMMENT 'Überschreibt lon des Verlegeorts',
    foto_pfad           VARCHAR(255),
    wikimedia_commons   VARCHAR(255)                COMMENT 'Dateiname auf Wikimedia Commons, z.B. Stolperstein_Berlin.jpg',
    foto_lizenz_autor   VARCHAR(255)                COMMENT 'Urheber/Fotograf, automatisch aus Commons befüllt',
    foto_lizenz_name    VARCHAR(100)                COMMENT 'Lizenz-Kürzel, z.B. CC BY-SA 4.0',
    foto_lizenz_url     VARCHAR(255)                COMMENT 'URL zur Lizenz',
    foto_eigenes        TINYINT(1)  NOT NULL DEFAULT 0  COMMENT '1 = eigenes Foto, Lizenzhinweis wird nicht angezeigt',
    wikidata_id_stein   VARCHAR(50),
    osm_id              BIGINT,
    status          ENUM(
                        'neu',
                        'validierung',
                        'freigegeben',
                        'archiviert',
                        'fehlerhaft'
                    ) NOT NULL DEFAULT 'neu',
    zustand         ENUM(
                        'verfuegbar',
                        'stein_fehlend',
                        'kein_stein',
                        'beschaedigt',
                        'unleserlich'
                    ) NOT NULL DEFAULT 'verfuegbar',
    erstellt_am     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    erstellt_von    VARCHAR(100)    NOT NULL,
    geaendert_am    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    geaendert_von   VARCHAR(100)    NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_stein_person      FOREIGN KEY (person_id)     REFERENCES personen(id)     ON UPDATE CASCADE,
    CONSTRAINT fk_stein_verlegeort  FOREIGN KEY (verlegeort_id) REFERENCES verlegeorte(id)  ON UPDATE CASCADE,
    INDEX idx_status    (status),
    INDEX idx_zustand   (zustand),
    INDEX idx_wikidata  (wikidata_id_stein),
    INDEX idx_osm       (osm_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- Dokumente
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS dokumente (
    id                      INT             NOT NULL AUTO_INCREMENT,
    stolperstein_id         INT,
    titel                   VARCHAR(255)    NOT NULL,
    beschreibung_kurz       TEXT,
    typ                     VARCHAR(20)     COMMENT 'z.B. foto, pdf, scan, url',
    dateiname               VARCHAR(255),
    dateipfad               VARCHAR(255),
    quelle_url              VARCHAR(255),
    hash                    CHAR(64)        UNIQUE COMMENT 'SHA-256 zur Duplikaterkennung',
    groesse_bytes           INT,
    quelle                  VARCHAR(255)    COMMENT 'Quellenhinweis oder Lizenz, Default: Domain aus quelle_url',
    spiegel_pfad            VARCHAR(255)    COMMENT 'Lokaler Spiegel-Pfad (nicht web-zugänglich)',
    spiegel_groesse_bytes   INT,
    url_geprueft_am         DATETIME        COMMENT 'Zeitpunkt der letzten URL-Prüfung',
    url_status              SMALLINT        COMMENT 'HTTP-Statuscode der letzten URL-Prüfung',
    erstellt_am             DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    erstellt_von            VARCHAR(100)    NOT NULL,
    geaendert_am            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    geaendert_von           VARCHAR(100)    NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_dok_stein FOREIGN KEY (stolperstein_id) REFERENCES stolpersteine(id) ON DELETE SET NULL ON UPDATE CASCADE,
    UNIQUE KEY uq_quelle_url (quelle_url(191)),
    INDEX idx_stein (stolperstein_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- Dokument-Personen-Zuordnung (m:n)
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS dokument_personen (
    id              INT NOT NULL AUTO_INCREMENT,
    dokument_id     INT NOT NULL,
    person_id       INT NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dok_person (dokument_id, person_id),
    CONSTRAINT fk_dp_dokument FOREIGN KEY (dokument_id) REFERENCES dokumente(id)  ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_dp_person   FOREIGN KEY (person_id)   REFERENCES personen(id)   ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_dokument (dokument_id),
    INDEX idx_person   (person_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- FK personen → dokumente (nachträglich, da dokumente nach personen definiert)
ALTER TABLE personen
    ADD CONSTRAINT fk_person_biografie_dok
        FOREIGN KEY (biografie_dokument_id) REFERENCES dokumente(id)
        ON DELETE SET NULL ON UPDATE CASCADE;


-- ------------------------------------------------------------
-- Suchindex (Volltext)
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS suchindex (
    id                  INT         NOT NULL AUTO_INCREMENT,
    stolperstein_id     INT         NOT NULL UNIQUE,
    personen_anteil     TEXT,
    lage_anteil         TEXT,
    dokumente_anteil    LONGTEXT,
    aktualisiert_am     DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_such_stein FOREIGN KEY (stolperstein_id) REFERENCES stolpersteine(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FULLTEXT INDEX ft_suche (personen_anteil, lage_anteil, dokumente_anteil)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- Templates
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS templates (
    id          INT             NOT NULL AUTO_INCREMENT,
    name        VARCHAR(100)    NOT NULL,
    version     INT             NOT NULL DEFAULT 1,
    zielsystem  ENUM('wikipedia', 'osm', 'json', 'csv') NOT NULL,
    inhalt      LONGTEXT        NOT NULL,
    aktiv       TINYINT(1)      NOT NULL DEFAULT 1,
    erstellt_am     DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    erstellt_von    VARCHAR(100) NOT NULL,
    geaendert_am    DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    geaendert_von   VARCHAR(100) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_name_version (name, version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- Audit-Log
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS audit_log (
    id              INT             NOT NULL AUTO_INCREMENT,
    benutzer        VARCHAR(100)    NOT NULL,
    aktion          VARCHAR(50)     NOT NULL COMMENT 'z.B. INSERT, UPDATE, DELETE, LOGIN',
    tabelle         VARCHAR(50),
    datensatz_id    INT,
    altwert         JSON,
    neuwert         JSON,
    zeitpunkt       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_tabelle       (tabelle, datensatz_id),
    INDEX idx_benutzer      (benutzer),
    INDEX idx_zeitpunkt     (zeitpunkt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- Externe Validierungen (Wikidata / OSM)
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS validierungen (
    id                  INT         NOT NULL AUTO_INCREMENT,
    stolperstein_id     INT         NOT NULL,
    system              ENUM('wikidata', 'osm') NOT NULL,
    syntax_ok           TINYINT(1),
    existenz_ok         TINYINT(1),
    semantik_ok         TINYINT(1),
    meldung             TEXT,
    geprueft_am         DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_val_stein FOREIGN KEY (stolperstein_id) REFERENCES stolpersteine(id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_stein_system (stolperstein_id, system)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
