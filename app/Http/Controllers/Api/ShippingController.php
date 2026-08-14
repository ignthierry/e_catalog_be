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
     * Search domestic destination (subdistrict, city, province).
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

        $apiKey = config('services.rajaongkir.key', env('RAJAONGKIR_API_KEY', 'kFS41bAP695509c8c8419da3PY9irtKv'));
        $baseUrl = rtrim(config('services.rajaongkir.base_url', 'https://rajaongkir.komerce.id/api/v1'), '/');

        try {
            $response = Http::withHeaders([
                'key' => $apiKey,
            ])->timeout(8)->get("{$baseUrl}/destination/domestic-destination", [
                'search' => $search,
                'limit' => 20,
            ]);

            if ($response->successful()) {
                $json = $response->json();
                $items = $json['data'] ?? [];

                $formatted = array_map(function ($item) {
                    return [
                        'id' => $item['id'],
                        'label' => $item['label'] ?? "{$item['subdistrict_name']}, {$item['city_name']}, {$item['province_name']}",
                        'subdistrict' => $item['subdistrict_name'] ?? '',
                        'district' => $item['district_name'] ?? '',
                        'city' => $item['city_name'] ?? '',
                        'province' => $item['province_name'] ?? '',
                        'zipCode' => $item['zip_code'] ?? '',
                    ];
                }, $items);

                return response()->json([
                    'status' => 'success',
                    'data' => $formatted,
                ]);
            }

            Log::warning("RajaOngkir search destination failed: " . $response->body());
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mencari data destinasi pengiriman',
                'data' => [],
            ], 500);
        } catch (\Throwable $e) {
            Log::error("RajaOngkir destination exception: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Layanan cek ongkir sedang tidak dapat dihubungi',
                'data' => [],
            ], 500);
        }
    }

    /**
     * Calculate live shipping costs for multiple couriers based on destination and weight.
     */
    public function calculateCost(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'destination' => 'required|integer',
            'weight' => 'nullable|integer|min:1',
            'courier' => 'nullable|string',
        ]);

        $apiKey = config('services.rajaongkir.key', env('RAJAONGKIR_API_KEY', 'kFS41bAP695509c8c8419da3PY9irtKv'));
        $baseUrl = rtrim(config('services.rajaongkir.base_url', 'https://rajaongkir.komerce.id/api/v1'), '/');
        $originId = (int) config('services.rajaongkir.origin_id', env('RAJAONGKIR_ORIGIN_ID', 17473));
        $weight = max((int) ($validated['weight'] ?? 1000), 500); // Minimal 500g
        $courier = $validated['courier'] ?? config('services.rajaongkir.couriers', 'jne:jnt:sicepat:anteraja:ninja:pos');

        try {
            $response = Http::asForm()->withHeaders([
                'key' => $apiKey,
            ])->timeout(10)->post("{$baseUrl}/calculate/domestic-cost", [
                'origin' => $originId,
                'destination' => (int) $validated['destination'],
                'weight' => $weight,
                'courier' => $courier,
            ]);

            if ($response->successful()) {
                $json = $response->json();
                $courierResults = $json['data'] ?? [];

                $formattedOptions = [];

                foreach ($courierResults as $cr) {
                    $code = strtolower($cr['code'] ?? 'jne');
                    $service = $cr['service'] ?? 'REG';
                    $courierName = $cr['name'] ?? strtoupper($code);
                    $cost = (float) ($cr['cost'] ?? 0);
                    $etd = trim((string) ($cr['etd'] ?? ''));

                    // Format ETD into readable Indonesian
                    $etdFormatted = '';
                    if (!empty($etd)) {
                        $etdClean = str_ireplace(['days', 'day', 'hari'], '', $etd);
                        $etdFormatted = trim($etdClean) . ' Hari';
                    }

                    $label = "{$courierName} - {$service}" . ($etdFormatted ? " ({$etdFormatted})" : '');

                    $formattedOptions[] = [
                        'id' => "{$code}_{$service}",
                        'code' => $code,
                        'courierName' => $courierName,
                        'service' => $service,
                        'description' => $cr['description'] ?? '',
                        'cost' => $cost,
                        'etd' => $etdFormatted ?: '1-3 Hari',
                        'name' => $label,
                    ];
                }

                // Sort by cheapest cost
                usort($formattedOptions, function ($a, $b) {
                    return $a['cost'] <=> $b['cost'];
                });

                return response()->json([
                    'status' => 'success',
                    'data' => $formattedOptions,
                    'originId' => $originId,
                    'destinationId' => (int) $validated['destination'],
                    'weight' => $weight,
                ]);
            }

            Log::warning("RajaOngkir calculate cost failed: " . $response->body());
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghitung ongkos kirim dari kurir',
                'data' => [],
            ], 500);
        } catch (\Throwable $e) {
            Log::error("RajaOngkir cost exception: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Layanan cek ongkir sedang gangguan',
                'data' => [],
            ], 500);
        }
    }
}
