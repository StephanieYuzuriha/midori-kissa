<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;

// Profile of the logged-in customer. The user always comes from the session,
// never from the URL or the request body, so nobody can change another user's data (RS7)
class UserController extends Controller
{
    public function getProfile(): void
    {
        $user = $this->auth->requireLogin();

        Response::json(['user' => $user->toArray()]);
    }

    // Personal data and shipping address (RF8, RF9). Email and role cannot be changed here
    public function updateProfile(): void
    {
        $user = $this->auth->requireLogin();

        $data = $this->readJson();
        $errors = $this->validate($data, [
            'firstName' => ['required', 'max:50'],
            'lastName' => ['required', 'max:100'],
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

        $user->firstName = trim($data['firstName']);
        $user->lastName = trim($data['lastName']);
        $user->phone = $this->optionalText($data, 'phone');
        $user->address = $this->optionalText($data, 'address');
        $user->postalCode = $this->optionalText($data, 'postalCode');
        $user->city = $this->optionalText($data, 'city');
        $user->province = $this->optionalText($data, 'province');
        $user->save();

        Response::json(['user' => $user->toArray()]);
    }

    public function changePassword(): void
    {
        $user = $this->auth->requireLogin();

        $data = $this->readJson();
        $errors = $this->validate($data, [
            'currentPassword' => ['required'],
            'newPassword' => ['required', 'password'],
        ]);
        if ($errors) {
            Response::json(['errors' => $errors], 422);
            return;
        }

        if (!$user->verifyPassword($data['currentPassword'])) {
            Response::json(['errors' => ['currentPassword' => 'La contraseña actual no es correcta.']], 422);
            return;
        }

        $user->setPassword($data['newPassword']);
        $user->save();

        // New session id after changing the password
        session_regenerate_id(true);
        Response::json(['message' => 'Contraseña actualizada.']);
    }
}
