<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

use App\Helpers\MediaHelper;
use App\Services\ActivityLogger;

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

        // Filter by Price Range
        if ($request->filled('min_price') && is_numeric($request->query('min_price'))) {
            $query->where('base_price', '>=', (float) $request->query('min_price'));
        }
        if ($request->filled('max_price') && is_numeric($request->query('max_price'))) {
            $query->where('base_price', '<=', (float) $request->query('max_price'));
        }

        // Sorting
        $sort = $request->query('sort', 'default');
        switch ($sort) {
            case 'price-asc':
            case 'price_asc':
            case 'price_low':
                $query->orderBy('base_price', 'asc');
                break;
            case 'price-desc':
            case 'price_desc':
            case 'price_high':
                $query->orderBy('base_price', 'desc');
                break;
            case 'bestseller':
            case 'terlaris':
            case 'popular':
                $query->withSum('orderItems', 'quantity')
                      ->orderByDesc('order_items_sum_quantity')
                      ->orderByDesc('created_at');
                break;
            case 'discount':
            case 'diskon':
                $query->orderByRaw('(CASE WHEN original_price > base_price THEN ((original_price - base_price) / original_price) ELSE 0 END) DESC')
                      ->orderBy('created_at', 'desc');
                break;
            case 'newest':
            case 'terbaru':
                $query->orderBy('created_at', 'desc');
                break;
            default:
                $query->orderBy('created_at', 'desc');
                break;
        }

        $query->withSum('orderItems', 'quantity');
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
            'original_price' => 'nullable|numeric|min:0',
            'originalPrice' => 'nullable|numeric|min:0',
            'weight_grams' => 'nullable|integer|min:0',
            'category_id' => 'nullable',
            'stock' => 'nullable|integer|min:0',
            'images' => 'nullable|array',
            'variants' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        $originalPrice = $request->input('original_price', $request->input('originalPrice', null));
        if (!empty($originalPrice) && is_numeric($originalPrice)) {
            $originalPrice = (float) $originalPrice;
        } else {
            $originalPrice = null;
        }

        $product = Product::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']) . '-' . uniqid(),
            'description' => $validated['description'] ?? '',
            'base_price' => $validated['price'],
            'original_price' => $originalPrice,
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
                if (is_array($v) && isset($v['items']) && is_array($v['items'])) {
                    foreach ($v['items'] as $item) {
                        $itemName = is_array($item) ? ($item['name'] ?? '') : (string) $item;
                        $addPrice = is_array($item) ? ($item['additional_price'] ?? ($item['additionalPrice'] ?? 0)) : 0;
                        if (trim($itemName) !== '') {
                            $product->variants()->create([
                                'sku' => Str::upper(Str::random(8)),
                                'name' => trim($itemName),
                                'additional_price' => (float) $addPrice,
                                'stock' => (int) ($validated['stock'] ?? 10),
                            ]);
                        }
                    }
                } elseif (is_array($v) && isset($v['options']) && is_array($v['options'])) {
                    foreach ($v['options'] as $opt) {
                        if (trim($opt) !== '') {
                            $product->variants()->create([
                                'sku' => Str::upper(Str::random(8)),
                                'name' => trim((string) $opt),
                                'additional_price' => 0,
                                'stock' => (int) ($validated['stock'] ?? 10),
                            ]);
                        }
                    }
                } else {
                    $variantName = is_array($v) ? ($v['name'] ?? 'Varian') : (string) $v;
                    $addPrice = is_array($v) ? ($v['additional_price'] ?? ($v['additionalPrice'] ?? 0)) : 0;
                    if (trim($variantName) !== '') {
                        $product->variants()->create([
                            'sku' => is_array($v) ? ($v['sku'] ?? Str::upper(Str::random(8))) : Str::upper(Str::random(8)),
                            'name' => trim($variantName),
                            'additional_price' => (float) $addPrice,
                            'stock' => is_array($v) ? ($v['stock'] ?? 10) : 10,
                        ]);
                    }
                }
            }
        }

        $product->load(['categories', 'images', 'variants']);

        ActivityLogger::log(
            $request,
            'CREATE_PRODUCT',
            "Menambahkan produk baru '{$product->name}' seharga Rp " . number_format($product->base_price, 0, ',', '.'),
            null,
            ['product_id' => $product->id, 'name' => $product->name, 'price' => $product->base_price]
        );

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
            'original_price' => 'nullable|numeric|min:0',
            'originalPrice' => 'nullable|numeric|min:0',
            'weight_grams' => 'nullable|integer|min:0',
            'category_id' => 'nullable',
            'stock' => 'nullable|integer|min:0',
            'images' => 'nullable|array',
            'variants' => 'nullable|array',
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
        if ($request->has('original_price') || $request->has('originalPrice')) {
            $origVal = $request->input('original_price', $request->input('originalPrice'));
            $product->original_price = (!empty($origVal) && is_numeric($origVal)) ? (float) $origVal : null;
        }
        if (isset($validated['weight_grams'])) {
            $product->weight_grams = $validated['weight_grams'];
        }
        if (isset($validated['is_active'])) {
            $product->is_active = $validated['is_active'];
        }

        $product->save();

        // Update category relationship if provided
        if (array_key_exists('category_id', $validated)) {
            $catId = $validated['category_id'];
            if (!empty($catId)) {
                $category = Category::where('id', $catId)->orWhere('slug', $catId)->first();
                if ($category) {
                    $product->categories()->sync([$category->id]);
                }
            } else {
                $product->categories()->detach();
            }
        }

        // Update images if provided
        if (array_key_exists('images', $validated)) {
            $product->images()->delete();
            $images = is_array($validated['images']) ? $validated['images'] : [];
            foreach ($images as $idx => $imgUrl) {
                if (!empty($imgUrl)) {
                    $product->images()->create([
                        'image_url' => $imgUrl,
                        'is_primary' => $idx === 0,
                        'order' => $idx,
                    ]);
                }
            }
        }

        // Update variants if provided
        if (array_key_exists('variants', $validated)) {
            $product->variants()->delete();
            $variants = is_array($validated['variants']) ? $validated['variants'] : [];
            foreach ($variants as $v) {
                if (is_array($v) && isset($v['options']) && is_array($v['options'])) {
                    $items = isset($v['items']) && is_array($v['items']) ? $v['items'] : [];
                    $itemMap = [];
                    foreach ($items as $itm) {
                        if (isset($itm['name'])) {
                            $itemMap[$itm['name']] = $itm;
                        }
                    }

                    foreach ($v['options'] as $opt) {
                        $optName = trim((string) $opt);
                        if ($optName !== '') {
                            $itm = $itemMap[$optName] ?? [];
                            $addPrice = (float) ($itm['additional_price'] ?? ($itm['additionalPrice'] ?? 0));
                            $vStock = (int) ($itm['stock'] ?? 10);
                            $product->variants()->create([
                                'sku' => $itm['sku'] ?? Str::upper(Str::random(8)),
                                'name' => $optName,
                                'additional_price' => $addPrice,
                                'stock' => $vStock,
                            ]);
                        }
                    }
                } else {
                    $variantName = is_array($v) ? ($v['name'] ?? 'Varian') : (string) $v;
                    $addPrice = is_array($v) ? ($v['additional_price'] ?? ($v['additionalPrice'] ?? 0)) : 0;
                    if (trim($variantName) !== '') {
                        $product->variants()->create([
                            'sku' => is_array($v) ? ($v['sku'] ?? Str::upper(Str::random(8))) : Str::upper(Str::random(8)),
                            'name' => trim($variantName),
                            'additional_price' => (float) $addPrice,
                            'stock' => is_array($v) ? ($v['stock'] ?? 10) : 10,
                        ]);
                    }
                }
            }
        }

        $product->load(['categories', 'images', 'variants']);

        ActivityLogger::log(
            $request,
            'UPDATE_PRODUCT',
            "Memperbarui data produk '{$product->name}' (ID: {$product->id})",
            null,
            ['product_id' => $product->id, 'name' => $product->name, 'price' => $product->base_price]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Produk berhasil diperbarui',
            'data' => $this->formatProduct($product),
        ]);
    }

    /**
     * Delete a product (Admin).
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $productName = $product->name;

        $product->categories()->detach();
        $product->images()->delete();
        $product->variants()->delete();
        $product->delete();

        ActivityLogger::log(
            $request,
            'DELETE_PRODUCT',
            "Menghapus produk '{$productName}' (ID: {$id})",
            null,
            ['product_id' => $id, 'name' => $productName]
        );

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
        
        $images = $product->images->pluck('image_url')->map(function ($img) {
            return MediaHelper::url($img);
        })->filter()->values()->toArray();

        if (empty($images)) {
            $images = ['https://images.unsplash.com/photo-1594787318286-3d835c1d207f?w=800&q=80'];
        }

        // Calculate total stock from variants or default
        $stock = $product->variants->sum('stock');
        if ($stock <= 0 && $product->variants->isEmpty()) {
            $stock = 25; // fallback default
        }

        // Calculate sold count from actual order items or realistic baseline
        $realSold = isset($product->order_items_sum_quantity) 
            ? (int) $product->order_items_sum_quantity 
            : (int) $product->orderItems()->sum('quantity');
        
        $soldCount = $realSold > 0 
            ? $realSold 
            : (((is_numeric($product->id) ? (int)$product->id : crc32($product->id)) * 7 + 13) % 45 + 5);

        // Transform variants for frontend with pricing support.
        // Skip unnamed variants (legacy data where variant name is empty string)
        // so the UI doesn't render an empty selector bar.
        $namedVariants = $product->variants->filter(fn($v) => trim((string) $v->name) !== '')->values();
        $variants = [];
        if ($namedVariants->isNotEmpty()) {
            $options = $namedVariants->pluck('name')->toArray();
            $variantItems = $namedVariants->map(function ($v) use ($product) {
                $addPrice = (float) $v->additional_price;
                return [
                    'id' => (string) $v->id,
                    'name' => $v->name,
                    'additionalPrice' => $addPrice,
                    'price' => (float) ($product->base_price + $addPrice),
                    'stock' => (int) $v->stock,
                ];
            })->values()->toArray();

            $variants[] = [
                'id' => 'v1',
                'name' => 'Pilihan Varian',
                'options' => $options,
                'items' => $variantItems,
            ];
        }

        $originalPrice = null;
        if (!empty($product->original_price) && (float) $product->original_price > (float) $product->base_price) {
            $originalPrice = (float) $product->original_price;
        }

        return [
            'id' => (string) $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'description' => $product->description ?? '',
            'price' => (float) $product->base_price,
            'originalPrice' => $originalPrice,
            'categoryId' => $primaryCategory ? (string) $primaryCategory->id : '1',
            'categoryName' => $primaryCategory ? $primaryCategory->name : 'Umum',
            'images' => $images,
            'stock' => (int) $stock,
            'soldCount' => (int) $soldCount,
            'sold' => (int) $soldCount,
            'isNew' => $product->created_at ? $product->created_at->diffInDays(now()) < 14 : true,
            'rating' => 4.9,
            'variants' => $variants,
            'createdAt' => $product->created_at ? $product->created_at->toISOString() : null,
        ];
    }
}
