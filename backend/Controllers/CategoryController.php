<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Models\Category;

class CategoryController extends Controller
{
    // Categories for the catalog filter (RF11)
    public function index(): void
    {
        $categories = array_map(fn(Category $category) => $category->toArray(), Category::findAll());
        Response::json(['categories' => $categories]);
    }

    // Admin panel: categories with the number of products of each one (RF31)
    public function adminIndex(): void
    {
        $this->auth->requireAdmin();

        $categories = array_map(
            fn(Category $category) => $category->toArray() + ['productCount' => $category->countProducts()],
            Category::findAll()
        );
        Response::json(['categories' => $categories]);
    }

    public function store(): void
    {
        $this->auth->requireAdmin();

        $category = new Category();
        $errors = $this->fillCategory($category, $this->readJson());
        if ($errors) {
            Response::json(['errors' => $errors], 422);
            return;
        }

        $category->save();
        Response::json(['category' => $category->toArray() + ['productCount' => 0]], 201);
    }

    public function update(int $id): void
    {
        $this->auth->requireAdmin();

        $category = Category::findById($id);
        if ($category === null) {
            Response::error('Categoría no encontrada', 404);
            return;
        }

        $errors = $this->fillCategory($category, $this->readJson());
        if ($errors) {
            Response::json(['errors' => $errors], 422);
            return;
        }

        $category->save();
        Response::json(['category' => $category->toArray() + ['productCount' => $category->countProducts()]]);
    }

    // A category with products cannot be deleted (RF31; products → categories is RESTRICT)
    public function destroy(int $id): void
    {
        $this->auth->requireAdmin();

        $category = Category::findById($id);
        if ($category === null) {
            Response::error('Categoría no encontrada', 404);
            return;
        }

        if ($category->hasProducts()) {
            Response::error('Esta categoría tiene productos y no se puede eliminar. Pasa antes sus productos a otra categoría.', 409);
            return;
        }

        $category->delete();
        Response::json(['message' => 'Categoría eliminada']);
    }

    // Checks the data of the category form and copies it into the category (RS9)
    private function fillCategory(Category $category, array $data): array
    {
        $errors = $this->validate($data, [
            'name' => ['required', 'max:80'],
            'description' => ['max:500'],
        ]);
        if (!isset($errors['name']) && Category::nameExists(trim($data['name']), $category->id)) {
            $errors['name'] = 'Ya existe una categoría con ese nombre.';
        }
        if ($errors) {
            return $errors;
        }

        $category->name = trim($data['name']);
        $category->description = $this->optionalText($data, 'description');

        return [];
    }
}
