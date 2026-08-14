<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ShopeeImporter
{
    /**
     * Clear all existing products, categories, orders, and associated media.
     * Preserves admin accounts and system logs.
     */
    public static function clearAllCatalogData(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        DB::table('order_items')->truncate();
        DB::table('orders')->truncate();
        DB::table('product_images')->truncate();
        DB::table('product_variants')->truncate();
        DB::table('category_product')->truncate();
        DB::table('products')->truncate();
        DB::table('categories')->truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Update store description to match 0meg4t0y5 Shopee store
        Setting::set('store_name', 'OMEGA TOYS');
        Setting::set('store_description', '100% Realpict Mainan Edukasi, Kendaraan, Boneka & Koleksi');
    }

    /**
     * Import products from Shopee JSON items array.
     */
    public static function importItems(array $shopeeItems): array
    {
        $importedCount = 0;
        $categoryMap = [];

        // 1. Pre-populate default official categories from 0meg4t0y5 Shopee shop
        $defaultCategories = [
            [
                'name' => 'Kendaraan Mainan',
                'image' => 'https://down-id.img.susercontent.com/file/id-11134207-7ra0m-mccr8z9r5qe102',
            ],
            [
                'name' => 'Boneka & Mainan Boneka',
                'image' => 'https://down-id.img.susercontent.com/file/id-11134207-7ra0i-mb7oyh237g9wcc',
            ],
            [
                'name' => 'Mainan Edukatif',
                'image' => 'https://down-id.img.susercontent.com/file/id-11134207-7ra0h-mcoogoxpezp84a',
            ],
            [
                'name' => 'Mainan Peran',
                'image' => 'https://down-id.img.susercontent.com/file/id-11134207-7ra0o-mcoog9t1k1m77e',
            ],
            [
                'name' => 'Kacamata Renang',
                'image' => 'https://down-id.img.susercontent.com/file/id-11134207-7ra0o-mbae86ejvye1dd',
            ],
            [
                'name' => 'Mainan Robot & Action Figure',
                'image' => null,
            ],
            [
                'name' => 'Koleksi & Hobi',
                'image' => null,
            ],
        ];

        foreach ($defaultCategories as $catData) {
            $cat = Category::firstOrCreate(
                ['name' => $catData['name']],
                [
                    'slug' => Str::slug($catData['name']),
                    'image' => $catData['image'],
                ]
            );
            $categoryMap[$catData['name']] = $cat->id;
        }

        foreach ($shopeeItems as $item) {
            $basic = $item['item_basic'] ?? $item;

            $name = trim($basic['name'] ?? $basic['title'] ?? '');
            if (empty($name)) continue;

            // Normalize Price: Shopee API usually divides by 100,000 or raw float
            $rawPrice = (float) ($basic['price'] ?? $basic['price_min'] ?? ($basic['priceText'] ?? 0));
            if ($rawPrice > 10000000) {
                // e.g. 3500000000 -> 35000
                $price = $rawPrice / 100000;
            } elseif ($rawPrice > 0) {
                $price = $rawPrice;
            } else {
                $price = 25000;
            }

            // Clean price from formatted string if needed
            if (isset($basic['priceText']) && is_string($basic['priceText'])) {
                $num = preg_replace('/[^0-9]/', '', $basic['priceText']);
                if (!empty($num)) {
                    $price = (float) $num;
                }
            }

            // Deduce Category based on product title keywords
            $assignedCategoryId = null;
            $lowerName = strtolower($name);
            if (preg_match('/mobil|truk|motor|rc|kereta|bus|pesawat|kendaraan|traktor|tank|kapal|excavator/i', $lowerName)) {
                $assignedCategoryId = $categoryMap['Kendaraan Mainan'] ?? null;
            } elseif (preg_match('/boneka|plush|teddy|barbie|bantal/i', $lowerName)) {
                $assignedCategoryId = $categoryMap['Boneka & Mainan Boneka'] ?? null;
            } elseif (preg_match('/edukasi|puzzle|balok|lego|belajar|montessori|sempoa|kartu|buku/i', $lowerName)) {
                $assignedCategoryId = $categoryMap['Mainan Edukatif'] ?? null;
            } elseif (preg_match('/masak|dokter|alat|kasir|make up|dapur|belanja|peran|bengkel/i', $lowerName)) {
                $assignedCategoryId = $categoryMap['Mainan Peran'] ?? null;
            } elseif (preg_match('/renang|kacamata|pelampung|kolam/i', $lowerName)) {
                $assignedCategoryId = $categoryMap['Kacamata Renang'] ?? null;
            } elseif (preg_match('/robot|gundam|figure|avengers|transformer|superhero/i', $lowerName)) {
                $assignedCategoryId = $categoryMap['Mainan Robot & Action Figure'] ?? null;
            } else {
                $assignedCategoryId = $categoryMap['Koleksi & Hobi'] ?? null;
            }

            // Create unique slug
            $baseSlug = Str::slug($name);
            $slug = $baseSlug;
            $counter = 1;
            while (Product::where('slug', $slug)->exists()) {
                $slug = "{$baseSlug}-" . $counter++;
            }

            // Description
            $desc = $basic['description'] ?? "Produk mainan berkualitas 100% realpict dari toko OMEGA TOYS. Siap kirim dan bergaransi.";

            $product = Product::create([
                'name' => $name,
                'slug' => $slug,
                'description' => $desc,
                'base_price' => $price,
                'weight_grams' => (int) ($basic['weight'] ?? 500),
                'is_active' => true,
            ]);

            // Assign Category
            if ($assignedCategoryId) {
                $product->categories()->attach($assignedCategoryId);
            }

            // Process Images
            $images = [];
            if (!empty($basic['images']) && is_array($basic['images'])) {
                foreach ($basic['images'] as $imgId) {
                    if (str_starts_with($imgId, 'http')) {
                        $images[] = $imgId;
                    } else {
                        $images[] = "https://down-id.img.susercontent.com/file/{$imgId}";
                    }
                }
            } elseif (!empty($basic['image'])) {
                $imgId = $basic['image'];
                $images[] = str_starts_with($imgId, 'http') ? $imgId : "https://down-id.img.susercontent.com/file/{$imgId}";
            } elseif (!empty($basic['imgUrl'])) {
                $images[] = $basic['imgUrl'];
            }

            foreach ($images as $idx => $imgUrl) {
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_url' => $imgUrl,
                    'is_primary' => $idx === 0,
                ]);
            }

            // Process Variants (from tier_variations / models)
            if (!empty($basic['tier_variations']) && is_array($basic['tier_variations'])) {
                foreach ($basic['tier_variations'] as $tv) {
                    $options = $tv['options'] ?? [];
                    $varName = $tv['name'] ?? 'Variasi';
                    foreach ($options as $opt) {
                        ProductVariant::create([
                            'product_id' => $product->id,
                            'sku' => Str::upper(Str::random(8)),
                            'name' => is_array($opt) ? ($opt['name'] ?? 'Varian') : (string) $opt,
                            'additional_price' => 0,
                            'stock' => 20,
                        ]);
                    }
                }
            }

            $importedCount++;
        }

        return [
            'status' => 'success',
            'imported' => $importedCount,
            'message' => "Berhasil mengimpor {$importedCount} produk dari toko Shopee 0meg4t0y5 ke database katalog!",
        ];
    }
}
