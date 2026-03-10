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
    geburtsdatum        DATE,
    sterbedatum         DATE,
    biografie_kurz      TEXT,
    wikidata_id_person  VARCHAR(50),
    erstellt_am         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    erstellt_von        VARCHAR(100)    NOT NULL,
    geaendert_am        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    geaendert_von       VARCHAR(100)    NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_nachname (nachname),
    INDEX idx_wikidata (wikidata_id_person)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- Verlegeorte
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS verlegeorte (
    id                      INT             NOT NULL AUTO_INCREMENT,
    beschreibung            TEXT,
    lat                     DECIMAL(10,8),
    lon                     DECIMAL(11,8),
    stadtteil               VARCHAR(100),
    strasse_aktuell         VARCHAR(255),
    hausnummer_aktuell      VARCHAR(50),
    plz_aktuell             VARCHAR(10),
    adresse_alt             JSON,
    bemerkung_historisch    TEXT,
    wikidata_id_strasse     VARCHAR(50),
    grid_n                  INT             COMMENT 'Rasterspalte, 1-basiert, Ursprung links oben',
    grid_m                  INT             COMMENT 'Rasterzeile, 1-basiert, Ursprung links oben',
    raster_beschreibung     TEXT,
    erstellt_am             DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    erstellt_von            VARCHAR(100)    NOT NULL,
    geaendert_am            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    geaendert_von           VARCHAR(100)    NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_stadtteil (stadtteil),
    INDEX idx_strasse (strasse_aktuell)
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
    foto_pfad       VARCHAR(255),
    wikidata_id_stein VARCHAR(50),
    osm_id          BIGINT,
    status          ENUM(
                        'neu',
                        'validierung',
                        'freigegeben',
                        'archiviert',
                        'fehlerhaft',
                        'abgleich_wikipedia',
                        'abgleich_osm',
                        'abgleich_wikidata'
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
    id                  INT             NOT NULL AUTO_INCREMENT,
    person_id           INT,
    stolperstein_id     INT,
    titel               VARCHAR(255)    NOT NULL,
    beschreibung_kurz   TEXT,
    typ                 VARCHAR(20)     COMMENT 'z.B. foto, pdf, scan, url',
    dateiname           VARCHAR(255),
    dateipfad           VARCHAR(255),
    quelle_url          VARCHAR(255),
    hash                CHAR(64)        UNIQUE COMMENT 'SHA-256 zur Duplikaterkennung',
    groesse_bytes       INT,
    erstellt_am         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    erstellt_von        VARCHAR(100)    NOT NULL,
    geaendert_am        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    geaendert_von       VARCHAR(100)    NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_dok_person    FOREIGN KEY (person_id)       REFERENCES personen(id)     ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_dok_stein     FOREIGN KEY (stolperstein_id) REFERENCES stolpersteine(id) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX idx_person    (person_id),
    INDEX idx_stein     (stolperstein_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


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
