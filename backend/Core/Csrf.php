<?php

declare(strict_types=1);

namespace App\Core;

// Token that the frontend must send in every request that changes data (RS11)
class Csrf
{
    public static function generateToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public static function validate(?string $token): bool
    {
        return isset($_SESSION['csrf_token'])
            && is_string($token)
            && hash_equals($_SESSION['csrf_token'], $token);
    }
}
