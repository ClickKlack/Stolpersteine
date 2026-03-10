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
if (Config::get('app')['debug'] ?? false) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
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
$router->add('GET',    '/stolpersteine',      'Stolpersteine\Api\StolpersteineHandler', 'index');
$router->add('POST',   '/stolpersteine',      'Stolpersteine\Api\StolpersteineHandler', 'create');
$router->add('GET',    '/stolpersteine/{id}', 'Stolpersteine\Api\StolpersteineHandler', 'show');
$router->add('PUT',    '/stolpersteine/{id}', 'Stolpersteine\Api\StolpersteineHandler', 'update');
$router->add('DELETE', '/stolpersteine/{id}', 'Stolpersteine\Api\StolpersteineHandler', 'delete');

// Dokumente
$router->add('GET',    '/dokumente',      'Stolpersteine\Api\DokumenteHandler', 'index');
$router->add('POST',   '/dokumente',      'Stolpersteine\Api\DokumenteHandler', 'create');
$router->add('GET',    '/dokumente/{id}', 'Stolpersteine\Api\DokumenteHandler', 'show');
$router->add('DELETE', '/dokumente/{id}', 'Stolpersteine\Api\DokumenteHandler', 'delete');

// Suche
$router->add('GET', '/suche', 'Stolpersteine\Api\SucheHandler', 'search');

// Export
$router->add('GET', '/export/{format}', 'Stolpersteine\Api\ExportHandler', 'export');

// Import
$router->add('POST', '/import/analyze', 'Stolpersteine\Api\ImportHandler', 'analyze');
$router->add('POST', '/import/preview', 'Stolpersteine\Api\ImportHandler', 'preview');
$router->add('POST', '/import/execute', 'Stolpersteine\Api\ImportHandler', 'execute');

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
