<?php

declare(strict_types=1);

namespace App\Core;

class Router
{
    private const API_PREFIX = '/api';

    // What each {parameter} of a route accepts: {id} only a positive whole number and
    // {orderNumber} only an order number such as MK-2027-7F3K9Q
    private const PARAMETERS = [
        'id' => '([0-9]+)',
        'orderNumber' => '(MK-[0-9]{4}-[A-Z0-9]{6})',
    ];

    // Routes grouped by HTTP method: ['GET' => ['/products/{id}' => [Controller::class, 'method']]]
    private array $routes = [];

    public function add(string $method, string $path, string $controller, string $action): void
    {
        $this->routes[$method][$path] = [$controller, $action];
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH);

        if (str_starts_with($path, self::API_PREFIX)) {
            $path = substr($path, strlen(self::API_PREFIX));
        }
        $path = rtrim($path, '/') ?: '/';

        foreach ($this->routes[$method] ?? [] as $route => [$controllerClass, $action]) {
            $params = $this->match($route, $path);
            if ($params !== null) {
                $controller = new $controllerClass();
                $controller->$action(...$params);
                return;
            }
        }

        Response::error('Ruta no encontrada', 404);
    }

    // Compares a route such as /products/{id} with the requested path.
    // Returns the parameter values (ids as integers), or null if the path does not match the route
    private function match(string $route, string $path): ?array
    {
        preg_match_all('#\{([a-zA-Z]+)\}#', $route, $names);
        $pattern = preg_replace_callback('#\{([a-zA-Z]+)\}#', fn(array $m) => self::PARAMETERS[$m[1]], $route);
        if (!preg_match('#^' . $pattern . '$#', $path, $matches)) {
            return null;
        }

        $values = array_slice($matches, 1);
        foreach ($names[1] as $i => $name) {
            if ($name === 'id') {
                $values[$i] = (int) $values[$i];
            }
        }

        return $values;
    }
}
