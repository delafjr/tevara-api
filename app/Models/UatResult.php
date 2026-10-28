<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UatResult extends Model
{
    protected $fillable = [
        'proposal_id',
        'project_team_id',
        'session_date',
        'overall_result',
        'client_notes',
        'client_signoff_name',
        'signed_off_at',
    ];

    protected $casts = [
        'session_date'  => 'date',
        'signed_off_at' => 'datetime',
    ];

    public function proposal()
    {
        return $this->belongsTo(Proposal::class);
    }

    public function projectTeam()
    {
        return $this->belongsTo(ProjectTeam::class);
    }

    public function items()
    {
        return $this->hasMany(UatItem::class);
    }
}