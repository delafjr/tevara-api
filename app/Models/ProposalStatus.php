<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProposalStatus extends Model
{
    protected $fillable = ['name'];

    public function proposals()
    {
        return $this->hasMany(Proposal::class, 'status_id');
    }
}