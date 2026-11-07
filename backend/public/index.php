<?php

declare(strict_types=1);

use App\Core\Response;
use App\Core\Router;

// Load App classes from their folder: App\Core\Router -> backend/Core/Router.php
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $file = __DIR__ . '/../' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// Session cookie: HttpOnly, only over HTTPS and deleted when the browser closes (RS4, RS5)
ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// The list of routes is in backend/routes.php
$router = new Router();
require __DIR__ . '/../routes.php';

try {
    $router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
} catch (Throwable $e) {
    // Log the real error and show a generic message (RS18)
    error_log($e->getMessage());
    Response::error('Error interno del servidor', 500);
}
