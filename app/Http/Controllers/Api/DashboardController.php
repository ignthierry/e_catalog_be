<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Banner;
use App\Models\Setting;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

use App\Helpers\MediaHelper;

class DashboardController extends Controller
{
    /**
     * Get aggregate statistics and summary for Admin Dashboard.
     */
    public function index(): JsonResponse
    {
        // 1. Core Counts
        $totalProducts = Product::where('is_active', true)->count();
        $totalCategories = Category::count();
        $totalBanners = Banner::where('is_active', true)->count();
        $totalOrders = Order::count();
        $pendingOrders = Order::where(function ($q) {
            $q->where('status', 'pending')
              ->orWhere('payment_status', 'verifying');
        })->count();
        $processingOrders = Order::where('status', 'processing')->count();
        $totalRevenue = (float) Order::where('status', '!=', 'cancelled')->sum('grand_total');

        // 2. Stock calculation
        $productsWithVariants = Product::with(['variants', 'categories', 'images'])
            ->where('is_active', true)
            ->get();

        $totalStock = 0;
        $lowStockCount = 0;

        foreach ($productsWithVariants as $product) {
            $variantStock = $product->variants->sum('stock');
            $stock = $variantStock > 0 ? $variantStock : 25; // fallback default
            $totalStock += $stock;
            if ($stock <= 10) {
                $lowStockCount++;
            }
        }

        // 3. Recent Products (Latest 5)
        $recentProducts = Product::with(['categories', 'images', 'variants'])
            ->where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($product) {
                $primaryCategory = $product->categories->first();
                $images = $product->images->pluck('image_url')->map(fn($img) => MediaHelper::url($img))->filter()->values()->toArray();
                if (empty($images)) {
                    $images = ['https://images.unsplash.com/photo-1594787318286-3d835c1d207f?w=800&q=80'];
                }
                $stock = $product->variants->sum('stock');
                if ($stock <= 0 && $product->variants->isEmpty()) {
                    $stock = 25;
                }

                return [
                    'id' => (string) $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'price' => (float) $product->base_price,
                    'stock' => (int) $stock,
                    'categoryId' => $primaryCategory ? (string) $primaryCategory->id : '1',
                    'categoryName' => $primaryCategory ? $primaryCategory->name : 'Umum',
                    'image' => $images[0],
                    'createdAt' => $product->created_at ? $product->created_at->toISOString() : null,
                ];
            });

        // 4. Recent Orders (Latest 5)
        $recentOrders = Order::with(['items.product.images', 'items.variant'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => (string) $order->id,
                    'orderNumber' => $order->order_number,
                    'customerName' => $order->customer_name ?: 'Customer',
                    'customerPhone' => $order->customer_phone ?: '-',
                    'grandTotal' => (float) $order->grand_total,
                    'status' => $order->status,
                    'paymentMethod' => $order->payment_method,
                    'paymentStatus' => $order->payment_status,
                    'paymentProof' => MediaHelper::url($order->payment_proof),
                    'itemCount' => $order->items->sum('quantity'),
                    'createdAt' => $order->created_at ? $order->created_at->toISOString() : null,
                ];
            });

        // 5. Category breakdown
        $categoriesSummary = Category::withCount('products')
            ->orderBy('products_count', 'desc')
            ->get()
            ->map(function ($cat) {
                return [
                    'id' => (string) $cat->id,
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                    'productCount' => $cat->products_count,
                ];
            });

        // 6. Store Settings
        $settingsRaw = Setting::all()->pluck('value', 'key')->toArray();
        $settings = array_merge([
            'store_name' => 'OMEGA TOYS',
            'store_description' => 'Katalog Mainan Edukasi & Koleksi Terbaik',
            'whatsapp_number' => '6281234567890',
            'contact_email' => 'hello@omegatoys.com',
            'address' => 'Jakarta, Indonesia',
        ], $settingsRaw);

        return response()->json([
            'status' => 'success',
            'data' => [
                'stats' => [
                    'totalProducts' => $totalProducts,
                    'totalCategories' => $totalCategories,
                    'totalBanners' => $totalBanners,
                    'totalStock' => $totalStock,
                    'lowStockCount' => $lowStockCount,
                    'totalOrders' => $totalOrders,
                    'pendingOrders' => $pendingOrders,
                    'processingOrders' => $processingOrders,
                    'totalRevenue' => $totalRevenue,
                ],
                'recentProducts' => $recentProducts,
                'recentOrders' => $recentOrders,
                'categoriesSummary' => $categoriesSummary,
                'settings' => $settings,
                'system' => [
                    'status' => 'online',
                    'database' => 'connected',
                    'phpVersion' => PHP_VERSION,
                    'timestamp' => now()->toIso8601String(),
                ]
            ]
        ]);
    }
}
