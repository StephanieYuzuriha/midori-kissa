<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Category extends Model
{
    public int $id = 0;
    public string $name = '';
    public ?string $description = null;

    public static function findAll(): array
    {
        $stmt = Database::getConnection()->query('SELECT * FROM categories ORDER BY id');

        return array_map(fn(array $row) => self::fromRow($row), $stmt->fetchAll());
    }

    public static function findById(int $id): ?Category
    {
        $stmt = Database::getConnection()->prepare('SELECT * FROM categories WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? self::fromRow($row) : null;
    }

    // True if another category already has this name (the name is unique)
    public static function nameExists(string $name, int $exceptId = 0): bool
    {
        $stmt = Database::getConnection()->prepare('SELECT 1 FROM categories WHERE name = ? AND id <> ?');
        $stmt->execute([$name, $exceptId]);

        return $stmt->fetchColumn() !== false;
    }

    // Creates the category or saves its changes (RF31)
    public function save(): bool
    {
        if ($this->id === 0) {
            $stmt = $this->pdo->prepare('INSERT INTO categories (name, description) VALUES (?, ?)');
            $saved = $stmt->execute([$this->name, $this->description]);
            $this->id = (int) $this->pdo->lastInsertId();

            return $saved;
        }

        $stmt = $this->pdo->prepare('UPDATE categories SET name = ?, description = ? WHERE id = ?');

        return $stmt->execute([$this->name, $this->description, $this->id]);
    }

    // Number of products of the category, hidden ones included
    public function countProducts(): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?');
        $stmt->execute([$this->id]);

        return (int) $stmt->fetchColumn();
    }

    // A category can only be deleted if it has no products (RF31)
    public function hasProducts(): bool
    {
        return $this->countProducts() > 0;
    }

    public function delete(): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM categories WHERE id = ?');

        return $stmt->execute([$this->id]);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
        ];
    }

    private static function fromRow(array $row): Category
    {
        $category = new Category();
        $category->id = (int) $row['id'];
        $category->name = $row['name'];
        $category->description = $row['description'];

        return $category;
    }
}
