<?php

use App\Http\Controllers\PersonaWebhookController;
use App\Models\Claim;
use App\Services\PersonaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::get('/', function () {
    return view('welcome');
});

// Kick off verification for a claimant. In the real system this would be
// triggered from the claims-intake flow, not a raw GET.

Route::post('/claims/{claim}/start-verification', function (Request $request, Claim $claim, PersonaService $persona) {
    $inquiry = $persona->createInquiry(claimantId: (string) $claim->id, prefill: [
        'email-address' => $request->input('email'),
    ]);

    // Save the Inquiry ID so the webhook handler can look this claim back up
    // when Persona posts the verification result.
    $claim->update(['persona_inquiry_id' => $inquiry['id']]);

    return response()->json($inquiry);
});

// Persona POSTs verification results here. Exempt from CSRF (see bootstrap/app.php).
Route::post('/webhooks/persona', [PersonaWebhookController::class, 'handle']);