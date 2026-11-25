<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use DomainException;
use Throwable;

class Order extends Model
{
    public int $id = 0;
    // Public number that the customer sees, for example MK-2027-7F3K9Q
    public string $orderNumber = '';
    public int $userId = 0;
    public string $createdAt = '';
    public string $status = 'pending_payment';
    public float $subtotal = 0;
    public float $shippingCost = 0;
    public float $total = 0;
    // Copy of the shipping data: name, address, postalCode, city, province and phone
    public array $shippingData = [];
    public ?string $stripeSessionId = null;
    /** @var OrderItem[] */
    public array $items = [];

    // Shipping rates from the configuration (RF19)
    public static function shippingRates(): array
    {
        $config = require __DIR__ . '/../config/config.php';

        return $config['shipping'];
    }

    /**
     * Saves a new order with its lines and takes the units out of the stock (RF24).
     * The prices come from the database, never from the cart (RS15).
     * Everything is done in one transaction: if a product is not available, nothing is saved.
     * Throws DomainException with a message for the customer if a product cannot be bought.
     */
    public function create(): int
    {
        $this->pdo->beginTransaction();
        try {
            // FOR UPDATE locks the product rows until the end of the transaction,
            // so two orders at the same time cannot sell the same units
            $stmt = $this->pdo->prepare('SELECT name, price, stock, is_active FROM products WHERE id = ? FOR UPDATE');
            foreach ($this->items as $item) {
                $stmt->execute([$item->productId]);
                $product = $stmt->fetch();
                if (!$product || !$product['is_active']) {
                    throw new DomainException('Uno de los productos del carrito ya no está a la venta. Quítalo del carrito para continuar.');
                }
                $stock = (int) $product['stock'];
                if ($stock === 0) {
                    throw new DomainException("«{$product['name']}» está agotado. Quítalo del carrito para continuar.");
                }
                if ($stock < $item->quantity) {
                    throw new DomainException("Solo quedan $stock unidades de «{$product['name']}». Cambia la cantidad en el carrito.");
                }
                $item->unitPrice = (float) $product['price'];
                $item->productName = $product['name'];
            }

            $this->calculateTotals();
            $this->orderNumber = $this->generateOrderNumber();

            $stmt = $this->pdo->prepare(
                'INSERT INTO orders (order_number, user_id, status, subtotal, shipping_cost, total,
                    shipping_name, shipping_address, shipping_postal_code, shipping_city,
                    shipping_province, shipping_phone)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $this->orderNumber, $this->userId, $this->status, $this->subtotal, $this->shippingCost, $this->total,
                $this->shippingData['name'], $this->shippingData['address'], $this->shippingData['postalCode'],
                $this->shippingData['city'], $this->shippingData['province'], $this->shippingData['phone'],
            ]);
            $this->id = (int) $this->pdo->lastInsertId();

            $insertItem = $this->pdo->prepare(
                'INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)'
            );
            $updateStock = $this->pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');
            foreach ($this->items as $item) {
                $item->orderId = $this->id;
                $insertItem->execute([$this->id, $item->productId, $item->quantity, $item->unitPrice]);
                $updateStock->execute([$item->quantity, $item->productId]);
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        // Read it again to have the date and the photos of the products
        $saved = self::findByOrderNumber($this->orderNumber);
        $this->createdAt = $saved->createdAt;
        $this->items = $saved->items;

        return $this->id;
    }

    // Subtotal, shipping costs and total (RF19). Shipping is free from the amount in the configuration
    public function calculateTotals(): void
    {
        $rates = self::shippingRates();
        $this->subtotal = round(array_sum(array_map(fn(OrderItem $item) => $item->getSubtotal(), $this->items)), 2);
        $this->shippingCost = $this->subtotal >= $rates['freeFrom'] ? 0.0 : $rates['flatRate'];
        $this->total = round($this->subtotal + $this->shippingCost, 2);
    }

    // Order with its lines, or null if it does not exist
    public static function findByOrderNumber(string $orderNumber): ?Order
    {
        $stmt = Database::getConnection()->prepare('SELECT * FROM orders WHERE order_number = ?');
        $stmt->execute([$orderNumber]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $order = self::fromRow($row);
        $order->items = OrderItem::findByOrder($order->id);

        return $order;
    }

    // Orders of a customer, newest first, without their lines (RF26)
    public static function findByUser(int $userId): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC, id DESC'
        );
        $stmt->execute([$userId]);

        return array_map(fn(array $row) => self::fromRow($row), $stmt->fetchAll());
    }

    public function toArray(): array
    {
        return [
            'orderNumber' => $this->orderNumber,
            'createdAt' => $this->createdAt,
            'status' => $this->status,
            'subtotal' => $this->subtotal,
            'shippingCost' => $this->shippingCost,
            'total' => $this->total,
            'shipping' => $this->shippingData,
            'items' => array_map(fn(OrderItem $item) => $item->toArray(), $this->items),
        ];
    }

    // Public number MK-YEAR-XXXXXX with 6 random letters and digits. It is not consecutive,
    // so it does not show how many orders the shop has. If it already exists, another one is made
    private function generateOrderNumber(): string
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM orders WHERE order_number = ?');
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $characters[random_int(0, strlen($characters) - 1)];
            }
            $number = 'MK-' . date('Y') . '-' . $code;
            $stmt->execute([$number]);
        } while ((int) $stmt->fetchColumn() > 0);

        return $number;
    }

    private static function fromRow(array $row): Order
    {
        $order = new Order();
        $order->id = (int) $row['id'];
        $order->orderNumber = $row['order_number'];
        $order->userId = (int) $row['user_id'];
        // ISO format (2027-01-08T10:30:00) so that Angular can read the date
        $order->createdAt = str_replace(' ', 'T', $row['created_at']);
        $order->status = $row['status'];
        $order->subtotal = (float) $row['subtotal'];
        $order->shippingCost = (float) $row['shipping_cost'];
        $order->total = (float) $row['total'];
        $order->shippingData = [
            'name' => $row['shipping_name'],
            'address' => $row['shipping_address'],
            'postalCode' => $row['shipping_postal_code'],
            'city' => $row['shipping_city'],
            'province' => $row['shipping_province'],
            'phone' => $row['shipping_phone'],
        ];
        $order->stripeSessionId = $row['stripe_session_id'];

        return $order;
    }
}
