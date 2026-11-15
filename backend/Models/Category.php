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
