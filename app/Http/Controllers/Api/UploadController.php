<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

use App\Helpers\MediaHelper;

class UploadController extends Controller
{
    /**
     * Upload an image file to local storage and FTP/SFTP server.
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
        ]);

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $extension = $file->getClientOriginalExtension() ?: 'jpg';
            $filename = 'img_' . date('Ymd_His') . '_' . Str::random(8) . '.' . $extension;

            // 1. Store locally in storage/app/public/uploads
            $localPath = $file->storeAs('uploads', $filename, 'public');
            $localFullPath = storage_path('app/public/uploads/' . $filename);

            // 2. Upload to remote FTP/SFTP Server (e.g. 100.91.206.4)
            $ftpUploaded = $this->uploadToFtp($localFullPath, $filename);

            // 3. Construct public accessible URL via API images route (supports dynamic FTP fallback)
            $url = MediaHelper::url($filename);

            return response()->json([
                'status' => 'success',
                'message' => 'Gambar berhasil diunggah ke server' . ($ftpUploaded ? ' dan tersimpan di server FTP' : ''),
                'data' => [
                    'url' => $url,
                    'filename' => $filename,
                    'path' => $localPath,
                    'ftp_stored' => $ftpUploaded,
                ],
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Tidak ada file gambar yang diunggah',
        ], 400);
    }

    /**
     * Stream image file from local cache or remote FTP server.
     */
    public function serve(string $filename)
    {
        // Sanitize filename to prevent directory traversal
        $filename = basename($filename);
        $localPath = storage_path('app/public/uploads/' . $filename);

        // Check if exists locally
        if (file_exists($localPath)) {
            $mime = mime_content_type($localPath) ?: 'image/png';
            return response()->file($localPath, [
                'Content-Type' => $mime,
                'Cache-Control' => 'public, max-age=86400',
                'Access-Control-Allow-Origin' => '*',
            ]);
        }

        // If not present locally, try to fetch from remote FTP server
        $remoteData = $this->fetchFromFtp($filename);
        if ($remoteData) {
            // Save to local cache
            if (!is_dir(dirname($localPath))) {
                @mkdir(dirname($localPath), 0755, true);
            }
            file_put_contents($localPath, $remoteData);

            $mime = mime_content_type($localPath) ?: 'image/png';
            return response()->file($localPath, [
                'Content-Type' => $mime,
                'Cache-Control' => 'public, max-age=86400',
                'Access-Control-Allow-Origin' => '*',
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Gambar tidak ditemukan',
        ], 404);
    }

    /**
     * Helper to upload file to FTP / SFTP server via cURL.
     */
    private function uploadToFtp(string $localFilePath, string $filename): bool
    {
        $host = env('FTP_HOST', '192.168.1.103');
        $user = env('FTP_USERNAME', 'lunaftp');
        $pass = env('FTP_PASSWORD', 'N2145tb@');
        $port = (int) env('FTP_PORT', 22);
        $protocol = env('FTP_PROTOCOL', 'sftp'); // sftp or ftp
        $remoteRoot = rtrim(env('FTP_ROOT', '/home/lunaftp/ftp/upload'), '/');

        if (!file_exists($localFilePath)) {
            return false;
        }

        $fileSize = filesize($localFilePath);
        $fp = fopen($localFilePath, 'r');
        if (!$fp) {
            return false;
        }

        try {
            $remoteUrl = "{$protocol}://{$host}:{$port}{$remoteRoot}/{$filename}";

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $remoteUrl);
            curl_setopt($ch, CURLOPT_USERPWD, "{$user}:{$pass}");
            curl_setopt($ch, CURLOPT_UPLOAD, true);
            curl_setopt($ch, CURLOPT_INFILE, $fp);
            curl_setopt($ch, CURLOPT_INFILESIZE, $fileSize);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

            if ($protocol === 'sftp') {
                curl_setopt($ch, CURLOPT_SSH_AUTH_TYPES, CURLSSH_AUTH_PASSWORD);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            } else {
                curl_setopt($ch, CURLOPT_FTP_USE_EPSV, true);
                curl_setopt($ch, CURLOPT_FTP_CREATE_MISSING_DIRS, true);
            }

            $response = curl_exec($ch);
            $err = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            fclose($fp);

            if ($err) {
                Log::warning("FTP upload error for {$filename}: {$err}");
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            if (is_resource($fp)) {
                fclose($fp);
            }
            Log::error("FTP upload exception for {$filename}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Helper to fetch file from FTP / SFTP server via cURL.
     */
    private function fetchFromFtp(string $filename): ?string
    {
        $host = env('FTP_HOST', '192.168.1.103');
        $user = env('FTP_USERNAME', 'lunaftp');
        $pass = env('FTP_PASSWORD', 'N2145tb@');
        $port = (int) env('FTP_PORT', 22);
        $protocol = env('FTP_PROTOCOL', 'sftp');
        $remoteRoot = rtrim(env('FTP_ROOT', '/home/lunaftp/ftp/upload'), '/');

        try {
            $remoteUrl = "{$protocol}://{$host}:{$port}{$remoteRoot}/{$filename}";

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $remoteUrl);
            curl_setopt($ch, CURLOPT_USERPWD, "{$user}:{$pass}");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

            if ($protocol === 'sftp') {
                curl_setopt($ch, CURLOPT_SSH_AUTH_TYPES, CURLSSH_AUTH_PASSWORD);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            }

            $data = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);

            if ($err || empty($data)) {
                return null;
            }

            return $data;
        } catch (\Throwable $e) {
            Log::error("FTP fetch exception for {$filename}: " . $e->getMessage());
            return null;
        }
    }
}
