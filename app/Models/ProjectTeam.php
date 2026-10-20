<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectTeam extends Model
{
    public $timestamps = false;
    protected $fillable = ['proposal_id', 'user_id', 'role_id', 'joined_at'];

    protected $casts = [
        'joined_at' => 'date',
    ];

    public function proposal()
    {
        return $this->belongsTo(Proposal::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function role()
    {
        return $this->belongsTo(ProjectRole::class, 'role_id');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function uatResults()
    {
        return $this->hasMany(UatResult::class);
    }
}