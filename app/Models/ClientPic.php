<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientPic extends Model
{
    protected $fillable = ['client_profile_id', 'user_id', 'position'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function clientProfile()
    {
        return $this->belongsTo(ClientProfile::class);
    }
}