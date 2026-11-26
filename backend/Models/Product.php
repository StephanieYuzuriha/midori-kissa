<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Product extends Model
{
    // Products per page in the catalog (RF10) and in the admin panel
    public const PER_PAGE = 10;
    public const ADMIN_PER_PAGE = 20;

    // The admin list highlights the products with fewer units than this (RF32)
    public const LOW_STOCK = 5;

    // Allowed sort options and their ORDER BY. The request only chooses a key,
    // so no text from the user is ever added to the SQL (RF13)
    public const SORT_OPTIONS = [
        'newest' => 'p.created_at DESC, p.id DESC',
        'price_asc' => 'p.price ASC, p.id ASC',
        'price_desc' => 'p.price DESC, p.id DESC',
    ];

    public int $id = 0;
    public int $categoryId = 0;
    public string $categoryName = '';
    public string $name = '';
    public string $description = '';
    public float $price = 0;
    public ?int $weightGrams = null;
    public ?string $grade = null;
    public ?string $originRegion = null;
    public ?string $cultivar = null;
    public ?string $preparation = null;
    public int $stock = 0;
    public bool $isActive = true;
    public string $createdAt = '';
    // Path of the first photo, used in the catalog cards
    public ?string $image = null;

    // Active products that match the filters, one page at a time (RF10–RF13).
    // Returns ['products' => Product[], 'total' => number of products with those filters]
    public static function search(?int $categoryId, string $search, string $sort, int $page): array
    {
        $conditions = ['p.is_active = 1'];
        $params = [];

        if ($categoryId !== null) {
            $conditions[] = 'p.category_id = ?';
            $params[] = $categoryId;
        }
        if ($search !== '') {
            $conditions[] = 'p.name LIKE ?';
            $params[] = '%' . $search . '%';
        }
        $where = implode(' AND ', $conditions);

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE $where");
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        // $orderBy comes from SORT_OPTIONS and LIMIT/OFFSET are integers calculated here
        $orderBy = self::SORT_OPTIONS[$sort];
        $offset = ($page - 1) * self::PER_PAGE;
        $stmt = $pdo->prepare(
            "SELECT p.*, c.name AS category_name,
                (SELECT m.path FROM media m WHERE m.product_id = p.id
                 ORDER BY m.sort_order, m.id LIMIT 1) AS image
             FROM products p
             JOIN categories c ON c.id = p.category_id
             WHERE $where
             ORDER BY $orderBy
             LIMIT " . self::PER_PAGE . " OFFSET $offset"
        );
        $stmt->execute($params);
        $products = array_map(fn(array $row) => self::fromRow($row), $stmt->fetchAll());

        return ['products' => $products, 'total' => $total];
    }

    // All the products, hidden ones included, for the admin panel (RF29, RF32).
    // $isActive null = all; $lowStock = only products with fewer than LOW_STOCK units
    public static function adminSearch(?int $categoryId, string $search, ?bool $isActive, bool $lowStock, int $page): array
    {
        $conditions = ['1 = 1'];
        $params = [];

        if ($categoryId !== null) {
            $conditions[] = 'p.category_id = ?';
            $params[] = $categoryId;
        }
        if ($search !== '') {
            $conditions[] = 'p.name LIKE ?';
            $params[] = '%' . $search . '%';
        }
        if ($isActive !== null) {
            $conditions[] = 'p.is_active = ?';
            $params[] = (int) $isActive;
        }
        if ($lowStock) {
            $conditions[] = 'p.stock < ' . self::LOW_STOCK;
        }
        $where = implode(' AND ', $conditions);

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE $where");
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        $offset = ($page - 1) * self::ADMIN_PER_PAGE;
        $stmt = $pdo->prepare(
            "SELECT p.*, c.name AS category_name,
                (SELECT m.path FROM media m WHERE m.product_id = p.id
                 ORDER BY m.sort_order, m.id LIMIT 1) AS image
             FROM products p
             JOIN categories c ON c.id = p.category_id
             WHERE $where
             ORDER BY p.name, p.id
             LIMIT " . self::ADMIN_PER_PAGE . " OFFSET $offset"
        );
        $stmt->execute($params);
        $products = array_map(fn(array $row) => self::fromRow($row), $stmt->fetchAll());

        return ['products' => $products, 'total' => $total];
    }

    // Any product, also a hidden one, for the admin panel
    public static function findById(int $id): ?Product
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT p.*, c.name AS category_name
             FROM products p
             JOIN categories c ON c.id = p.category_id
             WHERE p.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? self::fromRow($row) : null;
    }

    // Product for the product page. Withdrawn products (is_active = 0) are not shown (RF30)
    public static function findActiveById(int $id): ?Product
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT p.*, c.name AS category_name
             FROM products p
             JOIN categories c ON c.id = p.category_id
             WHERE p.id = ? AND p.is_active = 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? self::fromRow($row) : null;
    }

    public function getMedia(): array
    {
        return Media::findByProduct($this->id);
    }

    // Creates the product or saves its changes (RF29, RF30, RF32)
    public function save(): bool
    {
        $values = [
            $this->categoryId,
            $this->name,
            $this->description,
            $this->price,
            $this->weightGrams,
            $this->grade,
            $this->originRegion,
            $this->cultivar,
            $this->preparation,
            $this->stock,
            (int) $this->isActive,
        ];

        if ($this->id === 0) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO products (category_id, name, description, price, weight_grams, grade,
                    origin_region, cultivar, preparation, stock, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $saved = $stmt->execute($values);
            $this->id = (int) $this->pdo->lastInsertId();

            return $saved;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE products SET category_id = ?, name = ?, description = ?, price = ?, weight_grams = ?,
                grade = ?, origin_region = ?, cultivar = ?, preparation = ?, stock = ?, is_active = ?
             WHERE id = ?'
        );

        return $stmt->execute([...$values, $this->id]);
    }

    // A product that appears in some order cannot be deleted, so the order history is kept (RF30)
    public function hasOrders(): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM order_items WHERE product_id = ? LIMIT 1');
        $stmt->execute([$this->id]);

        return $stmt->fetchColumn() !== false;
    }

    // Deletes the product. Its photos are deleted with it (ON DELETE CASCADE in media)
    public function delete(): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM products WHERE id = ?');

        return $stmt->execute([$this->id]);
    }

    public function isLowStock(): bool
    {
        return $this->stock < self::LOW_STOCK;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'categoryId' => $this->categoryId,
            'categoryName' => $this->categoryName,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'weightGrams' => $this->weightGrams,
            'grade' => $this->grade,
            'originRegion' => $this->originRegion,
            'cultivar' => $this->cultivar,
            'preparation' => $this->preparation,
            'stock' => $this->stock,
            'isActive' => $this->isActive,
            'image' => $this->image,
        ];
    }

    private static function fromRow(array $row): Product
    {
        $product = new Product();
        $product->id = (int) $row['id'];
        $product->categoryId = (int) $row['category_id'];
        $product->categoryName = $row['category_name'];
        $product->name = $row['name'];
        $product->description = $row['description'];
        $product->price = (float) $row['price'];
        $product->weightGrams = $row['weight_grams'] === null ? null : (int) $row['weight_grams'];
        $product->grade = $row['grade'];
        $product->originRegion = $row['origin_region'];
        $product->cultivar = $row['cultivar'];
        $product->preparation = $row['preparation'];
        $product->stock = (int) $row['stock'];
        $product->isActive = (bool) $row['is_active'];
        $product->createdAt = $row['created_at'];
        $product->image = $row['image'] ?? null;

        return $product;
    }
}
