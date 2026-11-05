<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    private const PASSWORD_MIN_LENGTH = 8;

    protected Auth $auth;

    public function __construct()
    {
        $this->auth = new Auth();

        // Requests that change data must include the CSRF token (RS11)
        if ($_SERVER['REQUEST_METHOD'] !== 'GET' && !Csrf::validate($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
            Response::error('La sesión ha caducado. Recarga la página e inténtalo de nuevo.', 403);
            exit;
        }
    }

    // Reads the JSON body of the request
    protected function readJson(): array
    {
        $data = json_decode(file_get_contents('php://input'), true);

        return is_array($data) ? $data : [];
    }

    /**
     * Checks the data against simple rules and returns the errors by field.
     * Example: ['email' => ['required', 'email', 'max:150']]
     * Rules other than "required" are only checked when the field has a value.
     */
    protected function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? '';
            if (!is_string($value)) {
                $errors[$field] = 'El valor no es válido.';
                continue;
            }

            $value = trim($value);
            if ($value === '') {
                if (in_array('required', $fieldRules, true)) {
                    $errors[$field] = 'Este campo es obligatorio.';
                }
                continue;
            }

            foreach ($fieldRules as $rule) {
                $error = $this->checkRule($value, $rule);
                if ($error !== null) {
                    $errors[$field] = $error;
                    break;
                }
            }
        }

        return $errors;
    }

    private function checkRule(string $value, string $rule): ?string
    {
        if ($rule === 'email' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            return 'El email no es válido.';
        }

        if ($rule === 'password'
            && (mb_strlen($value) < self::PASSWORD_MIN_LENGTH
                || !preg_match('/[A-Za-z]/', $value)
                || !preg_match('/[0-9]/', $value))) {
            return 'La contraseña debe tener al menos 8 caracteres, una letra y un número.';
        }

        if ($rule === 'postal_code' && !preg_match('/^[0-9]{5}$/', $value)) {
            return 'El código postal debe tener 5 cifras.';
        }

        if (str_starts_with($rule, 'max:')) {
            $max = (int) substr($rule, 4);
            if (mb_strlen($value) > $max) {
                return "Máximo $max caracteres.";
            }
        }

        return null;
    }
}
