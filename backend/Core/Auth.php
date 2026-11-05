<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;

// Keeps the logged-in user in the PHP session
class Auth
{
    public function login(User $user): void
    {
        // New session id after login to prevent session fixation (RS4)
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user->id;
    }

    public function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    public function currentUser(): ?User
    {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        return User::findById($_SESSION['user_id']);
    }

    // Returns the logged-in user or stops the request with 401 (RS6)
    public function requireLogin(): User
    {
        $user = $this->currentUser();
        if ($user === null) {
            Response::error('Debes iniciar sesión', 401);
            exit;
        }

        return $user;
    }
}
