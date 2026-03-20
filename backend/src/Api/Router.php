<?php

declare(strict_types=1);

namespace Stolpersteine\Api;

use Stolpersteine\Config\Logger;

class Router
{
    private array $routes = [];

    public function add(string $method, string $pattern, string $handler, string $action): void
    {
        $this->routes[] = [
            'method'  => strtoupper($method),
            'pattern' => $pattern,
            'handler' => $handler,
            'action'  => $action,
        ];
    }

    public function dispatch(string $method, string $path): void
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== strtoupper($method)) {
                continue;
            }

            $params = $this->match($route['pattern'], $path);
            if ($params === null) {
                continue;
            }

            Logger::get()->debug('Route aufgelöst', [
                'method'  => $method,
                'pattern' => $route['pattern'],
                'handler' => $route['handler'] . '::' . $route['action'],
                'params'  => $params ?: null,
            ]);

            $handler = new $route['handler']();
            $action  = $route['action'];
            $handler->$action($params);
            return;
        }

        Logger::get()->warning('Route nicht gefunden', ['method' => $method, 'path' => $path]);
        Response::error('Endpunkt nicht gefunden.', 404);
    }

    // Gibt Pfad-Parameter zurück oder null bei keinem Treffer
    private function match(string $pattern, string $path): ?array
    {
        $regex = preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        if (!preg_match($regex, $path, $matches)) {
            return null;
        }

        // Nur benannte Gruppen zurückgeben
        return array_filter(
            $matches,
            fn($key) => is_string($key),
            ARRAY_FILTER_USE_KEY
        );
    }
}
