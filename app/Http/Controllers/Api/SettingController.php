<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\ActivityLogger;

class SettingController extends Controller
{
    /**
     * Display key-value settings.
     */
    public function index(): JsonResponse
    {
        $settings = Setting::all()->pluck('value', 'key')->toArray();

        // Defaults
        $defaults = [
            'store_name' => 'OMEGA TOYS',
            'store_description' => 'Katalog Mainan Edukasi & Koleksi Terbaik',
            'whatsapp_number' => '6281234567890',
            'contact_email' => 'hello@omegatoys.com',
            'address' => 'Jakarta, Indonesia',
        ];

        $merged = array_merge($defaults, $settings);

        return response()->json([
            'status' => 'success',
            'data' => $merged,
        ]);
    }

    /**
     * Update settings (Admin).
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->all();

        foreach ($data as $key => $value) {
            Setting::set($key, (string) $value);
        }

        ActivityLogger::log(
            $request,
            'UPDATE_SETTINGS',
            "Memperbarui konfigurasi toko (" . implode(', ', array_keys($data)) . ")",
            null,
            ['updated_keys' => array_keys($data)]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Pengaturan berhasil disimpan',
            'data' => Setting::all()->pluck('value', 'key'),
        ]);
    }
}
