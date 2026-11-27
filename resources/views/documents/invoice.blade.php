<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoiceNumber }}</title>
    <style>
        body { color: #1e293b; font-family: DejaVu Sans, sans-serif; font-size: 11.5px; line-height: 1.5; }
        h1 { color: #0b3b82; font-size: 24px; margin: 0 0 4px 0; }
        .muted { color: #64748b; }
        .header { border-bottom: 2px solid #0b3b82; margin-bottom: 20px; padding-bottom: 14px; }
        .meta-table, .meta-table td { border: none; padding: 2px 0; }
        .section { margin-bottom: 18px; }
        .box { border: 1px solid #cbd5e1; background: #f8fafc; padding: 12px 14px; border-radius: 6px; margin-bottom: 18px; }
        .box-highlight { border: 1.5px solid #0b3b82; background: #eff6ff; padding: 12px 14px; border-radius: 6px; margin-bottom: 18px; }
        .box-title { color: #0b3b82; font-weight: bold; font-size: 12px; margin-bottom: 6px; text-transform: uppercase; }
        table.items { border-collapse: collapse; width: 100%; margin-bottom: 18px; }
        table.items th, table.items td { border: 1px solid #cbd5e1; padding: 10px; text-align: left; vertical-align: top; }
        table.items th { background: #eff6ff; color: #0b3b82; font-size: 11px; text-transform: uppercase; }
        .amount { font-size: 15px; font-weight: bold; text-align: right; white-space: nowrap; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; background: #fef3c7; color: #92400e; }
        .badge-paid { background: #d1fae5; color: #065f46; }
        .mono { font-family: DejaVu Sans Mono, monospace; font-weight: bold; color: #0f172a; }
    </style>
</head>
<body>
@php
    $dueDate = $payment->due_date ?? $payment->payment_date;
    $dueDateText = $dueDate ? \Carbon\Carbon::parse($dueDate)->translatedFormat('d F Y') : 'Belum ditetapkan';
    $termNote = ($payment->verification_notes && $payment->status !== 'REJECTED')
        ? $payment->verification_notes
        : ('Pembayaran Termin ' . ($payment->term_sequence ?? 1));
    $statusLabel = match ($payment->status) {
        'VERIFIED' => 'LUNAS (TERVERIFIKASI)',
        'REJECTED' => 'DITOLAK (PERLU UPLOAD ULANG)',
        default => $payment->payment_proof_url ? 'MENUNGGU VERIFIKASI' : 'MENUNGGU PEMBAYARAN',
    };
    $methodFallback = match ($payment->payment_method) {
        'BANK_TRANSFER' => 'Transfer Bank',
        'VIRTUAL_ACCOUNT' => 'Virtual Account',
        'CASH' => 'Tunai',
        'OTHER' => 'E-Wallet / Lainnya',
        default => null,
    };
@endphp

    <div class="header">
        <table class="meta-table" style="width: 100%;">
            <tr>
                <td style="width: 60%; vertical-align: top;">
                    <h1>INVOICE</h1>
                    <div style="font-weight: bold; color: #0f172a;">TEVARA · Teaching Factory Polines</div>
                    <div class="muted">Politeknik Negeri Semarang · Jl. Prof. Soedarto, Tembalang, Semarang</div>
                </td>
                <td style="width: 40%; text-align: right; vertical-align: top;">
                    <div><strong>No. Invoice:</strong> <span class="mono">{{ $invoiceNumber }}</span></div>
                    <div><strong>Tanggal Terbit:</strong> {{ now()->translatedFormat('d F Y') }}</div>
                    <div><strong>Jatuh Tempo:</strong> {{ $dueDateText }}</div>
                    <div style="margin-top: 4px;">
                        <span class="badge {{ $payment->status === 'VERIFIED' ? 'badge-paid' : '' }}">{{ $statusLabel }}</span>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="box">
        <table class="meta-table" style="width: 100%;">
            <tr>
                <td style="width: 50%; vertical-align: top; padding-right: 12px;">
                    <div class="box-title">Ditagihkan Kepada</div>
                    <div><strong>{{ $proposal->client_company_name }}</strong></div>
                    @if($proposal->client?->name)
                        <div class="muted">PIC: {{ $proposal->client->name }}</div>
                    @endif
                    @if($proposal->client?->email)
                        <div class="muted">{{ $proposal->client->email }}</div>
                    @endif
                </td>
                <td style="width: 50%; vertical-align: top;">
                    <div class="box-title">Informasi Proyek</div>
                    <div><strong>{{ $proposal->title }}</strong></div>
                    <div class="muted">
                        Kode: {{ $proposal->project_code ?? $proposal->proposal_code }}
                        @if($proposal->spk_number) · SPK: {{ $proposal->spk_number }} @endif
                    </div>
                    <div class="muted">
                        {{ ($payment->payment_type ?? '') === 'maintenance' ? 'Layanan Maintenance' : ('Termin ke-' . ($payment->term_sequence ?? 1)) }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 52%;">Uraian &amp; Keterangan Tagihan</th>
                <th style="width: 23%;">Jatuh Tempo</th>
                <th style="width: 25%; text-align: right;">Nominal Tagihan</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <strong>{{ ($payment->payment_type ?? '') === 'maintenance' ? 'Layanan Maintenance' : ('Termin ' . ($payment->term_sequence ?? 1)) }}</strong><br>
                    <span>{{ $payment->description ?? $termNote }}</span><br>
                    <span class="muted">Proyek: {{ $proposal->title }}</span>
                </td>
                <td>{{ $dueDateText }}</td>
                <td class="amount">Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    @if(!empty($selectedChannel))
        <div class="box-highlight">
            <div class="box-title">Kanal Pembayaran Dipilih Klien</div>
            <table class="meta-table" style="width: 100%;">
                <tr>
                    <td style="width: 28%;"><strong>Metode / Kanal</strong></td>
                    <td>: {{ $selectedChannel['group'] }} — <strong>{{ $selectedChannel['label'] }}</strong></td>
                </tr>
                <tr>
                    <td><strong>Nomor Tujuan</strong></td>
                    <td>: <span class="mono" style="font-size: 13px;">{{ $selectedChannel['account'] }}</span></td>
                </tr>
                <tr>
                    <td><strong>Atas Nama</strong></td>
                    <td>: {{ $selectedChannel['holder'] }}</td>
                </tr>
                <tr>
                    <td><strong>Berita Transfer</strong></td>
                    <td>: {{ $invoiceNumber }}</td>
                </tr>
            </table>
        </div>
    @else
        <div class="box">
            <div class="box-title">Kanal Pembayaran Resmi TEVARA</div>
            @if($methodFallback)
                <div style="margin-bottom: 6px;">Metode tercatat: <strong>{{ $methodFallback }}</strong></div>
            @endif
            <div class="muted" style="margin-bottom: 6px;">Silakan lakukan pembayaran melalui salah satu kanal berikut dan cantumkan nomor invoice <strong>{{ $invoiceNumber }}</strong>:</div>
            <table class="meta-table" style="width: 100%; font-size: 10.5px;">
                @foreach(($channels ?? []) as $ch)
                    <tr>
                        <td style="width: 34%;">{{ $ch['group'] }} — <strong>{{ $ch['label'] }}</strong></td>
                        <td style="width: 32%;"><span class="mono">{{ $ch['account'] }}</span></td>
                        <td class="muted">a.n. {{ $ch['holder'] }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

    <p class="muted" style="font-size: 10.5px;">
        Dokumen ini diterbitkan secara otomatis oleh sistem TEVARA. Harap simpan bukti transfer dan unggah melalui halaman Tagihan &amp; Pembayaran untuk proses verifikasi.
    </p>
</body>
</html>
