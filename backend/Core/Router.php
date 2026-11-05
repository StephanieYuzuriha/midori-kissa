<?php

declare(strict_types=1);

namespace App\Core;

class Router
{
    private const API_PREFIX = '/api';

    // Routes grouped by HTTP method: ['GET' => ['/health' => [Controller::class, 'method']]]
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

        $handler = $this->routes[$method][$path] ?? null;
        if ($handler === null) {
            Response::error('Ruta no encontrada', 404);
            return;
        }

        [$controllerClass, $action] = $handler;
        $controller = new $controllerClass();
        $controller->$action();
    }
}
