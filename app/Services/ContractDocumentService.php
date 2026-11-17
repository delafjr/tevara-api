<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Proposal;
use Carbon\Carbon;
use PhpOffice\PhpWord\TemplateProcessor;
use RuntimeException;

class ContractDocumentService
{
    private const TEMPLATE = 'templates/spk-template.docx';

    public function __construct(
        private readonly ContractStorage $storage
    ) {}

    public function generate(Proposal $proposal): string
    {
        $proposal->loadMissing([
            'client.clientPic.clientProfile',
            'client.clientProfile',
            'payments',
            'maintenance',
        ]);

        $templatePath = storage_path('app/' . self::TEMPLATE);

        if (! file_exists($templatePath)) {
            throw new RuntimeException('Template SPK tidak ditemukan: ' . self::TEMPLATE);
        }

        $tp = new TemplateProcessor($templatePath);

        $now = Carbon::now()->locale('id');
        $start = $proposal->start_date ? Carbon::parse($proposal->start_date)->locale('id') : $now;

        $tp->setValue('SPK_NUMBER', $proposal->spk_number ?? '-');
        $tp->setValue('HARI', $now->translatedFormat('l'));
        $tp->setValue('TANGGAL_PANJANG', $now->translatedFormat('d') . ' bulan ' . $now->translatedFormat('F') . ' tahun ' . $now->translatedFormat('Y'));

        $tp->setValue('NAMA_PENGURUS', $proposal->reviewer?->name ?? 'Pengurus TeFa');
        $tp->setValue('JABATAN_PENGURUS', 'Pengurus Teaching Factory');
        $tp->setValue('ALAMAT_TEFA', 'Jl. Prof. Sudarto, SH, Tembalang, Semarang');

        $client = $proposal->client;
        $clientProfile = $client?->clientPic?->clientProfile ?? $client?->clientProfile;

        $tp->setValue('NAMA_KLIEN', $client?->name ?? '-');
        $tp->setValue('JABATAN_KLIEN', $client?->clientPic?->position ?? 'Perwakilan');
        $tp->setValue('PERUSAHAAN_KLIEN', $proposal->client_company_name);
        $tp->setValue('ALAMAT_KLIEN', $clientProfile?->address ?? '-');

        $tp->setValue('TANGGAL_MULAI', $start->translatedFormat('d F Y'));
        $tp->setValue('TARGET_SELESAI', $proposal->target_completion_date
            ? Carbon::parse($proposal->target_completion_date)->locale('id')->translatedFormat('d F Y')
            : '-');

        $tp->setValue('TOTAL_BIAYA', 'Rp ' . number_format((float) $proposal->total_amount, 0, ',', '.'));

        $tp->setValue('SKEMA_TERMIN', $this->buildTerminText($proposal));
        $tp->setValue('HAK_CIPTA', 'Milik Bersama (Shared)');
        $tp->setValue('GARANSI_BULAN', (string) ($proposal->maintenance?->maintenance_warranty_months ?? 3));
        $tp->setValue('KOTA', 'Semarang');
        $tp->setValue('TANGGAL_TTD', $now->translatedFormat('d F Y'));

        $fileName = $this->buildFileName($proposal);
        $tempFile = tempnam(sys_get_temp_dir(), 'spk_') . '.docx';
        $tp->saveAs($tempFile);

        $storagePath = ContractStorage::DIRECTORY . '/' . $fileName;
        $this->storage->disk()->put($storagePath, file_get_contents($tempFile));

        @unlink($tempFile);

        return $storagePath;
    }

    private function buildTerminText(Proposal $proposal): string
    {
        $payments = $proposal->payments->sortBy('term_sequence');

        if ($payments->isEmpty()) {
            return $proposal->payment_terms_description ?? 'Skema pembayaran akan ditentukan kemudian.';
        }

        $total = (float) $proposal->total_amount;
        $lines = [];

        foreach ($payments as $payment) {
            $seq = $payment->term_sequence;
            $amount = (float) $payment->amount;
            $pct = $total > 0 ? round($amount / $total * 100) : 0;
            $formatted = 'Rp ' . number_format($amount, 0, ',', '.');
            $note = $payment->verification_notes ?? ('Termin ' . $seq);

            $lines[] = "Termin {$seq} ({$note}): Sebesar {$formatted} ({$pct}%)";
        }

        return implode("\n", $lines);
    }

    private function buildFileName(Proposal $proposal): string
    {
        $base = $proposal->spk_number ?: ('SPK-PROPOSAL-' . $proposal->id);
        $safe = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $base), '-');

        return $safe . '.docx';
    }
}
