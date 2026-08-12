<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class PersonaService
{
    protected string $apiKey;
    protected string $baseUrl;
    protected string $templateId;

    public function __construct()
    {
        $this->apiKey = config('persona.api_key') ?: throw new \RuntimeException(
            'Missing PERSONA_API_KEY in environment configuration.'
        );

        $this->baseUrl = config('persona.base_url') ?: 'https://api.withpersona.com/api/v1';

        $this->templateId = config('persona.template_id') ?: throw new \RuntimeException(
            'Missing PERSONA_TEMPLATE_ID in environment configuration.'
        );
    }

    /**
     * Create an Inquiry for a claimant.
     *
     * Mirrors the same pattern used for any third-party API integration:
     * external call -> retry on transient failure -> idempotency key so a
     * retry never creates a duplicate resource on Persona's side.
     */
    public function createInquiry(string $claimantId, array $prefill = []): array
    {
        // Idempotency-Key ties this specific claim attempt to one Inquiry.
        // If our request times out and we retry, Persona returns the same
        // Inquiry instead of creating a second one.
        Log::info($claimantId);
        $idempotencyKey = "inquiry-{$claimantId}-" . Str::uuid();

        $response = Http::withToken($this->apiKey)
            ->withHeaders([
                'Idempotency-Key' => $idempotencyKey,
            ])
            ->retry(config('persona.max_retries', 3), 500, function ($exception, $request) {
                // Only retry on connection issues / 5xx — never retry a 4xx,
                // since that means our request itself was invalid.
                return $exception instanceof \Illuminate\Http\Client\ConnectionException
                    || (method_exists($exception, 'response') && $exception->response?->serverError());
            })
            ->post("{$this->baseUrl}/inquiries", [
                'data' => [
                    'attributes' => array_merge([
                        'inquiry-template-id' => $this->templateId,
                        'reference-id' => $claimantId, // links Inquiry back to our claim record
                    ], $prefill),
                ],
            ]);

        if ($response->failed()) {
            Log::error('Persona inquiry creation failed', [
                'claimant_id' => $claimantId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $response->throw();
        }

        return $response->json('data');
    }

    /**
     * Fetch the latest state of an Inquiry — used if a claimant asks for
     * status and we want to double check rather than trust a stale webhook.
     */
    public function getInquiry(string $inquiryId): array
    {
        $response = Http::withToken($this->apiKey)
            ->retry(config('persona.max_retries', 3), 500)
            ->get("{$this->baseUrl}/inquiries/{$inquiryId}");

        $response->throw();

        return $response->json('data');
    }
}