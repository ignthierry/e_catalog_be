<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Helpers\MediaHelper;
use App\Services\ActivityLogger;

class OrderController extends Controller
{
    /**
     * Create a new order (Customer Checkout).
     * BUG-1 FIX: Product prices are strictly calculated from database.
     * BUG-3 FIX: Throws ValidationException::withMessages for clean 422 errors.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:30',
            'customer_email' => 'nullable|email|max:255',
            'shipping_address' => 'required|string',
            'courier' => 'nullable|string|max:100',
            'shipping_cost' => 'nullable|numeric|min:0',
            'payment_method' => 'required|string|in:QRIS,Transfer Bank,WhatsApp,COD',
            'payment_proof' => 'nullable|string',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.product_variant_id' => 'nullable',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'nullable|numeric|min:0',
        ]);

        $user = $request->user() ?: auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda harus login atau mendaftar terlebih dahulu untuk melakukan transaksi.',
            ], 401);
        }

        // Generate unique order number (e.g. OMG-20260813-A8X9K)
        $orderNumber = 'OMG-' . date('Ymd') . '-' . Str::upper(Str::random(5));

        return DB::transaction(function () use ($validated, $user, $orderNumber) {
            $totalAmount = 0;
            $shippingCost = (float) ($validated['shipping_cost'] ?? 15000);
            $orderItemsData = [];

            foreach ($validated['items'] as $item) {
                $product = Product::with('variants')->findOrFail($item['product_id']);
                
                // BUG-1 FIX: Always calculate server-side price from database!
                $basePrice = (float) $product->base_price;
                $price = $basePrice;

                $variantId = null;
                $quantity = (int) $item['quantity'];

                // Validate & deduct stock for the selected variant
                if (!empty($item['product_variant_id'])) {
                    $variant = ProductVariant::find($item['product_variant_id']);
                    if (!$variant) {
                        // BUG-3 FIX: Use ValidationException::withMessages
                        throw ValidationException::withMessages([
                            'items' => "Varian produk tidak ditemukan untuk produk '{$product->name}'."
                        ]);
                    }
                    // Ensure variant belongs to the product being ordered
                    if ((int) $variant->product_id !== (int) $product->id) {
                        throw ValidationException::withMessages([
                            'items' => "Varian tidak sesuai dengan produk '{$product->name}'."
                        ]);
                    }
                    $variantId = $variant->id;
                    $price += (float) ($variant->additional_price ?? 0);

                    // Reject order if insufficient stock (prevent overselling)
                    if ($variant->stock < $quantity) {
                        throw ValidationException::withMessages([
                            'items' => "Stok varian '{$variant->name}' untuk produk '{$product->name}' tidak mencukupi (tersisa {$variant->stock}, diminta {$quantity})."
                        ]);
                    }
                    $variant->decrement('stock', $quantity);
                } elseif ($product->variants->isNotEmpty()) {
                    // Product has variants but none selected — reject UNLESS all variants are
                    // unnamed (legacy data where variant name is empty string).
                    $hasNamedVariant = $product->variants->contains(fn($v) => trim((string) $v->name) !== '');
                    if ($hasNamedVariant) {
                        $variantNames = $product->variants->pluck('name')->filter(fn($n) => trim((string) $n) !== '')->implode(', ');
                        $hint = $variantNames !== '' ? " Pilihan tersedia: {$variantNames}." : '';
                        throw ValidationException::withMessages([
                            'items' => "Silakan pilih varian untuk produk '{$product->name}'.{$hint}"
                        ]);
                    }
                }

                $itemTotal = $price * $quantity;
                $totalAmount += $itemTotal;

                $orderItemsData[] = [
                    'product_id' => $product->id,
                    'product_variant_id' => $variantId,
                    'quantity' => $quantity,
                    'price_at_purchase' => $price,
                ];
            }

            $grandTotal = $totalAmount + $shippingCost;

            // Initial status
            $hasProof = !empty($validated['payment_proof']);
            $status = $hasProof ? 'paid' : 'pending';
            $paymentStatus = $hasProof ? 'verifying' : 'unpaid';

            $order = Order::create([
                'user_id' => $user->id,
                'customer_name' => $validated['customer_name'],
                'customer_phone' => $validated['customer_phone'],
                'customer_email' => $validated['customer_email'] ?? $user->email,
                'order_number' => $orderNumber,
                'total_amount' => $totalAmount,
                'shipping_cost' => $shippingCost,
                'grand_total' => $grandTotal,
                'status' => $status,
                'payment_method' => $validated['payment_method'],
                'payment_status' => $paymentStatus,
                'payment_proof' => $validated['payment_proof'] ?? null,
                'courier' => $validated['courier'] ?? 'JNE REG',
                'shipping_address' => $validated['shipping_address'],
                'notes' => $validated['notes'] ?? null,
                'paid_at' => $hasProof ? now() : null,
            ]);

            foreach ($orderItemsData as $itemData) {
                $order->items()->create($itemData);
            }

            $order->load(['items.product.images', 'items.variant', 'user']);

            return response()->json([
                'status' => 'success',
                'message' => 'Pesanan berhasil dibuat dengan nomor invoice ' . $order->order_number,
                'data' => $this->formatOrder($order),
            ], 201);
        });
    }

    /**
     * Get Customer orders list.
     * BUG-2 FIX: Customer only gets their own orders based on authenticated user session.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user() ?: auth('sanctum')->user();

        if (!$user) {
            return response()->json([
                'status' => 'success',
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'total' => 0,
                ],
            ]);
        }

        $query = Order::with(['items.product.images', 'items.variant'])
            ->orderBy('created_at', 'desc');

        // Customers can strictly only view their own orders
        if ($user->role === 'customer') {
            $query->where('user_id', $user->id);
        }

        $orders = $query->paginate(20);

        $formatted = $orders->getCollection()->map(function ($order) {
            return $this->formatOrder($order);
        });

        return response()->json([
            'status' => 'success',
            'data' => $formatted,
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    /**
     * Display a specific order by ID or order_number.
     * BUG-2 FIX: Enforces authorization check. Only the order owner or admin can view order details.
     */
    public function show(Request $request, $idOrNumber): JsonResponse
    {
        $order = Order::with(['items.product.images', 'items.variant', 'user'])
            ->where(function ($q) use ($idOrNumber) {
                if (is_numeric($idOrNumber)) {
                    $q->where('id', $idOrNumber);
                } else {
                    $q->where('order_number', $idOrNumber);
                }
            })
            ->first();

        if (!$order) {
            return response()->json([
                'status' => 'error',
                'message' => 'Pesanan tidak ditemukan',
            ], 404);
        }

        $user = $request->user() ?: auth('sanctum')->user();
        
        // Authorization check: Admin or Order Owner
        $isOwner = $user && ((int) $order->user_id === (int) $user->id || $user->email === $order->customer_email);
        $isAdmin = $user && in_array($user->role, ['admin', 'warehouse', 'cs']);
        
        // If not owner and not admin, block access
        if (!$isOwner && !$isAdmin) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki akses untuk melihat pesanan ini.',
            ], 403);
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->formatOrder($order),
        ]);
    }

    /**
     * Upload / Update Payment Proof for an order.
     * BUG-2 FIX: Enforces authentication & ownership verification.
     */
    public function uploadProof(Request $request, $id): JsonResponse
    {
        $request->validate([
            'payment_proof' => 'required|string',
        ]);

        $user = $request->user() ?: auth('sanctum')->user();
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Silakan login terlebih dahulu untuk mengunggah bukti transfer.',
            ], 401);
        }

        $order = Order::where('id', $id)
            ->orWhere('order_number', $id)
            ->firstOrFail();

        // Authorization check
        $isOwner = (int) $order->user_id === (int) $user->id || $user->email === $order->customer_email;
        $isAdmin = in_array($user->role, ['admin', 'warehouse', 'cs']);
        if (!$isOwner && !$isAdmin) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki izin untuk mengunggah bukti pembayaran pesanan ini.',
            ], 403);
        }

        $order->payment_proof = $request->input('payment_proof');
        $order->payment_status = 'verifying';
        $order->status = 'paid';
        $order->paid_at = now();
        $order->save();

        $order->load(['items.product.images', 'items.variant', 'user']);

        return response()->json([
            'status' => 'success',
            'message' => 'Bukti pembayaran berhasil diunggah. Menunggu konfirmasi admin.',
            'data' => $this->formatOrder($order),
        ]);
    }

    /**
     * Admin: Listing all orders with status filters and search.
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $query = Order::with(['items.product.images', 'items.variant', 'user'])
            ->orderBy('created_at', 'desc');

        // Filter status
        if ($request->filled('status') && $request->query('status') !== 'all') {
            $status = $request->query('status');
            if ($status === 'unpaid') {
                $query->where('status', 'pending');
            } elseif ($status === 'verifying') {
                $query->where('payment_status', 'verifying');
            } else {
                $query->where('status', $status);
            }
        }

        // Search keyword (order number, customer name, phone)
        if ($request->filled('search')) {
            $search = '%' . trim($request->query('search')) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'LIKE', $search)
                  ->orWhere('customer_name', 'LIKE', $search)
                  ->orWhere('customer_phone', 'LIKE', $search)
                  ->orWhere('customer_email', 'LIKE', $search);
            });
        }

        $orders = $query->paginate(30);

        $formatted = $orders->getCollection()->map(function ($order) {
            return $this->formatOrder($order);
        });

        // Summary counts for badges
        $counts = [
            'all' => Order::count(),
            'pending' => Order::where('status', 'pending')->count(),
            'verifying' => Order::where('payment_status', 'verifying')->count(),
            'processing' => Order::where('status', 'processing')->count(),
            'shipped' => Order::where('status', 'shipped')->count(),
            'completed' => Order::where('status', 'completed')->count(),
            'cancelled' => Order::where('status', 'cancelled')->count(),
        ];

        return response()->json([
            'status' => 'success',
            'data' => $formatted,
            'counts' => $counts,
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    /**
     * Admin: Update order status, payment status, AWB/tracking, notes.
     * BUG-6 FIX: Consistent status flow when AWB is provided.
     */
    public function adminUpdateStatus(Request $request, $id): JsonResponse
    {
        $order = Order::where('id', $id)
            ->orWhere('order_number', $id)
            ->firstOrFail();

        $validated = $request->validate([
            'status' => 'nullable|string|in:pending,paid,processing,shipped,completed,cancelled',
            'payment_status' => 'nullable|string|in:unpaid,verifying,paid,rejected',
            'awb_number' => 'nullable|string|max:100',
            'courier' => 'nullable|string|max:100',
            'admin_notes' => 'nullable|string',
        ]);

        if (isset($validated['status'])) {
            $order->status = $validated['status'];
            if ($validated['status'] === 'processing' && $order->payment_status !== 'paid') {
                $order->payment_status = 'paid';
                if (!$order->paid_at) {
                    $order->paid_at = now();
                }
            }
        }

        if (isset($validated['payment_status'])) {
            $order->payment_status = $validated['payment_status'];
            if ($validated['payment_status'] === 'paid') {
                $order->paid_at = $order->paid_at ?: now();
                if ($order->status === 'pending' || $order->status === 'paid') {
                    $order->status = 'processing';
                }
            } elseif ($validated['payment_status'] === 'rejected') {
                $order->status = 'pending';
            }
        }

        if (isset($validated['awb_number'])) {
            $order->awb_number = $validated['awb_number'];
            if (!empty($validated['awb_number']) && in_array($order->status, ['processing', 'paid', 'pending'])) {
                $order->status = 'shipped';
            }
        }

        if (isset($validated['courier'])) {
            $order->courier = $validated['courier'];
        }

        if (isset($validated['admin_notes'])) {
            $order->admin_notes = $validated['admin_notes'];
        }

        $order->save();
        $order->load(['items.product.images', 'items.variant', 'user']);

        ActivityLogger::log(
            $request,
            'UPDATE_ORDER_STATUS',
            "Memperbarui status pesanan #{$order->order_number} menjadi '{$order->status}' (Pembayaran: '{$order->payment_status}')" . ($order->awb_number ? " [Resi: {$order->awb_number}]" : ""),
            null,
            [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'awb_number' => $order->awb_number,
                'grand_total' => $order->grand_total,
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Status pesanan ' . $order->order_number . ' berhasil diperbarui',
            'data' => $this->formatOrder($order),
        ]);
    }

    /**
     * Helper to transform Order model to frontend JSON format.
     */
    private function formatOrder(Order $order): array
    {
        $items = $order->items->map(function ($item) {
            $product = $item->product;
            $images = $product && $product->images ? $product->images->pluck('image_url')->map(fn($img) => MediaHelper::url($img))->filter()->values()->toArray() : [];
            $image = !empty($images) ? $images[0] : 'https://images.unsplash.com/photo-1594787318286-3d835c1d207f?w=800&q=80';

            return [
                'id' => (string) $item->id,
                'productId' => (string) $item->product_id,
                'productName' => $product ? $product->name : 'Produk Tidak Ditemukan',
                'productSlug' => $product ? $product->slug : '',
                'variantName' => $item->variant ? $item->variant->name : null,
                'image' => $image,
                'quantity' => (int) $item->quantity,
                'price' => (float) $item->price_at_purchase,
                'total' => (float) ($item->price_at_purchase * $item->quantity),
            ];
        });

        return [
            'id' => (string) $order->id,
            'orderNumber' => $order->order_number,
            'userId' => $order->user_id ? (string) $order->user_id : null,
            'customerName' => $order->customer_name ?: ($order->user ? $order->user->name : 'Customer'),
            'customerPhone' => $order->customer_phone ?: ($order->user ? $order->user->phone_number : '-'),
            'customerEmail' => $order->customer_email ?: ($order->user ? $order->user->email : '-'),
            'totalAmount' => (float) $order->total_amount,
            'shippingCost' => (float) $order->shipping_cost,
            'grandTotal' => (float) $order->grand_total,
            'status' => $order->status,
            'paymentMethod' => $order->payment_method,
            'paymentStatus' => $order->payment_status,
            'paymentProof' => MediaHelper::url($order->payment_proof),
            'courier' => $order->courier ?: 'JNE REG',
            'awbNumber' => $order->awb_number,
            'shippingAddress' => $order->shipping_address,
            'notes' => $order->notes,
            'adminNotes' => $order->admin_notes,
            'paidAt' => $order->paid_at ? $order->paid_at->toISOString() : null,
            'createdAt' => $order->created_at ? $order->created_at->toISOString() : null,
            'updatedAt' => $order->updated_at ? $order->updated_at->toISOString() : null,
            'items' => $items,
        ];
    }
}
