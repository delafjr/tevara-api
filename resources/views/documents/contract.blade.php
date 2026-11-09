<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 36px 42px; }
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 11px; line-height: 1.5; }
        h1 { font-size: 17px; text-align: center; margin: 0 0 4px; }
        h2 { font-size: 12px; margin: 20px 0 8px; }
        p { margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #eff6ff; }
        .muted { color: #64748b; }
        .signatures { margin-top: 42px; }
        .signatures td { height: 75px; width: 50%; text-align: center; vertical-align: bottom; }
        .no-border td { border: 0; }
    </style>
</head>
<body>
    <h1>SURAT PERJANJIAN KERJA SAMA</h1>
    <p style="text-align: center"><strong>Nomor: {{ $proposal->spk_number }}</strong></p>
    <p style="text-align: center" class="muted">Kode Proyek: {{ $proposal->project_code }}</p>

    <p>Perjanjian ini mencatat kesepakatan pelaksanaan proyek antara TEFA Politeknik Negeri Semarang dan pihak klien berdasarkan data yang disetujui di sistem TEVARA.</p>

    <h2>1. Para Pihak dan Proyek</h2>
    <table>
        <tr><th style="width: 30%">Klien / Instansi</th><td>{{ $proposal->client_company_name }}</td></tr>
        <tr><th>Kontak PIC</th><td>{{ $proposal->client?->name ?? '—' }} ({{ $proposal->client?->email ?? '—' }})</td></tr>
        <tr><th>Nama Proyek</th><td>{{ $proposal->title }}</td></tr>
        <tr><th>Project Manager</th><td>{{ $proposal->pm?->name ?? '—' }}</td></tr>
        <tr><th>Nilai Kesepakatan</th><td>Rp {{ number_format((float) $proposal->total_amount, 0, ',', '.') }}</td></tr>
        <tr><th>Periode Pengerjaan</th><td>{{ $proposal->start_date?->format('d/m/Y') ?? '—' }} sampai {{ $proposal->target_completion_date?->format('d/m/Y') ?? '—' }}</td></tr>
        <tr><th>Masa Garansi</th><td>{{ $proposal->maintenance?->maintenance_warranty_months ?? 0 }} bulan setelah serah terima resmi</td></tr>
    </table>

    <h2>2. Ruang Lingkup Pekerjaan</h2>
    <p>{!! nl2br(e($proposal->scope_of_work)) !!}</p>

    <h2>3. Termin Pembayaran</h2>
    <table>
        <thead>
            <tr><th style="width: 10%">No.</th><th>Uraian</th><th style="width: 25%">Jumlah</th><th style="width: 20%">Tanggal</th></tr>
        </thead>
        <tbody>
            @forelse ($terms as $index => $term)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $term['name'] ?? $term['notes'] ?? 'Termin ' . ($index + 1) }}</td>
                    <td>Rp {{ number_format((float) ($term['amount'] ?? 0), 0, ',', '.') }}</td>
                    <td>{{ !empty($term['payment_date']) ? \Carbon\Carbon::parse($term['payment_date'])->format('d/m/Y') : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="4">Termin pembayaran mengikuti kesepakatan para pihak.</td></tr>
            @endforelse
        </tbody>
    </table>
    <p>{{ $proposal->payment_terms_description }}</p>

    <h2>4. Tim Pengembang</h2>
    <table>
        <thead><tr><th style="width: 10%">No.</th><th>Nama</th><th>Peran</th></tr></thead>
        <tbody>
            @forelse ($teamMembers as $index => $member)
                <tr><td>{{ $index + 1 }}</td><td>{{ $member->user?->name ?? '—' }}</td><td>{{ $member->role?->name ?? '—' }}</td></tr>
            @empty
                <tr><td colspan="3">Tim pengembang belum dicantumkan.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="signatures no-border">
        <tr>
            <td>TEFA Politeknik Negeri Semarang<br><br><br><strong>(________________________)</strong></td>
            <td>{{ $proposal->client_company_name }}<br><br><br><strong>(________________________)</strong></td>
        </tr>
    </table>
    <p class="muted" style="text-align: center; margin-top: 18px">Dokumen dibuat otomatis oleh TEVARA pada {{ now()->format('d/m/Y H:i') }}. Dokumen menunggu pengesahan dan tanda tangan para pihak.</p>
</body>
</html>
