-- Migration 002: Persistentes Login via Remember-Token
-- Ausführen auf Produktion: mysql -u USER -p DBNAME < 002_remember_token.sql

ALTER TABLE benutzer
    ADD COLUMN remember_token        VARCHAR(64)  NULL COMMENT 'SHA-256-Hash des Remember-Me-Tokens',
    ADD COLUMN remember_token_ablauf DATETIME     NULL COMMENT 'Ablaufzeitpunkt des Remember-Me-Tokens';

CREATE INDEX idx_remember_token ON benutzer (remember_token);
