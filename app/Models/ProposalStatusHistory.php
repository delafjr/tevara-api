<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProposalStatusHistory extends Model
{
    protected $fillable = [
        'proposal_id',
        'status_id',
        'note',
        'changed_by',
    ];

    public function proposal()
    {
        return $this->belongsTo(Proposal::class);
    }

    public function status()
    {
        return $this->belongsTo(ProposalStatus::class, 'status_id');
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}