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
