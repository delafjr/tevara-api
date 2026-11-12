<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LecturerProfile extends Model
{
    protected $fillable = ['user_id', 'nidn', 'department'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}