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
use App\Services\ActivityLogger;

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

            ActivityLogger::log(
                $request,
                'UPLOAD_IMAGE',
                "Mengunggah gambar baru '{$filename}'" . ($ftpUploaded ? " (Tersimpan ke SFTP)" : ""),
                null,
                ['filename' => $filename, 'url' => $url, 'ftp_stored' => $ftpUploaded]
            );

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
     * Diagnostic endpoint to test and verify FTP / SFTP connectivity.
     */
    public function diagnostics(): JsonResponse
    {
        $protocol = config('filesystems.ftp_settings.protocol', env('FTP_PROTOCOL', 'sftp'));
        $host = config('filesystems.ftp_settings.host', env('FTP_HOST', '100.91.206.4'));
        $port = (int) config('filesystems.ftp_settings.port', env('FTP_PORT', 22));
        $user = config('filesystems.ftp_settings.username', env('FTP_USERNAME', 'lunaftp'));
        $pass = config('filesystems.ftp_settings.password', env('FTP_PASSWORD', 'N2145tb@'));
        $root = rtrim(config('filesystems.ftp_settings.root', env('FTP_ROOT', '/home/lunaftp/ftp/upload')), '/');

        // 1. Test TCP port reachability
        $socketConnected = false;
        $socketError = '';
        $errno = 0;
        $errstr = '';
        $fp = @fsockopen($host, $port, $errno, $errstr, 4);
        if ($fp) {
            $socketConnected = true;
            fclose($fp);
        } else {
            $socketError = "({$errno}) {$errstr}";
        }

        $curlProtocols = function_exists('curl_version') ? (curl_version()['protocols'] ?? []) : [];
        $supportsSftpCurl = in_array('sftp', $curlProtocols);

        // 2. Test sample file retrieval
        $sampleFilename = 'img_20260813_145548_4thmS3ZG.png';
        $fetched = $this->fetchFromFtp($sampleFilename);

        return response()->json([
            'status' => 'success',
            'php_version' => PHP_VERSION,
            'ftp_config' => [
                'host' => $host,
                'port' => $port,
                'user' => $user,
                'protocol' => $protocol,
                'root' => $root,
            ],
            'network_test' => [
                'tcp_connect' => $socketConnected ? 'SUCCESS' : "FAILED: {$socketError}",
            ],
            'drivers' => [
                'curl_installed' => function_exists('curl_init'),
                'curl_sftp_support' => $supportsSftpCurl,
                'ssh2_extension' => function_exists('ssh2_connect'),
                'phpseclib_available' => class_exists('\phpseclib3\Net\SFTP'),
                'native_ftp' => function_exists('ftp_connect'),
            ],
            'sample_fetch_test' => [
                'filename' => $sampleFilename,
                'success' => !empty($fetched),
                'bytes' => strlen($fetched ?? ''),
            ],
        ]);
    }

    /**
     * Helper to upload file to FTP / SFTP server using multi-strategy fallback.
     */
    private function uploadToFtp(string $localFilePath, string $filename): bool
    {
        $protocol = config('filesystems.ftp_settings.protocol', env('FTP_PROTOCOL', 'sftp'));
        $host = config('filesystems.ftp_settings.host', env('FTP_HOST', '100.91.206.4'));
        $port = (int) config('filesystems.ftp_settings.port', env('FTP_PORT', 22));
        $user = config('filesystems.ftp_settings.username', env('FTP_USERNAME', 'lunaftp'));
        $pass = config('filesystems.ftp_settings.password', env('FTP_PASSWORD', 'N2145tb@'));
        $remoteRoot = rtrim(config('filesystems.ftp_settings.root', env('FTP_ROOT', '/home/lunaftp/ftp/upload')), '/');
        $remotePath = "{$remoteRoot}/{$filename}";

        if (!file_exists($localFilePath)) {
            return false;
        }

        // Strategy 1: phpseclib if available
        if (class_exists('\phpseclib3\Net\SFTP')) {
            try {
                $sftp = new \phpseclib3\Net\SFTP($host, $port, 10);
                if ($sftp->login($user, $pass)) {
                    $res = $sftp->put($remotePath, file_get_contents($localFilePath));
                    if ($res) {
                        return true;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("phpseclib upload error: " . $e->getMessage());
            }
        }

        // Strategy 2: ssh2 extension
        if ($protocol === 'sftp' && function_exists('ssh2_connect')) {
            try {
                $connection = @ssh2_connect($host, $port);
                if ($connection && @ssh2_auth_password($connection, $user, $pass)) {
                    $sftp = @ssh2_sftp($connection);
                    if ($sftp) {
                        $remoteStream = @fopen("ssh2.sftp://" . intval($sftp) . $remotePath, 'w');
                        $localStream = @fopen($localFilePath, 'r');
                        if ($remoteStream && $localStream) {
                            stream_copy_to_stream($localStream, $remoteStream);
                            fclose($localStream);
                            fclose($remoteStream);
                            return true;
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("ssh2 upload error: " . $e->getMessage());
            }
        }

        // Strategy 3: cURL
        if (function_exists('curl_init')) {
            $fp = fopen($localFilePath, 'r');
            if ($fp) {
                try {
                    $remoteUrl = "{$protocol}://{$host}:{$port}{$remoteRoot}/{$filename}";
                    $fileSize = filesize($localFilePath);

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
                    curl_close($ch);
                    fclose($fp);

                    if (!$err) {
                        return true;
                    }
                    Log::warning("cURL upload error for {$filename} to {$host}: {$err}");
                } catch (\Throwable $e) {
                    if (is_resource($fp)) {
                        fclose($fp);
                    }
                    Log::error("cURL upload exception: " . $e->getMessage());
                }
            }
        }

        return false;
    }

    /**
     * Helper to fetch file from FTP / SFTP server using multi-strategy fallback.
     */
    private function fetchFromFtp(string $filename): ?string
    {
        $protocol = config('filesystems.ftp_settings.protocol', env('FTP_PROTOCOL', 'sftp'));
        $host = config('filesystems.ftp_settings.host', env('FTP_HOST', '100.91.206.4'));
        $port = (int) config('filesystems.ftp_settings.port', env('FTP_PORT', 22));
        $user = config('filesystems.ftp_settings.username', env('FTP_USERNAME', 'lunaftp'));
        $pass = config('filesystems.ftp_settings.password', env('FTP_PASSWORD', 'N2145tb@'));
        $remoteRoot = rtrim(config('filesystems.ftp_settings.root', env('FTP_ROOT', '/home/lunaftp/ftp/upload')), '/');
        $remotePath = "{$remoteRoot}/{$filename}";

        // Strategy 1: phpseclib if available
        if (class_exists('\phpseclib3\Net\SFTP')) {
            try {
                $sftp = new \phpseclib3\Net\SFTP($host, $port, 8);
                if ($sftp->login($user, $pass)) {
                    $data = $sftp->get($remotePath);
                    if ($data !== false && !empty($data)) {
                        return $data;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("phpseclib fetch error: " . $e->getMessage());
            }
        }

        // Strategy 2: ssh2 extension if available
        if ($protocol === 'sftp' && function_exists('ssh2_connect')) {
            try {
                $connection = @ssh2_connect($host, $port);
                if ($connection && @ssh2_auth_password($connection, $user, $pass)) {
                    $sftp = @ssh2_sftp($connection);
                    if ($sftp) {
                        $stream = @fopen("ssh2.sftp://" . intval($sftp) . $remotePath, 'r');
                        if ($stream) {
                            $contents = stream_get_contents($stream);
                            fclose($stream);
                            if ($contents !== false && !empty($contents)) {
                                return $contents;
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("ssh2 fetch error: " . $e->getMessage());
            }
        }

        // Strategy 3: cURL (SFTP or FTP)
        if (function_exists('curl_init')) {
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

                if (!$err && !empty($data)) {
                    return $data;
                }
                if ($err) {
                    Log::warning("cURL fetch error for {$filename} from {$host}: {$err}");
                }
            } catch (\Throwable $e) {
                Log::warning("cURL fetch exception: " . $e->getMessage());
            }
        }

        // Strategy 4: Native FTP (if protocol is ftp)
        if ($protocol === 'ftp' && function_exists('ftp_connect')) {
            try {
                $conn = @ftp_connect($host, $port, 5);
                if ($conn && @ftp_login($conn, $user, $pass)) {
                    @ftp_pasv($conn, true);
                    $tempStream = fopen('php://temp', 'r+');
                    if (@ftp_fget($conn, $tempStream, $remotePath, FTP_BINARY)) {
                        rewind($tempStream);
                        $contents = stream_get_contents($tempStream);
                        fclose($tempStream);
                        ftp_close($conn);
                        return $contents;
                    }
                    fclose($tempStream);
                    ftp_close($conn);
                }
            } catch (\Throwable $e) {
                Log::warning("Native FTP fetch error: " . $e->getMessage());
            }
        }

        return null;
    }
}
