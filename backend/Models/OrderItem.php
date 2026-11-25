<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

// A line of an order: product, units and the price paid for each unit
class OrderItem extends Model
{
    public int $orderId = 0;
    public int $productId = 0;
    public int $quantity = 0;
    public float $unitPrice = 0;
    // Name and first photo of the product, only to show the order (not stored in order_items)
    public string $productName = '';
    public ?string $productImage = null;

    // Lines of an order with the name and first photo of each product
    public static function findByOrder(int $orderId): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT oi.*, p.name AS product_name,
                (SELECT m.path FROM media m WHERE m.product_id = p.id
                 ORDER BY m.sort_order, m.id LIMIT 1) AS product_image
             FROM order_items oi
             JOIN products p ON p.id = oi.product_id
             WHERE oi.order_id = ?
             ORDER BY p.name'
        );
        $stmt->execute([$orderId]);

        return array_map(fn(array $row) => self::fromRow($row), $stmt->fetchAll());
    }

    public function getSubtotal(): float
    {
        return round($this->unitPrice * $this->quantity, 2);
    }

    public function toArray(): array
    {
        return [
            'productId' => $this->productId,
            'productName' => $this->productName,
            'productImage' => $this->productImage,
            'quantity' => $this->quantity,
            'unitPrice' => $this->unitPrice,
            'subtotal' => $this->getSubtotal(),
        ];
    }

    private static function fromRow(array $row): OrderItem
    {
        $item = new OrderItem();
        $item->orderId = (int) $row['order_id'];
        $item->productId = (int) $row['product_id'];
        $item->quantity = (int) $row['quantity'];
        $item->unitPrice = (float) $row['unit_price'];
        $item->productName = $row['product_name'];
        $item->productImage = $row['product_image'];

        return $item;
    }
}
