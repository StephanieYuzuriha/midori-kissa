<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Models\Order;
use App\Models\OrderItem;
use DomainException;

// Orders of the logged-in customer. The customer always comes from the session,
// so nobody can create or see orders of another user (RS7)
class OrderController extends Controller
{
    private const MAX_LINES = 50;
    private const MAX_QUANTITY = 99;

    // Shipping rates to show the costs in the cart (RF19). The server calculates them again when ordering
    public function shipping(): void
    {
        Response::json(Order::shippingRates());
    }

    // Creates an order from the cart (RF20, RF24). Example body:
    // {"items": [{"productId": 1, "quantity": 2}],
    //  "shipping": {"name": "...", "address": "...", "postalCode": "39700", "city": "...", "province": "...", "phone": "..."}}
    // Only the product ids and the units are read from the cart: prices come from the database (RS15)
    public function store(): void
    {
        $user = $this->auth->requireLogin();

        $data = $this->readJson();
        $shipping = is_array($data['shipping'] ?? null) ? $data['shipping'] : [];
        $errors = $this->validate($shipping, [
            'name' => ['required', 'max:150'],
            'address' => ['required', 'max:200'],
            'postalCode' => ['required', 'postal_code'],
            'city' => ['required', 'max:100'],
            'province' => ['required', 'max:100'],
            'phone' => ['required', 'max:20'],
        ]);
        $items = $this->readItems($data['items'] ?? null);
        if ($items === null) {
            $errors['items'] = 'El carrito no es válido.';
        }
        if ($errors) {
            Response::json(['errors' => $errors], 422);
            return;
        }

        $order = new Order();
        $order->userId = $user->id;
        $order->items = $items;
        foreach (['name', 'address', 'postalCode', 'city', 'province', 'phone'] as $field) {
            $order->shippingData[$field] = trim($shipping[$field]);
        }

        try {
            $order->create();
        } catch (DomainException $e) {
            // A product is sold out, has fewer units or is no longer for sale
            Response::error($e->getMessage(), 409);
            return;
        }

        Response::json(['order' => $order->toArray()], 201);
    }

    // Order history of the customer, newest first (RF26)
    public function myOrders(): void
    {
        $user = $this->auth->requireLogin();

        $orders = Order::findByUser($user->id);
        Response::json(['orders' => array_map(fn(Order $order) => $order->toArray(), $orders)]);
    }

    // Detail of an order (RF27). An order of another customer gives 404, as if it did not exist (RS7)
    public function show(string $orderNumber): void
    {
        $user = $this->auth->requireLogin();

        $order = Order::findByOrderNumber($orderNumber);
        if ($order === null || $order->userId !== $user->id) {
            Response::error('Pedido no encontrado', 404);
            return;
        }

        Response::json(['order' => $order->toArray()]);
    }

    // Lines of the cart: a list of {productId, quantity} with whole numbers, without repeated
    // products and with limits (RS9). Returns OrderItem[] or null if something is not valid
    private function readItems(mixed $lines): ?array
    {
        if (!is_array($lines) || !array_is_list($lines) || count($lines) === 0 || count($lines) > self::MAX_LINES) {
            return null;
        }

        $items = [];
        foreach ($lines as $line) {
            $productId = is_array($line) ? ($line['productId'] ?? null) : null;
            $quantity = is_array($line) ? ($line['quantity'] ?? null) : null;
            if (!is_int($productId) || $productId < 1 || isset($items[$productId])
                || !is_int($quantity) || $quantity < 1 || $quantity > self::MAX_QUANTITY) {
                return null;
            }

            $item = new OrderItem();
            $item->productId = $productId;
            $item->quantity = $quantity;
            $items[$productId] = $item;
        }

        return array_values($items);
    }
}
