<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * Display a listing of products with search, category filter, and sorting.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['categories', 'images', 'variants'])
            ->where('is_active', true);

        // Search by keyword
        if ($request->filled('search')) {
            $searchTerm = '%' . strtolower(trim($request->query('search'))) . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->whereRaw('LOWER(name) LIKE ?', [$searchTerm])
                  ->orWhereRaw('LOWER(description) LIKE ?', [$searchTerm]);
            });
        }

        // Filter by category (id or slug)
        if ($request->filled('category')) {
            $categoryParam = $request->query('category');
            $query->whereHas('categories', function ($q) use ($categoryParam) {
                if (is_numeric($categoryParam)) {
                    $q->where('categories.id', $categoryParam);
                } else {
                    $q->where('categories.slug', $categoryParam);
                }
            });
        }

        // Sorting
        $sort = $request->query('sort', 'newest');
        switch ($sort) {
            case 'price-asc':
            case 'price_asc':
                $query->orderBy('base_price', 'asc');
                break;
            case 'price-desc':
            case 'price_desc':
                $query->orderBy('base_price', 'desc');
                break;
            case 'newest':
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        $products = $query->get();

        $formatted = $products->map(function ($product) {
            return $this->formatProduct($product);
        });

        return response()->json([
            'status' => 'success',
            'data' => $formatted,
            'total' => $formatted->count(),
        ]);
    }

    /**
     * Get featured products for homepage.
     */
    public function featured(Request $request): JsonResponse
    {
        $products = Product::with(['categories', 'images', 'variants'])
            ->where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $formatted = $products->map(function ($product) {
            return $this->formatProduct($product);
        });

        return response()->json([
            'status' => 'success',
            'data' => $formatted,
        ]);
    }

    /**
     * Display a specific product by ID or Slug.
     */
    public function show($idOrSlug): JsonResponse
    {
        $product = Product::with(['categories', 'images', 'variants'])
            ->where(function ($q) use ($idOrSlug) {
                if (is_numeric($idOrSlug)) {
                    $q->where('id', $idOrSlug);
                } else {
                    $q->where('slug', $idOrSlug);
                }
            })
            ->first();

        if (!$product) {
            return response()->json([
                'status' => 'error',
                'message' => 'Produk tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->formatProduct($product),
        ]);
    }

    /**
     * Store a newly created product (Admin).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'weight_grams' => 'nullable|integer|min:0',
            'category_id' => 'nullable',
            'stock' => 'nullable|integer|min:0',
            'images' => 'nullable|array',
            'variants' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        $product = Product::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']) . '-' . uniqid(),
            'description' => $validated['description'] ?? '',
            'base_price' => $validated['price'],
            'weight_grams' => $validated['weight_grams'] ?? 500,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        // Attach category
        if (!empty($validated['category_id'])) {
            $product->categories()->sync([$validated['category_id']]);
        }

        // Add images
        if (!empty($validated['images']) && is_array($validated['images'])) {
            foreach ($validated['images'] as $index => $imageUrl) {
                $product->images()->create([
                    'image_url' => $imageUrl,
                    'is_primary' => $index === 0,
                ]);
            }
        }

        // Add variants
        if (!empty($validated['variants']) && is_array($validated['variants'])) {
            foreach ($validated['variants'] as $v) {
                $product->variants()->create([
                    'sku' => $v['sku'] ?? Str::upper(Str::random(8)),
                    'name' => $v['name'],
                    'additional_price' => $v['additional_price'] ?? 0,
                    'stock' => $v['stock'] ?? 10,
                    'image' => $v['image'] ?? null,
                ]);
            }
        }

        $product->load(['categories', 'images', 'variants']);

        return response()->json([
            'status' => 'success',
            'message' => 'Produk berhasil ditambahkan',
            'data' => $this->formatProduct($product),
        ], 201);
    }

    /**
     * Update an existing product (Admin).
     */
    public function update(Request $request, $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|required|numeric|min:0',
            'weight_grams' => 'nullable|integer|min:0',
            'category_id' => 'nullable',
            'images' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        if (isset($validated['name'])) {
            $product->name = $validated['name'];
        }
        if (isset($validated['description'])) {
            $product->description = $validated['description'];
        }
        if (isset($validated['price'])) {
            $product->base_price = $validated['price'];
        }
        if (isset($validated['weight_grams'])) {
            $product->weight_grams = $validated['weight_grams'];
        }
        if (isset($validated['is_active'])) {
            $product->is_active = $validated['is_active'];
        }

        $product->save();

        if (isset($validated['category_id'])) {
            $product->categories()->sync([$validated['category_id']]);
        }

        if (isset($validated['images']) && is_array($validated['images'])) {
            $product->images()->delete();
            foreach ($validated['images'] as $index => $imageUrl) {
                $product->images()->create([
                    'image_url' => $imageUrl,
                    'is_primary' => $index === 0,
                ]);
            }
        }

        $product->load(['categories', 'images', 'variants']);

        return response()->json([
            'status' => 'success',
            'message' => 'Produk berhasil diperbarui',
            'data' => $this->formatProduct($product),
        ]);
    }

    /**
     * Delete a product (Admin).
     */
    public function destroy($id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->categories()->detach();
        $product->images()->delete();
        $product->variants()->delete();
        $product->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Produk berhasil dihapus',
        ]);
    }

    /**
     * Helper to transform Product model to frontend schema.
     */
    private function formatProduct(Product $product): array
    {
        $primaryCategory = $product->categories->first();
        
        $images = $product->images->pluck('image_url')->toArray();
        if (empty($images)) {
            $images = ['https://images.unsplash.com/photo-1594787318286-3d835c1d207f?w=800&q=80'];
        }

        // Calculate total stock from variants or default
        $stock = $product->variants->sum('stock');
        if ($stock <= 0 && $product->variants->isEmpty()) {
            $stock = 25; // fallback default
        }

        // Transform variants for frontend
        $variants = [];
        if ($product->variants->isNotEmpty()) {
            $options = $product->variants->pluck('name')->toArray();
            $variants[] = [
                'id' => 'v1',
                'name' => 'Pilihan Varian',
                'options' => $options,
            ];
        }

        return [
            'id' => (string) $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'description' => $product->description ?? '',
            'price' => (float) $product->base_price,
            'originalPrice' => null,
            'categoryId' => $primaryCategory ? (string) $primaryCategory->id : '1',
            'categoryName' => $primaryCategory ? $primaryCategory->name : 'Umum',
            'images' => $images,
            'stock' => (int) $stock,
            'isNew' => $product->created_at ? $product->created_at->diffInDays(now()) < 14 : true,
            'rating' => 4.9,
            'variants' => $variants,
            'createdAt' => $product->created_at ? $product->created_at->toISOString() : null,
        ];
    }
}
