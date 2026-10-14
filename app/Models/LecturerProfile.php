<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LecturerProfile extends Model
{
    protected $fillable = ['user_id', 'nidn'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}