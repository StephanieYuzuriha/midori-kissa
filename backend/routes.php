<?php

declare(strict_types=1);

// API routes: HTTP method, path (without /api), controller and method.
// This file is loaded from public/index.php, which creates $router.

use App\Controllers\AuthController;
use App\Controllers\HealthController;
use App\Controllers\UserController;

/** @var App\Core\Router $router */

$router->add('GET', '/health', HealthController::class, 'index');

// Authentication
$router->add('GET', '/auth/session', AuthController::class, 'session');
$router->add('POST', '/auth/register', AuthController::class, 'register');
$router->add('POST', '/auth/login', AuthController::class, 'login');
$router->add('POST', '/auth/logout', AuthController::class, 'logout');

// Profile of the logged-in customer
$router->add('GET', '/users/me', UserController::class, 'getProfile');
$router->add('PUT', '/users/me', UserController::class, 'updateProfile');
$router->add('PUT', '/users/me/password', UserController::class, 'changePassword');
