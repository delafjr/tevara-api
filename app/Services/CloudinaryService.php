<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CloudinaryService
{
    public const FOLDER_TASK = 'tevara/task';
    public const FOLDER_PEMBAYARAN = 'tevara/pembayaran';

    private const MAX_SIZE_BYTES = 10 * 1024 * 1024; // 10 MB
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'];

    private string $cloudName;
    private string $apiKey;
    private string $apiSecret;

    public function __construct()
    {
        $this->cloudName = (string) config('services.cloudinary.cloud_name', env('CLOUDINARY_CLOUD_NAME', ''));
        $this->apiKey    = (string) config('services.cloudinary.api_key', env('CLOUDINARY_API_KEY', ''));
        $this->apiSecret = (string) config('services.cloudinary.api_secret', env('CLOUDINARY_API_SECRET', ''));
    }

    /**
     * Upload gambar ke Cloudinary.
     *
     * @param UploadedFile $file
     * @param string $folder Contoh: CloudinaryService::FOLDER_TASK atau 'task'
     * @param string $fieldName Nama field untuk pesan error validasi
     * @return string URL publik gambar (secure_url)
     *
     * @throws ValidationException
     * @throws RuntimeException
     */
    public function upload(UploadedFile $file, string $folder, string $fieldName = 'file'): string
    {
        $this->validateImage($file, $fieldName);

        if (empty($this->cloudName) || empty($this->apiKey) || empty($this->apiSecret)) {
            throw new RuntimeException('Cloudinary credentials belum lengkap di .env (CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY, CLOUDINARY_API_SECRET).');
        }

        // Normalisasi folder (jika 'task' -> 'tevara/task', jika 'tevara/task' tetap)
        $targetFolder = str_starts_with($folder, 'tevara/') ? $folder : 'tevara/' . ltrim($folder, '/');

        $timestamp = time();
        $paramsToSign = [
            'folder'    => $targetFolder,
            'timestamp' => $timestamp,
        ];
        ksort($paramsToSign);

        $signParts = [];
        foreach ($paramsToSign as $k => $v) {
            $signParts[] = "{$k}={$v}";
        }
        $toSign = implode('&', $signParts) . $this->apiSecret;
        $signature = sha1($toSign);

        $response = Http::timeout(60)
            ->attach('file', fopen($file->getRealPath(), 'r'), $file->getClientOriginalName())
            ->post("https://api.cloudinary.com/v1_1/{$this->cloudName}/image/upload", [
                'api_key'   => $this->apiKey,
                'timestamp' => $timestamp,
                'folder'    => $targetFolder,
                'signature' => $signature,
            ]);

        if (! $response->successful()) {
            $errorMsg = $response->json('error.message') ?? $response->body();
            Log::error('Cloudinary upload gagal', [
                'status' => $response->status(),
                'error'  => $errorMsg,
            ]);
            throw new RuntimeException('Gagal mengunggah gambar ke Cloudinary: ' . $errorMsg);
        }

        return (string) $response->json('secure_url');
    }

    /**
     * Dapatkan URL publik gambar siap saji untuk frontend.
     * Mendukung URL Cloudinary, URL eksternal (http/https), path lokal legacy, atau null.
     */
    public function getUrl(?string $pathOrUrl): ?string
    {
        if (empty($pathOrUrl)) {
            return null;
        }

        // Jika sudah berupa URL absolut (Cloudinary https://res.cloudinary.com/... atau URL eksternal lain)
        if (str_starts_with($pathOrUrl, 'http://') || str_starts_with($pathOrUrl, 'https://')) {
            return $pathOrUrl;
        }

        // Jika masih berupa path lokal relatif (legacy data)
        return asset('storage/' . ltrim($pathOrUrl, '/'));
    }

    /**
     * Hapus gambar dari Cloudinary atau disk lokal (jika legacy).
     */
    public function delete(?string $url): bool
    {
        if (empty($url)) {
            return false;
        }

        // Jika URL Cloudinary
        if (str_contains($url, 'cloudinary.com')) {
            $path = parse_url($url, PHP_URL_PATH);
            if ($path && preg_match('/upload\/(?:v\d+\/)?(.+?)(?:\.[^.]+)?$/', $path, $matches)) {
                $publicId = $matches[1];
                $timestamp = time();
                $signature = sha1("public_id={$publicId}&timestamp={$timestamp}" . $this->apiSecret);

                $response = Http::timeout(30)->post("https://api.cloudinary.com/v1_1/{$this->cloudName}/image/destroy", [
                    'public_id' => $publicId,
                    'api_key'   => $this->apiKey,
                    'timestamp' => $timestamp,
                    'signature' => $signature,
                ]);

                return $response->successful();
            }
            return false;
        }

        // Jika path lokal lama
        if (! str_starts_with($url, 'http') && Storage::disk('public')->exists($url)) {
            return Storage::disk('public')->delete($url);
        }

        return false;
    }

    /**
     * Validasi file harus gambar dan ukuran maks 10MB.
     *
     * @throws ValidationException
     */
    public function validateImage(UploadedFile $file, string $fieldName = 'file'): void
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                $fieldName => ['Berkas gambar yang diunggah tidak valid atau rusak.'],
            ]);
        }

        if ($file->getSize() > self::MAX_SIZE_BYTES) {
            throw ValidationException::withMessages([
                $fieldName => ['Ukuran berkas gambar maksimal 10 MB.'],
            ]);
        }

        $mime = (string) $file->getMimeType();
        $ext  = strtolower($file->getClientOriginalExtension());

        $isImageMime = str_starts_with($mime, 'image/');
        $isAllowedExt = in_array($ext, self::ALLOWED_EXTENSIONS, true);

        if (! $isImageMime && ! $isAllowedExt) {
            throw ValidationException::withMessages([
                $fieldName => ['Berkas harus berformat gambar (JPG, JPEG, PNG, WEBP, GIF, SVG).'],
            ]);
        }
    }
}
