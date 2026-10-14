<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UatItem extends Model
{
    protected $fillable = [
        'uat_result_id',
        'feature_name',
        'test_scenario',
        'result',
        'bug_severity',
        'bug_description',
        'client_feedback',
    ];

    public function uatResult()
    {
        return $this->belongsTo(UatResult::class);
    }
}