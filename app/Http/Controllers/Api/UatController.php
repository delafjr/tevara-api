<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProjectTeam;
use App\Models\Proposal;
use App\Models\ProposalStatus;
use App\Models\UatResult;
use App\Models\UatItem;
use App\Services\NotificationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

final class UatController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly NotificationService $notifications) {}

    // ──────────────────────────────────────────────────────
    //  1. GET /proposals/{id}/uat — Detail UAT + semua item
    // ──────────────────────────────────────────────────────
    #[OA\Get(
        path: '/proposals/{id}/uat',
        summary: 'Detail UAT proyek + semua item',
        description: 'Mengambil sesi UAT terbaru beserta riwayat sesi, hasil per fitur, catatan Pengurus, dan task tindak lanjut. Dapat diakses oleh Klien pemilik, Dosen PM, dan Pengurus TeFa.',
        security: [['bearerAuth' => []]],
        tags: ['F-06: UAT'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID Proposal', schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Data UAT proyek',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Data UAT proyek'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'proposal_id', type: 'integer', example: 1),
                                new OA\Property(property: 'session_date', type: 'string', format: 'date', example: '2026-11-04'),
                                new OA\Property(property: 'overall_result', type: 'string', enum: ['PASSED', 'FAILED', 'PARTIAL'], example: 'PASSED'),
                                new OA\Property(property: 'review_status', type: 'string', enum: ['PENDING_REVIEW', 'NEEDS_IMPROVEMENT', 'READY_FOR_RETEST', 'APPROVED'], example: 'PENDING_REVIEW'),
                                new OA\Property(property: 'review_notes', type: 'string', nullable: true),
                                new OA\Property(property: 'reviewed_at', type: 'string', format: 'date-time', nullable: true),
                                new OA\Property(property: 'client_notes', type: 'string', nullable: true, example: 'Semua fungsi utama berjalan dengan baik.'),
                                new OA\Property(property: 'client_signoff_name', type: 'string', nullable: true, example: 'Budi Santoso'),
                                new OA\Property(property: 'signed_off_at', type: 'string', format: 'date-time', nullable: true, example: '2026-11-04T10:00:00Z'),
                                new OA\Property(
                                    property: 'uat_items',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 1),
                                            new OA\Property(property: 'uat_result_id', type: 'integer', example: 1),
                                            new OA\Property(property: 'feature_name', type: 'string', example: 'Login & Autentikasi'),
                                            new OA\Property(property: 'test_scenario', type: 'string', nullable: true, example: 'Login dengan kredensial valid dan invalid'),
                                            new OA\Property(property: 'result', type: 'string', enum: ['PASSED', 'FAILED', 'BUG_FOUND'], example: 'PASSED'),
                                            new OA\Property(property: 'bug_severity', type: 'string', nullable: true, enum: ['MINOR', 'MAJOR', 'CRITICAL']),
                                            new OA\Property(property: 'bug_description', type: 'string', nullable: true),
                                            new OA\Property(property: 'client_feedback', type: 'string', nullable: true, example: 'UI login responsif dan jelas'),
                                        ]
                                    )
                                ),
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
                description: 'Akses ditolak',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Akses ditolak.'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Proposal atau UAT tidak ditemukan',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'UAT belum tersedia untuk proyek ini.'),
                    ]
                )
            ),
        ]
    )]
    public function show(Request $request, int|string $id): JsonResponse
    {
        $proposal = Proposal::findOrFail($id);
        $user     = $request->user();

        $this->authorizeView($proposal, $user);

        $uat = UatResult::with(['items.tasks.projectTeam.user', 'reviewer'])
            ->where('proposal_id', $proposal->id)
            ->orderByDesc('id')
            ->first();

        if (!$uat) {
            return $this->error('UAT belum tersedia untuk proyek ini.', 404);
        }

        $history = UatResult::with(['items.tasks.projectTeam.user', 'reviewer'])
            ->where('proposal_id', $proposal->id)
            ->orderByDesc('id')
            ->get();

        return $this->ok('Data UAT proyek', [
            ...$this->formatUat($uat),
            'uat_history' => $history->map(fn (UatResult $session) => $this->formatUat($session))->all(),
        ]);
    }

    // ──────────────────────────────────────────────────────
    //  2. POST /proposals/{id}/uat — Buat sesi UAT + item
    // ──────────────────────────────────────────────────────
    #[OA\Post(
        path: '/proposals/{id}/uat',
        summary: 'Klien mengirim hasil satu sesi UAT',
        description: 'Klien pemilik mengirim hasil uji setiap fitur pada proyek berstatus Testing. Sistem menghitung hasil keseluruhan dan menyimpan tiap pengiriman sebagai sesi baru. Sesi ulang hanya dapat dibuat setelah seluruh tindak lanjut sesi sebelumnya diverifikasi Pengurus.',
        security: [['bearerAuth' => []]],
        tags: ['F-06: UAT'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID Proposal', schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['items'],
                properties: [
                    new OA\Property(property: 'client_notes', type: 'string', nullable: true, example: 'Pengujian modul utama telah selesai.'),
                    new OA\Property(
                        property: 'items',
                        type: 'array',
                        items: new OA\Items(
                            required: ['feature_name', 'result'],
                            properties: [
                                new OA\Property(property: 'feature_name', type: 'string', example: 'Manajemen Proposal'),
                                new OA\Property(property: 'test_scenario', type: 'string', nullable: true, example: 'Pengajuan proposal dengan lampiran dokumen'),
                                new OA\Property(property: 'result', type: 'string', enum: ['PASSED', 'FAILED', 'BUG_FOUND'], example: 'PASSED', description: 'Hasil yang dipilih Klien untuk fitur ini.'),
                                new OA\Property(property: 'bug_severity', type: 'string', nullable: true, enum: ['MINOR', 'MAJOR', 'CRITICAL']),
                                new OA\Property(property: 'bug_description', type: 'string', nullable: true),
                                new OA\Property(property: 'client_feedback', type: 'string', nullable: true, example: 'Fitur sesuai kebutuhan'),
                            ]
                        )
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'UAT berhasil dibuat',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'UAT berhasil dibuat'),
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
                description: 'Akses ditolak (bukan Klien pemilik proyek)',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Hanya Klien pemilik proyek yang dapat mengirim hasil UAT.'),
                    ]
                )
            ),
            new OA\Response(
                response: 409,
                description: 'Sesi masih menunggu pemeriksaan atau perbaikan belum siap diuji ulang',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Selesaikan tindak lanjut dan tunggu verifikasi Pengurus sebelum mengirim sesi UAT berikutnya.'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validasi gagal atau status proposal bukan Testing',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Proposal harus berstatus Testing untuk membuat UAT.'),
                    ]
                )
            ),
        ]
    )]
    public function store(Request $request, int|string $id): JsonResponse
    {
        $proposal = Proposal::findOrFail($id);
        $user     = $request->user();

        $this->authorizeClient($proposal, $user);

        $data = $request->validate([
            'client_notes'              => ['nullable', 'string'],
            'items'                     => ['required', 'array', 'min:1'],
            'items.*.feature_name'      => ['required', 'string', 'max:255'],
            'items.*.test_scenario'     => ['nullable', 'string'],
            'items.*.result'            => ['required', Rule::in(['PASSED', 'FAILED', 'BUG_FOUND'])],
            'items.*.bug_severity'      => ['nullable', Rule::in(['MINOR', 'MAJOR', 'CRITICAL'])],
            'items.*.bug_description'   => ['nullable', 'string'],
            'items.*.client_feedback'   => ['nullable', 'string'],
        ]);

        foreach ($data['items'] as $index => $item) {
            if (in_array($item['result'], ['FAILED', 'BUG_FOUND'], true)
                && blank($item['bug_description'] ?? $item['client_feedback'] ?? null)) {
                return $this->error('Jelaskan temuan pada setiap fitur yang belum sesuai.', 422, [
                    "items.{$index}.bug_description" => ['Catatan temuan wajib diisi untuk hasil yang belum sesuai.'],
                ]);
            }
        }

        $overallResult = $this->calculateOverallResult($data['items']);

        $uat = DB::transaction(function () use ($data, $proposal, $user, $overallResult) {
            $lockedProposal = Proposal::with('status')->whereKey($proposal->id)->lockForUpdate()->firstOrFail();
            if (strtolower($lockedProposal->status?->name ?? '') !== 'testing') {
                return $this->error('Proposal harus berstatus Testing untuk membuat UAT.', 422);
            }

            $latest = UatResult::where('proposal_id', $lockedProposal->id)->latest('id')->lockForUpdate()->first();
            if ($latest && $latest->review_status !== 'READY_FOR_RETEST') {
                return $this->error('Sesi UAT sebelumnya masih diproses. Selesaikan tindak lanjut dan tunggu verifikasi Pengurus sebelum mengirim sesi berikutnya.', 409);
            }

            $uat = UatResult::create([
                'proposal_id'    => $lockedProposal->id,
                'submitted_by'   => $user->id,
                'session_date'   => now()->toDateString(),
                'overall_result' => $overallResult,
                'review_status'  => 'PENDING_REVIEW',
                'client_notes'   => $data['client_notes'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $uat->items()->create([
                    'feature_name'    => $item['feature_name'],
                    'test_scenario'   => $item['test_scenario'] ?? null,
                    'result'          => $item['result'],
                    'bug_severity'    => $item['bug_severity'] ?? null,
                    'bug_description' => $item['bug_description'] ?? null,
                    'client_feedback' => $item['client_feedback'] ?? null,
                ]);
            }

            return $uat->load('items');
        });

        if ($uat instanceof JsonResponse) {
            return $uat;
        }

        $this->notifications->notifyRole('pengurus_tefa', 'uat.submitted', [
            'code' => $proposal->proposal_code,
            'title' => $proposal->title,
        ], (int) $proposal->id);

        return $this->created('Hasil UAT berhasil dikirim untuk diperiksa.', $this->formatUat($uat));
    }

    // ──────────────────────────────────────────────────────
    //  3. PUT /proposals/{id}/uat — Update UAT (revisi item)
    // ──────────────────────────────────────────────────────
    #[OA\Put(
        path: '/proposals/{id}/uat',
        summary: 'Perbarui kiriman UAT yang belum diperiksa',
        description: 'Klien pemilik dapat memperbarui sesi terbaru hanya selama Pengurus belum memeriksanya. Setelah pemeriksaan dimulai, kiriman tidak dapat ditimpa.',
        security: [['bearerAuth' => []]],
        tags: ['F-06: UAT'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID Proposal', schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['items'],
                properties: [
                    new OA\Property(property: 'client_notes', type: 'string', nullable: true, example: 'Revisi minor bug sudah selesai diuji.'),
                    new OA\Property(
                        property: 'items',
                        type: 'array',
                        items: new OA\Items(
                            required: ['feature_name', 'result'],
                            properties: [
                                new OA\Property(property: 'feature_name', type: 'string', example: 'Manajemen Proposal'),
                                new OA\Property(property: 'test_scenario', type: 'string', nullable: true, example: 'Pengajuan proposal dengan lampiran dokumen'),
                                new OA\Property(property: 'result', type: 'string', enum: ['PASSED', 'FAILED', 'BUG_FOUND'], example: 'PASSED'),
                                new OA\Property(property: 'bug_severity', type: 'string', nullable: true, enum: ['MINOR', 'MAJOR', 'CRITICAL']),
                                new OA\Property(property: 'bug_description', type: 'string', nullable: true),
                                new OA\Property(property: 'client_feedback', type: 'string', nullable: true, example: 'Semua lulus uji coba'),
                            ]
                        )
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'UAT berhasil diperbarui',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'UAT berhasil diperbarui'),
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
                description: 'Akses ditolak',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Hanya Dosen/PM yang dapat mengelola UAT.'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'UAT tidak ditemukan',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'UAT tidak ditemukan untuk proyek ini.'),
                    ]
                )
            ),
            new OA\Response(
                response: 409,
                description: 'UAT sudah di-sign-off oleh klien',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'UAT sudah di-sign-off oleh klien dan tidak dapat diubah.'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validasi input gagal',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'The overall result field is required.'),
                        new OA\Property(property: 'errors', type: 'object'),
                    ]
                )
            ),
        ]
    )]
    public function update(Request $request, int|string $id): JsonResponse
    {
        $proposal = Proposal::findOrFail($id);
        $user     = $request->user();

        $this->authorizeClient($proposal, $user);

        $uat = UatResult::where('proposal_id', $proposal->id)->latest('id')->first();

        if (!$uat) {
            return $this->error('UAT tidak ditemukan untuk proyek ini.', 404);
        }

        if ($uat->review_status !== 'PENDING_REVIEW' || $uat->signed_off_at !== null) {
            return $this->error('Hasil UAT tidak dapat diubah setelah mulai diperiksa Pengurus.', 409);
        }

        $data = $request->validate([
            'client_notes'              => ['nullable', 'string'],
            'items'                     => ['required', 'array', 'min:1'],
            'items.*.feature_name'      => ['required', 'string', 'max:255'],
            'items.*.test_scenario'     => ['nullable', 'string'],
            'items.*.result'            => ['required', Rule::in(['PASSED', 'FAILED', 'BUG_FOUND'])],
            'items.*.bug_severity'      => ['nullable', Rule::in(['MINOR', 'MAJOR', 'CRITICAL'])],
            'items.*.bug_description'   => ['nullable', 'string'],
            'items.*.client_feedback'   => ['nullable', 'string'],
        ]);

        foreach ($data['items'] as $index => $item) {
            if (in_array($item['result'], ['FAILED', 'BUG_FOUND'], true)
                && blank($item['bug_description'] ?? $item['client_feedback'] ?? null)) {
                return $this->error('Jelaskan temuan pada setiap fitur yang belum sesuai.', 422, [
                    "items.{$index}.bug_description" => ['Catatan temuan wajib diisi untuk hasil yang belum sesuai.'],
                ]);
            }
        }

        $overallResult = $this->calculateOverallResult($data['items']);
        $uat = DB::transaction(function () use ($uat, $data, $overallResult) {
            $uat->update([
                'session_date'   => now()->toDateString(),
                'overall_result' => $overallResult,
                'client_notes'   => $data['client_notes'] ?? null,
            ]);

            // Hapus semua item lama, buat baru
            $uat->items()->delete();

            foreach ($data['items'] as $item) {
                $uat->items()->create([
                    'feature_name'    => $item['feature_name'],
                    'test_scenario'   => $item['test_scenario'] ?? null,
                    'result'          => $item['result'],
                    'bug_severity'    => $item['bug_severity'] ?? null,
                    'bug_description' => $item['bug_description'] ?? null,
                    'client_feedback' => $item['client_feedback'] ?? null,
                ]);
            }

            return $uat->load('items');
        });

        return $this->ok('UAT berhasil diperbarui', $this->formatUat($uat));
    }

    #[OA\Patch(
        path: '/proposals/{id}/uat/{uatId}/review',
        summary: 'Pengurus memeriksa hasil UAT dan meneruskan tindak lanjut',
        description: 'Pengurus dapat menyetujui hasil yang seluruh fiturnya lulus atau membuat checklist perbaikan sebagai Task untuk mahasiswa anggota tim proyek. UAT ulang hanya dibuka setelah seluruh Task perbaikan disetujui.',
        security: [['bearerAuth' => []]],
        tags: ['F-06: UAT'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'uatId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['decision'],
                properties: [
                    new OA\Property(property: 'decision', type: 'string', enum: ['APPROVED', 'NEEDS_IMPROVEMENT']),
                    new OA\Property(property: 'review_notes', type: 'string', nullable: true),
                    new OA\Property(
                        property: 'corrections',
                        type: 'array',
                        description: 'Wajib diisi jika decision NEEDS_IMPROVEMENT.',
                        items: new OA\Items(
                            required: ['item_id', 'note', 'assigned_student_id'],
                            properties: [
                                new OA\Property(property: 'item_id', type: 'integer'),
                                new OA\Property(property: 'note', type: 'string'),
                                new OA\Property(property: 'assigned_student_id', type: 'integer', description: 'Mahasiswa anggota tim proyek.'),
                                new OA\Property(property: 'due_date', type: 'string', format: 'date', nullable: true),
                            ]
                        )
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Pemeriksaan UAT berhasil disimpan'),
            new OA\Response(response: 403, description: 'Hanya Pengurus TeFa'),
            new OA\Response(response: 404, description: 'Proposal, sesi, atau item tidak ditemukan'),
            new OA\Response(response: 409, description: 'Sesi sudah pernah diperiksa'),
            new OA\Response(response: 422, description: 'Keputusan tidak sesuai dengan hasil atau data checklist tidak valid'),
        ]
    )]
    public function review(Request $request, int|string $id, int|string $uatId): JsonResponse
    {
        $proposal = Proposal::findOrFail($id);
        $user = $request->user();
        $uat = UatResult::with('items')
            ->where('proposal_id', $proposal->id)
            ->findOrFail($uatId);

        if ($uat->review_status !== 'PENDING_REVIEW') {
            return $this->error('Sesi UAT ini sudah diperiksa dan tidak dapat diubah.', 409);
        }

        $data = $request->validate([
            'decision' => ['required', Rule::in(['APPROVED', 'NEEDS_IMPROVEMENT'])],
            'review_notes' => ['nullable', 'string'],
            'corrections' => ['required_if:decision,NEEDS_IMPROVEMENT', 'array', 'min:1'],
            'corrections.*.item_id' => ['required', 'integer', 'distinct'],
            'corrections.*.note' => ['required', 'string', 'max:5000'],
            'corrections.*.assigned_student_id' => ['required', 'integer', 'exists:users,id'],
            'corrections.*.due_date' => ['nullable', 'date'],
        ]);

        if ($data['decision'] === 'APPROVED'
            && ($uat->overall_result !== 'PASSED' || $uat->items->contains(fn (UatItem $item) => $item->result !== 'PASSED'))) {
            return $this->error('Hasil UAT hanya dapat disetujui jika seluruh fitur berstatus sesuai.', 422);
        }

        $corrections = $data['corrections'] ?? [];
        $correctionItemIds = collect($corrections)->pluck('item_id')->map(fn ($value) => (int) $value);
        $failedItemIds = $uat->items
            ->whereIn('result', ['FAILED', 'BUG_FOUND'])
            ->pluck('id');

        if ($data['decision'] === 'NEEDS_IMPROVEMENT' && $failedItemIds->diff($correctionItemIds)->isNotEmpty()) {
            return $this->error('Tambahkan satu poin tindak lanjut untuk setiap fitur yang belum sesuai.', 422);
        }

        $teams = ProjectTeam::where('proposal_id', $proposal->id)->get()->keyBy('user_id');
        foreach ($corrections as $index => $correction) {
            if (! $uat->items->contains('id', (int) $correction['item_id'])) {
                return $this->error('Item UAT tidak ditemukan pada sesi ini.', 422, [
                    "corrections.{$index}.item_id" => ['Pilih item dari sesi UAT yang sedang diperiksa.'],
                ]);
            }
            if (! $teams->has((int) $correction['assigned_student_id'])) {
                return $this->error('Mahasiswa yang ditunjuk bukan anggota tim proyek ini.', 422, [
                    "corrections.{$index}.assigned_student_id" => ['Pilih mahasiswa anggota tim proyek.'],
                ]);
            }
        }

        $uat = DB::transaction(function () use ($data, $corrections, $teams, $uat, $user, $proposal) {
            $uat->update([
                'review_status' => $data['decision'] === 'APPROVED' ? 'APPROVED' : 'NEEDS_IMPROVEMENT',
                'review_notes' => $data['review_notes'] ?? null,
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
            ]);

            foreach ($corrections as $correction) {
                $item = $uat->items->firstWhere('id', (int) $correction['item_id']);
                $item->update(['review_notes' => $correction['note']]);

                $task = $proposal->tasks()->create([
                    'uat_item_id' => $item->id,
                    'project_team_id' => $teams->get((int) $correction['assigned_student_id'])->id,
                    'title' => 'Tindak lanjut UAT: ' . $item->feature_name,
                    'description' => $correction['note'],
                    'priority' => 'HIGH',
                    'due_date' => $correction['due_date'] ?? null,
                    'status' => 'TODO',
                    'progress_percentage' => 0,
                    'pm_status' => 'PENDING',
                ]);

                $this->notifications->notify((int) $correction['assigned_student_id'], 'task.assigned', [
                    'task' => $task->title,
                    'code' => $proposal->proposal_code,
                ], (int) $proposal->id);
            }

            return $uat->load(['items.tasks.projectTeam.user', 'reviewer']);
        });

        $this->notifications->notifyClient($proposal, 'uat.reviewed', [
            'code' => $proposal->proposal_code,
            'title' => $proposal->title,
            'status' => $data['decision'] === 'APPROVED' ? 'disetujui' : 'perlu perbaikan',
        ]);

        return $this->ok('Pemeriksaan UAT berhasil disimpan.', $this->formatUat($uat));
    }

    // ──────────────────────────────────────────────────────
    //  4. PATCH /proposals/{id}/uat/signoff — Sign-off oleh klien
    // ──────────────────────────────────────────────────────
    #[OA\Patch(
        path: '/proposals/{id}/uat/signoff',
        summary: 'Klien mengesahkan UAT yang sudah disetujui Pengurus',
        description: 'Hanya sesi terbaru berstatus APPROVED dan seluruh fitur PASSED yang dapat disahkan. Waktu pengesahan dicatat server; proyek berpindah ke Handover setelah persetujuan Klien.',
        security: [['bearerAuth' => []]],
        tags: ['F-06: UAT'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID Proposal', schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['client_signoff_name'],
                properties: [
                    new OA\Property(property: 'client_signoff_name', type: 'string', example: 'Budi Santoso'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Sign-off berhasil',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Sign-off UAT berhasil.'),
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
                description: 'Bukan klien pemilik proposal',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Anda bukan klien dari proyek ini.'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'UAT tidak ditemukan',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'UAT tidak ditemukan untuk proyek ini.'),
                    ]
                )
            ),
            new OA\Response(
                response: 409,
                description: 'UAT sudah di-sign-off sebelumnya',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'UAT sudah di-sign-off sebelumnya.'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validasi gagal',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'The client signoff name field is required.'),
                        new OA\Property(property: 'errors', type: 'object'),
                    ]
                )
            ),
        ]
    )]
    public function signoff(Request $request, int|string $id): JsonResponse
    {
        $proposal = Proposal::findOrFail($id);
        $user     = $request->user();

        // Hanya klien pemilik proposal ini yang bisa sign-off
        if (!$user->hasRole('klien')) {
            return $this->error('Hanya klien yang dapat melakukan sign-off UAT.', 403);
        }

        if ((int) $proposal->client_id !== $user->id) {
            return $this->error('Anda bukan klien dari proyek ini.', 403);
        }

        $uat = UatResult::where('proposal_id', $proposal->id)->latest('id')->first();

        if (!$uat) {
            return $this->error('UAT tidak ditemukan untuk proyek ini.', 404);
        }

        if ($uat->signed_off_at !== null) {
            return $this->error('UAT sudah di-sign-off sebelumnya.', 409);
        }

        if ($uat->review_status !== 'APPROVED' || $uat->overall_result !== 'PASSED') {
            return $this->error('UAT hanya dapat disahkan setelah Pengurus menyetujui hasil yang seluruh fiturnya sesuai.', 422);
        }

        $handoverStatusId = ProposalStatus::where('name', 'Handover')->value('id');
        if (! $handoverStatusId) {
            return $this->error('Status Handover belum terdaftar pada data master.', 422);
        }

        $data = $request->validate([
            'client_signoff_name' => ['required', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($uat, $data, $proposal, $handoverStatusId) {
            $uat->update([
                'client_signoff_name' => $data['client_signoff_name'],
                'signed_off_at'       => now(),
            ]);

            // Jika PASSED → update proposal ke status Handover (id=9)
            if ($uat->overall_result === 'PASSED') {
                $proposal->update(['status_id' => $handoverStatusId]);
            }
        });

        $uat->load('items');

        return $this->ok('Sign-off UAT berhasil.', $this->formatUat($uat));
    }

    // ──────────────────────────────────────────────────────
    //  5. PATCH /proposals/{id}/status/testing — Tandai Testing
    // ──────────────────────────────────────────────────────
    #[OA\Patch(
        path: '/proposals/{id}/status/testing',
        summary: 'Tandai proyek masuk fase Testing (Dosen PM / Pengurus TeFa)',
        description: 'Mengubah status proposal menjadi Testing menggunakan data status master agar Klien dapat memulai UAT.',
        security: [['bearerAuth' => []]],
        tags: ['F-06: UAT'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID Proposal', schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Status berhasil diperbarui ke Testing',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Status proyek berhasil diperbarui ke Testing.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'status_id', type: 'integer', example: 8),
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
                description: 'Akses ditolak',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Hanya Dosen PM atau Pengurus TeFa yang dapat mengelola status UAT.'),
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
        ]
    )]
    public function setTesting(Request $request, int|string $id): JsonResponse
    {
        $proposal = Proposal::findOrFail($id);
        $user     = $request->user();

        $this->authorizeManage($user, $proposal);

        $testingStatusId = ProposalStatus::where('name', 'Testing')->value('id');
        if (! $testingStatusId) {
            return $this->error('Status Testing belum terdaftar pada data master.', 422);
        }

        $proposal->update(['status_id' => $testingStatusId]);

        return $this->ok('Status proyek berhasil diperbarui ke Testing.', [
            'id'        => $proposal->id,
            'status_id' => $proposal->status_id,
        ]);
    }

    // ──────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────

    private function authorizeClient(Proposal $proposal, mixed $user): void
    {
        if (! $user->hasRole('klien') || (int) $proposal->client_id !== (int) $user->id) {
            abort(403, 'Hanya Klien pemilik proyek yang dapat mengirim hasil UAT.');
        }
    }

    /** Hanya dosen PM dan pengurus yang dapat mengelola status UAT. */
    private function authorizeManage(mixed $user, ?Proposal $proposal = null): void
    {
        if ($user->hasRole('pengurus_tefa')) {
            return;
        }

        if ($user->hasRole('dosen')) {
            if ($proposal && (int) $proposal->pm_id !== (int) $user->id) {
                abort(403, 'Akses ditolak: Anda bukan PM untuk proyek ini.');
            }
            return;
        }

        abort(403, 'Hanya Dosen PM atau Pengurus TeFa yang dapat mengelola status UAT.');
    }

    /** Klien, dosen, dan pengurus dapat melihat UAT. */
    private function authorizeView(mixed $proposal, mixed $user): void
    {
        if ($user->hasRole('pengurus_tefa')) {
            return;
        }

        if ($user->hasRole('dosen') && (int) $proposal->pm_id === (int) $user->id) {
            return;
        }

        if ($user->hasRole('klien') && (int) $proposal->client_id === $user->id) {
            return;
        }

        abort(403, 'Akses ditolak.');
    }

    private function formatUat(UatResult $uat): array
    {
        return [
            'id'                  => $uat->id,
            'proposal_id'         => $uat->proposal_id,
            'project_team_id'     => $uat->project_team_id,
            'session_date'        => $uat->session_date?->toDateString(),
            'overall_result'      => $uat->overall_result,
            'review_status'       => $uat->review_status
                ?? ($uat->signed_off_at !== null && $uat->overall_result === 'PASSED' ? 'APPROVED' : 'PENDING_REVIEW'),
            'review_notes'        => $uat->review_notes,
            'reviewed_by'         => $uat->reviewer ? [
                'id' => $uat->reviewer->id,
                'name' => $uat->reviewer->name,
            ] : null,
            'reviewed_at'         => $uat->reviewed_at?->toIso8601String(),
            'submitted_by'        => $uat->submitted_by,
            'client_notes'        => $uat->client_notes,
            'client_signoff_name' => $uat->client_signoff_name,
            'signed_off_at'       => $uat->signed_off_at?->toIso8601String(),
            'created_at'          => $uat->created_at?->toIso8601String(),
            'updated_at'          => $uat->updated_at?->toIso8601String(),
            'uat_items'           => $uat->items->map(fn (UatItem $item) => $this->formatItem($item))->all(),
        ];
    }

    private function formatItem(UatItem $item): array
    {
        return [
            'id'              => $item->id,
            'uat_result_id'   => $item->uat_result_id,
            'feature_name'    => $item->feature_name,
            'test_scenario'   => $item->test_scenario,
            'result'          => $item->result,
            'bug_severity'    => $item->bug_severity,
            'bug_description' => $item->bug_description,
            'client_feedback' => $item->client_feedback,
            'review_notes'    => $item->review_notes,
            'tasks'           => $item->relationLoaded('tasks')
                ? $item->tasks->map(fn ($task) => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'status' => $task->status,
                    'pm_status' => $task->pm_status,
                    'assignee' => $task->projectTeam?->user ? [
                        'id' => $task->projectTeam->user->id,
                        'name' => $task->projectTeam->user->name,
                    ] : null,
                ])->all()
                : [],
        ];
    }

    private function calculateOverallResult(array $items): string
    {
        $results = collect($items)->pluck('result');
        $passed = $results->filter(fn ($result) => $result === 'PASSED')->count();

        if ($passed === $results->count()) {
            return 'PASSED';
        }

        return $passed === 0 ? 'FAILED' : 'PARTIAL';
    }
}
