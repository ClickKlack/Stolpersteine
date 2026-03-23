-- Migration 004: Freies Bemerkungsfeld für Personen
-- Ausführen auf Produktion: mysql -u USER -p DBNAME < 004_person_bemerkung.sql

ALTER TABLE personen
    ADD COLUMN bemerkung TEXT NULL COMMENT 'Freies internes Bemerkungsfeld'
        AFTER biografie_kurz;
