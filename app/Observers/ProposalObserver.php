<?php

namespace App\Observers;

use App\Models\Proposal;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProposalObserver
{
    public function creating(Proposal $proposal)
    {
        $year   = date('Y');
        $prefix = "PRSL-{$year}-";

        $query = Proposal::query();
        // Hitung juga data soft-deleted supaya kode tidak bentrok
        if (in_array(SoftDeletes::class, class_uses_recursive(Proposal::class))) {
            $query->withTrashed();
        }

        $last = $query->where('proposal_code', 'like', $prefix . '%')
            ->orderByDesc('proposal_code')
            ->lockForUpdate()
            ->first();

        $sequence = $last ? intval(substr($last->proposal_code, -4)) + 1 : 1;

        // Auto-generate proposal_code: PRSL-{Y}-{SEQ}
        $proposal->proposal_code = $prefix . str_pad($sequence, 4, '0', STR_PAD_LEFT);

        // Status awal: Submitted (id=2)
        $proposal->status_id = 2;

        // Fallback client_id dari user login (controller sudah set eksplisit)
        if (empty($proposal->client_id) && auth()->check()) {
            $proposal->client_id = auth()->id();
        }
    }

    public function created(Proposal $proposal)
    {
        // Catatan pertama di timeline status
        $proposal->statusHistories()->create([
            'status_id'  => $proposal->status_id,
            'note'       => 'Proposal diajukan',
            'changed_by' => $proposal->client_id,
        ]);
    }
}