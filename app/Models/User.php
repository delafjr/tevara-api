<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone_number',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    public function isPm(): bool
    {
        return $this->relationLoaded('managedProposals')
            ? $this->managedProposals->isNotEmpty()
            : $this->managedProposals()->exists();
    }

    // ── Profile extensions ─────────────────────────────

    public function clientProfile()
    {
        return $this->hasOne(ClientProfile::class);
    }

    public function clientPic()
    {
        return $this->hasOne(ClientPic::class);
    }

    public function lecturerProfile()
    {
        return $this->hasOne(LecturerProfile::class);
    }

    public function studentProfile()
    {
        return $this->hasOne(StudentProfile::class);
    }

    // ── Proposal relationships ─────────────────────────

    public function submittedProposals()
    {
        return $this->hasMany(Proposal::class, 'client_id');
    }

    public function reviewedProposals()
    {
        return $this->hasMany(Proposal::class, 'reviewer_id');
    }

    public function managedProposals()
    {
        return $this->hasMany(Proposal::class, 'pm_id');
    }

    // ── Tim ────────────────────────────────────────────

    public function projectTeams()
    {
        return $this->hasMany(ProjectTeam::class);
    }

    // ── Pembayaran ─────────────────────────────────────

    public function verifiedPayments()
    {
        return $this->hasMany(Payment::class, 'verified_by');
    }
}