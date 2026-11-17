<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ContractStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateContractFilesCommand extends Command
{
    protected $signature = 'contracts:migrate
                            {--delete-legacy : Hapus berkas dari disk publik setelah berhasil dipindahkan}';

    protected $description = 'Pindahkan berkas SPK/kontrak dari disk publik ke disk privat kontrak';

    public function handle(ContractStorage $contracts): int
    {
        $legacy = Storage::disk(ContractStorage::LEGACY_DISK);
        $files = $legacy->files(ContractStorage::DIRECTORY);

        if (empty($files)) {
            $this->info('Tidak ada berkas kontrak pada disk publik.');

            return self::SUCCESS;
        }

        $moved = 0;
        $skipped = 0;

        foreach ($files as $path) {
            if ($contracts->disk()->exists($path)) {
                $skipped++;

                continue;
            }

            $stream = $legacy->readStream($path);

            if (! is_resource($stream)) {
                $this->warn("Gagal membaca {$path}, dilewati.");
                $skipped++;

                continue;
            }

            $contracts->disk()->writeStream($path, $stream);

            if (is_resource($stream)) {
                fclose($stream);
            }

            if (! $contracts->disk()->exists($path)) {
                $this->error("Gagal menulis {$path} ke disk kontrak, dilewati.");
                $skipped++;

                continue;
            }

            $moved++;
            $this->line("Dipindahkan: {$path}");

            if ($this->option('delete-legacy')) {
                $legacy->delete($path);
            }
        }

        $disk = $contracts->diskName();
        $this->info("Selesai. {$moved} berkas dipindahkan ke disk '{$disk}', {$skipped} dilewati.");

        if (! $this->option('delete-legacy') && $moved > 0) {
            $this->comment('Berkas lama masih ada di disk publik. Jalankan ulang dengan --delete-legacy untuk menghapusnya.');
        }

        return self::SUCCESS;
    }
}
