<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\ProjectMaintenance;
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
            'months' => ['required', 'integer', 'in:1,3,6,12,24,36'],
            'notes'  => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validated['type'] === 'warranty') {
            return $this->error('Garansi cacat bawaan bersifat tetap dan tidak dapat diperpanjang. Silakan ajukan layanan maintenance.', 422);
        }

        // Tolak jika masih ada request aktif (pending/offered/waiting_payment) untuk tipe yang sama
        $hasPending = WarrantyExtensionRequest::where('proposal_id', $proposal->id)
            ->where('type', $validated['type'])
            ->whereIn('status', ['pending', 'offered', 'waiting_payment'])
            ->exists();

        if ($hasPending) {
            return $this->error('Masih ada permintaan perpanjangan yang sedang diproses atau menunggu persetujuan/pembayaran.', 422);
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
     * Pengurus TeFa: berikan penawaran harga & batas waktu bayar, atau tolak.
     */
    public function review(Request $request, int $id, int $reqId): JsonResponse
    {
        abort_unless($request->user()->hasRole('pengurus_tefa'), 403, 'Hanya Pengurus TeFa yang dapat mereview.');

        $proposal = Proposal::findOrFail($id);
        $extensionReq = WarrantyExtensionRequest::where('proposal_id', $proposal->id)->findOrFail($reqId);

        abort_unless($extensionReq->status === 'pending', 422, 'Permintaan ini sudah diproses sebelumnya.');

        $validated = $request->validate([
            'decision'               => ['required', 'in:approved,rejected'],
            'price'                  => ['nullable', 'numeric', 'min:0'],
            'payment_deadline_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
            'reviewer_notes'         => ['nullable', 'string', 'max:1000'],
        ]);

        $code = $proposal->project_code ?? $proposal->proposal_code;

        if ($validated['decision'] === 'approved') {
            $price = (float) ($validated['price'] ?? 0);
            $hours = (int) ($validated['payment_deadline_hours'] ?? 24);
            $deadline = now()->addHours($hours);

            $extensionReq->forceFill([
                'status'           => 'offered',
                'price'            => $price,
                'payment_deadline' => $deadline,
                'reviewer_notes'   => $validated['reviewer_notes'] ?? null,
                'reviewed_by'      => $request->user()->id,
                'reviewed_at'      => now(),
            ])->save();

            $this->notifications->notifyClient($proposal, 'maintenance.extension_offered', [
                'type'     => 'maintenance',
                'months'   => $extensionReq->months,
                'price'    => number_format($price, 0, ',', '.'),
                'deadline' => $deadline->translatedFormat('d M Y H:i'),
                'code'     => $code,
            ]);

            return $this->ok('Penawaran harga berhasil dikirim ke klien.', $extensionReq->fresh(['requester:id,name', 'reviewer:id,name']));
        }

        $extensionReq->forceFill([
            'status'         => 'rejected',
            'reviewer_notes' => $validated['reviewer_notes'] ?? null,
            'reviewed_by'    => $request->user()->id,
            'reviewed_at'    => now(),
        ])->save();

        $this->notifications->notifyClient($proposal, 'maintenance.extension_rejected', [
            'type' => 'maintenance',
            'code' => $code,
        ]);

        return $this->ok('Permintaan perpanjangan telah ditolak.', $extensionReq->fresh(['requester:id,name', 'reviewer:id,name']));
    }

    /**
     * PATCH /proposals/{id}/warranty-extensions/{reqId}/respond
     * Klien menyetujui penawaran harga (terbit tagihan) atau menolak.
     */
    public function respond(Request $request, int $id, int $reqId): JsonResponse
    {
        abort_unless($request->user()->hasRole('klien'), 403, 'Hanya klien yang dapat merespons penawaran.');

        $proposal = Proposal::findOrFail($id);
        abort_unless($proposal->client_id === $request->user()->id, 403, 'Akses ditolak.');

        $extensionReq = WarrantyExtensionRequest::where('proposal_id', $proposal->id)->findOrFail($reqId);
        abort_unless($extensionReq->status === 'offered', 422, 'Permintaan ini belum memiliki penawaran harga aktif.');

        if ($extensionReq->payment_deadline && $extensionReq->payment_deadline->isPast()) {
            return $this->error('Batas waktu pembayaran penawaran telah kadaluarsa. Silakan ajukan perpanjangan baru.', 422);
        }

        $validated = $request->validate([
            'response' => ['required', 'in:accept,reject'],
        ]);

        $code = $proposal->project_code ?? $proposal->proposal_code;

        if ($validated['response'] === 'reject') {
            $extensionReq->update(['status' => 'rejected']);
            $this->notifications->notifyRole('pengurus_tefa', 'maintenance.extension_declined', [
                'client' => $request->user()->name,
                'code'   => $code,
            ], $proposal->id);

            return $this->ok('Penawaran perpanjangan maintenance telah ditolak.', $extensionReq);
        }

        // Klien accept -> Generate termin payment baru bertipe maintenance
        $payment = DB::transaction(function () use ($proposal, $extensionReq) {
            $maxSeq = Payment::where('proposal_id', $proposal->id)->max('term_sequence') ?? 0;
            $nextSeq = $maxSeq + 1;

            $year = date('Y');
            $count = Payment::count() + 1;
            $invoiceNumber = sprintf('INV/%s/%06d', $year, $count);

            $payment = Payment::create([
                'proposal_id'   => $proposal->id,
                'term_sequence' => $nextSeq,
                'payment_type'  => 'maintenance',
                'description'   => "Perpanjangan Layanan Maintenance {$extensionReq->months} Bulan",
                'amount'        => $extensionReq->price ?? 0,
                'due_date'      => $extensionReq->payment_deadline?->toDateString(),
                'invoice_number'=> $invoiceNumber,
                'status'        => 'PENDING',
            ]);

            $extensionReq->update([
                'status'     => 'waiting_payment',
                'payment_id' => $payment->id,
            ]);

            return $payment;
        });

        $this->notifications->notifyRole('pengurus_tefa', 'maintenance.extension_accepted', [
            'client' => $request->user()->name,
            'code'   => $code,
            'amount' => number_format((float) ($extensionReq->price ?? 0), 0, ',', '.'),
        ], $proposal->id);

        return $this->ok('Penawaran disetujui. Tagihan telah diterbitkan ke menu Tagihan & Pembayaran.', [
            'extension' => $extensionReq->fresh(['payment']),
            'payment'   => $payment,
        ]);
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
                ? $proposal->warranty_end_date->copy()
                : Carbon::today();
            $newEnd = $baseDate->copy()->addMonthsNoOverflow($months);
            $proposal->warranty_end_date = $newEnd;
            $proposal->has_warranty      = true;
            $proposal->warranty_months   = (int) ($proposal->warranty_months ?? 0) + $months;
            $proposal->save();

            ProjectMaintenance::create([
                'proposal_id'     => $proposal->id,
                'type'            => 'warranty',
                'duration_months' => $months,
                'start_date'      => $baseDate->toDateString(),
                'end_date'        => $newEnd->toDateString(),
                'status'          => 'active',
                'notes'           => 'Perpanjangan garansi cacat produk disetujui Pengurus TeFa',
            ]);
        }

        if ($type === 'maintenance') {
            $latestMaintenance = ProjectMaintenance::where('proposal_id', $proposal->id)
                ->where('type', 'maintenance')
                ->where('status', 'active')
                ->whereNotNull('end_date')
                ->orderByDesc('end_date')
                ->first();

            $baseDate = $latestMaintenance && $latestMaintenance->end_date && $latestMaintenance->end_date->isFuture()
                ? $latestMaintenance->end_date->copy()
                : Carbon::today();

            $newEnd = $baseDate->copy()->addMonthsNoOverflow($months);

            ProjectMaintenance::create([
                'proposal_id'     => $proposal->id,
                'type'            => 'maintenance',
                'duration_months' => $months,
                'start_date'      => $baseDate->toDateString(),
                'end_date'        => $newEnd->toDateString(),
                'status'          => 'active',
                'notes'           => 'Perpanjangan layanan maintenance disetujui Pengurus TeFa',
            ]);

            $proposal->maintenance_months = (int) ($proposal->maintenance_months ?? 0) + $months;
            $proposal->save();
        }
    }

    private function mapProposalForMaintenance(Proposal $p): array
    {
        $today      = Carbon::today();
        $warrantyEnd = $p->warranty_end_date;
        $isWarrantyActive = $p->has_warranty && $warrantyEnd && $warrantyEnd->gte($today);

        // Cari active paid maintenance jika ada
        $activeMaintenance = $p->projectMaintenances
            ->where('type', 'maintenance')
            ->where('status', 'active')
            ->filter(fn ($m) => $m->end_date && Carbon::parse($m->end_date)->gte($today))
            ->sortByDesc('end_date')
            ->first();

        $isActive = $isWarrantyActive || !empty($activeMaintenance);
        $effectiveEndDate = $activeMaintenance?->end_date ?? $warrantyEnd;
        $daysLeft = $effectiveEndDate ? $today->diffInDays($effectiveEndDate, false) : null;

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
            'has_active_maintenance' => !empty($activeMaintenance),
            'maintenance_end_date' => $activeMaintenance?->end_date?->toDateString(),
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
