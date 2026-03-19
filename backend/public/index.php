<?php

declare(strict_types=1);

// Fehler nur im Debug-Modus ausgeben
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';

use Stolpersteine\Config\Config;
use Stolpersteine\Api\Router;
use Stolpersteine\Api\Response;

// Konfiguration laden
Config::load(__DIR__ . '/../config.php');

// Nur JSON-Antworten
header('Content-Type: application/json; charset=utf-8');

// CORS für lokale Entwicklung
// Credentials (Cookies) erfordern eine explizite Origin statt Wildcard '*'
if (Config::get('app')['debug'] ?? false) {
    $allowedOrigins = Config::get('app')['cors_origins'] ?? [];
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (in_array($origin, $allowedOrigins, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
    }
}

// OPTIONS-Preflight sofort beantworten
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Pfad ermitteln (ohne Query-String)
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Führenden /api-Prefix entfernen
$path = preg_replace('#^/api#', '', $uri) ?: '/';

// Routing
$router = new Router();

// Öffentliche API (kein Auth erforderlich) – vor allen anderen Routen registrieren
$router->add('GET', '/public/statistiken',       'Stolpersteine\Api\PublicHandler', 'statistiken');
$router->add('GET', '/public/stolpersteine',     'Stolpersteine\Api\PublicHandler', 'liste');
$router->add('GET', '/public/stolpersteine/{id}','Stolpersteine\Api\PublicHandler', 'detail');
$router->add('GET', '/public/suche',             'Stolpersteine\Api\PublicHandler', 'suche');

// Personen
$router->add('GET',    '/personen',        'Stolpersteine\Api\PersonenHandler', 'index');
$router->add('POST',   '/personen',        'Stolpersteine\Api\PersonenHandler', 'create');
$router->add('GET',    '/personen/{id}',   'Stolpersteine\Api\PersonenHandler', 'show');
$router->add('PUT',    '/personen/{id}',   'Stolpersteine\Api\PersonenHandler', 'update');
$router->add('DELETE', '/personen/{id}',   'Stolpersteine\Api\PersonenHandler', 'delete');

// Verlegeorte
$router->add('GET',    '/verlegeorte',      'Stolpersteine\Api\VerlegeorteHandler', 'index');
$router->add('POST',   '/verlegeorte',      'Stolpersteine\Api\VerlegeorteHandler', 'create');
$router->add('GET',    '/verlegeorte/{id}', 'Stolpersteine\Api\VerlegeorteHandler', 'show');
$router->add('PUT',    '/verlegeorte/{id}', 'Stolpersteine\Api\VerlegeorteHandler', 'update');
$router->add('DELETE', '/verlegeorte/{id}', 'Stolpersteine\Api\VerlegeorteHandler', 'delete');

// Stolpersteine
$router->add('GET',    '/stolpersteine',                          'Stolpersteine\Api\StolpersteineHandler', 'index');
$router->add('POST',   '/stolpersteine',                          'Stolpersteine\Api\StolpersteineHandler', 'create');
$router->add('GET',    '/stolpersteine/{id}',                     'Stolpersteine\Api\StolpersteineHandler', 'show');
$router->add('PUT',    '/stolpersteine/{id}',                     'Stolpersteine\Api\StolpersteineHandler', 'update');
$router->add('DELETE', '/stolpersteine/{id}',                     'Stolpersteine\Api\StolpersteineHandler', 'delete');

// Fotos
$router->add('GET',    '/stolpersteine/{id}/foto/vergleich',      'Stolpersteine\Api\FotoHandler', 'vergleich');
$router->add('POST',   '/stolpersteine/{id}/foto/upload',         'Stolpersteine\Api\FotoHandler', 'upload');
$router->add('POST',   '/stolpersteine/{id}/foto/commons-import', 'Stolpersteine\Api\FotoHandler', 'commonsImport');
$router->add('DELETE', '/stolpersteine/{id}/foto',                'Stolpersteine\Api\FotoHandler', 'delete');

// Dokumente (Literal-Pfade vor {id} registrieren)
$router->add('GET',    '/dokumente',              'Stolpersteine\Api\DokumenteHandler', 'index');
$router->add('POST',   '/dokumente',              'Stolpersteine\Api\DokumenteHandler', 'create');
$router->add('GET',    '/dokumente/url-pruefung', 'Stolpersteine\Api\DokumenteHandler', 'urlPruefung');
$router->add('POST',   '/dokumente/url-check',    'Stolpersteine\Api\DokumenteHandler', 'urlCheck');
$router->add('POST',   '/dokumente/url-info',     'Stolpersteine\Api\DokumenteHandler', 'urlInfo');
$router->add('GET',    '/dokumente/{id}',         'Stolpersteine\Api\DokumenteHandler', 'show');
$router->add('PUT',    '/dokumente/{id}',         'Stolpersteine\Api\DokumenteHandler', 'update');
$router->add('GET',    '/dokumente/{id}/spiegel',   'Stolpersteine\Api\DokumenteHandler', 'spiegelDownload');
$router->add('POST',   '/dokumente/{id}/spiegel',   'Stolpersteine\Api\DokumenteHandler', 'spiegel');
$router->add('POST',   '/dokumente/{id}/biografie', 'Stolpersteine\Api\DokumenteHandler', 'setBiografie');
$router->add('DELETE', '/dokumente/{id}',         'Stolpersteine\Api\DokumenteHandler', 'delete');

// Suche
$router->add('GET', '/suche', 'Stolpersteine\Api\SucheHandler', 'search');

// Export
$router->add('GET', '/export/wikipedia/diff', 'Stolpersteine\Api\ExportHandler', 'diff');
$router->add('GET', '/export/osm/diff',       'Stolpersteine\Api\ExportHandler', 'osmDiff');
$router->add('GET', '/export/osm/datei',      'Stolpersteine\Api\ExportHandler', 'osmDatei');
$router->add('GET', '/export/{format}',       'Stolpersteine\Api\ExportHandler', 'export');

// Templates
$router->add('GET', '/templates',      'Stolpersteine\Api\TemplateHandler', 'index');
$router->add('GET', '/templates/{id}', 'Stolpersteine\Api\TemplateHandler', 'show');
$router->add('PUT', '/templates/{id}', 'Stolpersteine\Api\TemplateHandler', 'update');

// Import
$router->add('POST', '/import/analyze', 'Stolpersteine\Api\ImportHandler', 'analyze');
$router->add('POST', '/import/preview', 'Stolpersteine\Api\ImportHandler', 'preview');
$router->add('POST', '/import/execute', 'Stolpersteine\Api\ImportHandler', 'execute');

// Adressen (Lookup für Autocomplete)
$router->add('GET',  '/adressen/strassen',   'Stolpersteine\Api\AdressenHandler', 'strassen');
$router->add('GET',  '/adressen/stadtteile', 'Stolpersteine\Api\AdressenHandler', 'stadtteile');
$router->add('POST', '/adressen/lokationen', 'Stolpersteine\Api\AdressenHandler', 'createLokation');

// Adressen (Verwaltung)
$router->add('GET',    '/adressen/staedte',              'Stolpersteine\Api\AdressenHandler', 'staedte');
$router->add('POST',   '/adressen/staedte',              'Stolpersteine\Api\AdressenHandler', 'createStadt');
$router->add('GET',    '/adressen/staedte/{id}',         'Stolpersteine\Api\AdressenHandler', 'showStadt');
$router->add('PUT',    '/adressen/staedte/{id}',         'Stolpersteine\Api\AdressenHandler', 'updateStadt');
$router->add('DELETE', '/adressen/staedte/{id}',         'Stolpersteine\Api\AdressenHandler', 'deleteStadt');

$router->add('GET',    '/adressen/alle-stadtteile',      'Stolpersteine\Api\AdressenHandler', 'alleStadtteile');
$router->add('POST',   '/adressen/alle-stadtteile',      'Stolpersteine\Api\AdressenHandler', 'createStadtteil');
$router->add('GET',    '/adressen/alle-stadtteile/{id}', 'Stolpersteine\Api\AdressenHandler', 'showStadtteil');
$router->add('PUT',    '/adressen/alle-stadtteile/{id}', 'Stolpersteine\Api\AdressenHandler', 'updateStadtteil');
$router->add('DELETE', '/adressen/alle-stadtteile/{id}', 'Stolpersteine\Api\AdressenHandler', 'deleteStadtteil');

$router->add('GET',    '/adressen/alle-strassen',        'Stolpersteine\Api\AdressenHandler', 'alleStrassen');
$router->add('POST',   '/adressen/alle-strassen',        'Stolpersteine\Api\AdressenHandler', 'createStrasse');
$router->add('GET',    '/adressen/alle-strassen/{id}',   'Stolpersteine\Api\AdressenHandler', 'showStrasse');
$router->add('PUT',    '/adressen/alle-strassen/{id}',   'Stolpersteine\Api\AdressenHandler', 'updateStrasse');
$router->add('DELETE', '/adressen/alle-strassen/{id}',   'Stolpersteine\Api\AdressenHandler', 'deleteStrasse');

$router->add('GET',    '/adressen/alle-plz',             'Stolpersteine\Api\AdressenHandler', 'allePlz');
$router->add('POST',   '/adressen/alle-plz',             'Stolpersteine\Api\AdressenHandler', 'createPlz');
$router->add('GET',    '/adressen/alle-plz/{id}',        'Stolpersteine\Api\AdressenHandler', 'showPlz');
$router->add('PUT',    '/adressen/alle-plz/{id}',        'Stolpersteine\Api\AdressenHandler', 'updatePlz');
$router->add('DELETE', '/adressen/alle-plz/{id}',        'Stolpersteine\Api\AdressenHandler', 'deletePlz');

$router->add('GET',    '/adressen/alle-lokationen',      'Stolpersteine\Api\AdressenHandler', 'alleLokationen');
$router->add('POST',   '/adressen/alle-lokationen',      'Stolpersteine\Api\AdressenHandler', 'createLokationDirect');
$router->add('PUT',    '/adressen/alle-lokationen/{id}', 'Stolpersteine\Api\AdressenHandler', 'updateLokation');
$router->add('DELETE', '/adressen/alle-lokationen/{id}', 'Stolpersteine\Api\AdressenHandler', 'deleteLokation');

// Konfiguration
$router->add('GET', '/konfiguration', 'Stolpersteine\Api\KonfigurationHandler', 'index');

// Auth
$router->add('POST', '/auth/login',  'Stolpersteine\Api\AuthHandler', 'login');
$router->add('POST', '/auth/logout', 'Stolpersteine\Api\AuthHandler', 'logout');
$router->add('GET',  '/auth/me',     'Stolpersteine\Api\AuthHandler', 'me');

// Request dispatchen
try {
    $router->dispatch($method, $path);
} catch (\Throwable $e) {
    $debug = Config::get('app')['debug'] ?? false;
    Response::error(
        'Interner Serverfehler.',
        500,
        $debug ? ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()] : []
    );
}
