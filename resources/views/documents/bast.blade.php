<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>BAST {{ $proposal->bast_number }}</title>
    <style>
        body { color: #1e293b; font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h1 { color: #0b3b82; font-size: 22px; margin-bottom: 4px; text-align: center; }
        h2 { color: #0b3b82; font-size: 14px; margin-bottom: 8px; }
        .muted { color: #64748b; }
        .header { border-bottom: 2px solid #0b3b82; margin-bottom: 24px; padding-bottom: 14px; }
        .section { margin-bottom: 20px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #cbd5e1; padding: 8px; text-align: left; }
        th { background: #eff6ff; }
        .signatures { margin-top: 56px; width: 100%; }
        .signatures td { border: 0; text-align: center; vertical-align: top; width: 50%; }
    </style>
</head>
<body>
    <div class="header">
        <h1>BERITA ACARA SERAH TERIMA</h1>
        <div class="muted" style="text-align: center;">Nomor: {{ $proposal->bast_number }}</div>
    </div>

    <p>
        Pada tanggal {{ $proposal->handover_date?->translatedFormat('d F Y') ?? '-' }},
        telah dilakukan serah terima hasil pekerjaan berikut:
    </p>

    <div class="section">
        <h2>Informasi Proyek</h2>
        <table>
            <tbody>
                <tr><th>Nama proyek</th><td>{{ $proposal->title }}</td></tr>
                <tr><th>Kode proyek</th><td>{{ $proposal->project_code ?? $proposal->proposal_code }}</td></tr>
                <tr><th>Klien</th><td>{{ $proposal->client_company_name }}</td></tr>
                <tr><th>Perwakilan klien</th><td>{{ $proposal->client_signoff_name }}</td></tr>
                <tr><th>Repository</th><td>{{ $proposal->repository_url }}</td></tr>
                <tr><th>Panduan pengguna</th><td>{{ $proposal->user_manual_url ?? '-' }}</td></tr>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Hasil/Barang yang Diserahkan</h2>
        <table>
            <thead>
                <tr><th>Nama</th><th>Jenis</th><th>Jumlah</th><th>Kondisi</th><th>Keterangan</th></tr>
            </thead>
            <tbody>
                @foreach ($proposal->handover_deliverables ?? [] as $deliverable)
                    <tr>
                        <td>{{ $deliverable['name'] ?? '-' }}</td>
                        <td>{{ $deliverable['type'] ?? '-' }}</td>
                        <td>{{ $deliverable['quantity'] ?? '-' }} {{ $deliverable['unit'] ?? '' }}</td>
                        <td>{{ $deliverable['condition'] ?? '-' }}</td>
                        <td>{{ $deliverable['notes'] ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Garansi</h2>
        @if ($proposal->has_warranty)
            <p>
                Masa garansi {{ $proposal->warranty_months }} bulan,
                berlaku {{ $proposal->warranty_start_date?->translatedFormat('d F Y') ?? '-' }}
                sampai {{ $proposal->warranty_end_date?->translatedFormat('d F Y') ?? '-' }}.
            </p>
        @else
            <p>Proyek ini tidak memiliki masa garansi.</p>
        @endif
    </div>

    @if ($proposal->handover_notes)
        <div class="section">
            <h2>Catatan</h2>
            <p>{{ $proposal->handover_notes }}</p>
        </div>
    @endif

    <table class="signatures">
        <tr>
            <td>TEVARA<br><br><br><br>Pengurus TeFa</td>
            <td>Klien<br><br><br><br>{{ $proposal->client_signoff_name }}</td>
        </tr>
    </table>
</body>
</html>
