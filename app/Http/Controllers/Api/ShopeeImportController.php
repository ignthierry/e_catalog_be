<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ShopeeImporter;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopeeImportController extends Controller
{
    /**
     * Clear all existing catalog data (products, categories, orders) and optionally import Shopee items.
     */
    public function resetAndImport(Request $request): JsonResponse
    {
        // 1. Reset all catalog data
        ShopeeImporter::clearAllCatalogData();

        $items = $request->input('items', []);

        // If file uploaded (JSON)
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $content = file_get_contents($file->getRealPath());
            $json = json_decode($content, true);
            if (is_array($json)) {
                $items = isset($json['items']) ? $json['items'] : (isset($json['data']) ? $json['data'] : $json);
            }
        }

        $importedResult = ['imported' => 0];

        if (!empty($items) && is_array($items)) {
            $importedResult = ShopeeImporter::importItems($items);
        }

        ActivityLogger::log(
            $request,
            'RESET_AND_IMPORT_CATALOG',
            "Mereset database katalog dan mengimpor {$importedResult['imported']} produk Shopee",
            null,
            ['imported_count' => $importedResult['imported']]
        );

        return response()->json([
            'status' => 'success',
            'message' => "Database katalog berhasil dibersihkan! " . ($importedResult['imported'] > 0 ? "Berhasil memasukkan {$importedResult['imported']} produk Shopee." : "Database kini dalam kondisi bersih siap diisi."),
            'data' => $importedResult,
        ]);
    }

    /**
     * Clear all catalog data only.
     */
    public function clearData(Request $request): JsonResponse
    {
        ShopeeImporter::clearAllCatalogData();

        ActivityLogger::log(
            $request,
            'CLEAR_CATALOG_DATA',
            "Mengosongkan seluruh data produk, kategori, dan pesanan",
            null
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Seluruh data produk, kategori, dan riwayat pesanan simulasi telah berhasil dibersihkan.',
        ]);
    }
}
