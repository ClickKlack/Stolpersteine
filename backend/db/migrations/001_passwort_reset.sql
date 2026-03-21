-- Migration 001: Passwort-Reset-Felder in benutzer-Tabelle
-- Führe dieses Skript einmalig aus:
--   mysql -u <user> -p <datenbank> < backend/db/migrations/001_passwort_reset.sql

ALTER TABLE benutzer
    ADD COLUMN passwort_reset_token  VARCHAR(64) NULL     COMMENT 'Einmaliger Reset-Token (SHA-256-Hex)',
    ADD COLUMN passwort_reset_ablauf DATETIME    NULL     COMMENT 'Ablaufzeitpunkt des Reset-Tokens';
