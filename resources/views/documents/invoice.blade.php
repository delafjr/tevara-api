<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoiceNumber }}</title>
    <style>
        body { color: #1e293b; font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h1 { color: #0b3b82; font-size: 24px; margin-bottom: 4px; }
        .muted { color: #64748b; }
        .header { border-bottom: 2px solid #0b3b82; margin-bottom: 28px; padding-bottom: 18px; }
        .section { margin-bottom: 22px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #cbd5e1; padding: 10px; text-align: left; }
        th { background: #eff6ff; }
        .amount { font-size: 18px; font-weight: bold; text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <h1>INVOICE</h1>
        <div class="muted">Nomor: {{ $invoiceNumber }}</div>
        <div class="muted">Tanggal: {{ now()->translatedFormat('d F Y') }}</div>
    </div>

    <div class="section">
        <strong>Ditagihkan kepada</strong><br>
        {{ $proposal->client_company_name }}
    </div>

    <div class="section">
        <strong>Proyek</strong><br>
        {{ $proposal->title }}<br>
        <span class="muted">
            {{ $proposal->project_code ?? $proposal->proposal_code }}
            @if($proposal->spk_number) · {{ $proposal->spk_number }} @endif
        </span>
    </div>

    <table>
        <thead>
            <tr>
                <th>Uraian</th>
                <th>Jatuh Tempo</th>
                <th>Nominal</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Termin {{ $payment->term_sequence }} – {{ $proposal->title }}</td>
                <td>{{ $payment->due_date?->translatedFormat('d F Y') ?? 'Belum ditetapkan' }}</td>
                <td class="amount">Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <p class="muted">Dokumen ini merupakan ringkasan tagihan sistem TEVARA. Gunakan rekening resmi yang tertera pada dokumen kerja sama.</p>
</body>
</html>
