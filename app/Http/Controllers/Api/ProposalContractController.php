<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proposal;
use App\Models\User;
use App\Services\NotificationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class ProposalContractController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications
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
                mediaType: 'application/json',
                schema: new OA\Schema(
                    required: ['scope_of_work', 'total_amount', 'payment_terms_description', 'start_date', 'target_completion_date'],
                    properties: [
                        new OA\Property(property: 'scope_of_work', type: 'string', example: 'Pengembangan modul backend REST API, database MySQL, dan integrasi frontend.'),
                        new OA\Property(property: 'total_amount', type: 'number', format: 'double', example: 25000000),
                        new OA\Property(property: 'payment_terms_description', type: 'string', example: 'Termin 1: 50% setelah penandatanganan SPK. Termin 2: 50% setelah BAST disetujui.'),
                        new OA\Property(property: 'maintenance_warranty_months', type: 'integer', example: 3, default: 3),
                        new OA\Property(property: 'start_date', type: 'string', format: 'date', example: '2026-11-01'),
                        new OA\Property(property: 'target_completion_date', type: 'string', format: 'date', example: '2027-02-28'),
                        new OA\Property(property: 'pm_id', type: 'integer', nullable: true),
                        new OA\Property(property: 'terms', type: 'array', items: new OA\Items(type: 'object')),
                        new OA\Property(property: 'members', type: 'array', items: new OA\Items(type: 'object')),
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
    public function updateContract(Request $request, int|string $id): JsonResponse
    {
        $proposal = Proposal::findOrFail($id);

        $validated = $request->validate([
            'scope_of_work'               => 'required|string',
            'total_amount'                => 'required|numeric|min:0',
            'payment_terms_description'   => 'nullable|string',
            'maintenance_warranty_months' => 'nullable|integer|min:0',
            'start_date'                  => 'required|date',
            'target_completion_date'      => 'required|date|after_or_equal:start_date',
            'pm_id'                       => 'nullable|integer|exists:users,id',
            'terms'                       => 'nullable|array',
            'terms.*'                     => 'array',
            'terms.*.amount'              => 'nullable|numeric|min:0',
            'terms.*.payment_date'        => 'nullable|date',
            'members'                     => 'nullable|array',
            'members.*'                   => 'array',
            'members.*.student_id'        => ['required_with:members', 'integer', 'exists:users,id', 'distinct'],
            'members.*.role_id'           => 'required_with:members|integer|exists:project_roles,id',
            'members.*.joined_at'         => 'nullable|date',
        ]);

        $members = $validated['members'] ?? [];
        $studentIds = array_values(array_unique(array_map(
            static fn (array $member): int => (int) $member['student_id'],
            $members
        )));
        if ($studentIds !== [] && User::whereIn('id', $studentIds)->get()->contains(
            static fn (User $user): bool => !$user->hasRole('mahasiswa')
        )) {
            return response()->json([
                'message' => 'Semua anggota tim harus memiliki role mahasiswa.',
                'errors' => ['members' => ['Pilih mahasiswa yang terdaftar pada sistem.']],
            ], 422);
        }

        $oldPmId      = $proposal->pm_id;
        $addedMembers = [];
        $termsCreated = 0;
        $generatedContractPath = null;

        DB::transaction(function () use ($proposal, $validated, $request, $members, &$addedMembers, &$termsCreated, &$generatedContractPath) {
            $year = date('Y', strtotime($validated['start_date']));

            // Auto-generate the official document number when one has not been assigned.
            if (empty($proposal->spk_number)) {
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

            $proposal->scope_of_work             = $validated['scope_of_work'];
            $proposal->total_amount              = $validated['total_amount'];
            $proposal->payment_terms_description = $validated['payment_terms_description'] ?? 'Termin pembayaran telah disepakati sesuai SPK.';
            $proposal->start_date                = $validated['start_date'];
            $proposal->target_completion_date    = $validated['target_completion_date'];
            $proposal->status_id                 = 7; // Active (id=7)
            $proposal->save();

            // Set atau update masa garansi pemeliharaan
            $warrantyMonths = isset($validated['maintenance_warranty_months'])
                ? (int) $validated['maintenance_warranty_months']
                : 3;

            $proposal->maintenance()->updateOrCreate(
                ['proposal_id' => $proposal->id],
                ['maintenance_warranty_months' => $warrantyMonths]
            );

            // Simpan termin pembayaran jika ada
            $terms = $validated['terms'] ?? [];
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
            if (is_array($members) && count($members) > 0) {
                $proposal->projectTeams()->delete();
                foreach ($members as $m) {
                    $studentId = $m['student_id'] ?? $m['user_id'] ?? null;
                    $roleId = $m['role_id'] ?? null;
                    if ($studentId && $roleId) {
                        $team = $proposal->projectTeams()->create([
                            'user_id'   => (int) $studentId,
                            'role_id'   => (int) $roleId,
                            'joined_at' => !empty($m['joined_at']) ? $m['joined_at'] : $validated['start_date'],
                        ]);

                        $addedMembers[] = [
                            'user_id' => (int) $studentId,
                            'role'    => $team->role?->name ?? 'Anggota',
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

            $proposal->load(['client', 'pm', 'maintenance']);
            $teamMembers = $proposal->projectTeams()->with(['user', 'role'])->get();
            $generatedContractPath = 'contracts/' . $proposal->id . '-' . Str::slug($proposal->spk_number) . '.pdf';
            $contractPdf = Pdf::loadView('documents.contract', [
                'proposal' => $proposal,
                'teamMembers' => $teamMembers,
                'terms' => $terms,
            ])->setPaper('a4');

            if (!Storage::disk('local')->put($generatedContractPath, $contractPdf->output())) {
                throw new \RuntimeException('Dokumen SPK gagal disimpan.');
            }

            $proposal->contract_file_url = $generatedContractPath;
            $proposal->save();
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
                'code'  => $proposal->proposal_code,
                'title' => $proposal->title,
                'role'  => $member['role'],
            ], (int) $proposal->id);
        }

        return response()->json([
            'message' => 'Data SPK/kontrak berhasil disimpan',
            'data'    => $proposal->fresh(['status', 'maintenance', 'pm.lecturerProfile', 'client']),
        ]);
    }

    public function download(Request $request, int|string $id)
    {
        $proposal = Proposal::findOrFail($id);
        $user = $request->user();

        if (!$user->hasRole('pengurus_tefa')) {
            $clientProfile = $user->clientProfile ?? $user->clientPic?->clientProfile;
            $clientIds = $clientProfile
                ? $clientProfile->pics()->pluck('user_id')->push($clientProfile->user_id)
                : collect([$user->id]);

            if (!$clientIds->contains($proposal->client_id)) {
                abort(403, 'Akses ditolak: Anda bukan bagian dari klien pemilik proyek ini.');
            }
        }

        $path = $proposal->contract_file_url;
        abort_if(!$path || !Storage::disk('local')->exists($path), 404, 'Dokumen SPK belum tersedia.');

        return Storage::disk('local')->download($path, basename($path));
    }

    #[OA\Patch(
        path: '/proposals/{id}/contract/sign',
        summary: 'Tandai kontrak telah ditandatangani (Pengurus TeFa)',
        description: 'Mengubah status kontrak menjadi sudah ditandatangani (is_signed = true, signed_at = now). Opsi untuk mengunggah berkas kontrak bertandatangan.',
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
            required: false,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'contract_file', type: 'string', format: 'binary', description: 'File kontrak yang sudah di-TTD (PDF, max 10MB)'),
                    ]
                )
            )
        ),
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
    public function sign(Request $request, int|string $id): JsonResponse
    {
        $proposal = Proposal::findOrFail($id);

        $request->validate([
            'contract_file' => 'nullable|file|mimes:pdf|max:10240',
        ]);

        if ($request->hasFile('contract_file')) {
            $proposal->contract_file_url = $request->file('contract_file')
                ->store('contracts', 'public');
        }

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
}
