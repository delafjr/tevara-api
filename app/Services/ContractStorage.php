<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Titik akses tunggal untuk berkas SPK/kontrak.
 *
 * Disk penyimpanan diatur lewat `filesystems.contracts_disk`, sehingga
 * penyimpanan lokal dapat diganti ke S3 / Cloudflare R2 / B2 hanya dengan
 * mengubah environment variable.
 */
class ContractStorage
{
    public const DIRECTORY = 'contracts';

    /** Disk lama: berkas kontrak sebelumnya disimpan di disk publik. */
    public const LEGACY_DISK = 'public';

    public function diskName(): string
    {
        return (string) config('filesystems.contracts_disk', 'local');
    }

    public function disk(): FilesystemAdapter
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($this->diskName());

        return $disk;
    }

    public function store(UploadedFile $file): string
    {
        $path = $file->store(self::DIRECTORY, $this->diskName());

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Berkas kontrak gagal disimpan.');
        }

        return $path;
    }

    /**
     * Cari disk yang benar-benar menyimpan berkas.
     *
     * Mendukung berkas lama yang masih berada di disk publik agar tetap dapat
     * diunduh sebelum dijalankan `php artisan contracts:migrate`.
     */
    public function resolveDisk(?string $path): ?FilesystemAdapter
    {
        if (empty($path)) {
            return null;
        }

        if ($this->disk()->exists($path)) {
            return $this->disk();
        }

        $legacy = Storage::disk(self::LEGACY_DISK);

        return $legacy->exists($path) ? $legacy : null;
    }

    public function delete(?string $path): void
    {
        if (empty($path)) {
            return;
        }

        if ($this->disk()->exists($path)) {
            $this->disk()->delete($path);
        }
    }
}
