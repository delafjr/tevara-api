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

    public function generate(Proposal $proposal): ?string
    {
        $proposal->loadMissing([
            'client.clientPic.clientProfile',
            'client.clientProfile',
            'payments',
        ]);

        $templatePath = storage_path('app/' . self::TEMPLATE);

        if (! file_exists($templatePath)) {
            $this->ensureTemplateExists($templatePath);
        }

        if (! file_exists($templatePath) || ! class_exists(TemplateProcessor::class)) {
            \Illuminate\Support\Facades\Log::warning('Template SPK atau PhpWord TemplateProcessor tidak tersedia: ' . self::TEMPLATE);
            return null;
        }

        try {
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
            $tp->setValue('PERUSAHAAN_KLIEN', $proposal->client_company_name ?? ($clientProfile?->company_name ?? '-'));
            $tp->setValue('ALAMAT_KLIEN', $clientProfile?->address ?? '-');

            $tp->setValue('TANGGAL_MULAI', $start->translatedFormat('d F Y'));
            $tp->setValue('TARGET_SELESAI', $proposal->target_completion_date
                ? Carbon::parse($proposal->target_completion_date)->locale('id')->translatedFormat('d F Y')
                : '-');

            $tp->setValue('TOTAL_BIAYA', 'Rp ' . number_format((float) $proposal->total_amount, 0, ',', '.'));

            $tp->setValue('SKEMA_TERMIN', $this->buildTerminText($proposal));
            $tp->setValue('HAK_CIPTA', 'Milik Bersama (Shared)');
            $tp->setValue('GARANSI_BULAN', (string) ($proposal->warranty_months ?? 0));
            $tp->setValue('KOTA', 'Semarang');
            $tp->setValue('TANGGAL_TTD', $now->translatedFormat('d F Y'));

            $fileName = $this->buildFileName($proposal);
            $tempFile = tempnam(sys_get_temp_dir(), 'spk_') . '.docx';
            $tp->saveAs($tempFile);

            $storagePath = ContractStorage::DIRECTORY . '/' . $fileName;
            $this->storage->disk()->put($storagePath, file_get_contents($tempFile));

            @unlink($tempFile);

            return $storagePath;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal menghasilkan dokumen SPK: ' . $e->getMessage());
            return null;
        }
    }

    private function ensureTemplateExists(string $templatePath): void
    {
        $dir = dirname($templatePath);
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        if (class_exists(\PhpOffice\PhpWord\PhpWord::class)) {
            try {
                $phpWord = new \PhpOffice\PhpWord\PhpWord();
                $section = $phpWord->addSection();
                $section->addTitle('SURAT PERJANJIAN KERJA SAMA (SPK)', 1);
                $section->addText('Nomor: ${SPK_NUMBER}');
                $section->addText('Pada hari ${HARI}, ${TANGGAL_PANJANG}, di ${KOTA}:');
                $section->addText('Pihak Pertama: ${NAMA_PENGURUS} (${JABATAN_PENGURUS}) - ${ALAMAT_TEFA}');
                $section->addText('Pihak Kedua: ${NAMA_KLIEN} (${JABATAN_KLIEN}) - ${PERUSAHAAN_KLIEN}, ${ALAMAT_KLIEN}');
                $section->addText('Periode: ${TANGGAL_MULAI} s/d ${TARGET_SELESAI}');
                $section->addText('Total Biaya: ${TOTAL_BIAYA}');
                $section->addText('Skema Pembayaran:');
                $section->addText('${SKEMA_TERMIN}');
                $section->addText('Garansi: ${GARANSI_BULAN} Bulan | Hak Cipta: ${HAK_CIPTA}');
                $section->addText('Ditetapkan di ${KOTA}, tanggal ${TANGGAL_TTD}');

                $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
                $objWriter->save($templatePath);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Tidak dapat membuat file template SPK otomatis: ' . $e->getMessage());
            }
        }
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
