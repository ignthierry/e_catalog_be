<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

use App\Helpers\MediaHelper;
use App\Services\ActivityLogger;

class CategoryController extends Controller
{
    /**
     * Map slug to Lucide icon name for frontend.
     */
    private array $iconMap = [
        'action-figure' => 'Bot',
        'mainan-edukasi' => 'BrainCircuit',
        'mobil-remote-control' => 'Car',
        'gundam-model-kit' => 'Blocks',
        'boneka' => 'Heart',
        'board-game' => 'Gamepad2',
        'sepeda-anak' => 'Bike',
        'seni-kreasi' => 'Palette',
    ];

    /**
     * Display a listing of categories with product counts.
     */
    public function index(): JsonResponse
    {
        $categories = Category::withCount('products')->get();

        $formatted = $categories->map(function ($cat) {
            $icon = $this->iconMap[$cat->slug] ?? 'LayoutGrid';

            return [
                'id' => (string) $cat->id,
                'name' => $cat->name,
                'slug' => $cat->slug,
                'icon' => $icon,
                'image' => MediaHelper::url($cat->image),
                'parentId' => $cat->parent_id ? (string) $cat->parent_id : null,
                'productCount' => $cat->products_count,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $formatted,
        ]);
    }

    /**
     * Display a specific category.
     */
    public function show($idOrSlug): JsonResponse
    {
        $category = Category::with(['products.images', 'children'])
            ->where(function ($q) use ($idOrSlug) {
                if (is_numeric($idOrSlug)) {
                    $q->where('id', $idOrSlug);
                } else {
                    $q->where('slug', $idOrSlug);
                }
            })
            ->first();

        if (!$category) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kategori tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => (string) $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'icon' => $this->iconMap[$category->slug] ?? 'LayoutGrid',
                'image' => MediaHelper::url($category->image),
                'products' => $category->products,
            ],
        ]);
    }

    /**
     * Store a newly created category (Admin).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'image' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id',
        ]);

        $category = Category::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'image' => $validated['image'] ?? null,
            'parent_id' => $validated['parent_id'] ?? null,
        ]);

        ActivityLogger::log(
            $request,
            'CREATE_CATEGORY',
            "Menambahkan kategori baru '{$category->name}'",
            null,
            ['category_id' => $category->id, 'name' => $category->name]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Kategori berhasil ditambahkan',
            'data' => $category,
        ], 201);
    }

    /**
     * Update a category (Admin).
     */
    public function update(Request $request, $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'image' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id',
        ]);

        if (isset($validated['name'])) {
            $category->name = $validated['name'];
            $category->slug = Str::slug($validated['name']);
        }
        if (isset($validated['image'])) {
            $category->image = $validated['image'];
        }
        if (array_key_exists('parent_id', $validated)) {
            $category->parent_id = $validated['parent_id'];
        }

        $category->save();

        ActivityLogger::log(
            $request,
            'UPDATE_CATEGORY',
            "Memperbarui kategori '{$category->name}' (ID: {$category->id})",
            null,
            ['category_id' => $category->id, 'name' => $category->name]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Kategori berhasil diperbarui',
            'data' => $category,
        ]);
    }

    /**
     * Delete a category (Admin).
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $category = Category::findOrFail($id);
        $categoryName = $category->name;
        $category->products()->detach();
        $category->delete();

        ActivityLogger::log(
            $request,
            'DELETE_CATEGORY',
            "Menghapus kategori '{$categoryName}' (ID: {$id})",
            null,
            ['category_id' => $id, 'name' => $categoryName]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Kategori berhasil dihapus',
        ]);
    }
}
