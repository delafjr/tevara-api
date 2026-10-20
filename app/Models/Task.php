<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $fillable = [
        'proposal_id',
        'project_team_id',
        'category_id',
        'title',
        'description',
        'priority',
        'due_date',
        'status',
        'achieved_summary',
        'blockers_summary',
        'progress_percentage',
        'report_attachment_url',
        'pm_notes',
        'pm_status',
        'pm_reviewed_at',
    ];

    protected $casts = [
        'due_date'            => 'date',
        'pm_reviewed_at'      => 'datetime',
        'progress_percentage' => 'decimal:2',
    ];

    public function proposal()
    {
        return $this->belongsTo(Proposal::class);
    }

    public function projectTeam()
    {
        return $this->belongsTo(ProjectTeam::class);
    }

    public function category()
    {
        return $this->belongsTo(TaskCategory::class, 'category_id');
    }
}