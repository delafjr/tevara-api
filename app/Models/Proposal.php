<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Proposal extends Model
{
    protected $fillable = [
        'proposal_code',
        'client_id',
        'title',
        'background',
        'target_audience',
        'category_id',
        'desired_deadline',
        'estimated_budget',
        'attachment_url',
        'reviewer_id',
        'complexity_level',
        'risk_analysis',
        'estimated_cost',
        'review_notes',
        'reviewed_at',
        'spk_number',
        'scope_of_work',
        'total_amount',
        'payment_terms_description',
        'contract_file_url',
        'is_signed',
        'signed_at',
        'project_code',
        'pm_id',
        'start_date',
        'target_completion_date',
        'actual_completion_date',
        'bast_number',
        'repository_url',
        'documentation_url',
        'user_manual_url',
        'handover_notes',
        'handover_date',
        'client_signoff_name',
        'client_approved',
        'bast_approved_at',
        'status_id',
    ];

    protected $casts = [
        'desired_deadline'       => 'date',
        'reviewed_at'            => 'datetime',
        'signed_at'              => 'datetime',
        'start_date'             => 'date',
        'target_completion_date' => 'date',
        'actual_completion_date' => 'date',
        'handover_date'          => 'date',
        'bast_approved_at'       => 'datetime',
        'is_signed'              => 'boolean',
        'client_approved'        => 'boolean',
        'estimated_budget'       => 'decimal:2',
        'estimated_cost'         => 'decimal:2',
        'total_amount'           => 'decimal:2',
    ];

    // ── Relationships ──────────────────────────────────

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function getClientCompanyNameAttribute(): string
    {
        return $this->client?->clientPic?->clientProfile?->user?->name
            ?? $this->client?->clientProfile?->user?->name
            ?? $this->client?->name
            ?? '—';
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function pm()
    {
        return $this->belongsTo(User::class, 'pm_id');
    }

    public function category()
    {
        return $this->belongsTo(ProposalCategory::class, 'category_id');
    }

    public function status()
    {
        return $this->belongsTo(ProposalStatus::class, 'status_id');
    }

    public function modules()
    {
        return $this->hasMany(Module::class);
    }

    public function projectTeams()
    {
        return $this->hasMany(ProjectTeam::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function uatResult()
    {
        return $this->hasOne(UatResult::class);
    }

    public function maintenance()
    {
        return $this->hasOne(ProjectMaintenance::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
    
    public function statusHistories()
    {
    return $this->hasMany(ProposalStatusHistory::class);
    }
}