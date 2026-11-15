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
}
