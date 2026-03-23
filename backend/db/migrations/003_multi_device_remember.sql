-- Migration 003: Multi-Device Remember-Tokens
-- Ermöglicht gleichzeitiges Eingeloggt-Sein auf mehreren Geräten.
-- Ausführen auf Produktion: mysql -u USER -p DBNAME < 003_multi_device_remember.sql

-- Neue Tabelle: ein Datensatz pro Gerät/Session
CREATE TABLE remember_tokens (
    id          INT          AUTO_INCREMENT PRIMARY KEY,
    benutzer_id INT          NOT NULL,
    token_hash  VARCHAR(64)  NOT NULL  COMMENT 'SHA-256-Hash des Raw-Tokens (nie Klartext)',
    ablauf      DATETIME     NOT NULL,
    erstellt_am DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE  KEY uq_token_hash (token_hash),
    INDEX   idx_benutzer    (benutzer_id),
    FOREIGN KEY (benutzer_id) REFERENCES benutzer(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Alte Einzelspalten aus benutzer entfernen (Migration 002)
ALTER TABLE benutzer
    DROP INDEX  idx_remember_token,
    DROP COLUMN remember_token,
    DROP COLUMN remember_token_ablauf;
