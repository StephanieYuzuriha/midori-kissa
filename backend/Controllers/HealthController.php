<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;

// Checks that the API and the database are working
class HealthController
{
    public function index(): void
    {
        Database::getConnection()->query('SELECT 1');
        Response::json(['status' => 'ok']);
    }
}
