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
        'review_status',
        'review_notes',
        'submitted_by',
        'reviewed_by',
        'reviewed_at',
        'client_notes',
        'client_signoff_name',
        'signed_off_at',
    ];

    protected $casts = [
        'session_date'  => 'date',
        'reviewed_at'   => 'datetime',
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

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}