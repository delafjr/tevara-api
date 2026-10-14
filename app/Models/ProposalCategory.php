<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProposalCategory extends Model
{
    protected $fillable = ['name'];

    public function proposals()
    {
        return $this->hasMany(Proposal::class, 'category_id');
    }
}