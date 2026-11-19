<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceTicket;
use App\Models\Proposal;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

final class MaintenanceTicketController extends Controller
{
    use ApiResponse;

    #[OA\Get(
        path: '/proposals/{id}/maintenance-tickets',
        summary: 'Daftar tiket maintenance sebuah proyek',
        security: [['bearerAuth' => []]],
        tags: ['F-08: Maintenance'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Daftar tiket maintenance')]
    )]
    public function index(Request $request, int|string $id): JsonResponse
    {
        $proposal = Proposal::findOrFail($id);
        $this->authorizeAccess($proposal, $request);

        $tickets = MaintenanceTicket::where('proposal_id', $proposal->id)
            ->with([
                'reporter:id,name',
                'handler:id,name',
            ])
            ->latest()
            ->get();

        return $this->ok('Daftar tiket maintenance.', $tickets);
    }

    #[OA\Post(
        path: '/proposals/{id}/maintenance-tickets',
        summary: 'Buat tiket maintenance baru',
        description: 'Klien bisa lapor bug. Pengurus TeFa bisa buat tiket inisiatif. `reported_by` otomatis dari token.',
        security: [['bearerAuth' => []]],
        tags: ['F-08: Maintenance'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['title'],
                properties: [
                    new OA\Property(property: 'title', type: 'string', maxLength: 255),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'priority', type: 'string', enum: ['low', 'medium', 'high'], example: 'medium'),
                ]
            )
        ),
        responses: [new OA\Response(response: 201, description: 'Tiket dibuat')]
    )]
    public function store(Request $request, int|string $id): JsonResponse
    {
        $proposal = Proposal::findOrFail($id);
        $this->authorizeAccess($proposal, $request);

        $validated = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority'    => ['nullable', 'in:low,medium,high'],
        ]);

        $ticket = MaintenanceTicket::create([
            'proposal_id'  => $proposal->id,
            'reported_by'  => $request->user()->id,
            'title'        => $validated['title'],
            'description'  => $validated['description'] ?? null,
            'priority'     => $validated['priority'] ?? 'medium',
            'status'       => 'open',
        ]);

        return $this->created('Tiket maintenance dibuat.', $ticket->load(['reporter:id,name', 'handler:id,name']));
    }

    #[OA\Get(
        path: '/proposals/{id}/maintenance-tickets/{ticketId}',
        summary: 'Detail tiket maintenance',
        security: [['bearerAuth' => []]],
        tags: ['F-08: Maintenance'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'ticketId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'Detail tiket')]
    )]
    public function show(Request $request, int|string $id, int|string $ticketId): JsonResponse
    {
        $proposal = Proposal::findOrFail($id);
        $this->authorizeAccess($proposal, $request);

        $ticket = MaintenanceTicket::where('proposal_id', $proposal->id)->findOrFail($ticketId);

        return $this->ok('Detail tiket.', $ticket->load(['reporter:id,name', 'handler:id,name']));
    }

    #[OA\Patch(
        path: '/proposals/{id}/maintenance-tickets/{ticketId}',
        summary: 'Update status/progress tiket (Pengurus TeFa)',
        description: 'Pengurus TeFa dapat mengubah status, menugaskan handler, dan mengisi resolution_notes. Status `resolved` otomatis mengisi `resolved_at`.',
        security: [['bearerAuth' => []]],
        tags: ['F-08: Maintenance'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'ticketId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', enum: ['open', 'in_progress', 'resolved']),
                    new OA\Property(property: 'handled_by', type: 'integer', nullable: true, description: 'ID pengurus tefa yang handle'),
                    new OA\Property(property: 'resolution_notes', type: 'string', nullable: true),
                    new OA\Property(property: 'priority', type: 'string', enum: ['low', 'medium', 'high']),
                ]
            )
        ),
        responses: [new OA\Response(response: 200, description: 'Tiket diperbarui')]
    )]
    public function update(Request $request, int|string $id, int|string $ticketId): JsonResponse
    {
        $proposal = Proposal::findOrFail($id);
        abort_unless($request->user()->hasRole('pengurus_tefa'), 403, 'Hanya pengurus TeFa yang dapat mengubah status tiket.');

        $ticket = MaintenanceTicket::where('proposal_id', $proposal->id)->findOrFail($ticketId);

        $validated = $request->validate([
            'status'           => ['nullable', 'in:open,in_progress,resolved'],
            'handled_by'       => ['nullable', 'integer', 'exists:users,id'],
            'resolution_notes' => ['nullable', 'string'],
            'priority'         => ['nullable', 'in:low,medium,high'],
        ]);

        DB::transaction(function () use ($ticket, $validated) {
            if (isset($validated['status'])) {
                $ticket->status = $validated['status'];
                if ($validated['status'] === 'resolved' && ! $ticket->resolved_at) {
                    $ticket->resolved_at = now();
                } elseif ($validated['status'] !== 'resolved') {
                    $ticket->resolved_at = null;
                }
            }
            if (array_key_exists('handled_by', $validated)) {
                $ticket->handled_by = $validated['handled_by'];
            }
            if (array_key_exists('resolution_notes', $validated)) {
                $ticket->resolution_notes = $validated['resolution_notes'];
            }
            if (isset($validated['priority'])) {
                $ticket->priority = $validated['priority'];
            }
            $ticket->save();
        });

        return $this->ok('Tiket diperbarui.', $ticket->fresh(['reporter:id,name', 'handler:id,name']));
    }

    #[OA\Delete(
        path: '/proposals/{id}/maintenance-tickets/{ticketId}',
        summary: 'Hapus tiket maintenance (Pengurus TeFa)',
        security: [['bearerAuth' => []]],
        tags: ['F-08: Maintenance'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'ticketId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'Tiket dihapus')]
    )]
    public function destroy(Request $request, int|string $id, int|string $ticketId): JsonResponse
    {
        $proposal = Proposal::findOrFail($id);
        abort_unless($request->user()->hasRole('pengurus_tefa'), 403, 'Hanya pengurus TeFa yang dapat menghapus tiket.');

        $ticket = MaintenanceTicket::where('proposal_id', $proposal->id)->findOrFail($ticketId);
        $ticket->delete();

        return $this->ok('Tiket dihapus.');
    }

    private function authorizeAccess(Proposal $proposal, Request $request): void
    {
        $user = $request->user();
        if ($user->hasRole('klien') && $proposal->client_id !== $user->id) {
            abort(403, 'Akses ditolak.');
        }
    }
}
