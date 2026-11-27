<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'proposal_id',
        'invoice_number',
        'term_sequence',
        'payment_type',
        'description',
        'amount',
        'due_date',
        'payment_date',
        'payment_method',
        'transfer_reference',
        'proof_url',
        'verified_by',
        'verified_at',
        'verification_notes',
        'status',
    ];

    protected $casts = [
        'due_date'     => 'date',
        'payment_date' => 'date',
        'verified_at'  => 'datetime',
        'amount'       => 'decimal:2',
    ];

    public function getProofUrlAttribute(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        return asset('storage/' . ltrim($value, '/'));
    }

    public function proposal()
    {
        return $this->belongsTo(Proposal::class);
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}

