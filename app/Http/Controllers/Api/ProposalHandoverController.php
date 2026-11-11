<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proposal;
use App\Models\ProposalStatus;
use App\Models\UatResult;
use App\Services\NotificationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ProposalHandoverController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly NotificationService $notifications) {}

    #[OA\Get(
        path: '/proposals/handover',
        summary: 'Daftar proyek dengan UAT lulus dan sudah ditandatangani klien',
        security: [['bearerAuth' => []]],
        tags: ['F-07: BAST'],
        responses: [new OA\Response(response: 200, description: 'Daftar proyek untuk proses BAST')]
    )]
    public function indexForPengurus(): JsonResponse
    {
        $projects = Proposal::query()
            ->whereHas('uatResult', fn ($query) => $query
                ->where('overall_result', 'PASSED')
                ->whereNotNull('signed_off_at'))
            ->with(['status:id,name', 'client.clientPic.clientProfile.user', 'client.clientProfile.user', 'maintenance', 'uatResult'])
            ->latest()
            ->get()
            ->map(function (Proposal $proposal) {
                $uat = $proposal->uatResult;

                return [
                    'id' => $proposal->id,
                    'project_code' => $proposal->project_code,
                    'title' => $proposal->title,
                    'client_name' => $proposal->client_company_name,
                    'status' => $proposal->status?->name,
                    'latest_uat_result' => $uat?->overall_result,
                    'uat_signed_off_at' => $uat?->signed_off_at?->toIso8601String(),
                    'uat_signoff_name' => $uat?->client_signoff_name,
                    'bast_eligible' => ! $proposal->client_approved,
                    'bast_prepared' => filled($proposal->bast_number) && ! $proposal->client_approved,
                    'bast_number' => $proposal->bast_number,
                    'repository_url' => $proposal->repository_url,
                    'documentation_url' => $proposal->documentation_url,
                    'user_manual_url' => $proposal->user_manual_url,
                    'handover_notes' => $proposal->handover_notes,
                    'client_approved' => $proposal->client_approved,
                    'maintenance' => $proposal->maintenance ? [
                        'maintenance_warranty_months' => $proposal->maintenance->maintenance_warranty_months,
                        'maintenance_start_date' => $proposal->maintenance->maintenance_start_date?->toDateString(),
                        'maintenance_end_date' => $proposal->maintenance->maintenance_end_date?->toDateString(),
                        'maintenance_notes' => $proposal->maintenance->maintenance_notes,
                    ] : null,
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
        $proposal = Proposal::with(['status', 'maintenance', 'uatResult'])->findOrFail($id);
        $this->authorizeClient($proposal, $request);
        $proposal->makeHidden(['risk_analysis']);
        $latestUat = $this->latestUat($proposal);

        return $this->ok('Detail BAST proyek', [
            'proposal' => $proposal,
            'latest_uat' => $latestUat,
            'eligible_for_handover' => $this->uatIsSignedAndPassed($latestUat),
            'awaiting_client_approval' => filled($proposal->bast_number) && ! $proposal->client_approved,
        ]);
    }

    #[OA\Post(
        path: '/proposals/{id}/handover/prepare',
        summary: 'Siapkan BAST oleh Pengurus TeFa',
        description: 'Hanya dapat disiapkan setelah UAT terakhir lulus dan telah ditandatangani klien.',
        security: [['bearerAuth' => []]],
        tags: ['F-07: BAST'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['repository_url', 'maintenance_warranty_months'],
                    properties: [
                        new OA\Property(property: 'repository_url', type: 'string', format: 'uri'),
                        new OA\Property(property: 'documentation_file', type: 'string', format: 'binary', nullable: true),
                        new OA\Property(property: 'user_manual_url', type: 'string', format: 'uri', nullable: true),
                        new OA\Property(property: 'handover_notes', type: 'string', nullable: true),
                        new OA\Property(property: 'maintenance_warranty_months', type: 'integer', minimum: 0),
                        new OA\Property(property: 'maintenance_notes', type: 'string', nullable: true),
                    ]
                )
            )
        ),
        responses: [new OA\Response(response: 200, description: 'BAST disiapkan untuk persetujuan PIC Klien')]
    )]
    public function prepare(Request $request, int|string $id): JsonResponse
    {
        $proposal = Proposal::with('maintenance')->findOrFail($id);
        $this->assertUatSignedAndPassed($proposal);

        if ($proposal->client_approved) {
            return $this->error('BAST yang sudah disetujui klien tidak dapat diubah.', 409);
        }

        $validated = $request->validate([
            'repository_url' => ['required', 'url', 'max:255'],
            'documentation_file' => [
                $proposal->documentation_url ? 'nullable' : 'required',
                'file',
                'mimes:pdf,doc,docx',
                'max:10240',
            ],
            'user_manual_url' => ['nullable', 'url', 'max:255'],
            'handover_notes' => ['nullable', 'string'],
            'maintenance_warranty_months' => ['required', 'integer', 'min:0'],
            'maintenance_notes' => ['nullable', 'string'],
        ]);

        $wasPrepared = filled($proposal->bast_number);
        $newDocumentationPath = $request->file('documentation_file')?->store('handover-documents', 'public');
        $oldDocumentationPath = $proposal->documentation_url;

        try {
            $preparedProposal = DB::transaction(function () use ($proposal, $validated, $newDocumentationPath) {
                $lockedProposal = Proposal::query()->lockForUpdate()->with('maintenance')->findOrFail($proposal->id);
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
                    'bast_number' => $lockedProposal->bast_number ?: $prefix.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
                    'repository_url' => $validated['repository_url'],
                    'documentation_url' => $newDocumentationPath ?? $lockedProposal->documentation_url,
                    'user_manual_url' => $validated['user_manual_url'] ?? null,
                    'handover_notes' => $validated['handover_notes'] ?? null,
                    'handover_date' => null,
                    'client_signoff_name' => null,
                    'client_approved' => false,
                    'bast_approved_at' => null,
                ])->save();

                $lockedProposal->maintenance()->updateOrCreate(
                    ['proposal_id' => $lockedProposal->id],
                    [
                        'maintenance_warranty_months' => $validated['maintenance_warranty_months'],
                        'maintenance_start_date' => null,
                        'maintenance_end_date' => null,
                        'maintenance_notes' => $validated['maintenance_notes'] ?? null,
                    ]
                );

                return $lockedProposal->fresh(['status', 'maintenance']);
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
        description: 'Mencatat persetujuan PIC, tanggal serah terima resmi, status Completed, dan awal masa garansi keesokan hari.',
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
        $proposal = Proposal::with(['status', 'maintenance'])->findOrFail($id);
        $this->authorizeClient($proposal, $request);

        if ($proposal->client_approved) {
            return $this->error('BAST proyek ini sudah disetujui.', 409);
        }
        if (! $proposal->bast_number || ! $proposal->repository_url || ! $proposal->documentation_url) {
            throw ValidationException::withMessages([
                'handover' => ['BAST belum disiapkan oleh Pengurus TeFa.'],
            ]);
        }

        $this->assertUatSignedAndPassed($proposal);
        $validated = $request->validate([
            'client_signoff_name' => ['required', 'string', 'max:255'],
            'client_approved' => ['required', 'accepted'],
        ]);

        $handoverDate = now();
        $maintenanceStartDate = $handoverDate->copy()->addDay();

        $approvedProposal = DB::transaction(function () use ($proposal, $validated, $request, $handoverDate, $maintenanceStartDate) {
            $lockedProposal = Proposal::query()->lockForUpdate()->with('maintenance')->findOrFail($proposal->id);
            if ($lockedProposal->client_approved) {
                return null;
            }

            $this->assertUatSignedAndPassed($lockedProposal);
            if (! $lockedProposal->bast_number || ! $lockedProposal->repository_url || ! $lockedProposal->documentation_url) {
                throw ValidationException::withMessages([
                    'handover' => ['BAST belum disiapkan oleh Pengurus TeFa.'],
                ]);
            }

            $completedStatusId = ProposalStatus::query()->where('name', 'Completed')->value('id');
            if (! $completedStatusId) {
                throw new \RuntimeException('Status proposal Completed belum tersedia.');
            }

            $lockedProposal->forceFill([
                'handover_date' => $handoverDate->toDateString(),
                'client_signoff_name' => $validated['client_signoff_name'],
                'client_approved' => true,
                'bast_approved_at' => $handoverDate,
                'status_id' => $completedStatusId,
            ])->save();

            $maintenance = $lockedProposal->maintenance()->firstOrCreate(
                ['proposal_id' => $lockedProposal->id],
                ['maintenance_warranty_months' => 0]
            );
            $maintenance->forceFill([
                'maintenance_start_date' => $maintenanceStartDate->toDateString(),
                'maintenance_end_date' => $maintenanceStartDate
                    ->copy()
                    ->addMonthsNoOverflow((int) $maintenance->maintenance_warranty_months)
                    ->toDateString(),
            ])->save();

            $lockedProposal->statusHistories()->create([
                'status_id' => $completedStatusId,
                'note' => 'BAST disetujui klien: '.$lockedProposal->bast_number,
                'changed_by' => $request->user()->id,
            ]);

            return $lockedProposal->fresh(['status', 'maintenance']);
        });

        if (! $approvedProposal) {
            return $this->error('BAST proyek ini sudah disetujui.', 409);
        }

        $approvedProposal->makeHidden(['risk_analysis']);
        $this->notifications->notifyRole('pengurus_tefa', 'handover.approved', [
            'code' => $approvedProposal->project_code ?: $approvedProposal->proposal_code,
            'title' => $approvedProposal->title,
        ], $approvedProposal->id);

        return $this->ok('BAST berhasil disetujui dan proyek telah selesai.', $approvedProposal);
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
