<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proposal;
use App\Models\WarrantyExtensionRequest;
use App\Services\NotificationService;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class WarrantyExtensionController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * GET /proposals/{id}/warranty-extensions
     * Klien: lihat request milik proyek ini.
     * Pengurus TeFa: lihat semua request.
     */
    public function index(Request $request, int $id): JsonResponse
    {
        $proposal = Proposal::findOrFail($id);
        $this->authorizeAccess($proposal, $request);

        $requests = WarrantyExtensionRequest::where('proposal_id', $proposal->id)
            ->with(['requester:id,name', 'reviewer:id,name'])
            ->latest()
            ->get();

        return $this->ok('Daftar permintaan perpanjangan.', $requests);
    }

    /**
     * POST /proposals/{id}/warranty-extensions
     * Klien mengajukan perpanjangan. Status = pending.
     * - delivery_type=source_code: hanya bisa type=warranty
     * - delivery_type=tefa_managed: bisa type=warranty atau type=maintenance
     */
    public function store(Request $request, int $id): JsonResponse
    {
        $proposal = Proposal::findOrFail($id);
        abort_unless($request->user()->hasRole('klien'), 403, 'Hanya klien yang dapat mengajukan perpanjangan.');
        abort_unless(
            $proposal->client_id === $request->user()->id,
            403,
            'Akses ditolak.'
        );
        abort_unless($proposal->client_approved, 422, 'Proyek belum melalui serah terima (BAST).');

        $validated = $request->validate([
            'type'   => ['required', 'in:warranty,maintenance'],
            'months' => ['required', 'integer', 'in:1,3,6,12'],
            'notes'  => ['nullable', 'string', 'max:1000'],
        ]);

        // source_code hanya boleh warranty
        if ($proposal->delivery_type === 'source_code' && $validated['type'] === 'maintenance') {
            return $this->error('Proyek dengan skema penyerahan source code hanya dapat mengajukan perpanjangan garansi, bukan maintenance.', 422);
        }

        // Tolak jika masih ada request pending untuk tipe yang sama
        $hasPending = WarrantyExtensionRequest::where('proposal_id', $proposal->id)
            ->where('type', $validated['type'])
            ->where('status', 'pending')
            ->exists();

        if ($hasPending) {
            return $this->error('Masih ada permintaan perpanjangan ' . $validated['type'] . ' yang menggantung. Tunggu keputusan Pengurus TeFa.', 422);
        }

        $extensionReq = WarrantyExtensionRequest::create([
            'proposal_id'  => $proposal->id,
            'requested_by' => $request->user()->id,
            'type'         => $validated['type'],
            'months'       => $validated['months'],
            'notes'        => $validated['notes'] ?? null,
            'status'       => 'pending',
        ]);

        // Notifikasi ke semua pengurus_tefa
        $typeLabel = $validated['type'] === 'warranty' ? 'garansi' : 'maintenance';
        $this->notifications->notifyRole('pengurus_tefa', 'maintenance.extension_requested', [
            'client'  => $request->user()->name,
            'type'    => $typeLabel,
            'months'  => $validated['months'],
            'code'    => $proposal->project_code ?? $proposal->proposal_code,
        ], $proposal->id);

        return $this->created('Permintaan perpanjangan berhasil diajukan.', $extensionReq->load(['requester:id,name']));
    }

    /**
     * PATCH /proposals/{id}/warranty-extensions/{reqId}/review
     * Pengurus TeFa: approve / reject.
     */
    public function review(Request $request, int $id, int $reqId): JsonResponse
    {
        abort_unless($request->user()->hasRole('pengurus_tefa'), 403, 'Hanya Pengurus TeFa yang dapat mereview.');

        $proposal = Proposal::findOrFail($id);
        $extensionReq = WarrantyExtensionRequest::where('proposal_id', $proposal->id)->findOrFail($reqId);

        abort_unless($extensionReq->status === 'pending', 422, 'Permintaan ini sudah diproses sebelumnya.');

        $validated = $request->validate([
            'decision'       => ['required', 'in:approved,rejected'],
            'reviewer_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($extensionReq, $validated, $request, $proposal) {
            $extensionReq->status         = $validated['decision'];
            $extensionReq->reviewer_notes = $validated['reviewer_notes'] ?? null;
            $extensionReq->reviewed_by    = $request->user()->id;
            $extensionReq->reviewed_at    = now();
            $extensionReq->save();

            if ($validated['decision'] === 'approved') {
                // Update proposal: extend warranty or set maintenance end date
                $this->applyExtension($proposal, $extensionReq->type, $extensionReq->months);
            }
        });

        $typeLabel = $extensionReq->type === 'warranty' ? 'garansi' : 'maintenance';
        $code      = $proposal->project_code ?? $proposal->proposal_code;

        if ($validated['decision'] === 'approved') {
            $endDate = $extensionReq->type === 'warranty'
                ? $proposal->fresh()->warranty_end_date?->translatedFormat('d M Y')
                : 'diperbarui';

            $this->notifications->notifyClient($proposal, 'maintenance.extension_approved', [
                'type'     => $typeLabel,
                'code'     => $code,
                'end_date' => $endDate ?? '-',
            ]);
        } else {
            $this->notifications->notifyClient($proposal, 'maintenance.extension_rejected', [
                'type' => $typeLabel,
                'code' => $code,
            ]);
        }

        return $this->ok('Permintaan perpanjangan telah ' . ($validated['decision'] === 'approved' ? 'disetujui' : 'ditolak') . '.', $extensionReq->fresh(['requester:id,name', 'reviewer:id,name']));
    }

    /**
     * GET /maintenance/proposals
     * Pengurus TeFa: semua proposal yang sudah handover + data maintenance.
     */
    public function listProposals(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasRole('pengurus_tefa'), 403);

        $proposals = Proposal::whereNotNull('handover_date')
            ->where('client_approved', true)
            ->with(['client:id,name,email', 'status:id,name'])
            ->withCount('maintenanceTickets')
            ->orderByDesc('handover_date')
            ->get()
            ->map(fn ($p) => $this->mapProposalForMaintenance($p));

        return $this->ok('Daftar proyek pasca serah terima.', $proposals);
    }

    private function applyExtension(Proposal $proposal, string $type, int $months): void
    {
        $proposal->refresh();

        if ($type === 'warranty') {
            $baseDate = $proposal->warranty_end_date && $proposal->warranty_end_date->isFuture()
                ? $proposal->warranty_end_date
                : Carbon::today();
            $proposal->warranty_end_date = $baseDate->addMonths($months);
            $proposal->has_warranty      = true;
            $proposal->save();
        }
        // type=maintenance: untuk tefa_managed, kita perpanjang warranty_end_date juga
        // karena tefa_managed = TEVARA yang manage, warranty mencakup maintenance
        if ($type === 'maintenance') {
            $baseDate = $proposal->warranty_end_date && $proposal->warranty_end_date->isFuture()
                ? $proposal->warranty_end_date
                : Carbon::today();
            $proposal->warranty_end_date = $baseDate->addMonths($months);
            $proposal->has_warranty      = true;
            $proposal->save();
        }
    }

    private function mapProposalForMaintenance(Proposal $p): array
    {
        $today      = Carbon::today();
        $warrantyEnd = $p->warranty_end_date;
        $isActive   = $p->has_warranty && $warrantyEnd && $warrantyEnd->gte($today);
        $daysLeft   = $warrantyEnd ? $today->diffInDays($warrantyEnd, false) : null;

        return [
            'id'                   => $p->id,
            'proposal_code'        => $p->proposal_code,
            'project_code'         => $p->project_code,
            'title'                => $p->title,
            'delivery_type'        => $p->delivery_type,
            'handover_date'        => $p->handover_date?->toDateString(),
            'has_warranty'         => $p->has_warranty,
            'warranty_months'      => $p->warranty_months,
            'warranty_start_date'  => $p->warranty_start_date?->toDateString(),
            'warranty_end_date'    => $warrantyEnd?->toDateString(),
            'warranty_active'      => $isActive,
            'warranty_days_left'   => $daysLeft,
            'maintenance_tickets_count' => $p->maintenance_tickets_count,
            'client'               => $p->client ? ['id' => $p->client->id, 'name' => $p->client->name, 'email' => $p->client->email] : null,
            'status'               => $p->status ? ['id' => $p->status->id, 'name' => $p->status->name] : null,
        ];
    }

    private function authorizeAccess(Proposal $proposal, Request $request): void
    {
        $user = $request->user();
        if ($user->hasRole('klien') && $proposal->client_id !== $user->id) {
            abort(403, 'Akses ditolak.');
        }
    }
}
