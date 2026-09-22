# Persona KYC Sandbox Integration — Demo

A minimal Laravel integration with Persona's Inquiry API, built to have
something real and working for the interview — not just talking points.

## What this demonstrates

- **Outbound API call with retries + idempotency** (`PersonaService::createInquiry`)
  — same pattern as any payment gateway or third-party API integration:
  call out, retry transient failures, never double-create on retry.
- **Inbound webhook handling** (`PersonaWebhookController`)
  — signature verification (HMAC-SHA256, constant-time compare), duplicate-event
  protection (Persona explicitly documents at-least-once delivery), and
  routing pass/fail/needs-review outcomes back onto the claim record.
- **PII discipline** — only the Persona `reference-id` and inquiry status are
  stored locally; raw ID documents/selfies never touch our servers, they stay
  in Persona. Webhook payloads are logged for audit but this table would be
  access-restricted and encrypted at rest in production.

## Current status (last verified 2026-08-13)

- **Outbound inquiry creation — working end-to-end.** Tested against the live
  Persona sandbox (not mocked): `POST /claims/{id}/start-verification` creates
  a real Inquiry and stores its `inq_...` ID on the claim.
- **Webhook receipt, signature verification, idempotency — working end-to-end.**
  Confirmed via real sandbox deliveries: `inquiry.created` → `inquiry.started` →
  `inquiry.approved` all arrived, HMAC signatures verified, and each event was
  logged exactly once in `webhook_events` (7 events logged, no duplicate
  processing observed even on redelivery).
- **Known bug — claim status is never actually updated.** `verification_status`
  stays `pending` even after a confirmed `inquiry.approved` webhook.
  `PersonaWebhookController::applyToClaimRecord()` looks for the inquiry ID at
  `data.relationships.inquiry.data.id` (falling back to `data.id`, which is
  the *event* ID), but Persona actually nests it at
  `data.attributes.payload.data.id`. `Claim::where('persona_inquiry_id', ...)`
  never matches, so the claim lookup silently misses and only
  `Log::warning('Received Persona webhook for unknown inquiry', ...)` fires.
  **Fix this path before demoing the "updates a claim record" step live** —
  everything up to that point is real and working, this is the one gap.

## Setup (do this before Thursday)

1. Sign up for a free sandbox account at https://withpersona.com
2. In the Dashboard, create an Inquiry Template with Government ID + Selfie
   verification enabled. Copy the Template ID.
3. Under Webhooks, create a webhook pointing at your local tunnel
   (e.g. `ngrok http 8000` → `https://xxxx.ngrok.io/webhooks/persona`).
   Copy the signing secret shown once at creation.
4. Copy `.env.example` values into your `.env`:
   ```
   PERSONA_API_KEY=sandbox_xxx
   PERSONA_TEMPLATE_ID=itmpl_xxx
   PERSONA_WEBHOOK_SECRET=whsec_xxx
   ```
5. `php artisan migrate`
6. Exempt `/webhooks/persona` from CSRF in `bootstrap/app.php`:
   ```php
   ->withMiddleware(function (Middleware $middleware) {
       $middleware->validateCsrfTokens(except: ['webhooks/persona']);
   })
   ```
7. Trigger a test inquiry, complete it in the hosted flow with Persona's
   test documents, and confirm the webhook lands and updates a claim record.

## Talking through it live

Walk the interviewer through: `POST /claims/{id}/start-verification` →
`PersonaService` calls the Inquiries API with an idempotency key → claimant
completes the hosted flow → Persona POSTs to `/webhooks/persona` →
signature verified → event logged (idempotent) → claim status updated.

That end-to-end loop, built and running, is worth more than any rehearsed
answer about "experience with Persona."
