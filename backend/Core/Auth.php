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

        // Delete the session cookie in the browser as well
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 3600,
            'path' => $params['path'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'],
        ]);

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

    // Returns the logged-in administrator or stops the request: 401 without a session
    // and 403 if the user is a customer (RF28). Every admin route of the API calls it (RS6)
    public function requireAdmin(): User
    {
        $user = $this->requireLogin();
        if ($user->role !== 'admin') {
            Response::error('No tienes permiso para realizar esta acción', 403);
            exit;
        }

        return $user;
    }
}
