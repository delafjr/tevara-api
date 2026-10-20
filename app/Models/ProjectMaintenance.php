<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectMaintenance extends Model
{
    protected $fillable = [
        'proposal_id',
        'maintenance_warranty_months',
        'maintenance_start_date',
        'maintenance_end_date',
        'maintenance_notes',
    ];

    protected $casts = [
        'maintenance_start_date' => 'date',
        'maintenance_end_date'   => 'date',
    ];

    public function proposal()
    {
        return $this->belongsTo(Proposal::class);
    }
}