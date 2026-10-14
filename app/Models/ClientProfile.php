<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientProfile extends Model
{
    protected $fillable = ['user_id', 'address', 'client_type', 'signature_url'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function pics()
    {
        return $this->hasMany(ClientPic::class);
    }
}