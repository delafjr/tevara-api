<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Proposal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProposalReviewController extends Controller
{
    // PATCH /proposals/{id}/review (pengurus_tefa)
    public function review(Request $request, $id)
    {
        $proposal = Proposal::findOrFail($id);

        // Review hanya boleh saat status Submitted (2) atau Under Review (3)
        if (! in_array($proposal->status_id, [2, 3])) {
            return response()->json([
                'message' => 'Proposal tidak dalam status yang bisa di-review.',
            ], 422);
        }

        $validated = $request->validate([
            'complexity_level' => 'required|in:LOW,MEDIUM,HIGH',
            'risk_analysis'    => 'required|string',
            'estimated_cost'   => 'required|numeric',
            'review_notes'     => 'nullable|string',
            'decision'         => 'required|in:approved,rejected,revision_required',
        ]);

        $statusMap = [
            'approved'          => 5, // Approved
            'rejected'          => 6, // Rejected
            'revision_required' => 4, // Revision Required
        ];
        $newStatusId = $statusMap[$validated['decision']];

        DB::transaction(function () use ($proposal, $validated, $newStatusId, $request) {
            $proposal->update([
                'complexity_level' => $validated['complexity_level'],
                'risk_analysis'    => $validated['risk_analysis'],
                'estimated_cost'   => $validated['estimated_cost'],
                'review_notes'     => $validated['review_notes'] ?? null,
                'status_id'        => $newStatusId,
                'reviewer_id'      => $request->user()->id,
                'reviewed_at'      => now(),
            ]);

            // Catat ke timeline status
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

    // PATCH /proposals/{id}/status (pengurus_tefa) - override manual
    public function updateStatus(Request $request, $id)
    {
        $proposal = Proposal::findOrFail($id);

        $validated = $request->validate([
            'status_id' => 'required|exists:proposal_statuses,id',
            'note'      => 'nullable|string',
        ]);

        DB::transaction(function () use ($proposal, $validated, $request) {
            $proposal->update(['status_id' => $validated['status_id']]);

            // Selalu catat history, note boleh kosong
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