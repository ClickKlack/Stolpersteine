-- =============================================================================
-- templates.sql – Standard-Templates (Seed)
--
-- Idempotent: INSERT IGNORE überspringt bereits vorhandene Einträge.
-- =============================================================================

INSERT IGNORE INTO templates (name, version, zielsystem, inhalt, aktiv, erstellt_am, erstellt_von, geaendert_am, geaendert_von)
VALUES

-- ── Wikipedia: Seiten-Template ───────────────────────────────────────────────
('seite', 1, 'wikipedia',
'Die \'\'\'[[SEITE.STOLPERSTEINE_WIKIPEDIA]]\'\'\' enthält die [[Stolpersteine]] im [[Magdeburg]]er Stadtteil [[SEITE.STADTTEIL_WIKIPEDIA_LINK]], die an das Schicksal der Menschen erinnern, die im Nationalsozialismus ermordet, deportiert, vertrieben oder in den Suizid getrieben wurden.<ref>{{Internetquelle | url=https://www.magdeburg.de/Start/B%c3%bcrger-Stadt/Stadt/Ehrungen-Preise/Stolpersteine/index.php?La=1&NavID=37.701&object=tx,698.5844.1&kat=&kuo=1&sub=0 | titel=Verlegte Stolpersteine | hrsg=Stadt Magdeburg | abruf=2019-07-22}}</ref> Die Tabelle erfasst insgesamt [[SEITE.ANZAHL_ZEILEN]] Stolpersteine und ist teilweise sortierbar; die Grundsortierung erfolgt alphabetisch nach dem Familiennamen.

{{Stolpersteinliste Tabellenkopf}}
[[SEITE.ZEILEN]]
|}',
1, NOW(), 'system', NOW(), 'system'),

-- ── Wikipedia: Zeilen-Template ───────────────────────────────────────────────
('zeile', 1, 'wikipedia',
'{{Stolpersteinliste Tabellenzeile |
Bild         = [[STEIN.WIKIMEDIA_COMMONS]] |
Inschrift    = [[STEIN.INSCHRIFT_BR]] |
NS           = [[STEIN.LAT]] |
EW           = [[STEIN.LON]] |
Region       = DE-ST |
Name         = [[PERSON.NAME_VOLL]] |
Ort          = [[ORT.ADRESSE]] |
Verlegedatum = [[STEIN.VERLEGEDATUM]] |
Anmerkungen  = [[PERSON.BIOGRAFIE_LANG]]
}}',
1, NOW(), 'system', NOW(), 'system'),

-- ── OSM: Abfrage-Template ────────────────────────────────────────────────────
('abfrage', 1, 'osm',
'[out:json][timeout:60];
area[name="[[STADT.NAME]]"][admin_level]->.a;
node(area.a)["memorial"="stolperstein"];
out meta;',
1, NOW(), 'system', NOW(), 'system'),

-- ── OSM: Tag-Mapping-Template ────────────────────────────────────────────────
('tags', 1, 'osm',
'{
  "historic": "memorial",
  "memorial": "stolperstein",
  "name": "[[PERSON.NAME_OSM]]",
  "inscription": "[[STEIN.INSCHRIFT_ORIGINAL]]",
  "start_date": "[[STEIN.VERLEGEDATUM_ISO]]",
  "wikidata": "[[STEIN.WIKIDATA_ID]]",
  "wikimedia_commons": "[[STEIN.WIKIMEDIA_COMMONS]]",
  "addr:street": "[[ORT.STRASSE]]",
  "addr:housenumber": "[[ORT.HAUSNUMMER]]",
  "addr:postcode": "[[ORT.PLZ]]",
  "addr:city": "[[ORT.STADT]]",
  "addr:suburb": "[[ORT.STADTTEIL]]",
  "network": "Stolpersteine [[ORT.STADT]]",
  "website": "[[DOK.URL]]",
  "subject:wikidata": "[[PERSON.WIKIDATA_ID]]"
}',
1, NOW(), 'system', NOW(), 'system');
