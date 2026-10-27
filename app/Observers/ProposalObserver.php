<?php

namespace App\Observers;

use App\Models\Proposal;
use App\Models\ProposalStatus;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProposalObserver
{
    public function creating(Proposal $proposal)
    {
        if (empty($proposal->proposal_code)) {
            $proposal->proposal_code = $this->generateProposalCode();
        }

        // Status awal: Submitted
        if (empty($proposal->status_id)) {
            $proposal->status_id = ProposalStatus::where('name', 'Submitted')->value('id') ?? 2;
        }

        // Fallback client_id dari user login (controller sudah set eksplisit)
        if (empty($proposal->client_id) && auth()->check()) {
            $proposal->client_id = auth()->id();
        }
    }

    private function generateProposalCode(): string
    {
        $prefix = 'PRSL-' . now()->year . '-';

        for ($attempt = 0; $attempt < 50; $attempt++) {
            $code = $prefix . str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);

            $query = Proposal::query();

            // Hitung juga data soft-deleted supaya kode tidak bentrok
            if (in_array(SoftDeletes::class, class_uses_recursive(Proposal::class))) {
                $query->withTrashed();
            }

            if (! $query->where('proposal_code', $code)->exists()) {
                return $code;
            }
        }

        throw new \RuntimeException('Gagal membuat proposal_code unik.');
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