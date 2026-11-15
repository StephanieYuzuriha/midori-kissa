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

    // Photos of a product, in the order they are shown
    public static function findByProduct(int $productId): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT * FROM media WHERE product_id = ? ORDER BY sort_order, id'
        );
        $stmt->execute([$productId]);

        return array_map(fn(array $row) => self::fromRow($row), $stmt->fetchAll());
    }

    public function toArray(): array
    {
        return [
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
