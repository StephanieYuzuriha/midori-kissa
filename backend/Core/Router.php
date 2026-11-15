<?php

declare(strict_types=1);

namespace App\Core;

class Router
{
    private const API_PREFIX = '/api';

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
    // A {parameter} only accepts a positive whole number (an id). Returns the parameter
    // values as integers, or null if the path does not match the route
    private function match(string $route, string $path): ?array
    {
        $pattern = '#^' . preg_replace('#\{[a-z]+\}#', '([0-9]+)', $route) . '$#';
        if (!preg_match($pattern, $path, $matches)) {
            return null;
        }

        return array_map('intval', array_slice($matches, 1));
    }
}
