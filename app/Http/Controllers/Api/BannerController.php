<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
                'image' => $banner->image,
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

        return response()->json([
            'status' => 'success',
            'message' => 'Banner berhasil diperbarui',
            'data' => $banner,
        ]);
    }

    /**
     * Delete a banner (Admin).
     */
    public function destroy($id): JsonResponse
    {
        $banner = Banner::findOrFail($id);
        $banner->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Banner berhasil dihapus',
        ]);
    }
}
