<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    /**
     * Upload an image file and return accessible URL.
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ]);

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('uploads', $filename, 'public');

            $url = asset('storage/' . $path);

            return response()->json([
                'status' => 'success',
                'message' => 'Gambar berhasil diunggah',
                'data' => [
                    'url' => $url,
                    'path' => $path,
                ],
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Tidak ada file yang diunggah',
        ], 400);
    }
}
