<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShippingController extends Controller
{
    /**
     * Search domestic destination (subdistrict, city, province) from BinderByte district dataset.
     */
    public function searchDestinations(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));
        if (strlen($search) < 2) {
            return response()->json([
                'status' => 'success',
                'data' => [],
            ]);
        }

        try {
            $districtsFile = database_path('data/binderbyte_districts.json');
            if (!file_exists($districtsFile)) {
                $districtsFile = storage_path('app/binderbyte_districts.json');
            }

            if (file_exists($districtsFile)) {
                $districts = json_decode(file_get_contents($districtsFile), true) ?: [];

                $searchTerms = array_filter(explode(' ', strtolower($search)));
                $matched = [];

                foreach ($districts as $item) {
                    $labelLower = strtolower($item['label'] ?? '');
                    $matchCount = 0;

                    foreach ($searchTerms as $term) {
                        if (str_contains($labelLower, $term)) {
                            $matchCount++;
                        }
                    }

                    if ($matchCount === count($searchTerms)) {
                        $matched[] = [
                            'id' => $item['id'],
                            'label' => $item['label'],
                            'subdistrict' => $item['district'] ?? '',
                            'district' => $item['district'] ?? '',
                            'city' => $item['city'] ?? '',
                            'province' => $item['province'] ?? '',
                            'zipCode' => $item['zipCode'] ?? '',
                        ];

                        if (count($matched) >= 30) {
                            break;
                        }
                    }
                }

                return response()->json([
                    'status' => 'success',
                    'data' => $matched,
                ]);
            }

            // Fallback: If dataset file doesn't exist, query BinderByte API or return empty
            return response()->json([
                'status' => 'success',
                'data' => [],
            ]);
        } catch (\Throwable $e) {
            Log::error("BinderByte destination search exception: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memuat data destinasi wilayah',
                'data' => [],
            ], 500);
        }
    }

    /**
     * Calculate live shipping costs for JNE and JNT via BinderByte API.
     */
    public function calculateCost(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'destination' => 'required',
            'weight' => 'nullable|integer|min:1',
            'courier' => 'nullable|string',
        ]);

        $apiKey = config('services.binderbyte.key', env('BINDERBYTE_API_KEY', 'sk_9utxs5qa60bmkycnvokiqrnsyjon0dygkkg71f92c2gqvpmkhdjzaisrmvzikl2t'));
        $baseUrl = rtrim(config('services.binderbyte.base_url', 'https://api.binderbyte.com'), '/');
        $originId = config('services.binderbyte.origin_id', env('BINDERBYTE_ORIGIN_ID', 'dist_35.15.07'));

        // Normalize destination id format (e.g. "dist_32.73.25" or "32.73.25")
        $destinationId = (string) $validated['destination'];
        if (!str_starts_with($destinationId, 'dist_') && !str_starts_with($destinationId, 'city_')) {
            $destinationId = 'dist_' . $destinationId;
        }

        // Convert weight (grams to kg integer/decimal, min 1kg for standard calculation)
        $weightGrams = max((int) ($validated['weight'] ?? 1000), 500);
        $weightKg = max(ceil($weightGrams / 1000), 1);

        // Filter: ONLY JNE, JNT, and J&T Cargo couriers are permitted
        $courier = 'jne,jnt,jnt_cargo';

        try {
            $response = Http::timeout(12)->get("{$baseUrl}/v1/cost", [
                'api_key' => $apiKey,
                'origin' => $originId,
                'destination' => $destinationId,
                'weight' => (string) $weightKg,
                'courier' => $courier,
            ]);

            if ($response->successful()) {
                $json = $response->json();
                $courierResults = $json['data']['results'] ?? [];

                $formattedOptions = [];

                foreach ($courierResults as $cr) {
                    $code = strtolower($cr['code'] ?? 'jne');

                    // Strictly only allow jne, jnt, and jnt_cargo
                    if (!in_array($code, ['jne', 'jnt', 'jnt_cargo'])) {
                        continue;
                    }

                    $courierName = $cr['name'] ?? ($code === 'jnt_cargo' ? 'J&T Cargo' : ($code === 'jnt' ? 'J&T Express' : 'JNE Express'));
                    $costs = $cr['costs'] ?? [];

                    foreach ($costs as $costItem) {
                        $service = $costItem['service'] ?? 'REG';
                        $price = (float) ($costItem['price'] ?? 0);
                        $type = $costItem['type'] ?? '';
                        $etdRaw = trim((string) ($costItem['estimated'] ?? ''));

                        // Format ETD into readable Indonesian
                        $etdFormatted = '';
                        if (!empty($etdRaw) && $etdRaw !== '-' && $etdRaw !== '- hari') {
                            $etdClean = str_ireplace(['days', 'day', 'hari', 'H'], '', $etdRaw);
                            $etdFormatted = trim($etdClean) . ' Hari';
                        } else {
                            $etdFormatted = $code === 'jnt' ? '1-3 Hari' : '2-3 Hari';
                        }

                        $label = "{$courierName} - {$service}" . ($etdFormatted ? " ({$etdFormatted})" : '');

                        $formattedOptions[] = [
                            'id' => "{$code}_{$service}",
                            'code' => $code,
                            'courierName' => $courierName,
                            'service' => $service,
                            'description' => !empty($type) ? "Layanan {$type}" : "Pengiriman {$service}",
                            'cost' => $price,
                            'etd' => $etdFormatted,
                            'name' => $label,
                        ];
                    }
                }

                // Sort by cheapest cost
                usort($formattedOptions, function ($a, $b) {
                    return $a['cost'] <=> $b['cost'];
                });

                return response()->json([
                    'status' => 'success',
                    'data' => $formattedOptions,
                    'originId' => $originId,
                    'destinationId' => $destinationId,
                    'weight' => $weightGrams,
                ]);
            }

            Log::warning("BinderByte calculate cost failed: " . $response->body());
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghitung ongkos kirim dari kurir BinderByte',
                'data' => [],
            ], 500);
        } catch (\Throwable $e) {
            Log::error("BinderByte cost exception: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Layanan cek ongkir sedang tidak dapat dihubungi',
                'data' => [],
            ], 500);
        }
    }

    /**
     * Live Tracking (Cek Resi) via BinderByte API.
     */
    public function trackAwb(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'awb' => 'required|string|max:100',
            'courier' => 'required|string|max:50',
        ]);

        $apiKey = config('services.binderbyte.key', env('BINDERBYTE_API_KEY', 'sk_9utxs5qa60bmkycnvokiqrnsyjon0dygkkg71f92c2gqvpmkhdjzaisrmvzikl2t'));
        $baseUrl = rtrim(config('services.binderbyte.base_url', 'https://api.binderbyte.com'), '/');

        $awb = trim($validated['awb']);
        $courier = strtolower(trim($validated['courier']));

        // Normalize common courier names/codes
        $courierMap = [
            'j&t' => 'jnt',
            'j&t express' => 'jnt',
            'j&t cargo' => 'jnt_cargo',
            'jnt cargo' => 'jnt_cargo',
            'jne express' => 'jne',
            'sicepat express' => 'sicepat',
            'pos indonesia' => 'pos',
            'shopee express' => 'spx',
            'id express' => 'ide',
            'ninja xpress' => 'ninja',
            'lion parcel' => 'lion',
        ];

        if (isset($courierMap[$courier])) {
            $courier = $courierMap[$courier];
        }

        try {
            $response = Http::timeout(15)->get("{$baseUrl}/v1/track", [
                'api_key' => $apiKey,
                'courier' => $courier,
                'awb' => $awb,
            ]);

            $json = $response->json();
            $statusCode = $response->status();

            if ($response->successful() && isset($json['data'])) {
                $data = $json['data'];
                $summary = $data['summary'] ?? [];
                $detail = $data['detail'] ?? [];
                $history = $data['history'] ?? [];

                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'summary' => [
                            'awb' => $summary['awb'] ?? $awb,
                            'courier' => $summary['courier'] ?? strtoupper($courier),
                            'service' => $summary['service'] ?? '-',
                            'status' => strtoupper($summary['status'] ?? 'ON PROCESS'),
                            'date' => $summary['date'] ?? '-',
                            'desc' => $summary['desc'] ?? '',
                            'amount' => $summary['amount'] ?? '',
                            'weight' => $summary['weight'] ?? '',
                        ],
                        'detail' => [
                            'origin' => $detail['origin'] ?? '-',
                            'destination' => $detail['destination'] ?? '-',
                            'shipper' => $detail['shipper'] ?? 'OMEGA TOYS',
                            'receiver' => $detail['receiver'] ?? '-',
                        ],
                        'history' => array_map(function ($h) {
                            return [
                                'date' => $h['date'] ?? '',
                                'desc' => $h['desc'] ?? '',
                                'location' => $h['location'] ?? '',
                            ];
                        }, $history),
                    ],
                ]);
            }

            // If not found or specific BinderByte error message
            $message = $json['message'] ?? 'Nomor resi tidak ditemukan atau belum terupdate oleh pihak kurir.';
            return response()->json([
                'status' => 'error',
                'message' => $message,
                'data' => null,
            ], 404);

        } catch (\Throwable $e) {
            Log::error("BinderByte track exception: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Layanan pelacakan resi sedang gangguan, silakan coba beberapa saat lagi.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Get list of supported couriers for Tracking and Shipping.
     */
    public function listCouriers(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                'shipping' => [
                    ['code' => 'jne', 'name' => 'JNE Express'],
                    ['code' => 'jnt', 'name' => 'J&T Express'],
                    ['code' => 'jnt_cargo', 'name' => 'J&T Cargo'],
                ],
                'tracking' => [
                    ['code' => 'jne', 'name' => 'JNE Express'],
                    ['code' => 'jnt', 'name' => 'J&T Express'],
                    ['code' => 'jnt_cargo', 'name' => 'J&T Cargo'],
                    ['code' => 'sicepat', 'name' => 'SiCepat Express'],
                    ['code' => 'anteraja', 'name' => 'AnterAja'],
                    ['code' => 'pos', 'name' => 'POS Indonesia'],
                    ['code' => 'tiki', 'name' => 'TIKI'],
                    ['code' => 'ninja', 'name' => 'Ninja Xpress'],
                    ['code' => 'lion', 'name' => 'Lion Parcel'],
                    ['code' => 'wahana', 'name' => 'Wahana'],
                    ['code' => 'spx', 'name' => 'Shopee Express (SPX)'],
                    ['code' => 'ide', 'name' => 'ID Express'],
                ],
            ],
        ]);
    }
}
