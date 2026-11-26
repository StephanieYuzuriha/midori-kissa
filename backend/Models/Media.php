<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

// Images of the shop. Product photos have a product_id; images of the website do not
class Media extends Model
{
    public int $id = 0;
    public ?int $productId = null;
    public string $context = 'product';
    public string $path = '';
    public string $altText = '';
    public int $sortOrder = 1;

    public static function findById(int $id): ?Media
    {
        $stmt = Database::getConnection()->prepare('SELECT * FROM media WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? self::fromRow($row) : null;
    }

    // Photos of a product, in the order they are shown
    public static function findByProduct(int $productId): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT * FROM media WHERE product_id = ? ORDER BY sort_order, id'
        );
        $stmt->execute([$productId]);

        return array_map(fn(array $row) => self::fromRow($row), $stmt->fetchAll());
    }

    // Saves a new photo of a product after the last one (RF29)
    public function save(): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO media (product_id, context, path, alt_text, sort_order)
             SELECT ?, ?, ?, ?, COALESCE(MAX(sort_order), 0) + 1 FROM media WHERE product_id = ?'
        );
        $saved = $stmt->execute([$this->productId, $this->context, $this->path, $this->altText, $this->productId]);
        $this->id = (int) $this->pdo->lastInsertId();

        return $saved;
    }

    // Deletes the photo and, if it was uploaded from the panel, also its file.
    // The test images of the project are part of the Angular app and are not deleted
    public function delete(): bool
    {
        $config = require __DIR__ . '/../config/config.php';
        $prefix = $config['uploads']['url'] . '/';
        if (str_starts_with($this->path, $prefix)) {
            $file = $config['uploads']['dir'] . '/' . basename($this->path);
            if (is_file($file)) {
                unlink($file);
            }
        }

        $stmt = $this->pdo->prepare('DELETE FROM media WHERE id = ?');

        return $stmt->execute([$this->id]);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'path' => $this->path,
            'altText' => $this->altText,
        ];
    }

    private static function fromRow(array $row): Media
    {
        $media = new Media();
        $media->id = (int) $row['id'];
        $media->productId = $row['product_id'] === null ? null : (int) $row['product_id'];
        $media->context = $row['context'];
        $media->path = $row['path'];
        $media->altText = $row['alt_text'];
        $media->sortOrder = (int) $row['sort_order'];

        return $media;
    }
}
