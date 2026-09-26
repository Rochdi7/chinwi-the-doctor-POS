<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** A category is only a filing label: one field, unique (CategoryResource). */
class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Category::query()->withCount('articles')->orderBy('nom')->get(['id', 'nom']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(Category::create($this->validated($request, null))->loadCount('articles'), 201);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $category->update($this->validated($request, $category));

        return response()->json($category->loadCount('articles'));
    }

    /** Its articles stay, uncategorised (category_id is nullOnDelete). */
    public function destroy(Category $category): JsonResponse
    {
        $category->delete();

        return response()->json(null, 204);
    }

    /** @return array{nom: string} */
    private function validated(Request $request, ?Category $category): array
    {
        return $request->validate([
            'nom' => ['required', 'string', 'max:60', Rule::unique('categories', 'nom')->ignore($category)],
        ]);
    }
}
