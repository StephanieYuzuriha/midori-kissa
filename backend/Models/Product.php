<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Product extends Model
{
    // Products per page in the catalog (RF10)
    public const PER_PAGE = 10;

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
