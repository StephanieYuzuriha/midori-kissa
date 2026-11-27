<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use DateTime;
use DomainException;

// Orders of the logged-in customer. The customer always comes from the session,
// so nobody can create or see orders of another user (RS7)
class OrderController extends Controller
{
    private const MAX_LINES = 50;
    private const MAX_QUANTITY = 99;
    private const MAX_PAGE = 1000;

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

    // Admin panel: all the orders with filters by status and dates and pages (RF33).
    // Example: GET /api/admin/orders?status=paid&from=2026-12-01&to=2026-12-31&page=1
    public function index(): void
    {
        $this->auth->requireAdmin();

        $status = $this->queryParam('status');
        $from = $this->queryParam('from');
        $to = $this->queryParam('to');
        $page = $this->queryParam('page');

        $errors = [];
        if ($status === null || ($status !== '' && !array_key_exists($status, Order::STATUS_LABELS))) {
            $errors['status'] = 'El estado no es válido.';
        }
        if ($from === null || ($from !== '' && !$this->isDate($from))) {
            $errors['from'] = 'La fecha no es válida.';
        }
        if ($to === null || ($to !== '' && !$this->isDate($to))) {
            $errors['to'] = 'La fecha no es válida.';
        }
        if (!$errors && $from !== '' && $to !== '' && $from > $to) {
            $errors['to'] = 'La fecha final no puede ser anterior a la inicial.';
        }
        $pageNumber = $page === '' ? 1 : filter_var($page, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => self::MAX_PAGE],
        ]);
        if ($pageNumber === false) {
            $errors['page'] = 'La página no es válida.';
        }
        if ($errors) {
            Response::json(['errors' => $errors], 422);
            return;
        }

        $result = Order::findAll($status ?: null, $from ?: null, $to ?: null, $pageNumber);

        Response::json([
            'orders' => array_map(fn(Order $order) => $order->toArray() + ['customer' => $order->customer], $result['orders']),
            'total' => $result['total'],
            'page' => $pageNumber,
            'pages' => (int) ceil($result['total'] / Order::ADMIN_PER_PAGE),
        ]);
    }

    // Admin panel: detail of any order with the customer data
    public function adminShow(string $orderNumber): void
    {
        $this->auth->requireAdmin();

        $order = Order::findByOrderNumber($orderNumber);
        if ($order === null) {
            Response::error('Pedido no encontrado', 404);
            return;
        }

        Response::json(['order' => $this->adminOrderData($order)]);
    }

    // Changes the status of an order (RF34). Body: {"status": "shipped"}.
    // Allowed: Pagado → Enviado → Entregado, and cancel a pending order, which returns the stock
    public function changeStatus(string $orderNumber): void
    {
        $this->auth->requireAdmin();

        $order = Order::findByOrderNumber($orderNumber);
        if ($order === null) {
            Response::error('Pedido no encontrado', 404);
            return;
        }

        $status = $this->readJson()['status'] ?? null;
        if (!is_string($status) || !array_key_exists($status, Order::STATUS_LABELS)) {
            Response::json(['errors' => ['status' => 'El estado no es válido.']], 422);
            return;
        }

        if ($status === 'cancelled') {
            $changed = $order->cancel();
        } elseif ((Order::NEXT_STATUS[$order->status] ?? null) === $status) {
            $changed = $order->changeStatus($status);
        } else {
            $changed = false;
        }
        if (!$changed) {
            $current = Order::findByOrderNumber($orderNumber);
            $message = $status === 'cancelled' && $current->status !== 'cancelled'
                ? 'Solo se pueden cancelar los pedidos pendientes de pago. Un pedido pagado necesita un reembolso, que no se gestiona desde la tienda.'
                : 'Un pedido «' . Order::STATUS_LABELS[$current->status] . '» no se puede pasar a «' . Order::STATUS_LABELS[$status] . '».';
            Response::error($message, 409);
            return;
        }

        Response::json(['order' => $this->adminOrderData(Order::findByOrderNumber($orderNumber))]);
    }

    // Order data for the panel: the order, the customer and the next statuses it can have
    private function adminOrderData(Order $order): array
    {
        $user = User::findById($order->userId);
        $next = [];
        if (isset(Order::NEXT_STATUS[$order->status])) {
            $next[] = Order::NEXT_STATUS[$order->status];
        }
        if ($order->status === 'pending_payment') {
            $next[] = 'cancelled';
        }

        return $order->toArray() + [
            'customer' => [
                'name' => $user->firstName . ' ' . $user->lastName,
                'email' => $user->email,
                'phone' => $user->phone,
            ],
            'nextStatuses' => $next,
        ];
    }

    // Date in the format YYYY-MM-DD that exists in the calendar
    private function isDate(string $value): bool
    {
        $date = DateTime::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
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
