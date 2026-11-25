<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProjectRole;
use App\Models\Proposal;
use App\Models\User;
use App\Services\ContractDocumentService;
use App\Services\ContractStorage;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProposalContractController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly ContractStorage $contracts,
        private readonly ContractDocumentService $documentService
    ) {}

    #[OA\Patch(
        path: '/proposals/{id}/contract',
        summary: 'Isi data SPK / Kontrak (Pengurus TeFa)',
        description: 'Menyimpan rincian SPK/kontrak kerja, scope of work, nilai kontrak, termin pembayaran, serta periode proyek. Otomatis mengenerate spk_number dan project_code jika belum ada, serta mengubah status proposal menjadi Active (id=7).',
        security: [['bearerAuth' => []]],
        tags: ['F-03: SPK & Kontrak'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID Proposal',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['scope_of_work', 'total_amount', 'start_date', 'target_completion_date'],
                    properties: [
                        new OA\Property(property: 'spk_number', type: 'string', nullable: true, maxLength: 100),
                        new OA\Property(property: 'scope_of_work', type: 'string', example: 'Pengembangan modul backend REST API, database MySQL, dan integrasi frontend.'),
                        new OA\Property(property: 'total_amount', type: 'number', format: 'double', example: 25000000),
                        new OA\Property(property: 'payment_terms_description', type: 'string', nullable: true, example: 'Termin 1: 50% setelah penandatanganan SPK. Termin 2: 50% setelah BAST disetujui.'),
                        new OA\Property(property: 'maintenance_warranty_months', type: 'integer', nullable: true, minimum: 0, example: 3, default: 3),
                        new OA\Property(property: 'start_date', type: 'string', format: 'date', example: '2026-11-01'),
                        new OA\Property(property: 'target_completion_date', type: 'string', format: 'date', example: '2027-02-28'),
                        new OA\Property(property: 'contract_file', type: 'string', format: 'binary', description: 'File SPK/Kontrak (PDF, DOC, atau DOCX, max 10 MB).'),
                        new OA\Property(property: 'pm_id', type: 'integer', nullable: true, description: 'ID user dosen yang menjadi PM.'),
                        new OA\Property(property: 'terms', type: 'string', nullable: true, description: 'Array termin dalam JSON string; setiap item berisi amount dan opsional term_sequence, payment_date, name/notes.'),
                        new OA\Property(property: 'members', type: 'string', nullable: true, description: 'Array anggota dalam JSON string; setiap item berisi student_id (atau user_id), role_id, dan opsional joined_at.'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Data SPK berhasil disimpan dan status menjadi Active',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Data SPK/kontrak berhasil disimpan'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/Proposal'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden (bukan pengurus_tefa)'),
            new OA\Response(response: 404, description: 'Proposal tidak ditemukan'),
            new OA\Response(response: 422, description: 'Validasi gagal'),
        ]
    )]
    #[OA\Post(
        path: '/proposals/{id}/contract',
        summary: 'Isi data SPK / Kontrak (Pengurus TeFa)',
        description: 'Kompatibilitas untuk pengiriman form multipart. Menjalankan validasi dan proses yang sama dengan PATCH pada endpoint ini.',
        security: [['bearerAuth' => []]],
        tags: ['F-03: SPK & Kontrak'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID Proposal', schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['scope_of_work', 'total_amount', 'start_date', 'target_completion_date'],
                    properties: [
                        new OA\Property(property: 'spk_number', type: 'string', nullable: true, maxLength: 100),
                        new OA\Property(property: 'scope_of_work', type: 'string'),
                        new OA\Property(property: 'total_amount', type: 'number', format: 'double'),
                        new OA\Property(property: 'payment_terms_description', type: 'string', nullable: true),
                        new OA\Property(property: 'maintenance_warranty_months', type: 'integer', nullable: true, minimum: 0),
                        new OA\Property(property: 'start_date', type: 'string', format: 'date'),
                        new OA\Property(property: 'target_completion_date', type: 'string', format: 'date'),
                        new OA\Property(property: 'contract_file', type: 'string', format: 'binary', description: 'PDF, DOC, atau DOCX; max 10 MB.'),
                        new OA\Property(property: 'pm_id', type: 'integer', nullable: true),
                        new OA\Property(property: 'terms', type: 'string', nullable: true, description: 'Array termin dalam JSON string; item berisi amount dan opsional term_sequence, payment_date, name/notes.'),
                        new OA\Property(property: 'members', type: 'string', nullable: true, description: 'Array anggota dalam JSON string; item berisi student_id (atau user_id), role_id, dan opsional joined_at.'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Data SPK berhasil disimpan dan status menjadi Active'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden (bukan pengurus_tefa)'),
            new OA\Response(response: 404, description: 'Proposal tidak ditemukan'),
            new OA\Response(response: 422, description: 'Validasi gagal'),
        ]
    )]
    public function updateContract(Request $request, int|string $id): JsonResponse
    {
        $proposal = Proposal::findOrFail($id);

        $validated = $request->validate([
            'spk_number'                  => 'nullable|string|max:100',
            'scope_of_work'               => 'required|string',
            'total_amount'                => 'required|numeric|min:0',
            'payment_terms_description'   => 'nullable|string',
            'maintenance_warranty_months' => 'nullable|integer|min:0',
            'start_date'                  => 'required|date',
            'target_completion_date'      => 'required|date|after_or_equal:start_date',
            'pm_id'                       => 'nullable|integer|exists:users,id',
            'terms'                       => 'nullable',
            'members'                     => 'nullable',
        ]);

        $oldPmId      = $proposal->pm_id;
        $addedMembers = [];
        $termsCreated = 0;

        DB::transaction(function () use ($proposal, $validated, $request, &$addedMembers, &$termsCreated) {
            $year = date('Y', strtotime($validated['start_date']));

            // Auto-generate atau set spk_number: SPK/TEFA/{Y}/{SEQ}
            if (!empty($validated['spk_number'])) {
                $proposal->spk_number = $validated['spk_number'];
            } elseif (empty($proposal->spk_number)) {
                $prefixSpk = "SPK/TEFA/{$year}/";
                $lastSpk = Proposal::where('spk_number', 'like', $prefixSpk . '%')
                    ->orderByDesc('spk_number')
                    ->lockForUpdate()
                    ->first();

                $seqSpk = 1;
                if ($lastSpk && preg_match('/\/(\d+)$/', $lastSpk->spk_number, $matches)) {
                    $seqSpk = intval($matches[1]) + 1;
                }
                $proposal->spk_number = $prefixSpk . str_pad((string) $seqSpk, 3, '0', STR_PAD_LEFT);
            }

            // Auto-generate project_code: PRJ-{Y}/{SEQ}
            if (empty($proposal->project_code)) {
                $prefixPrj = "PRJ-{$year}/";
                $lastPrj = Proposal::where('project_code', 'like', $prefixPrj . '%')
                    ->orderByDesc('project_code')
                    ->lockForUpdate()
                    ->first();

                $seqPrj = 1;
                if ($lastPrj && preg_match('/\/(\d+)$/', $lastPrj->project_code, $matches)) {
                    $seqPrj = intval($matches[1]) + 1;
                }
                $proposal->project_code = $prefixPrj . str_pad((string) $seqPrj, 3, '0', STR_PAD_LEFT);
            }

            if (!empty($validated['pm_id'])) {
                $proposal->pm_id = (int) $validated['pm_id'];
            }

            if (!empty($validated['maintenance_warranty_months'])) {
                $proposal->has_warranty    = true;
                $proposal->warranty_months = (int) $validated['maintenance_warranty_months'];
            }

            // Generate SPK after warranty is set so the document reflects the agreed duration.
            try {
                $proposal->contract_file_url = $this->documentService->generate($proposal);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('SPK generation failed: ' . $e->getMessage());
                $proposal->contract_file_url = null;
            }

            $proposal->scope_of_work             = $validated['scope_of_work'];
            $proposal->total_amount              = $validated['total_amount'];
            $proposal->payment_terms_description = $validated['payment_terms_description'] ?? 'Termin pembayaran telah disepakati sesuai SPK.';
            $proposal->start_date                = $validated['start_date'];
            $proposal->target_completion_date    = $validated['target_completion_date'];
            $proposal->status_id                 = 7; // Active (id=7)
            $proposal->save();

            // Simpan termin pembayaran jika ada
            $terms = $request->input('terms');
            if (is_string($terms)) {
                $terms = json_decode($terms, true);
            }
            if (is_array($terms) && count($terms) > 0) {
                $proposal->payments()->delete();
                foreach ($terms as $idx => $t) {
                    if (isset($t['amount']) && (float) $t['amount'] > 0) {
                        $proposal->payments()->create([
                            'term_sequence'      => (int) ($t['term_sequence'] ?? ($idx + 1)),
                            'amount'             => (float) $t['amount'],
                            'payment_date'       => !empty($t['payment_date']) ? $t['payment_date'] : null,
                            'verification_notes' => $t['name'] ?? $t['notes'] ?? ('Termin ' . ($idx + 1)),
                            'status'             => 'PENDING',
                        ]);
                        $termsCreated++;
                    }
                }
            }

            // Simpan anggota tim pengembang jika ada
            $members = $request->input('members');
            if (is_string($members)) {
                $members = json_decode($members, true);
            }
            if (is_array($members) && count($members) > 0) {
                $proposal->projectTeams()->delete();
                $seenUserIds = [];
                foreach ($members as $m) {
                    $studentId = $m['student_id'] ?? $m['user_id'] ?? null;
                    $roleId = $m['role_id'] ?? null;
                    if ($studentId && $roleId && !in_array((int) $studentId, $seenUserIds, true)) {
                        $seenUserIds[] = (int) $studentId;
                        $team = $proposal->projectTeams()->create([
                            'user_id'   => (int) $studentId,
                            'role_id'   => (int) $roleId,
                            'joined_at' => !empty($m['joined_at']) ? $m['joined_at'] : $validated['start_date'],
                        ]);

                        $roleName = $team->role?->name ?? ProjectRole::find((int) $roleId)?->name ?? 'Anggota';

                        $addedMembers[] = [
                            'user_id' => (int) $studentId,
                            'role'    => $roleName,
                        ];
                    }
                }
            }

            // Rekam di timeline status
            $proposal->statusHistories()->create([
                'status_id'  => 7,
                'note'       => 'Proposal disetujui & SPK/Kontrak diterbitkan',
                'changed_by' => $request->user()->id,
            ]);
        });

        if ($proposal->pm_id && (int) $proposal->pm_id !== (int) $oldPmId) {
            $this->notifications->notify((int) $proposal->pm_id, 'proposal.pm_assigned', [
                'code'  => $proposal->proposal_code,
                'title' => $proposal->title,
            ], (int) $proposal->id);
        }

        if ($termsCreated > 0) {
            $this->notifications->notifyClient($proposal, 'payment.terms_set', [
                'count' => $termsCreated,
                'code'  => $proposal->proposal_code,
            ]);
        }

        foreach ($addedMembers as $member) {
            $this->notifications->notify($member['user_id'], 'project.team_assigned', [
                'code'  => $proposal->project_code ?? $proposal->proposal_code,
                'title' => $proposal->title,
                'role'  => $member['role'],
            ], (int) $proposal->id);
        }

        return response()->json([
            'message' => 'Data SPK/kontrak berhasil disimpan',
            'data'    => $proposal->fresh(['status', 'pm.lecturerProfile', 'client']),        ]);
    }

    #[OA\Patch(
        path: '/proposals/{id}/contract/sign',
        summary: 'Tandai kontrak telah ditandatangani (Pengurus TeFa)',
        description: 'Mengubah status kontrak menjadi sudah ditandatangani (is_signed = true, signed_at = now).',
        security: [['bearerAuth' => []]],
        tags: ['F-03: SPK & Kontrak'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID Proposal',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Kontrak berhasil ditandatangani',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Kontrak berhasil ditandatangani'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/Proposal'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden (bukan pengurus_tefa)'),
            new OA\Response(response: 404, description: 'Proposal tidak ditemukan'),
        ]
    )]
    #[OA\Post(
        path: '/proposals/{id}/contract/sign',
        summary: 'Tandai kontrak telah ditandatangani (Pengurus TeFa)',
        description: 'Kompatibilitas untuk pengiriman form multipart. Menjalankan validasi dan proses yang sama dengan PATCH pada endpoint ini.',
        security: [['bearerAuth' => []]],
        tags: ['F-03: SPK & Kontrak'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID Proposal', schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'contract_file', type: 'string', format: 'binary', description: 'PDF yang sudah ditandatangani, max 10 MB.'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Kontrak berhasil ditandatangani'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden (bukan pengurus_tefa)'),
            new OA\Response(response: 404, description: 'Proposal tidak ditemukan'),
            new OA\Response(response: 422, description: 'Validasi gagal'),
        ]
    )]
    public function sign(Request $request, int|string $id): JsonResponse
    {
        $proposal = Proposal::findOrFail($id);

        $proposal->is_signed = true;
        $proposal->signed_at = now();
        $proposal->save();

        return response()->json([
            'message' => 'Kontrak berhasil ditandatangani',
            'data'    => $proposal->fresh(['status', 'pm.lecturerProfile', 'client']),
        ]);
    }

    #[OA\Patch(
        path: '/proposals/{id}/contract/pm',
        summary: 'Assign Project Manager / Dosen (Pengurus TeFa)',
        description: 'Menugaskan dosen sebagai Project Manager untuk proposal / proyek. User harus memiliki role dosen.',
        security: [['bearerAuth' => []]],
        tags: ['F-03: SPK & Kontrak'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID Proposal',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['pm_id'],
                properties: [
                    new OA\Property(property: 'pm_id', type: 'integer', description: 'ID user dosen yang menjadi PM', example: 3),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Project Manager berhasil ditugaskan',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Project Manager (Dosen) berhasil ditugaskan'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/Proposal'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden (bukan pengurus_tefa)'),
            new OA\Response(response: 404, description: 'Proposal tidak ditemukan'),
            new OA\Response(response: 422, description: 'User bukan dosen / validasi gagal'),
        ]
    )]
    public function assignPm(Request $request, int|string $id): JsonResponse
    {
        $proposal = Proposal::findOrFail($id);

        $validated = $request->validate([
            'pm_id' => 'required|integer|exists:users,id',
        ]);

        $dosen = User::findOrFail($validated['pm_id']);
        if (! $dosen->hasRole('dosen')) {
            return response()->json([
                'message' => 'User yang dipilih bukan merupakan Dosen.',
                'errors'  => [
                    'pm_id' => ['User yang dipilih harus memiliki role dosen.'],
                ],
            ], 422);
        }

        $proposal->pm_id = $dosen->id;
        $proposal->save();

        $this->notifications->notify((int) $dosen->id, 'proposal.pm_assigned', [
            'code'  => $proposal->proposal_code,
            'title' => $proposal->title,
        ], (int) $proposal->id);

        return response()->json([
            'message' => 'Project Manager (Dosen) berhasil ditugaskan',
            'data'    => $proposal->fresh(['pm.lecturerProfile', 'status']),
        ]);
    }

    #[OA\Get(
        path: '/proposals/{id}/contract/file',
        summary: 'Unduh berkas SPK / Kontrak',
        description: 'Mengunduh berkas SPK/kontrak yang tersimpan pada disk privat. Dapat diakses oleh Pengurus TeFa, PM proyek, serta klien/PIC pemilik proposal.',
        security: [['bearerAuth' => []]],
        tags: ['F-03: SPK & Kontrak'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID Proposal',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Berkas SPK/kontrak',
                content: new OA\MediaType(
                    mediaType: 'application/octet-stream',
                    schema: new OA\Schema(type: 'string', format: 'binary')
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden (bukan pihak yang berhak)'),
            new OA\Response(response: 404, description: 'Proposal atau berkas tidak ditemukan'),
        ]
    )]
    public function download(Request $request, int|string $id): StreamedResponse|JsonResponse
    {
        $proposal = Proposal::findOrFail($id);

        $this->authorizeContractDownload($proposal, $request->user());

        if (empty($proposal->contract_file_url)) {
            return response()->json([
                'message' => 'Dokumen SPK belum tersedia untuk proposal ini.',
            ], 404);
        }

        $disk = $this->contracts->resolveDisk($proposal->contract_file_url);

        if ($disk === null) {
            return response()->json([
                'message' => 'Berkas SPK tidak ditemukan pada penyimpanan.',
            ], 404);
        }

        return $disk->download($proposal->contract_file_url, $this->contractFileName($proposal));
    }

    /**
     * Hanya Pengurus TeFa, PM proyek, dan klien/PIC pemilik proposal yang
     * boleh mengunduh dokumen komersial ini.
     */
    private function authorizeContractDownload(Proposal $proposal, User $user): void
    {
        if ($user->hasRole('pengurus_tefa')) {
            return;
        }

        if ((int) $proposal->pm_id === (int) $user->id) {
            return;
        }

        if ((int) $proposal->client_id === (int) $user->id) {
            return;
        }

        $pic = $user->clientPic()->with('clientProfile:id,user_id')->first();
        $isPicOfClient = $pic !== null
            && (int) $pic->clientProfile?->user_id === (int) $proposal->client_id;

        if (! $isPicOfClient) {
            abort(403, 'Akses ditolak: Anda tidak berhak mengunduh dokumen SPK proyek ini.');
        }
    }

    private function contractFileName(Proposal $proposal): string
    {
        $base = $proposal->spk_number ?: 'SPK-PROPOSAL-' . $proposal->id;
        $base = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $base), '-');

        $extension = pathinfo((string) $proposal->contract_file_url, PATHINFO_EXTENSION);

        return $extension !== '' ? "{$base}.{$extension}" : $base;
    }
}
