<?php

namespace App\Helpers;

class MediaHelper
{
    /**
     * Normalize any image URL or path to a full public URL.
     */
    public static function url(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        $path = trim($path);

        // If already an external third-party URL (e.g. Unsplash, external CDN), return as-is
        if (preg_match('#^https?://(images\.unsplash\.com|via\.placeholder\.com|placehold\.co)#i', $path)) {
            return $path;
        }

        // If it's a local/uploaded image filename or URL (e.g., img_20260813_145548_4thmS3ZG.png or /storage/uploads/... or http://localhost:8000/...)
        if (preg_match('#img_[0-9_a-zA-Z]+\.(png|jpg|jpeg|webp|gif|svg)#i', $path, $matches)) {
            $filename = $matches[0];
            return url('api/images/' . $filename);
        }

        // If it starts with /api/images/ or api/images/
        if (preg_match('#api/images/([^/?#]+)#i', $path, $matches)) {
            return url('api/images/' . $matches[1]);
        }

        // If it starts with /storage/uploads/ or storage/uploads/
        if (preg_match('#storage/uploads/([^/?#]+)#i', $path, $matches)) {
            return url('api/images/' . $matches[1]);
        }

        // If starts with http:// or https:// but is from localhost / 127.0.0.1
        if (preg_match('#https?://(?:localhost|127\.0\.0\.1)(?::\d+)?/(?:storage/uploads/|api/images/)([^/?#]+)#i', $path, $matches)) {
            return url('api/images/' . $matches[1]);
        }

        // If it is another full http(s) URL with an image filename
        if (preg_match('#^https?://#i', $path)) {
            // If it points to this API domain's storage/uploads or api/images, ensure /api/images
            if (preg_match('#/(?:storage/uploads/|api/images/)([^/?#]+)#i', $path, $matches)) {
                return url('api/images/' . $matches[1]);
            }
            return $path;
        }

        // Otherwise, if it has an image extension, route through api/images
        if (preg_match('#\.(png|jpg|jpeg|webp|gif|svg)$#i', $path)) {
            return url('api/images/' . basename($path));
        }

        return $path;
    }
}
