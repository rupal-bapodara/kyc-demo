<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Claim extends Model
{
    protected $fillable = [
        'claimant_email',
        'persona_inquiry_id',
        'verification_status',
    ];

    // Possible verification_status values: pending, verified, needs_resubmission, failed

    public function markVerified(): void
    {
        $this->update(['verification_status' => 'verified']);
    }

    public function markNeedsResubmission(): void
    {
        $this->update(['verification_status' => 'needs_resubmission']);
    }

    public function markVerificationFailed(): void
    {
        $this->update(['verification_status' => 'failed']);
    }
}
