<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Proposal;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

final class PaymentController extends Controller
{
    use ApiResponse;

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
                        new OA\Property(property: 'proof', type: 'string', format: 'binary', description: 'File bukti pembayaran (JPG, PNG, PDF, max 5MB)'),
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
            'proof'              => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'], // max 5MB
        ]);

        // Hapus file lama jika ada
        if ($payment->proof_url) {
            Storage::disk('public')->delete($payment->proof_url);
        }

        $path = $request->file('proof')->store('payments/proofs', 'public');

        $payment->update([
            'payment_date'       => $request->input('payment_date'),
            'payment_method'     => $request->input('payment_method'),
            'transfer_reference' => $request->input('transfer_reference'),
            'proof_url'          => $path,
            'status'             => 'PENDING', // kembali ke PENDING setelah re-upload
        ]);

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
                                    new OA\Property(property: 'proof_url', type: 'string', example: 'http://localhost:8000/storage/payments/proofs/abc.jpg'),
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
    private function formatPayment(Payment $payment): array
    {
        return [
            'id'                 => $payment->id,
            'proposal_id'        => $payment->proposal_id,
            'term_sequence'      => $payment->term_sequence,
            'amount'             => (float) $payment->amount,
            'due_date'           => $payment->due_date?->toDateString(),
            'payment_date'       => $payment->payment_date?->toDateString(),
            'payment_method'     => $payment->payment_method,
            'transfer_reference' => $payment->transfer_reference,
            'proof_url'          => $payment->proof_url
                ? asset('storage/' . $payment->proof_url)
                : null,
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
                'id'    => $payment->proposal->client->id,
                'name'  => $payment->proposal->client->name,
                'email' => $payment->proposal->client->email,
            ] : null,
        ] : null;

        return $result;
    }
}
