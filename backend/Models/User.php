<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class User extends Model
{
    // Login lock after several failed attempts (RS3)
    private const MAX_FAILED_ATTEMPTS = 5;
    private const LOCK_MINUTES = 15;

    public ?int $id = null;
    public string $firstName = '';
    public string $lastName = '';
    public string $email = '';
    public string $passwordHash = '';
    public ?string $phone = null;
    public string $role = 'customer';
    public ?string $address = null;
    public ?string $postalCode = null;
    public ?string $city = null;
    public ?string $province = null;
    public int $failedAttempts = 0;
    public ?string $lockedUntil = null;
    public ?string $createdAt = null;

    public static function findById(int $id): ?User
    {
        $stmt = Database::getConnection()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? self::fromRow($row) : null;
    }

    public static function findByEmail(string $email): ?User
    {
        $stmt = Database::getConnection()->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $row = $stmt->fetch();

        return $row ? self::fromRow($row) : null;
    }

    // Inserts a new user or updates an existing one
    public function save(): bool
    {
        $values = [
            $this->firstName,
            $this->lastName,
            $this->email,
            $this->passwordHash,
            $this->phone,
            $this->role,
            $this->address,
            $this->postalCode,
            $this->city,
            $this->province,
        ];

        if ($this->id === null) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO users (first_name, last_name, email, password_hash, phone, role,
                    address, postal_code, city, province)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $saved = $stmt->execute($values);
            $this->id = (int) $this->pdo->lastInsertId();

            return $saved;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE users SET first_name = ?, last_name = ?, email = ?, password_hash = ?, phone = ?,
                role = ?, address = ?, postal_code = ?, city = ?, province = ?
             WHERE id = ?'
        );

        return $stmt->execute([...$values, $this->id]);
    }

    // Stores the password as a bcrypt hash, never in plain text (RS1)
    public function setPassword(string $password): void
    {
        $this->passwordHash = password_hash($password, PASSWORD_BCRYPT);
    }

    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->passwordHash);
    }

    // Counts a failed login and locks the account after 5 in a row
    public function registerFailedAttempt(): void
    {
        $this->failedAttempts++;

        if ($this->failedAttempts >= self::MAX_FAILED_ATTEMPTS) {
            $this->lockedUntil = date('Y-m-d H:i:s', time() + self::LOCK_MINUTES * 60);
            $this->failedAttempts = 0;
        }

        $this->saveLoginState();
    }

    public function resetFailedAttempts(): void
    {
        $this->failedAttempts = 0;
        $this->lockedUntil = null;
        $this->saveLoginState();
    }

    public function isLocked(): bool
    {
        return $this->lockedUntil !== null && strtotime($this->lockedUntil) > time();
    }

    // Data that can be sent to the frontend (no password or lock data)
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'address' => $this->address,
            'postalCode' => $this->postalCode,
            'city' => $this->city,
            'province' => $this->province,
        ];
    }

    private function saveLoginState(): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET failed_attempts = ?, locked_until = ? WHERE id = ?');
        $stmt->execute([$this->failedAttempts, $this->lockedUntil, $this->id]);
    }

    private static function fromRow(array $row): User
    {
        $user = new User();
        $user->id = (int) $row['id'];
        $user->firstName = $row['first_name'];
        $user->lastName = $row['last_name'];
        $user->email = $row['email'];
        $user->passwordHash = $row['password_hash'];
        $user->phone = $row['phone'];
        $user->role = $row['role'];
        $user->address = $row['address'];
        $user->postalCode = $row['postal_code'];
        $user->city = $row['city'];
        $user->province = $row['province'];
        $user->failedAttempts = (int) $row['failed_attempts'];
        $user->lockedUntil = $row['locked_until'];
        $user->createdAt = $row['created_at'];

        return $user;
    }
}
