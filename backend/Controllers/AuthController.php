<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Response;
use App\Models\User;

class AuthController extends Controller
{
    // Same message whether the email does not exist, the password is wrong or the account is locked
    private const LOGIN_ERROR = 'Email o contraseña incorrectos. Tras 5 intentos fallidos seguidos, si la cuenta existe, se bloquea durante 15 minutos.';

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
        $canLogin = $user !== null && !$user->isLocked() && $user->verifyPassword($data['password']);

        if (!$canLogin) {
            // Only count the attempt if the account exists and is not locked yet (RS3)
            if ($user !== null && !$user->isLocked()) {
                $user->registerFailedAttempt();
            }
            Response::error(self::LOGIN_ERROR, 401);
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
}
