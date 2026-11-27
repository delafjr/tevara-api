<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMaintenance extends Model
{
    protected $table = 'project_maintenances';

    protected $fillable = [
        'proposal_id',
        'type',
        'duration_months',
        'start_date',
        'end_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'duration_months' => 'integer',
        'start_date'      => 'date',
        'end_date'        => 'date',
    ];

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    public function scopeWarranty(Builder $query): Builder
    {
        return $query->where('type', 'warranty');
    }

    public function scopeMaintenance(Builder $query): Builder
    {
        return $query->where('type', 'maintenance');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
