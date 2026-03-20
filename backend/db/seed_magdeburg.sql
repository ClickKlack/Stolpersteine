-- =============================================================================
-- seed_magdeburg.sql – Magdeburger Stammdaten
--
-- Spielt ein:
--   - Stadt Magdeburg
--   - 14 Postleitzahlen (39104–39130)
--   - 40 Stadtteile mit Wikidata-IDs
--
-- Idempotent: INSERT IGNORE überspringt bereits vorhandene Einträge.
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

INSERT IGNORE INTO stadtteile (name, wikidata_id, stadt_id)
    SELECT v.name, v.wikidata_id, s.id
    FROM (
        SELECT 'Alt Olvenstedt'        AS name, 'Q433187'    AS wikidata_id UNION ALL
        SELECT 'Alte Neustadt',                 'Q436046'                   UNION ALL
        SELECT 'Altstadt',                      'Q445520'                   UNION ALL
        SELECT 'Barleber See',                  'Q808316'                   UNION ALL
        SELECT 'Berliner Chaussee',             'Q821469'                   UNION ALL
        SELECT 'Beyendorf-Sohlen',              'Q631858'                   UNION ALL
        SELECT 'Beyendorfer Grund',             'Q853060'                   UNION ALL
        SELECT 'Brückfeld',                     'Q996595'                   UNION ALL
        SELECT 'Buckau',                        'Q999376'                   UNION ALL
        SELECT 'Cracau',                        'Q1138395'                  UNION ALL
        SELECT 'Diesdorf',                      'Q1221358'                  UNION ALL
        SELECT 'Fermersleben',                  'Q1406403'                  UNION ALL
        SELECT 'Gewerbegebiet Nord',            'Q1520432'                  UNION ALL
        SELECT 'Großer Silberberg',             'Q1549004'                  UNION ALL
        SELECT 'Herrenkrug',                    'Q1614118'                  UNION ALL
        SELECT 'Hopfengarten',                  'Q1627618'                  UNION ALL
        SELECT 'Kannenstieg',                   'Q1723829'                  UNION ALL
        SELECT 'Kreuzhorst',                    'Q1788288'                  UNION ALL
        SELECT 'Leipziger Straße',              'Q1645431'                  UNION ALL
        SELECT 'Lemsdorf',                      'Q1306371'                  UNION ALL
        SELECT 'Industriehafen',                'Q1529168'                  UNION ALL
        SELECT 'Neu Olvenstedt',                'Q1979169'                  UNION ALL
        SELECT 'Neue Neustadt',                 'Q1979660'                  UNION ALL
        SELECT 'Neustädter Feld',               'Q1981756'                  UNION ALL
        SELECT 'Neustädter See',                'Q1757712'                  UNION ALL
        SELECT 'Nordwest',                      'Q1755709'                  UNION ALL
        SELECT 'Ottersleben',                   'Q2037628'                  UNION ALL
        SELECT 'Pechau',                        'Q282044'                   UNION ALL
        SELECT 'Prester',                       'Q2109001'                  UNION ALL
        SELECT 'Randau-Calenberge',             'Q1942394'                  UNION ALL
        SELECT 'Reform',                        'Q2136749'                  UNION ALL
        SELECT 'Rothensee',                     'Q2168698'                  UNION ALL
        SELECT 'Salbke',                        'Q1661362'                  UNION ALL
        SELECT 'Stadtfeld Ost',                 'Q1476910'                  UNION ALL
        SELECT 'Stadtfeld West',                'Q2327094'                  UNION ALL
        SELECT 'Sudenburg',                     'Q1456670'                  UNION ALL
        SELECT 'Sülzegrund',                    'Q974584'                   UNION ALL
        SELECT 'Werder',                        'Q2560130'                  UNION ALL
        SELECT 'Westerhüsen',                   'Q979278'                   UNION ALL
        SELECT 'Zipkeleben',                    'Q205449'
    ) v
    JOIN staedte s ON s.name = 'Magdeburg';
