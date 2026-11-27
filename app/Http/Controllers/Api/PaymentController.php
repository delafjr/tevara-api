<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\ProjectMaintenance;
use App\Models\Proposal;
use App\Models\WarrantyExtensionRequest;
use App\Services\CloudinaryService;
use App\Services\NotificationService;
use App\Traits\ApiResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

final class PaymentController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly NotificationService $notifications,
        private readonly CloudinaryService $cloudinary
    ) {}

    // ──────────────────────────────────────────────────────
    //  1. POST /proposals/{id}/payments — Buat termin pembayaran
    // ──────────────────────────────────────────────────────
    #[OA\Post(
        path: '/proposals/{id}/payments',
        summary: 'Buat record termin pembayaran (Dosen PM / Pengurus TeFa)',
        description: 'Membuat jadwal termin pembayaran baru untuk proyek. Status awal selalu PENDING. Nomor urut termin (term_sequence) harus unik per proposal.',
        security: [['bearerAuth' => []]],
        tags: ['F-09: Pembayaran'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID Proposal', schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['term_sequence', 'amount'],
                properties: [
                    new OA\Property(property: 'term_sequence', type: 'integer', example: 1, description: 'Urutan termin (1, 2, dst)'),
                    new OA\Property(property: 'amount', type: 'number', format: 'double', example: 7500000, description: 'Nominal pembayaran'),
                    new OA\Property(property: 'due_date', type: 'string', format: 'date', nullable: true, example: '2026-12-01', description: 'Batas akhir pembayaran'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Termin pembayaran berhasil dibuat',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Termin pembayaran berhasil dibuat.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'proposal_id', type: 'integer', example: 1),
                                new OA\Property(property: 'invoice_number', type: 'string', example: 'INV/2026/000001'),
                                new OA\Property(property: 'term_sequence', type: 'integer', example: 1),
                                new OA\Property(property: 'amount', type: 'number', example: 7500000),
                                new OA\Property(property: 'due_date', type: 'string', format: 'date', example: '2026-12-01'),
                                new OA\Property(property: 'payment_date', type: 'string', nullable: true),
                                new OA\Property(property: 'payment_method', type: 'string', nullable: true),
                                new OA\Property(property: 'transfer_reference', type: 'string', nullable: true),
                                new OA\Property(property: 'proof_url', type: 'string', nullable: true),
                                new OA\Property(property: 'status', type: 'string', example: 'PENDING'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.'),
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Akses ditolak (bukan dosen/PM/pengurus)',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Hanya Dosen/PM yang dapat membuat termin pembayaran.'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Proposal tidak ditemukan',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'No query results for model [App\\Models\\Proposal] 999'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validasi gagal (termin sudah ada / field salah)',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'The term sequence has already been taken.'),
                        new OA\Property(
                            property: 'errors',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'term_sequence',
                                    type: 'array',
                                    items: new OA\Items(type: 'string', example: 'The term sequence has already been taken.')
                                ),
                            ]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function store(Request $request, int|string $id): JsonResponse
    {
        $proposal = Proposal::findOrFail($id);
        $user     = $request->user();

        // Hanya dosen atau pengurus_tefa yang bisa membuat termin pembayaran
        if (!$user->hasAnyRole(['dosen', 'pengurus_tefa'])) {
            return $this->error('Hanya Dosen/PM yang dapat membuat termin pembayaran.', 403);
        }

        $data = $request->validate([
            'term_sequence' => [
                'required', 'integer', 'min:1',
                Rule::unique('payments')->where(fn ($q) => $q->where('proposal_id', $proposal->id)),
            ],
            'amount'   => ['required', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
        ]);

        $payment = Payment::create([
            'proposal_id'   => $proposal->id,
            'term_sequence' => $data['term_sequence'],
            'amount'        => $data['amount'],
            'due_date'      => $data['due_date'] ?? null,
            'status'        => 'PENDING',
        ]);
        $payment->invoice_number = sprintf('INV/%s/%06d', now()->format('Y'), $payment->id);
        $payment->save();

        $this->notifications->notifyClient($proposal, 'payment.created', [
            'term'   => $payment->term_sequence,
            'amount' => $this->rupiah($payment->amount),
            'code'   => $proposal->proposal_code,
        ]);

        return $this->created('Termin pembayaran berhasil dibuat.', $this->formatPayment($payment));
    }

    // ──────────────────────────────────────────────────────
    //  2. PATCH /proposals/{id}/payments/{paymentId}/proof
    //     Upload bukti transfer (klien)
    // ──────────────────────────────────────────────────────
    #[OA\Patch(
        path: '/proposals/{id}/payments/{paymentId}/proof',
        summary: 'Upload bukti transfer (Klien)',
        description: 'Mengunggah file bukti transfer untuk termin pembayaran tertentu. Hanya klien pemilik proposal yang dapat mengunggah dan hanya dapat dilakukan saat payment berstatus PENDING atau REJECTED.',
        security: [['bearerAuth' => []]],
        tags: ['F-09: Pembayaran'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID Proposal', schema: new OA\Schema(type: 'integer', example: 1)),
            new OA\Parameter(name: 'paymentId', in: 'path', required: true, description: 'ID Payment', schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['payment_date', 'payment_method', 'proof'],
                    properties: [
                        new OA\Property(property: 'payment_date', type: 'string', format: 'date', example: '2026-11-04'),
                        new OA\Property(property: 'payment_method', type: 'string', enum: ['BANK_TRANSFER', 'VIRTUAL_ACCOUNT', 'CASH', 'OTHER'], example: 'BANK_TRANSFER'),
                        new OA\Property(property: 'transfer_reference', type: 'string', nullable: true, example: 'TRX-123456789'),
                        new OA\Property(property: 'proof', type: 'string', format: 'binary', description: 'File bukti pembayaran (gambar JPG, PNG, WEBP, max 10MB)'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Bukti transfer berhasil diupload',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Bukti pembayaran berhasil diupload.'),
                        new OA\Property(property: 'data', type: 'object'),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.'),
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Akses ditolak (bukan klien pemilik)',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Anda bukan klien dari proyek ini.'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Proposal atau Payment tidak ditemukan',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Data termin pembayaran tidak ditemukan.'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Status pembayaran tidak valid atau validasi form gagal',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Bukti hanya dapat diupload untuk pembayaran berstatus PENDING atau REJECTED.'),
                    ]
                )
            ),
        ]
    )]
    public function uploadProof(Request $request, int|string $id, int|string $paymentId): JsonResponse
    {
        $proposal = Proposal::findOrFail($id);
        $user     = $request->user();

        // Hanya klien
        if (!$user->hasRole('klien')) {
            return $this->error('Hanya klien yang dapat mengupload bukti pembayaran.', 403);
        }

        // Klien hanya bisa upload ke proposal miliknya sendiri
        if ((int) $proposal->client_id !== $user->id) {
            return $this->error('Anda bukan klien dari proyek ini.', 403);
        }

        $payment = Payment::where('proposal_id', $proposal->id)
            ->where('id', $paymentId)
            ->first();

        if (!$payment) {
            return $this->error('Data termin pembayaran tidak ditemukan.', 404);
        }

        // Hanya bisa upload jika status PENDING atau REJECTED
        if (!in_array($payment->status, ['PENDING', 'REJECTED'])) {
            return $this->error(
                'Bukti hanya dapat diupload untuk pembayaran berstatus PENDING atau REJECTED. Status saat ini: ' . $payment->status,
                422
            );
        }

        $request->validate([
            'payment_date'       => ['required', 'date'],
            'payment_method'     => ['required', Rule::in(['BANK_TRANSFER', 'VIRTUAL_ACCOUNT', 'CASH', 'OTHER'])],
            'transfer_reference' => ['nullable', 'string', 'max:255'],
            'proof'              => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'], // max 5MB
        ], [
            'proof.required' => 'Berkas bukti pembayaran wajib diunggah.',
            'proof.mimes'    => 'Format berkas harus JPG, PNG, atau PDF.',
            'proof.max'      => 'Ukuran berkas maksimal 5 MB.',
        ]);

        // Hapus file lama jika ada
        if ($payment->proof_url) {
            $this->cloudinary->delete($payment->proof_url);
        }

        $path = $this->cloudinary->uploadProofFile($request->file('proof'), CloudinaryService::FOLDER_PEMBAYARAN, 'proof');

        $payment->update([
            'payment_date'       => $request->input('payment_date'),
            'payment_method'     => $request->input('payment_method'),
            'transfer_reference' => $request->input('transfer_reference'),
            'proof_url'          => $path,
            'status'             => 'PENDING', // kembali ke PENDING setelah re-upload
        ]);

        $notificationPayload = [
            'client' => $proposal->client_company_name,
            'term'   => $payment->term_sequence,
            'amount' => $this->rupiah($payment->amount),
            'code'   => $proposal->proposal_code,
        ];

        // Notifikasi ke dosen PM jika ada
        $this->notifications->notifyPm($proposal, 'payment.proof_uploaded', $notificationPayload);

        // Notifikasi ke seluruh Pengurus TeFa
        $this->notifications->notifyRole('pengurus_tefa', 'payment.proof_uploaded', $notificationPayload, (int) $proposal->id);

        return $this->ok('Bukti pembayaran berhasil diupload.', $this->formatPayment($payment));
    }

    // ──────────────────────────────────────────────────────
    //  3. PATCH /proposals/{id}/payments/{paymentId}/verify
    //     Verifikasi bukti bayar (dosen/pm)
    // ──────────────────────────────────────────────────────
    #[OA\Patch(
        path: '/proposals/{id}/payments/{paymentId}/verify',
        summary: 'Verifikasi bukti bayar (Dosen PM / Pengurus TeFa)',
        description: 'Memverifikasi atau menolak bukti pembayaran termin. Otomatis merekam verified_by dan verified_at=now().',
        security: [['bearerAuth' => []]],
        tags: ['F-09: Pembayaran'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID Proposal', schema: new OA\Schema(type: 'integer', example: 1)),
            new OA\Parameter(name: 'paymentId', in: 'path', required: true, description: 'ID Payment', schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['status'],
                properties: [
                    new OA\Property(property: 'status', type: 'string', enum: ['VERIFIED', 'REJECTED'], example: 'VERIFIED'),
                    new OA\Property(property: 'verification_notes', type: 'string', nullable: true, example: 'Pembayaran telah dicek di mutasi rekening.'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Verifikasi berhasil',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Verifikasi pembayaran berhasil.'),
                        new OA\Property(property: 'data', type: 'object'),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.'),
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Akses ditolak (bukan dosen/PM/pengurus)',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Hanya Dosen/PM yang dapat memverifikasi pembayaran.'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Payment tidak ditemukan',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Data termin pembayaran tidak ditemukan.'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Tidak ada bukti atau validasi gagal',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Bukti pembayaran belum diupload oleh klien.'),
                    ]
                )
            ),
        ]
    )]
    public function verify(Request $request, int|string $id, int|string $paymentId): JsonResponse
    {
        $proposal = Proposal::findOrFail($id);
        $user     = $request->user();

        // Hanya dosen atau pengurus_tefa yang dapat memverifikasi
        if (!$user->hasAnyRole(['dosen', 'pengurus_tefa'])) {
            return $this->error('Hanya Dosen/PM yang dapat memverifikasi pembayaran.', 403);
        }

        $payment = Payment::where('proposal_id', $proposal->id)
            ->where('id', $paymentId)
            ->first();

        if (!$payment) {
            return $this->error('Data termin pembayaran tidak ditemukan.', 404);
        }

        // Harus ada bukti upload terlebih dahulu
        if (!$payment->proof_url) {
            return $this->error('Bukti pembayaran belum diupload oleh klien.', 422);
        }

        $data = $request->validate([
            'status'             => ['required', Rule::in(['VERIFIED', 'REJECTED'])],
            'verification_notes' => ['nullable', 'string'],
        ]);

        $payment->update([
            'status'             => $data['status'],
            'verification_notes' => $data['verification_notes'] ?? null,
            'verified_by'        => $user->id,
            'verified_at'        => now(),
        ]);

        // Jika pembayaran maintenance diverifikasi, aktifkan masa pemeliharaan
        if ($data['status'] === 'VERIFIED' && $payment->payment_type === 'maintenance') {
            $extReq = WarrantyExtensionRequest::where('payment_id', $payment->id)->first();
            if ($extReq) {
                $extReq->update(['status' => 'approved']);

                $latestMaintenance = ProjectMaintenance::where('proposal_id', $proposal->id)
                    ->where('type', 'maintenance')
                    ->where('status', 'active')
                    ->whereNotNull('end_date')
                    ->orderByDesc('end_date')
                    ->first();

                $baseDate = $latestMaintenance && $latestMaintenance->end_date && $latestMaintenance->end_date->isFuture()
                    ? $latestMaintenance->end_date->copy()
                    : Carbon::today();

                $newEnd = $baseDate->copy()->addMonthsNoOverflow($extReq->months);

                ProjectMaintenance::create([
                    'proposal_id'     => $proposal->id,
                    'type'            => 'maintenance',
                    'duration_months' => $extReq->months,
                    'start_date'      => $baseDate->toDateString(),
                    'end_date'        => $newEnd->toDateString(),
                    'status'          => 'active',
                    'notes'           => "Perpanjangan maintenance {$extReq->months} bulan lunas (Invoice: {$payment->invoice_number})",
                ]);

                $proposal->maintenance_months = (int) ($proposal->maintenance_months ?? 0) + $extReq->months;
                $proposal->save();
            }
        }

        $this->notifications->notifyClient(
            $proposal,
            $data['status'] === 'VERIFIED' ? 'payment.verified' : 'payment.rejected',
            [
                'term'   => $payment->term_sequence,
                'amount' => $this->rupiah($payment->amount),
                'code'   => $proposal->proposal_code,
            ]
        );

        return $this->ok('Verifikasi pembayaran berhasil.', $this->formatPayment($payment->load('verifier')));
    }

    // ──────────────────────────────────────────────────────
    //  4. GET /payments/pending — List semua pembayaran pending
    // ──────────────────────────────────────────────────────
    #[OA\Get(
        path: '/payments/pending',
        summary: 'List semua pembayaran yang butuh verifikasi (Dosen PM / Pengurus TeFa)',
        description: 'Mengambil daftar pembayaran dengan status PENDING yang telah diunggah bukti pembayarannya oleh klien, dilengkapi data relasi proposal dan klien.',
        security: [['bearerAuth' => []]],
        tags: ['F-09: Pembayaran'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Daftar pembayaran pending',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Daftar pembayaran yang menunggu verifikasi'),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'proposal_id', type: 'integer', example: 1),
                                    new OA\Property(property: 'term_sequence', type: 'integer', example: 1),
                                    new OA\Property(property: 'amount', type: 'number', example: 7500000),
                                    new OA\Property(property: 'due_date', type: 'string', format: 'date', example: '2026-12-01'),
                                    new OA\Property(property: 'payment_date', type: 'string', format: 'date', example: '2026-11-04'),
                                    new OA\Property(property: 'payment_method', type: 'string', example: 'BANK_TRANSFER'),
                                    new OA\Property(property: 'transfer_reference', type: 'string', example: 'TRX-123456'),
                                    new OA\Property(property: 'proof_url', type: 'string', example: 'https://res.cloudinary.com/demo/image/upload/v1/tevara/pembayaran/proof.jpg'),
                                    new OA\Property(property: 'status', type: 'string', example: 'PENDING'),
                                    new OA\Property(
                                        property: 'proposal',
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 1),
                                            new OA\Property(property: 'title', type: 'string', example: 'Sistem Informasi Manajemen Teaching Factory'),
                                            new OA\Property(property: 'proposal_code', type: 'string', example: 'PRSL-2025-001'),
                                        ]
                                    ),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.'),
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Akses ditolak',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Akses ditolak.'),
                    ]
                )
            ),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Hanya dosen atau pengurus_tefa
        if (!$user->hasAnyRole(['dosen', 'pengurus_tefa'])) {
            return $this->error('Akses ditolak.', 403);
        }

        $payments = Payment::with(['proposal.client', 'proposal.status'])
            ->latest('payment_date')
            ->latest('id')
            ->get();

        return $this->ok(
            'Daftar seluruh transaksi pembayaran',
            $payments->map(fn (Payment $p) => $this->formatPaymentWithRelation($p))
        );
    }

    public function pending(Request $request): JsonResponse
    {
        $user = $request->user();

        // Hanya dosen atau pengurus_tefa
        if (!$user->hasAnyRole(['dosen', 'pengurus_tefa'])) {
            return $this->error('Akses ditolak.', 403);
        }

        $payments = Payment::with(['proposal.client', 'proposal.status'])
            ->where('status', 'PENDING')
            ->whereNotNull('proof_url')
            ->orderBy('created_at')
            ->get();

        return $this->ok(
            'Daftar pembayaran yang menunggu verifikasi',
            $payments->map(fn (Payment $p) => $this->formatPaymentWithRelation($p))
        );
    }

    // ──────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────
    private function rupiah(float|string $amount): string
    {
        return 'Rp ' . number_format((float) $amount, 0, ',', '.');
    }

    #[OA\Get(
        path: '/payments/{paymentId}/invoice',
        summary: 'Unduh invoice untuk termin pembayaran',
        description: 'Mengunduh invoice PDF untuk termin pembayaran milik klien yang sedang login.',
        security: [['bearerAuth' => []]],
        tags: ['F-08: Invoice'],
        parameters: [
            new OA\Parameter(name: 'paymentId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Invoice PDF',
                content: new OA\MediaType(
                    mediaType: 'application/pdf',
                    schema: new OA\Schema(type: 'string', format: 'binary')
                )
            ),
            new OA\Response(response: 403, description: 'Invoice is not owned by the authenticated client'),
            new OA\Response(response: 404, description: 'Payment not found'),
        ]
    )]
    public function downloadInvoice(Request $request, int|string $paymentId)
    {
        $payment = Payment::with([
            'proposal.status',
            'proposal.client.clientProfile.pics',
            'proposal.client.clientPic.clientProfile.pics',
        ])->findOrFail($paymentId);
        $user = $request->user();
        $clientProfile = $user->clientProfile ?? $user->clientPic?->clientProfile;
        $clientIds = $clientProfile
            ? $clientProfile->pics()->pluck('user_id')->push($clientProfile->user_id)
            : collect([$user->id]);

        abort_unless($clientIds->contains($payment->proposal->client_id), 403, 'Akses ditolak untuk invoice ini.');

        $channels = [
            'bca'       => ['group' => 'Transfer Bank', 'label' => 'Bank BCA', 'account' => '1234 5678 9012', 'holder' => 'TEFA Polines / TEVARA'],
            'mandiri'   => ['group' => 'Transfer Bank', 'label' => 'Bank Mandiri', 'account' => '1350 0987 6543 2', 'holder' => 'TEFA Polines / TEVARA'],
            'bni'       => ['group' => 'Transfer Bank', 'label' => 'Bank BNI', 'account' => '0889 1122 3344', 'holder' => 'TEFA Polines / TEVARA'],
            'bri'       => ['group' => 'Transfer Bank', 'label' => 'Bank BRI', 'account' => '0022 0100 4455 501', 'holder' => 'TEFA Polines / TEVARA'],
            'gopay'     => ['group' => 'E-Wallet', 'label' => 'GoPay', 'account' => '0811 2345 6789', 'holder' => 'TEVARA Polines'],
            'ovo'       => ['group' => 'E-Wallet', 'label' => 'OVO', 'account' => '0811 2345 6789', 'holder' => 'TEVARA Polines'],
            'dana'      => ['group' => 'E-Wallet', 'label' => 'DANA', 'account' => '0811 2345 6789', 'holder' => 'TEVARA Polines'],
            'shopeepay' => ['group' => 'E-Wallet', 'label' => 'ShopeePay', 'account' => '0811 2345 6789', 'holder' => 'TEVARA Polines'],
        ];
        $channelKey = (string) $request->query('channel', '');
        $selectedChannel = $channels[$channelKey] ?? null;

        $invoiceNumber = $payment->invoice_number
            ?? sprintf('INV/%s/%06d', $payment->created_at?->format('Y') ?? now()->format('Y'), $payment->id);
        $filename = str_replace('/', '-', $invoiceNumber) . '.pdf';
        $pdf = Pdf::loadView('documents.invoice', [
            'payment'         => $payment,
            'proposal'        => $payment->proposal,
            'invoiceNumber'   => $invoiceNumber,
            'selectedChannel' => $selectedChannel,
            'channels'        => $channels,
        ])->setPaper('a4');

        return $pdf->download($filename);
    }

    private function formatPayment(Payment $payment): array
    {
        return [
            'id'                 => $payment->id,
            'proposal_id'        => $payment->proposal_id,
            'invoice_number'     => $payment->invoice_number,
            'term_sequence'      => $payment->term_sequence,
            'payment_type'       => $payment->payment_type ?? 'project',
            'description'        => $payment->description,
            'amount'             => (float) $payment->amount,
            'due_date'           => $payment->due_date?->toDateString(),
            'payment_date'       => $payment->payment_date?->toDateString(),
            'payment_method'     => $payment->payment_method,
            'transfer_reference' => $payment->transfer_reference,
            'proof_url'          => $this->cloudinary->getUrl($payment->proof_url),
            'status'             => $payment->status,
            'verification_notes' => $payment->verification_notes,
            'verified_by'        => $payment->verifier ? [
                'id'   => $payment->verifier->id,
                'name' => $payment->verifier->name,
            ] : null,
            'verified_at'        => $payment->verified_at?->toIso8601String(),
            'created_at'         => $payment->created_at?->toIso8601String(),
            'updated_at'         => $payment->updated_at?->toIso8601String(),
        ];
    }

    private function formatPaymentWithRelation(Payment $payment): array
    {
        $result = $this->formatPayment($payment);

        $result['proposal'] = $payment->proposal ? [
            'id'           => $payment->proposal->id,
            'project_code' => $payment->proposal->project_code,
            'title'        => $payment->proposal->title,
            'status'       => $payment->proposal->status?->name ?? null,
            'client'       => $payment->proposal->client ? [
                'id'           => $payment->proposal->client->id,
                'name'         => $payment->proposal->client->name,
                'email'        => $payment->proposal->client->email,
                'company_name' => $payment->proposal->client_company_name,
            ] : null,
        ] : null;

        return $result;
    }
}
