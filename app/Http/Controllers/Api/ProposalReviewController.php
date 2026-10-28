<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proposal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class ProposalReviewController extends Controller
{
    #[OA\Patch(
        path: '/proposals/{id}/review',
        summary: 'Review proposal (Pengurus TeFa)',
        description: 'Melakukan review kelayakan proposal. Hanya bisa dilakukan saat proposal berstatus Submitted (2) atau Under Review (3). Pengurus TeFa mengisi analisis kompleksitas, risiko, estimasi biaya, dan keputusan (approved / rejected / revision_required).',
        security: [['bearerAuth' => []]],
        tags: ['F-02: Review & Validasi'],
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
                required: ['complexity_level', 'risk_analysis', 'estimated_cost', 'decision'],
                properties: [
                    new OA\Property(property: 'complexity_level', type: 'string', enum: ['LOW', 'MEDIUM', 'HIGH'], example: 'HIGH'),
                    new OA\Property(property: 'risk_analysis', type: 'string', example: 'Risiko integrasi API pihak ketiga cukup tinggi, perlu tim berpengalaman.'),
                    new OA\Property(property: 'estimated_cost', type: 'number', format: 'double', example: 25000000),
                    new OA\Property(property: 'review_notes', type: 'string', nullable: true, example: 'Proposal layak dengan catatan revisi scope modul keuangan.'),
                    new OA\Property(property: 'decision', type: 'string', enum: ['approved', 'rejected', 'revision_required'], example: 'approved'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Review berhasil',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Review submitted successfully'),
                        new OA\Property(property: 'data', ref: '#/components/schemas/Proposal'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden (bukan pengurus_tefa)'),
            new OA\Response(response: 404, description: 'Proposal tidak ditemukan'),
            new OA\Response(response: 422, description: 'Proposal tidak dalam status yang bisa di-review / validasi gagal'),
        ]
    )]
    public function review(Request $request, $id)
    {
        $proposal = Proposal::findOrFail($id);

        if (! in_array($proposal->status_id, [2, 3])) {
            return response()->json([
                'message' => 'Proposal tidak dalam status yang bisa di-review.',
            ], 422);
        }

        $validated = $request->validate([
            'decision'         => 'required|in:approved,rejected,revision_required',
            'complexity_level' => 'required_if:decision,approved|nullable|in:LOW,MEDIUM,HIGH',
            'risk_analysis'    => 'required_if:decision,approved|nullable|string',
            'estimated_cost'   => 'required_if:decision,approved|nullable|numeric',
            'review_notes'     => 'required_if:decision,revision_required|nullable|string',
        ]);

        $statusMap = [
            'approved'          => 5,
            'rejected'          => 6,
            'revision_required' => 4,
        ];
        $newStatusId = $statusMap[$validated['decision']];

        $updateData = [
            'status_id'    => $newStatusId,
            'reviewer_id'  => $request->user()->id,
            'reviewed_at'  => now(),
            'review_notes' => $validated['review_notes'] ?? $proposal->review_notes,
        ];

        if (array_key_exists('complexity_level', $validated)) {
            $updateData['complexity_level'] = $validated['complexity_level'];
        }
        if (array_key_exists('risk_analysis', $validated)) {
            $updateData['risk_analysis'] = $validated['risk_analysis'];
        }
        if (array_key_exists('estimated_cost', $validated)) {
            $updateData['estimated_cost'] = $validated['estimated_cost'];
        }

        DB::transaction(function () use ($proposal, $updateData, $newStatusId, $validated, $request) {
            $proposal->update($updateData);

            $proposal->statusHistories()->create([
                'status_id'  => $newStatusId,
                'note'       => $validated['review_notes'] ?? null,
                'changed_by' => $request->user()->id,
            ]);
        });

        return response()->json([
            'message' => 'Review submitted successfully',
            'data'    => $proposal->fresh(['status', 'reviewer']),
        ]);
    }

    #[OA\Patch(
        path: '/proposals/{id}/status',
        summary: 'Update status proposal (Pengurus TeFa)',
        description: 'Override manual status proposal oleh pengurus TeFa. Setiap perubahan status tercatat di history.',
        security: [['bearerAuth' => []]],
        tags: ['F-02: Review & Validasi'],
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
                required: ['status_id'],
                properties: [
                    new OA\Property(
                        property: 'status_id',
                        type: 'integer',
                        description: '1=Draft, 2=Submitted, 3=Under Review, 4=Revision Required, 5=Approved, 6=Rejected, 7=Active, 8=Testing, 9=Handover, 10=Completed',
                        example: 7
                    ),
                    new OA\Property(property: 'note', type: 'string', nullable: true, example: 'Kontrak sudah ditandatangani, proyek dimulai.'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Status berhasil diupdate',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Status updated successfully'),
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
    public function updateStatus(Request $request, $id)
    {
        $proposal = Proposal::findOrFail($id);

        $validated = $request->validate([
            'status_id' => 'required|exists:proposal_statuses,id',
            'note'      => 'nullable|string',
        ]);

        DB::transaction(function () use ($proposal, $validated, $request) {
            $proposal->update(['status_id' => $validated['status_id']]);

            $proposal->statusHistories()->create([
                'status_id'  => $validated['status_id'],
                'note'       => $validated['note'] ?? null,
                'changed_by' => $request->user()->id,
            ]);
        });

        return response()->json([
            'message' => 'Status updated successfully',
            'data'    => $proposal->fresh(['status']),
        ]);
    }
}
