<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proposal;
use App\Models\ProposalStatus;
use App\Models\UatResult;
use App\Services\NotificationService;
use App\Traits\ApiResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ProposalHandoverController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly NotificationService $notifications) {}

    #[OA\Get(
        path: '/proposals/handover',
        summary: 'Daftar proyek dan kelayakan BAST berdasarkan UAT terakhir',
        security: [['bearerAuth' => []]],
        tags: ['F-07: BAST'],
        responses: [new OA\Response(response: 200, description: 'Daftar proyek untuk proses BAST')]
    )]
    public function indexForPengurus(): JsonResponse
    {
        $projects = Proposal::query()
            ->where(function ($query) {
                $query->whereHas('uatResults')
                    ->orWhereHas('projectTeams.uatResults');
            })
            ->with([
                'status:id,name',
                'client.clientPic.clientProfile.user',
                'client.clientProfile.user',
                'uatResults',
                'projectTeams.uatResults',
            ])
            ->latest()
            ->get()
            ->map(function (Proposal $proposal) {
                $uat = $proposal->uatResults
                    ->concat($proposal->projectTeams->flatMap(fn ($team) => $team->uatResults))
                    ->sortByDesc(fn ($result) => $result->session_date->format('Ymd').str_pad((string) $result->id, 12, '0', STR_PAD_LEFT))
                    ->first();

                return [
                    'id' => $proposal->id,
                    'project_code' => $proposal->project_code,
                    'title' => $proposal->title,
                    'client_name' => $proposal->client_company_name,
                    'status' => $proposal->status?->name,
                    'latest_uat_result' => $uat?->overall_result,
                    'uat_signed_off_at' => $uat?->signed_off_at?->toIso8601String(),
                    'uat_signoff_name' => $uat?->client_signoff_name,
                    'bast_eligible' => $this->uatIsSignedAndPassed($uat) && ! $proposal->client_approved,
                    'bast_prepared' => filled($proposal->bast_number)
                        && $this->uatIsSignedAndPassed($uat)
                        && ! $proposal->client_approved,
                    'bast_number' => $proposal->bast_number,
                    'bast_document_url' => $proposal->bast_document_url,
                    'repository_url' => $proposal->repository_url,
                    'documentation_url' => $proposal->documentation_url,
                    'user_manual_url' => $proposal->user_manual_url,
                    'handover_notes' => $proposal->handover_notes,
                    'handover_deliverables' => $proposal->handover_deliverables ?? [],
                    'client_approved'  => $proposal->client_approved,
                    'warranty'         => $proposal->has_warranty ? [
                        'has_warranty'        => true,
                        'warranty_months'     => $proposal->warranty_months,
                        'warranty_start_date' => $proposal->warranty_start_date?->toDateString(),
                        'warranty_end_date'   => $proposal->warranty_end_date?->toDateString(),
                    ] : ['has_warranty' => false],
                ];
            })
            ->values();

        return response()->json($projects);
    }

    #[OA\Get(
        path: '/proposals/{id}/handover',
        summary: 'Detail BAST untuk PIC Klien',
        security: [['bearerAuth' => []]],
        tags: ['F-07: BAST'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Detail BAST dan status persetujuan')]
    )]
    public function show(Request $request, int|string $id): JsonResponse
    {
        $proposal = Proposal::with(['status', 'uatResult'])->findOrFail($id);
        $this->authorizeClient($proposal, $request);
        $proposal->makeHidden(['risk_analysis']);
        $latestUat = $this->latestUat($proposal);

        return $this->ok('Detail BAST proyek', [
            'proposal' => $proposal,
            'latest_uat' => $latestUat,
            'eligible_for_handover' => $this->uatIsSignedAndPassed($latestUat),
            'awaiting_client_approval' => filled($proposal->bast_number)
                && $this->uatIsSignedAndPassed($latestUat)
                && ! $proposal->client_approved,
        ]);
    }

    #[OA\Post(
        path: '/proposals/{id}/handover/prepare',
        summary: 'Siapkan BAST oleh Pengurus TeFa',
        description: 'Hanya dapat disiapkan setelah UAT terakhir lulus dan telah ditandatangani klien. Masa garansi mengikuti data proposal/kontrak. Nomor BAST dan tanggal serah terima tidak boleh dimanipulasi manual.',
        security: [['bearerAuth' => []]],
        tags: ['F-07: BAST'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['repository_url', 'handover_deliverables'],
                    properties: [
                        new OA\Property(property: 'repository_url', type: 'string', format: 'uri'),
                        new OA\Property(property: 'documentation_file', type: 'string', format: 'binary', nullable: true, description: 'File lampiran aman: PDF, DOCX, ZIP (maks 10MB)'),
                        new OA\Property(property: 'user_manual_url', type: 'string', format: 'uri', nullable: true),
                        new OA\Property(property: 'handover_notes', type: 'string', nullable: true),
                        new OA\Property(property: 'handover_deliverables', type: 'string', description: 'JSON array berisi nama, jenis, jumlah, satuan, kondisi, dan keterangan setiap hasil/barang yang diserahkan.'),
                    ]
                )
            )
        ),
        responses: [new OA\Response(response: 200, description: 'BAST disiapkan untuk persetujuan PIC Klien')]
    )]
    public function prepare(Request $request, int|string $id): JsonResponse
    {
        // Tolak jika ada yang mencoba memanipulasi nomor_bast atau tanggal_serah_terima
        if ($request->hasAny(['nomor_bast', 'bast_number', 'tanggal_serah_terima', 'handover_date'])) {
            throw ValidationException::withMessages([
                'nomor_bast' => ['Nomor BAST dan tanggal serah terima tidak boleh dimanipulasi secara manual. Nilai ini di-generate murni di sisi server saat request diproses.'],
            ]);
        }

        $proposal = Proposal::findOrFail($id);
        $this->assertUatSignedAndPassed($proposal);

        if ($proposal->client_approved) {
            return $this->error('BAST yang sudah disetujui klien tidak dapat diubah.', 409);
        }

        $deliverables = $request->input('handover_deliverables');
        if (is_string($deliverables)) {
            $deliverables = json_decode($deliverables, true);
            $request->merge(['handover_deliverables' => $deliverables]);
        }

        $validated = $request->validate([
            'repository_url'               => ['required', 'url', 'max:255'],
            'documentation_file'           => [
                $proposal->documentation_url ? 'nullable' : 'required',
                'file',
                'mimes:pdf,docx,zip,doc',
                'max:10240',
            ],
            'user_manual_url'              => ['nullable', 'url', 'max:255'],
            'handover_notes'               => ['nullable', 'string'],
            'handover_deliverables'        => ['required', 'array', 'min:1', 'max:50'],
            'handover_deliverables.*.name'      => ['required', 'string', 'max:255'],
            'handover_deliverables.*.type'      => ['required', 'in:physical,digital,document,other'],
            'handover_deliverables.*.quantity'  => ['required', 'integer', 'min:1', 'max:1000000'],
            'handover_deliverables.*.unit'      => ['required', 'string', 'max:50'],
            'handover_deliverables.*.condition' => ['required', 'in:new,good,needs_repair,not_applicable'],
            'handover_deliverables.*.notes'     => ['nullable', 'string', 'max:2000'],
            'nomor_bast'                   => ['prohibited'],
            'bast_number'                  => ['prohibited'],
            'tanggal_serah_terima'         => ['prohibited'],
            'handover_date'                => ['prohibited'],
        ], [
            'documentation_file.mimes' => 'Berkas lampiran dokumentasi API harus berformat aman: .pdf, .docx, atau .zip.',
            'documentation_file.max' => 'Ukuran berkas lampiran dokumentasi API maksimal 10 MB.',
            'nomor_bast.prohibited' => 'Nomor BAST tidak boleh dimanipulasi manual.',
            'bast_number.prohibited' => 'Nomor BAST tidak boleh dimanipulasi manual.',
            'tanggal_serah_terima.prohibited' => 'Tanggal serah terima tidak boleh dimanipulasi manual.',
            'handover_date.prohibited' => 'Tanggal serah terima tidak boleh dimanipulasi manual.',
        ]);

        $wasPrepared = filled($proposal->bast_number);
        $newDocumentationPath = $request->file('documentation_file')?->store('handover-documents', 'public');
        $oldDocumentationPath = $proposal->documentation_url;

        try {
            $preparedProposal = DB::transaction(function () use ($proposal, $validated, $newDocumentationPath) {
                $lockedProposal = Proposal::query()->lockForUpdate()->findOrFail($proposal->id);
                $this->assertUatSignedAndPassed($lockedProposal);

                if ($lockedProposal->client_approved) {
                    return null;
                }

                $year = now()->format('Y');
                $prefix = "BAST/TEFA/{$year}/";
                $lastNumber = Proposal::query()
                    ->where('bast_number', 'like', $prefix.'%')
                    ->orderByDesc('bast_number')
                    ->lockForUpdate()
                    ->value('bast_number');
                $sequence = 1;
                if ($lastNumber && preg_match('/\/(\d+)$/', $lastNumber, $matches)) {
                    $sequence = (int) $matches[1] + 1;
                }

                $lockedProposal->forceFill([
                    'bast_number'          => $lockedProposal->bast_number ?: $prefix.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
                    'repository_url'       => $validated['repository_url'],
                    'documentation_url'    => $newDocumentationPath ?? $lockedProposal->documentation_url,
                    'user_manual_url'      => $validated['user_manual_url'] ?? null,
                    'handover_notes'       => $validated['handover_notes'] ?? null,
                    'handover_deliverables'=> $validated['handover_deliverables'],
                    'handover_date'        => null,
                    'client_signoff_name'  => null,
                    'client_approved'      => false,
                    'bast_approved_at'     => null,
                    'warranty_start_date'  => null,
                    'warranty_end_date'    => null,
                ])->save();

                return $lockedProposal->fresh(['status']);
            });
        } catch (\Throwable $exception) {
            if ($newDocumentationPath) {
                Storage::disk('public')->delete($newDocumentationPath);
            }

            throw $exception;
        }

        if (! $preparedProposal) {
            if ($newDocumentationPath) {
                Storage::disk('public')->delete($newDocumentationPath);
            }

            return $this->error('BAST yang sudah disetujui klien tidak dapat diubah.', 409);
        }

        if ($newDocumentationPath && $oldDocumentationPath && $oldDocumentationPath !== $newDocumentationPath) {
            Storage::disk('public')->delete($oldDocumentationPath);
        }

        if (! $wasPrepared) {
            $preparedProposal->loadMissing('client');
            $this->notifications->notifyClient($preparedProposal, 'handover.prepared', [
                'code' => $preparedProposal->project_code ?: $preparedProposal->proposal_code,
                'title' => $preparedProposal->title,
            ]);
        }
        $preparedProposal->makeHidden(['risk_analysis']);

        return $this->ok('BAST disiapkan dan menunggu persetujuan PIC Klien.', $preparedProposal);
    }

    #[OA\Patch(
        path: '/proposals/{id}/handover/approve',
        summary: 'Setujui BAST oleh PIC Klien',
        description: 'Mencatat persetujuan PIC, membuat PDF BAST otomatis, mengirim notifikasi kepada klien, dan mengubah status menjadi Completed. Durasi garansi diambil dari data proposal/kontrak.',
        security: [['bearerAuth' => []]],
        tags: ['F-07: BAST'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['client_signoff_name', 'client_approved'],
                properties: [
                    new OA\Property(property: 'client_signoff_name', type: 'string'),
                    new OA\Property(property: 'client_approved', type: 'boolean', example: true),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'BAST disetujui')]
    )]
    public function approve(Request $request, int|string $id): JsonResponse
    {
        // Tolak jika ada yang mencoba memanipulasi nomor_bast atau tanggal_serah_terima
        if ($request->hasAny(['nomor_bast', 'bast_number', 'tanggal_serah_terima', 'handover_date'])) {
            throw ValidationException::withMessages([
                'nomor_bast' => ['Nomor BAST dan tanggal serah terima tidak boleh dimanipulasi secara manual. Nilai ini di-generate murni di sisi server saat request diproses.'],
            ]);
        }

        $proposal = Proposal::with(['status'])->findOrFail($id);
        $this->authorizeClient($proposal, $request);

        if ($proposal->client_approved) {
            return $this->error('BAST proyek ini sudah disetujui.', 409);
        }
        if (! $proposal->bast_number || ! $proposal->repository_url || ! $proposal->documentation_url || empty($proposal->handover_deliverables)) {
            throw ValidationException::withMessages([
                'handover' => ['BAST belum lengkap. Pengurus TeFa harus mencatat hasil atau barang yang diserahkan.'],
            ]);
        }

        $this->assertUatSignedAndPassed($proposal);
        $validated = $request->validate([
            'client_signoff_name' => ['required', 'string', 'max:255'],
            'client_approved' => ['required', 'accepted'],
            'nomor_bast' => ['prohibited'],
            'bast_number' => ['prohibited'],
            'tanggal_serah_terima' => ['prohibited'],
            'handover_date' => ['prohibited'],
        ], [
            'nomor_bast.prohibited' => 'Nomor BAST tidak boleh dimanipulasi manual.',
            'bast_number.prohibited' => 'Nomor BAST tidak boleh dimanipulasi manual.',
            'tanggal_serah_terima.prohibited' => 'Tanggal serah terima tidak boleh dimanipulasi manual.',
            'handover_date.prohibited' => 'Tanggal serah terima tidak boleh dimanipulasi manual.',
        ]);

        // Murni di-generate di sisi server saat request diproses
        $handoverDate = now();
        // Logika kalkulasi tanggal mulai garansi: tanggal_serah_terima + 1 hari (addDay())
        $maintenanceStartDate = $handoverDate->copy()->addDay();

        $documentPath = null;
        try {
            $approvedProposal = DB::transaction(function () use ($proposal, $validated, $request, $handoverDate, $maintenanceStartDate, &$documentPath) {
                $lockedProposal = Proposal::query()->lockForUpdate()->findOrFail($proposal->id);
                if ($lockedProposal->client_approved) {
                    return null;
                }

                $this->assertUatSignedAndPassed($lockedProposal);
                if (! $lockedProposal->bast_number || ! $lockedProposal->repository_url || ! $lockedProposal->documentation_url || empty($lockedProposal->handover_deliverables)) {
                    throw ValidationException::withMessages([
                        'handover' => ['BAST belum lengkap. Pengurus TeFa harus mencatat hasil atau barang yang diserahkan.'],
                    ]);
                }

                $completedStatusId = ProposalStatus::query()->where('name', 'Completed')->value('id');
                if (! $completedStatusId) {
                    throw new \RuntimeException('Status proposal Completed belum tersedia.');
                }

                $warrantyEndDate = null;
                if ($lockedProposal->has_warranty && $lockedProposal->warranty_months > 0) {
                    $warrantyEndDate = $maintenanceStartDate->copy()
                        ->addMonthsNoOverflow($lockedProposal->warranty_months)
                        ->toDateString();
                }

                $lockedProposal->forceFill([
                    'handover_date'       => $handoverDate->toDateString(),
                    'client_signoff_name' => $validated['client_signoff_name'],
                    'client_approved'     => true,
                    'bast_approved_at'    => $handoverDate,
                    'status_id'           => $completedStatusId,
                    'has_warranty'        => $lockedProposal->has_warranty,
                    'warranty_months'     => $lockedProposal->warranty_months,
                    'warranty_start_date' => $lockedProposal->has_warranty ? $maintenanceStartDate->toDateString() : null,
                    'warranty_end_date'   => $warrantyEndDate,
                ])->save();

                $lockedProposal->load(['status', 'client.clientPic.clientProfile.user', 'client.clientProfile.user']);
                $safeNumber = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $lockedProposal->bast_number);
                $documentPath = 'bast-documents/'.$safeNumber.'.pdf';
                $pdf = Pdf::loadView('documents.bast', ['proposal' => $lockedProposal])->output();
                if (! Storage::disk('public')->put($documentPath, $pdf)) {
                    throw new RuntimeException('PDF BAST gagal disimpan.');
                }
                $lockedProposal->forceFill(['bast_document_url' => $documentPath])->save();

                $lockedProposal->statusHistories()->create([
                    'status_id'  => $completedStatusId,
                    'note'       => 'BAST disetujui klien: '.$lockedProposal->bast_number,
                    'changed_by' => $request->user()->id,
                ]);

                return $lockedProposal->fresh(['status']);
            });
        } catch (\Throwable $exception) {
            if ($documentPath) {
                Storage::disk('public')->delete($documentPath);
            }

            throw $exception;
        }

        if (! $approvedProposal) {
            return $this->error('BAST proyek ini sudah disetujui.', 409);
        }

        $approvedProposal->makeHidden(['risk_analysis']);
        $this->notifications->notifyRole('pengurus_tefa', 'handover.approved', [
            'code' => $approvedProposal->project_code ?: $approvedProposal->proposal_code,
            'title' => $approvedProposal->title,
        ], $approvedProposal->id);
        $this->notifications->notifyClient($approvedProposal, 'handover.document_generated', [
            'code' => $approvedProposal->project_code ?: $approvedProposal->proposal_code,
            'title' => $approvedProposal->title,
        ]);

        return $this->ok('BAST berhasil disetujui, PDF telah dibuat, dan proyek telah selesai.', $approvedProposal);
    }

    #[OA\Get(
        path: '/proposals/{id}/handover/documentation',
        summary: 'Unduh dokumentasi API BAST',
        security: [['bearerAuth' => []]],
        tags: ['F-07: BAST'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Dokumentasi API')]
    )]
    public function downloadDocumentation(Request $request, int|string $id): StreamedResponse
    {
        $proposal = Proposal::query()->findOrFail($id);
        if ($request->user()->hasRole('klien')) {
            $this->authorizeClient($proposal, $request);
        }

        $path = $proposal->documentation_url;
        abort_unless($path && Storage::disk('public')->exists($path), 404, 'Dokumentasi BAST tidak ditemukan.');

        return Storage::disk('public')->download($path);
    }

    #[OA\Get(
        path: '/proposals/{id}/handover/bast',
        summary: 'Unduh PDF BAST yang telah disetujui',
        security: [['bearerAuth' => []]],
        tags: ['F-07: BAST'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'PDF BAST')]
    )]
    public function downloadBast(Request $request, int|string $id): StreamedResponse
    {
        $proposal = Proposal::query()->findOrFail($id);
        if ($request->user()->hasRole('klien')) {
            $this->authorizeClient($proposal, $request);
        }

        $path = $proposal->bast_document_url;
        abort_unless(
            $proposal->client_approved && $path && Storage::disk('public')->exists($path),
            404,
            'PDF BAST belum tersedia.'
        );

        return Storage::disk('public')->download($path);
    }

    private function authorizeClient(Proposal $proposal, Request $request): void
    {
        if ($proposal->client_id !== $request->user()->id) {
            abort(403, 'Akses ditolak: Anda bukan klien pemilik proposal ini.');
        }
    }

    private function assertUatSignedAndPassed(Proposal $proposal): void
    {
        if (! $this->uatIsSignedAndPassed($this->latestUat($proposal))) {
            throw ValidationException::withMessages([
                'uat' => ['BAST hanya dapat diproses setelah UAT terakhir berstatus PASSED dan ditandatangani klien.'],
            ]);
        }
    }

    private function uatIsSignedAndPassed(?UatResult $uat): bool
    {
        return $uat?->overall_result === 'PASSED' && $uat->signed_off_at !== null;
    }

    private function latestUat(Proposal $proposal): ?UatResult
    {
        return UatResult::query()
            ->where(function ($query) use ($proposal) {
                $query->where('proposal_id', $proposal->id)
                    ->orWhereHas('projectTeam', fn ($teamQuery) => $teamQuery->where('proposal_id', $proposal->id));
            })
            ->with('items')
            ->orderByDesc('session_date')
            ->orderByDesc('id')
            ->first();
    }
}
