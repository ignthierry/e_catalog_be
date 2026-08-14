<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Helpers\MediaHelper;
use App\Services\ActivityLogger;

class BannerController extends Controller
{
    /**
     * Display a listing of active banners.
     */
    public function index(): JsonResponse
    {
        $banners = Banner::where('is_active', true)
            ->orderBy('order', 'asc')
            ->get();

        $formatted = $banners->map(function ($banner) {
            return [
                'id' => (string) $banner->id,
                'title' => $banner->title,
                'subtitle' => $banner->subtitle ?? '',
                'image' => MediaHelper::url($banner->image),
                'link' => $banner->link ?? '/products',
                'order' => $banner->order,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $formatted,
        ]);
    }

    /**
     * Store a newly created banner (Admin).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string',
            'image' => 'required|string',
            'link' => 'nullable|string',
            'order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $banner = Banner::create([
            'title' => $validated['title'],
            'subtitle' => $validated['subtitle'] ?? '',
            'image' => $validated['image'],
            'link' => $validated['link'] ?? '/products',
            'order' => $validated['order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        ActivityLogger::log(
            $request,
            'CREATE_BANNER',
            "Menambahkan banner promosi '{$banner->title}'",
            null,
            ['banner_id' => $banner->id, 'title' => $banner->title]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Banner promosi berhasil ditambahkan',
            'data' => $banner,
        ], 201);
    }

    /**
     * Update a banner (Admin).
     */
    public function update(Request $request, $id): JsonResponse
    {
        $banner = Banner::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'subtitle' => 'nullable|string',
            'image' => 'sometimes|required|string',
            'link' => 'nullable|string',
            'order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $banner->update($validated);

        ActivityLogger::log(
            $request,
            'UPDATE_BANNER',
            "Memperbarui banner '{$banner->title}' (ID: {$banner->id})",
            null,
            ['banner_id' => $banner->id, 'title' => $banner->title]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Banner berhasil diperbarui',
            'data' => $banner,
        ]);
    }

    /**
     * Delete a banner (Admin).
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $banner = Banner::findOrFail($id);
        $bannerTitle = $banner->title;
        $banner->delete();

        ActivityLogger::log(
            $request,
            'DELETE_BANNER',
            "Menghapus banner '{$bannerTitle}' (ID: {$id})",
            null,
            ['banner_id' => $id, 'title' => $bannerTitle]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Banner berhasil dihapus',
        ]);
    }
}
