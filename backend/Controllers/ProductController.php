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

    // Product photos (RS12): JPG, PNG or WebP of up to 2 MB. They are saved as WebP with their
    // own proportions, reduced to 1200 px on the longest side if they are bigger
    private const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    private const IMAGE_MAX_BYTES = 2 * 1024 * 1024;
    private const IMAGE_MAX_PIXELS = 16000000;
    private const IMAGE_MAX_SIDE = 1200;
    private const WEBP_QUALITY = 80;
    private const MAX_IMAGES = 6;

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

        // The rows of its photos would be deleted by the database; the files are deleted here
        foreach ($product->getMedia() as $media) {
            $media->delete();
        }
        $product->delete();
        Response::json(['message' => 'Producto eliminado']);
    }

    // Uploads a photo of a product (RF29, RS12). Form fields: image (the file) and altText,
    // which is required (5.2: every product photo has a descriptive alternative text)
    public function uploadImage(int $id): void
    {
        $this->auth->requireAdmin();

        $product = Product::findById($id);
        if ($product === null) {
            Response::error('Producto no encontrado', 404);
            return;
        }

        $errors = $this->validate($_POST, ['altText' => ['required', 'max:150']]);
        $file = $_FILES['image'] ?? null;
        $imageError = $this->checkImage($file);
        if ($imageError !== null) {
            $errors['image'] = $imageError;
        } elseif (count($product->getMedia()) >= self::MAX_IMAGES) {
            $errors['image'] = 'Cada producto puede tener como máximo ' . self::MAX_IMAGES . ' fotos.';
        }
        if ($errors) {
            Response::json(['errors' => $errors], 422);
            return;
        }

        $fileName = $this->saveAsWebp($file['tmp_name']);
        if ($fileName === null) {
            Response::json(['errors' => ['image' => 'No se ha podido procesar la foto. Prueba con otro archivo.']], 422);
            return;
        }

        $config = require __DIR__ . '/../config/config.php';
        $media = new Media();
        $media->productId = $product->id;
        $media->context = 'product';
        $media->path = $config['uploads']['url'] . '/' . $fileName;
        $media->altText = trim($_POST['altText']);
        $media->save();

        Response::json(['image' => $media->toArray()], 201);
    }

    // Deletes a photo of a product
    public function deleteImage(int $id): void
    {
        $this->auth->requireAdmin();

        $media = Media::findById($id);
        if ($media === null || $media->productId === null) {
            Response::error('Foto no encontrada', 404);
            return;
        }

        $media->delete();
        Response::json(['message' => 'Foto eliminada']);
    }

    // Checks the uploaded file: no upload errors, up to 2 MB, and a real JPG, PNG or WebP image.
    // The type is read from the content of the file, not from its name or from the browser (RS12)
    private function checkImage(mixed $file): ?string
    {
        if (!is_array($file) || !isset($file['error']) || !is_int($file['error'])) {
            return 'Selecciona una foto.';
        }
        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
            return 'La foto no puede ocupar más de 2 MB.';
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            return 'No se ha podido subir la foto. Inténtalo de nuevo.';
        }
        if ($file['size'] > self::IMAGE_MAX_BYTES) {
            return 'La foto no puede ocupar más de 2 MB.';
        }

        $type = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $size = getimagesize($file['tmp_name']);
        if (!in_array($type, self::IMAGE_TYPES, true) || $size === false) {
            return 'La foto debe ser JPG, PNG o WebP.';
        }
        // Very big images would need too much memory to be converted
        if ($size[0] * $size[1] > self::IMAGE_MAX_PIXELS) {
            return 'La foto tiene demasiada resolución (máximo 16 megapíxeles).';
        }

        return null;
    }

    // Converts the photo to WebP with a random name and saves it in the uploads folder.
    // It keeps the proportions and the transparency; it never makes small photos bigger
    private function saveAsWebp(string $tmpFile): ?string
    {
        $image = @imagecreatefromstring(file_get_contents($tmpFile));
        if ($image === false) {
            return null;
        }
        imagepalettetotruecolor($image);

        $width = imagesx($image);
        $height = imagesy($image);
        $longestSide = max($width, $height);
        if ($longestSide > self::IMAGE_MAX_SIDE) {
            $ratio = self::IMAGE_MAX_SIDE / $longestSide;
            $resized = imagescale($image, (int) round($width * $ratio), (int) round($height * $ratio));
            imagedestroy($image);
            if ($resized === false) {
                return null;
            }
            $image = $resized;
        }
        imagesavealpha($image, true);

        $config = require __DIR__ . '/../config/config.php';
        $fileName = bin2hex(random_bytes(16)) . '.webp';
        $saved = imagewebp($image, $config['uploads']['dir'] . '/' . $fileName, self::WEBP_QUALITY);
        imagedestroy($image);

        return $saved ? $fileName : null;
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
