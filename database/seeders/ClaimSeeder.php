<?php

namespace Database\Seeders;

use App\Models\Claim;
use Illuminate\Database\Seeder;

class ClaimSeeder extends Seeder
{
    public function run(): void
    {
        $dummyClaims = [
            [
                'claimant_email' => 'jane.doe@example.com',
                'verification_status' => 'pending',
            ],
            [
                'claimant_email' => 'john.smith@example.com',
                'verification_status' => 'pending',
            ],
            [
                'claimant_email' => 'maria.garcia@example.com',
                // Simulates a claim that's already been through Persona once,
                // useful for testing the webhook handler without re-running
                // the whole Inquiry flow.
                'persona_inquiry_id' => 'inq_dummy00000000000000000001',
                'verification_status' => 'pending',
            ],
        ];

        foreach ($dummyClaims as $claim) {
            Claim::create($claim);
        }
    }
}
