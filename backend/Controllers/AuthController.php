<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Response;
use App\Models\User;

class AuthController extends Controller
{
    // Tells the frontend who is logged in and gives it the CSRF token
    public function session(): void
    {
        $user = $this->auth->currentUser();

        Response::json([
            'user' => $user?->toArray(),
            'csrfToken' => Csrf::generateToken(),
        ]);
    }

    public function register(): void
    {
        $data = $this->readJson();
        $errors = $this->validate($data, [
            'firstName' => ['required', 'max:50'],
            'lastName' => ['required', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'password' => ['required', 'password'],
            'phone' => ['max:20'],
            'address' => ['max:200'],
            'postalCode' => ['postal_code'],
            'city' => ['max:100'],
            'province' => ['max:100'],
        ]);
        if ($errors) {
            Response::json(['errors' => $errors], 422);
            return;
        }

        $email = mb_strtolower(trim($data['email']));
        if (User::findByEmail($email) !== null) {
            Response::error('Ya existe una cuenta con este email. Inicia sesión para continuar.', 409);
            return;
        }

        $user = new User();
        $user->firstName = trim($data['firstName']);
        $user->lastName = trim($data['lastName']);
        $user->email = $email;
        $user->phone = $this->optionalText($data, 'phone');
        $user->address = $this->optionalText($data, 'address');
        $user->postalCode = $this->optionalText($data, 'postalCode');
        $user->city = $this->optionalText($data, 'city');
        $user->province = $this->optionalText($data, 'province');
        $user->setPassword($data['password']);
        $user->save();

        $this->auth->login($user);
        Response::json(['user' => $user->toArray()], 201);
    }

    public function login(): void
    {
        $data = $this->readJson();
        $errors = $this->validate($data, [
            'email' => ['required'],
            'password' => ['required'],
        ]);
        if ($errors) {
            Response::json(['errors' => $errors], 422);
            return;
        }

        $user = User::findByEmail(mb_strtolower(trim($data['email'])));

        if ($user !== null && $user->isLocked()) {
            $this->respondLocked();
            return;
        }

        if ($user === null || !$user->verifyPassword($data['password'])) {
            if ($user !== null) {
                $user->registerFailedAttempt();
                if ($user->isLocked()) {
                    $this->respondLocked();
                    return;
                }
            }
            // Same message whether the email exists or not
            Response::error('Email o contraseña incorrectos.', 401);
            return;
        }

        $user->resetFailedAttempts();
        $this->auth->login($user);
        Response::json(['user' => $user->toArray()]);
    }

    public function logout(): void
    {
        $this->auth->logout();
        Response::json(['message' => 'Sesión cerrada.']);
    }

    private function respondLocked(): void
    {
        Response::error(
            'La cuenta está bloqueada durante 15 minutos por varios intentos fallidos. Inténtalo más tarde.',
            423
        );
    }

    // Empty optional fields are stored as NULL
    private function optionalText(array $data, string $field): ?string
    {
        $value = trim($data[$field] ?? '');

        return $value === '' ? null : $value;
    }
}
