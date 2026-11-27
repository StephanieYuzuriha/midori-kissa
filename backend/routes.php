<?php

declare(strict_types=1);

// API routes: HTTP method, path (without /api), controller and method.
// This file is loaded from public/index.php, which creates $router.

use App\Controllers\AuthController;
use App\Controllers\CategoryController;
use App\Controllers\HealthController;
use App\Controllers\OrderController;
use App\Controllers\ProductController;
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

// Catalog
$router->add('GET', '/categories', CategoryController::class, 'index');
$router->add('GET', '/products', ProductController::class, 'index');
$router->add('GET', '/products/{id}', ProductController::class, 'show');

// Admin panel: products (RF28–RF30, RF32). Every method checks the admin role (RS6)
$router->add('GET', '/admin/products', ProductController::class, 'adminIndex');
$router->add('GET', '/admin/products/{id}', ProductController::class, 'adminShow');
$router->add('POST', '/admin/products', ProductController::class, 'store');
$router->add('PUT', '/admin/products/{id}', ProductController::class, 'update');
$router->add('PUT', '/admin/products/{id}/active', ProductController::class, 'setActive');
$router->add('PUT', '/admin/products/{id}/stock', ProductController::class, 'updateStock');
$router->add('DELETE', '/admin/products/{id}', ProductController::class, 'destroy');
$router->add('POST', '/admin/products/{id}/images', ProductController::class, 'uploadImage');
$router->add('DELETE', '/admin/images/{id}', ProductController::class, 'deleteImage');

// Admin panel: categories (RF31)
$router->add('GET', '/admin/categories', CategoryController::class, 'adminIndex');
$router->add('POST', '/admin/categories', CategoryController::class, 'store');
$router->add('PUT', '/admin/categories/{id}', CategoryController::class, 'update');
$router->add('DELETE', '/admin/categories/{id}', CategoryController::class, 'destroy');

// Admin panel: orders (RF33, RF34)
$router->add('GET', '/admin/orders', OrderController::class, 'index');
$router->add('GET', '/admin/orders/{orderNumber}', OrderController::class, 'adminShow');
$router->add('PUT', '/admin/orders/{orderNumber}/status', OrderController::class, 'changeStatus');

// Cart and orders of the logged-in customer
$router->add('GET', '/shipping', OrderController::class, 'shipping');
$router->add('POST', '/orders', OrderController::class, 'store');
$router->add('GET', '/orders/mine', OrderController::class, 'myOrders');
$router->add('GET', '/orders/{orderNumber}', OrderController::class, 'show');
