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
     * Resolve Lucide icon name based on category slug or name.
     */
    private function resolveIcon(string $name, ?string $slug = null): string
    {
        $target = strtolower(trim(($slug ?? '') . ' ' . $name));

        if (str_contains($target, 'kendaraan') || str_contains($target, 'mobil') || str_contains($target, 'car') || str_contains($target, 'truk') || str_contains($target, 'diecast') || str_contains($target, 'hot wheels')) {
            return 'Car';
        }
        if (str_contains($target, 'boneka') || str_contains($target, 'plush') || str_contains($target, 'teddy') || str_contains($target, 'heart') || str_contains($target, 'barbie')) {
            return 'Heart';
        }
        if (str_contains($target, 'edukasi') || str_contains($target, 'edukatif') || str_contains($target, 'logic') || str_contains($target, 'belajar') || str_contains($target, 'brain') || str_contains($target, 'pintar')) {
            return 'BrainCircuit';
        }
        if (str_contains($target, 'peran') || str_contains($target, 'roleplay') || str_contains($target, 'kostum') || str_contains($target, 'drama') || str_contains($target, 'theater') || str_contains($target, 'profesi') || str_contains($target, 'dapur') || str_contains($target, 'masak')) {
            return 'Theater';
        }
        if (str_contains($target, 'renang') || str_contains($target, 'kacamata') || str_contains($target, 'swim') || str_contains($target, 'water') || str_contains($target, 'air') || str_contains($target, 'pantai') || str_contains($target, 'glasses') || str_contains($target, 'waves')) {
            return 'Glasses';
        }
        if (str_contains($target, 'robot') || str_contains($target, 'action figure') || str_contains($target, 'figure') || str_contains($target, 'bot') || str_contains($target, 'gundam') || str_contains($target, 'hero') || str_contains($target, 'marvel') || str_contains($target, 'avengers')) {
            return 'Bot';
        }
        if (str_contains($target, 'koleksi') || str_contains($target, 'hobi') || str_contains($target, 'hobby') || str_contains($target, 'kartu') || str_contains($target, 'trophy') || str_contains($target, 'gem') || str_contains($target, 'rare') || str_contains($target, 'board game') || str_contains($target, 'gamepad')) {
            return 'Trophy';
        }
        if (str_contains($target, 'balok') || str_contains($target, 'lego') || str_contains($target, 'brick') || str_contains($target, 'blocks') || str_contains($target, 'susun')) {
            return 'Blocks';
        }
        if (str_contains($target, 'sepeda') || str_contains($target, 'bike') || str_contains($target, 'outdoor') || str_contains($target, 'olahraga') || str_contains($target, 'skuter')) {
            return 'Bike';
        }
        if (str_contains($target, 'seni') || str_contains($target, 'kreasi') || str_contains($target, 'lukis') || str_contains($target, 'gambar') || str_contains($target, 'palette') || str_contains($target, 'craft') || str_contains($target, 'warna')) {
            return 'Palette';
        }
        if (str_contains($target, 'puzzle') || str_contains($target, 'teka-teki') || str_contains($target, 'rubik')) {
            return 'Puzzle';
        }
        if (str_contains($target, 'bayi') || str_contains($target, 'balita') || str_contains($target, 'baby')) {
            return 'Baby';
        }

        return 'LayoutGrid';
    }

    /**
     * Display a listing of categories with product counts.
     */
    public function index(): JsonResponse
    {
        $categories = Category::withCount('products')->get();

        $formatted = $categories->map(function ($cat) {
            $icon = $this->resolveIcon($cat->name, $cat->slug);

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
