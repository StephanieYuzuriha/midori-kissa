<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Models\Category;
use App\Models\Media;
use App\Models\Product;

class ProductController extends Controller
{
    private const SEARCH_MAX_LENGTH = 100;
    private const MAX_PAGE = 1000;

    // Limits of the product data in the admin panel (RS9)
    private const MAX_PRICE = 999999.99;
    private const MAX_WEIGHT = 65535;
    private const MAX_STOCK = 99999;
    private const GRADES = ['ceremonial', 'culinary'];

    // Catalog: active products with category filter, search by name, sorting and pages (RF10–RF13).
    // Example: GET /api/products?category=1&search=uji&sort=price_asc&page=2
    public function index(): void
    {
        $category = $this->queryParam('category');
        $search = $this->queryParam('search');
        $sort = $this->queryParam('sort');
        $page = $this->queryParam('page');

        // The filters are checked in the server too (RS9). Empty filters are not applied
        $errors = [];
        $categoryId = null;
        if ($category !== '') {
            if ($category !== null && $this->categoryExists($category)) {
                $categoryId = (int) $category;
            } else {
                $errors['category'] = 'La categoría no existe.';
            }
        }
        if ($search === null) {
            $errors['search'] = 'La búsqueda no es válida.';
        } elseif (mb_strlen($search) > self::SEARCH_MAX_LENGTH) {
            $errors['search'] = 'Máximo ' . self::SEARCH_MAX_LENGTH . ' caracteres.';
        }
        if ($sort === '') {
            $sort = 'newest';
        }
        if ($sort === null || !array_key_exists($sort, Product::SORT_OPTIONS)) {
            $errors['sort'] = 'El orden no es válido.';
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

        $result = Product::search($categoryId, $search, $sort, $pageNumber);

        Response::json([
            'products' => array_map(fn(Product $product) => $product->toArray(), $result['products']),
            'total' => $result['total'],
            'page' => $pageNumber,
            'pages' => (int) ceil($result['total'] / Product::PER_PAGE),
        ]);
    }

    // Product page with all its photos (RF14). Withdrawn or nonexistent products give 404
    public function show(int $id): void
    {
        $product = Product::findActiveById($id);
        if ($product === null) {
            Response::error('Producto no encontrado', 404);
            return;
        }

        $data = $product->toArray();
        $data['images'] = array_map(fn(Media $media) => $media->toArray(), $product->getMedia());
        Response::json(['product' => $data]);
    }

    // Admin panel: all the products, hidden ones included, with search, filters and pages (RF32).
    // Example: GET /api/admin/products?search=uji&category=1&status=hidden&stock=low&page=2
    public function adminIndex(): void
    {
        $this->auth->requireAdmin();

        $category = $this->queryParam('category');
        $search = $this->queryParam('search');
        $status = $this->queryParam('status');
        $stock = $this->queryParam('stock');
        $page = $this->queryParam('page');

        $errors = [];
        $categoryId = null;
        if ($category !== '') {
            if ($category !== null && $this->categoryExists($category)) {
                $categoryId = (int) $category;
            } else {
                $errors['category'] = 'La categoría no existe.';
            }
        }
        if ($search === null) {
            $errors['search'] = 'La búsqueda no es válida.';
        } elseif (mb_strlen($search) > self::SEARCH_MAX_LENGTH) {
            $errors['search'] = 'Máximo ' . self::SEARCH_MAX_LENGTH . ' caracteres.';
        }
        $statuses = ['' => null, 'active' => true, 'hidden' => false];
        if ($status === null || !array_key_exists($status, $statuses)) {
            $errors['status'] = 'El estado no es válido.';
        }
        if ($stock !== '' && $stock !== 'low') {
            $errors['stock'] = 'El filtro de stock no es válido.';
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

        $result = Product::adminSearch($categoryId, $search, $statuses[$status], $stock === 'low', $pageNumber);

        Response::json([
            'products' => array_map(
                fn(Product $product) => $product->toArray() + ['lowStock' => $product->isLowStock()],
                $result['products']
            ),
            'total' => $result['total'],
            'page' => $pageNumber,
            'pages' => (int) ceil($result['total'] / Product::ADMIN_PER_PAGE),
        ]);
    }

    // Admin panel: one product with its photos, also if it is hidden
    public function adminShow(int $id): void
    {
        $this->auth->requireAdmin();

        $product = Product::findById($id);
        if ($product === null) {
            Response::error('Producto no encontrado', 404);
            return;
        }

        $data = $product->toArray();
        $data['images'] = array_map(fn(Media $media) => $media->toArray(), $product->getMedia());
        Response::json(['product' => $data]);
    }

    // New product with all its data (RF29). The photos are uploaded afterwards
    public function store(): void
    {
        $this->auth->requireAdmin();

        $product = new Product();
        $errors = $this->fillProduct($product, $this->readJson());
        if ($errors) {
            Response::json(['errors' => $errors], 422);
            return;
        }

        $product->save();
        Response::json(['product' => Product::findById($product->id)->toArray()], 201);
    }

    // Changes in the data of a product (RF29)
    public function update(int $id): void
    {
        $this->auth->requireAdmin();

        $product = Product::findById($id);
        if ($product === null) {
            Response::error('Producto no encontrado', 404);
            return;
        }

        $errors = $this->fillProduct($product, $this->readJson());
        if ($errors) {
            Response::json(['errors' => $errors], 422);
            return;
        }

        $product->save();
        Response::json(['product' => Product::findById($product->id)->toArray()]);
    }

    // Publishes or hides a product in the shop (RF30). Body: {"isActive": true}
    public function setActive(int $id): void
    {
        $this->auth->requireAdmin();

        $product = Product::findById($id);
        if ($product === null) {
            Response::error('Producto no encontrado', 404);
            return;
        }

        $isActive = $this->readJson()['isActive'] ?? null;
        if (!is_bool($isActive)) {
            Response::json(['errors' => ['isActive' => 'El valor no es válido.']], 422);
            return;
        }

        $product->isActive = $isActive;
        $product->save();
        Response::json(['product' => $product->toArray()]);
    }

    // Changes the units in stock (RF32). Body: {"stock": 12}
    public function updateStock(int $id): void
    {
        $this->auth->requireAdmin();

        $product = Product::findById($id);
        if ($product === null) {
            Response::error('Producto no encontrado', 404);
            return;
        }

        $stock = $this->readJson()['stock'] ?? null;
        if (!is_int($stock) || $stock < 0 || $stock > self::MAX_STOCK) {
            Response::json(['errors' => ['stock' => 'El stock debe ser un número entero entre 0 y ' . self::MAX_STOCK . '.']], 422);
            return;
        }

        $product->stock = $stock;
        $product->save();
        Response::json(['product' => $product->toArray() + ['lowStock' => $product->isLowStock()]]);
    }

    // Deletes a product only if it has no orders; if it has, it must be hidden instead (RF30)
    public function destroy(int $id): void
    {
        $this->auth->requireAdmin();

        $product = Product::findById($id);
        if ($product === null) {
            Response::error('Producto no encontrado', 404);
            return;
        }

        if ($product->hasOrders()) {
            Response::error('Este producto tiene pedidos y no se puede eliminar. Ocúltalo para que no aparezca en la tienda.', 409);
            return;
        }

        $product->delete();
        Response::json(['message' => 'Producto eliminado']);
    }

    // Checks the data of the product form and copies it into the product (RS9).
    // Text fields come as strings; price, weight and stock as JSON numbers. Returns the errors by field
    private function fillProduct(Product $product, array $data): array
    {
        $errors = $this->validate($data, [
            'name' => ['required', 'max:150'],
            'description' => ['required', 'max:5000'],
            'originRegion' => ['max:100'],
            'cultivar' => ['max:100'],
            'preparation' => ['max:2000'],
        ]);

        $categoryId = $data['categoryId'] ?? null;
        if (!is_int($categoryId) || Category::findById($categoryId) === null) {
            $errors['categoryId'] = 'La categoría no existe.';
        }

        $price = $data['price'] ?? null;
        if (!is_int($price) && !is_float($price)) {
            $errors['price'] = 'Este campo es obligatorio.';
        } elseif ($price <= 0 || $price > self::MAX_PRICE || round($price, 2) != $price) {
            $errors['price'] = 'El precio debe ser mayor que 0 y tener como máximo dos decimales.';
        }

        $weight = $data['weightGrams'] ?? null;
        if ($weight !== null && (!is_int($weight) || $weight < 1 || $weight > self::MAX_WEIGHT)) {
            $errors['weightGrams'] = 'El formato debe ser un número entero de gramos.';
        }

        $grade = $data['grade'] ?? null;
        if ($grade !== null && !in_array($grade, self::GRADES, true)) {
            $errors['grade'] = 'El grado no es válido.';
        }

        $stock = $data['stock'] ?? null;
        if (!is_int($stock) || $stock < 0 || $stock > self::MAX_STOCK) {
            $errors['stock'] = 'El stock debe ser un número entero entre 0 y ' . self::MAX_STOCK . '.';
        }

        $isActive = $data['isActive'] ?? null;
        if (!is_bool($isActive)) {
            $errors['isActive'] = 'El valor no es válido.';
        }

        if ($errors) {
            return $errors;
        }

        $product->categoryId = $categoryId;
        $product->name = trim($data['name']);
        $product->description = trim($data['description']);
        $product->price = round((float) $price, 2);
        $product->weightGrams = $weight;
        $product->grade = $grade;
        $product->originRegion = $this->optionalText($data, 'originRegion');
        $product->cultivar = $this->optionalText($data, 'cultivar');
        $product->preparation = $this->optionalText($data, 'preparation');
        $product->stock = $stock;
        $product->isActive = $isActive;

        return [];
    }

    // Value of a query string parameter without spaces at the ends, '' if it is missing,
    // or null if it is not a text (for example ?search[]=x)
    private function queryParam(string $name): ?string
    {
        $value = $_GET[$name] ?? '';

        return is_string($value) ? trim($value) : null;
    }

    private function categoryExists(string $category): bool
    {
        $id = filter_var($category, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $id !== false && Category::findById($id) !== null;
    }
}
