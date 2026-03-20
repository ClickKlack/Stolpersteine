-- =============================================================================
-- seed_magdeburg.sql – Magdeburger Stammdaten
--
-- Spielt ein:
--   - Stadt Magdeburg
--   - 14 Postleitzahlen (39104–39130)
--   - 40 Stadtteile mit Wikidata-IDs und Wikipedia-Seitentiteln
--
-- Idempotent:
--   staedte/plz:   INSERT IGNORE (überspringt vorhandene Einträge)
--   stadtteile:    ON DUPLICATE KEY UPDATE (aktualisiert Wikipedia-Felder)
-- =============================================================================

INSERT IGNORE INTO staedte (name, wikidata_id)
    VALUES ('Magdeburg', 'Q1733');

INSERT IGNORE INTO plz (plz, stadt_id)
    SELECT v.plz, s.id
    FROM (
        SELECT '39104' AS plz UNION ALL
        SELECT '39106' UNION ALL
        SELECT '39108' UNION ALL
        SELECT '39110' UNION ALL
        SELECT '39112' UNION ALL
        SELECT '39114' UNION ALL
        SELECT '39116' UNION ALL
        SELECT '39118' UNION ALL
        SELECT '39120' UNION ALL
        SELECT '39122' UNION ALL
        SELECT '39124' UNION ALL
        SELECT '39126' UNION ALL
        SELECT '39128' UNION ALL
        SELECT '39130'
    ) v
    JOIN staedte s ON s.name = 'Magdeburg';

