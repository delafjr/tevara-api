<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proposal;
use App\Models\ProposalStatus;
use App\Models\UatResult;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'F-07: BAST', description: 'Berita Acara Serah Terima dan persetujuan klien setelah UAT PASSED')]
final class ProposalHandoverController extends Controller
{
    use ApiResponse;

    public function show(Request $request, int|string $id): JsonResponse
    {
        $proposal = Proposal::with(['status', 'maintenance'])->findOrFail($id);
        $this->authorizeClient($proposal, $request);
        $proposal->makeHidden(['risk_analysis']);

        $latestUat = $this->latestUat($proposal);

        return $this->ok('Detail BAST proyek', [
            'proposal' => $proposal,
            'latest_uat' => $latestUat,
            'eligible_for_handover' => $latestUat?->overall_result === 'PASSED',
        ]);
    }

    #[OA\Post(
        path: '/proposals/{id}/handover',
        summary: 'Setujui dan simpan BAST oleh klien',
        description: 'Hanya klien pemilik proposal yang dapat menyetujui BAST setelah hasil UAT terakhir PASSED. Nomor BAST dibuat otomatis dan status proposal menjadi Handover.',
        security: [['bearerAuth' => []]],
        tags: ['F-07: BAST'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['handover_date', 'repository_url', 'documentation_url', 'user_manual_url', 'client_signoff_name', 'client_approved', 'maintenance_warranty_months', 'maintenance_start_date', 'maintenance_end_date'],
                properties: [
                    new OA\Property(property: 'handover_date', type: 'string', format: 'date'),
                    new OA\Property(property: 'repository_url', type: 'string', format: 'uri'),
                    new OA\Property(property: 'documentation_url', type: 'string', format: 'uri'),
                    new OA\Property(property: 'user_manual_url', type: 'string', format: 'uri'),
                    new OA\Property(property: 'handover_notes', type: 'string', nullable: true),
                    new OA\Property(property: 'client_signoff_name', type: 'string'),
                    new OA\Property(property: 'client_approved', type: 'boolean', example: true),
                    new OA\Property(property: 'maintenance_warranty_months', type: 'integer', example: 3),
                    new OA\Property(property: 'maintenance_start_date', type: 'string', format: 'date'),
                    new OA\Property(property: 'maintenance_end_date', type: 'string', format: 'date'),
                    new OA\Property(property: 'maintenance_notes', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'BAST berhasil disetujui'),
            new OA\Response(response: 403, description: 'Bukan klien pemilik proposal'),
            new OA\Response(response: 404, description: 'Proposal tidak ditemukan'),
            new OA\Response(response: 409, description: 'BAST telah disetujui'),
            new OA\Response(response: 422, description: 'UAT belum PASSED atau validasi gagal'),
        ]
    )]
    public function store(Request $request, int|string $id): JsonResponse
    {
        $proposal = Proposal::with('status')->findOrFail($id);
        $this->authorizeClient($proposal, $request);

        if ($proposal->client_approved) {
            return $this->error('BAST proyek ini sudah disetujui.', 409);
        }

        $latestUat = $this->latestUat($proposal);
        if ($latestUat?->overall_result !== 'PASSED') {
            throw ValidationException::withMessages([
                'uat' => ['BAST hanya dapat disetujui setelah hasil UAT terakhir berstatus PASSED.'],
            ]);
        }

        $validated = $request->validate([
            'handover_date' => ['required', 'date'],
            'repository_url' => ['required', 'url', 'max:255'],
            'documentation_url' => ['required', 'url', 'max:255'],
            'user_manual_url' => ['required', 'url', 'max:255'],
            'handover_notes' => ['nullable', 'string'],
            'client_signoff_name' => ['required', 'string', 'max:255'],
            'client_approved' => ['required', 'accepted'],
            'maintenance_warranty_months' => ['required', 'integer', 'min:0'],
            'maintenance_start_date' => ['required', 'date'],
            'maintenance_end_date' => ['required', 'date', 'after_or_equal:maintenance_start_date'],
            'maintenance_notes' => ['nullable', 'string'],
        ]);

        $updatedProposal = DB::transaction(function () use ($proposal, $validated, $request) {
            $lockedProposal = Proposal::query()->lockForUpdate()->findOrFail($proposal->id);
            if ($lockedProposal->client_approved) {
                return null;
            }

            $year = date('Y', strtotime($validated['handover_date']));
            $prefix = "BAST/TEFA/{$year}/";
            $lastNumber = Proposal::query()
                ->where('bast_number', 'like', $prefix . '%')
                ->orderByDesc('bast_number')
                ->lockForUpdate()
                ->value('bast_number');
            $sequence = 1;
            if ($lastNumber && preg_match('/\/(\d+)$/', $lastNumber, $matches)) {
                $sequence = (int) $matches[1] + 1;
            }

            $lockedProposal->forceFill([
                'bast_number' => $prefix . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
                'repository_url' => $validated['repository_url'],
                'documentation_url' => $validated['documentation_url'],
                'user_manual_url' => $validated['user_manual_url'],
                'handover_notes' => $validated['handover_notes'] ?? null,
                'handover_date' => $validated['handover_date'],
                'client_signoff_name' => $validated['client_signoff_name'],
                'client_approved' => true,
                'bast_approved_at' => now(),
                'status_id' => ProposalStatus::query()->where('name', 'Handover')->value('id'),
            ]);

            if (!$lockedProposal->status_id) {
                throw new \RuntimeException('Status proposal Handover belum tersedia.');
            }

            $lockedProposal->save();
            $lockedProposal->maintenance()->updateOrCreate(
                ['proposal_id' => $lockedProposal->id],
                [
                    'maintenance_warranty_months' => $validated['maintenance_warranty_months'],
                    'maintenance_start_date' => $validated['maintenance_start_date'],
                    'maintenance_end_date' => $validated['maintenance_end_date'],
                    'maintenance_notes' => $validated['maintenance_notes'] ?? null,
                ]
            );
            $lockedProposal->statusHistories()->create([
                'status_id' => $lockedProposal->status_id,
                'note' => 'BAST disetujui klien: ' . $lockedProposal->bast_number,
                'changed_by' => $request->user()->id,
            ]);

            return $lockedProposal->fresh(['status', 'maintenance']);
        });

        if (!$updatedProposal) {
            return $this->error('BAST proyek ini sudah disetujui.', 409);
        }

        $updatedProposal->makeHidden(['risk_analysis']);
        return $this->ok('BAST berhasil disetujui', $updatedProposal);
    }

    private function authorizeClient(Proposal $proposal, Request $request): void
    {
        if ($proposal->client_id !== $request->user()->id) {
            abort(403, 'Akses ditolak: Anda bukan klien pemilik proyek ini.');
        }
    }

    private function latestUat(Proposal $proposal): ?UatResult
    {
        return UatResult::query()
            ->whereHas('projectTeam', fn ($query) => $query->where('proposal_id', $proposal->id))
            ->with('items')
            ->orderByDesc('session_date')
            ->orderByDesc('id')
            ->first();
    }
}
