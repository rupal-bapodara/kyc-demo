<?php

namespace App\Http\Controllers;

use App\Models\WebhookEvent;
use App\Models\Claim;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PersonaWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $signatureHeader = $request->header('Persona-Signature');

        if (! $signatureHeader || ! $this->verifySignature($request->getContent(), $signatureHeader)) {
            Log::warning('Persona webhook signature verification failed');
            return response()->json(['error' => 'invalid signature'], 401);
        }

        $payload = $request->json()->all();
        $eventId = $payload['data']['id'] ?? null;
        $eventType = $payload['data']['attributes']['name'] ?? null; // e.g. inquiry.approved

        if (! $eventId) {
            return response()->json(['error' => 'missing event id'], 400);
        }

        // Idempotency on the receiving end: Persona explicitly documents that
        // webhooks may be delivered more than once. We log processed event
        // IDs and skip anything we've already handled.
        $alreadyProcessed = WebhookEvent::where('persona_event_id', $eventId)->exists();

        if ($alreadyProcessed) {
            return response()->json(['status' => 'already processed'], 200);
        }

        WebhookEvent::create([
            'persona_event_id' => $eventId,
            'event_type' => $eventType,
            'payload' => $payload,
        ]);

        $this->applyToClaimRecord($payload, $eventType);

        return response()->json(['status' => 'ok'], 200);
    }

    /**
     * Verify the Persona-Signature header per Persona's documented scheme:
     * header is "t=<timestamp>,v1=<hmac>" (space-separated pairs during
     * secret rotation). HMAC-SHA256 is computed over "{timestamp}.{rawBody}"
     * using the webhook secret, and must be compared in constant time.
     */
    protected function verifySignature(string $rawBody, string $signatureHeader): bool
    {
        $secret = config('persona.webhook_secret');
        $pairs = explode(' ', $signatureHeader); // handles secret-rotation case

        foreach ($pairs as $pair) {
            [$tPart, $vPart] = explode(',', $pair);
            $timestamp = explode('=', $tPart, 2)[1] ?? null;
            $signature = explode('=', $vPart, 2)[1] ?? null;

            if (! $timestamp || ! $signature) {
                continue;
            }

            $expected = hash_hmac('sha256', "{$timestamp}.{$rawBody}", $secret);

            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Update the claim's verification status based on the event.
     * Failure handling: on decline, flag the claim for claimant resubmission
     * rather than silently failing the claim outright.
     */
    protected function applyToClaimRecord(array $payload, ?string $eventType): void
    {
        $inquiryId = $payload['data']['attributes']['payload']['data']['id'] ?? null;

        $claim = Claim::where('persona_inquiry_id', $inquiryId)->first();

        if (! $claim) {
            Log::warning('Received Persona webhook for unknown inquiry', ['inquiry_id' => $inquiryId]);
            return;
        }

        match ($eventType) {
            'inquiry.approved' => $claim->markVerified(),
            'inquiry.declined' => $claim->markNeedsResubmission(),
            'inquiry.failed' => $claim->markVerificationFailed(),
            default => Log::info('Unhandled Persona event type', ['type' => $eventType]),
        };
    }
}