INSERT INTO stadtteile (name, wikidata_id, wikipedia_stolpersteine, wikipedia_stadtteil, stadt_id)
    SELECT v.name, v.wikidata_id, v.wikipedia_stolpersteine, v.wikipedia_stadtteil, s.id
    FROM (
        SELECT 'Alt Olvenstedt'   AS name, 'Q433187'  AS wikidata_id, NULL                                                      AS wikipedia_stolpersteine, NULL                          AS wikipedia_stadtteil UNION ALL
        SELECT 'Alte Neustadt',            'Q436046',  'Liste der Stolpersteine in Magdeburg-Alte Neustadt',                     'Alte Neustadt'                                                                        UNION ALL
        SELECT 'Altstadt',                 'Q445520',  'Liste der Stolpersteine in Magdeburg-Altstadt',                          'Altstadt (Magdeburg)'                                                                 UNION ALL
        SELECT 'Barleber See',             'Q808316',  NULL,                                                                     NULL                                                                                   UNION ALL
        SELECT 'Berliner Chaussee',        'Q821469',  NULL,                                                                     NULL                                                                                   UNION ALL
        SELECT 'Beyendorf-Sohlen',         'Q631858',  NULL,                                                                     NULL                                                                                   UNION ALL
        SELECT 'Beyendorfer Grund',        'Q853060',  NULL,                                                                     NULL                                                                                   UNION ALL
        SELECT 'Brückfeld',                'Q996595',  'Liste der Stolpersteine in Magdeburg-Brückfeld',                         'Brückfeld'                                                                            UNION ALL
        SELECT 'Buckau',                   'Q999376',  'Liste der Stolpersteine in Magdeburg-Buckau',                            'Buckau (Magdeburg)'                                                                   UNION ALL
        SELECT 'Cracau',                   'Q1138395', 'Liste der Stolpersteine in Magdeburg-Cracau',                            'Cracau (Magdeburg)'                                                                   UNION ALL
        SELECT 'Diesdorf',                 'Q1221358', NULL,                                                                     NULL                                                                                   UNION ALL
        SELECT 'Fermersleben',             'Q1406403', 'Liste der Stolpersteine in Magdeburg-Fermersleben',                      'Fermersleben'                                                                         UNION ALL
        SELECT 'Gewerbegebiet Nord',       'Q1520432', NULL,                                                                     NULL                                                                                   UNION ALL
        SELECT 'Großer Silberberg',        'Q1549004', NULL,                                                                     NULL                                                                                   UNION ALL
        SELECT 'Herrenkrug',               'Q1614118', NULL,                                                                     NULL                                                                                   UNION ALL
        SELECT 'Hopfengarten',             'Q1627618', NULL,                                                                     NULL                                                                                   UNION ALL
        SELECT 'Industriehafen',           'Q1529168', NULL,                                                                     NULL                                                                                   UNION ALL
        SELECT 'Kannenstieg',              'Q1723829', NULL,                                                                     NULL                                                                                   UNION ALL
        SELECT 'Kreuzhorst',               'Q1788288', NULL,                                                                     NULL                                                                                   UNION ALL
        SELECT 'Leipziger Straße',         'Q1645431', 'Liste der Stolpersteine in Magdeburg-Leipziger Straße',                  'Leipziger Straße (Magdeburg)'                                                         UNION ALL
        SELECT 'Lemsdorf',                 'Q1306371', NULL,                                                                     NULL                                                                                   UNION ALL
        SELECT 'Neu Olvenstedt',           'Q1979169', NULL,                                                                     NULL                                                                                   UNION ALL
        SELECT 'Neue Neustadt',            'Q1979660', 'Liste der Stolpersteine in Magdeburg-Neue Neustadt',                     'Neue Neustadt'                                                                        UNION ALL
        SELECT 'Neustädter Feld',          'Q1981756', NULL,                                                                     NULL                                                                                   UNION ALL
        SELECT 'Neustädter See',           'Q1757712', NULL,                                                                     NULL                                                                                   UNION ALL
        SELECT 'Nordwest',                 'Q1755709', 'Liste der Stolpersteine in Magdeburg-Nordwest',                          'Nordwest (Magdeburg)'                                                                 UNION ALL
        SELECT 'Ottersleben',              'Q2037628', 'Liste der Stolpersteine in Magdeburg-Ottersleben',                       'Ottersleben'                                                                          UNION ALL
        SELECT 'Pechau',                   'Q282044',  NULL,                                                                     NULL                                                                                   UNION ALL
        SELECT 'Prester',                  'Q2109001', NULL,                                                                     NULL                                                                                   UNION ALL
        SELECT 'Randau-Calenberge',        'Q1942394', NULL,                                                                     NULL                                                                                   UNION ALL
        SELECT 'Reform',                   'Q2136749', 'Liste der Stolpersteine in Magdeburg-Reform',                            'Reform (Magdeburg)'                                                                   UNION ALL
        SELECT 'Rothensee',                'Q2168698', 'Liste der Stolpersteine in Magdeburg-Rothensee',                         'Rothensee'                                                                            UNION ALL
        SELECT 'Salbke',                   'Q1661362', NULL,                                                                     NULL                                                                                   UNION ALL
        SELECT 'Stadtfeld Ost',            'Q1476910', 'Liste der Stolpersteine in Magdeburg-Stadtfeld Ost',                     'Stadtfeld Ost'                                                                        UNION ALL
        SELECT 'Stadtfeld West',           'Q2327094', 'Liste der Stolpersteine in Magdeburg-Stadtfeld West',                    'Stadtfeld West'                                                                       UNION ALL
        SELECT 'Sudenburg',                'Q1456670', 'Liste der Stolpersteine in Magdeburg-Sudenburg',                         'Sudenburg'                                                                            UNION ALL
        SELECT 'Sülzegrund',               'Q974584',  NULL,                                                                     NULL                                                                                   UNION ALL
        SELECT 'Werder',                   'Q2560130', 'Liste der Stolpersteine in Magdeburg-Werder',                            'Werder (Magdeburg)'                                                                   UNION ALL
        SELECT 'Westerhüsen',              'Q979278',  NULL,                                                                     NULL                                                                                   UNION ALL
        SELECT 'Zipkeleben',               'Q205449',  NULL,                                                                     NULL
    ) v
    JOIN staedte s ON s.name = 'Magdeburg'
ON DUPLICATE KEY UPDATE
    wikidata_id             = VALUES(wikidata_id),
    wikipedia_stolpersteine = VALUES(wikipedia_stolpersteine),
    wikipedia_stadtteil     = VALUES(wikipedia_stadtteil);
