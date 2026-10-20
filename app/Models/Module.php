<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    public $timestamps = false;

    protected $fillable = ['proposal_id', 'title', 'description'];

    public function proposal()
    {
        return $this->belongsTo(Proposal::class);
    }
}