<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectRole extends Model
{
    protected $fillable = ['name'];

    public function projectTeams()
    {
        return $this->hasMany(ProjectTeam::class, 'role_id');
    }
}