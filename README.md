# Persona KYC Sandbox Integration — Demo

A Laravel backend integration with [Persona](https://withpersona.com)'s
Identity Verification (Inquiry) API — built as a working demo of KYC/identity
verification integration patterns: outbound API calls with retry/idempotency,
inbound webhook handling with signature verification, and safe PII handling.

## Tech stack

- **PHP / Laravel** (Eloquent, HTTP client, migrations)
- **Persona API** — hosted Inquiry flow for government ID + selfie verification
- **SQLite** (demo) — `claims` and `webhook_events` tables
- **ngrok** — local tunnel for receiving webhooks in dev

## How the demo works

The flow is a standard outbound-call / hosted-flow / webhook-callback loop,
the same shape used to integrate any third-party verification or payment
provider:

1. **`POST /claims/{id}/start-verification`** — our backend calls Persona's
   `POST /inquiries` API (`PersonaService::createInquiry`) with an
   `Idempotency-Key` header, so a network retry can never create a duplicate
   Inquiry. The response's Inquiry ID (`inq_...`) is saved on the `claims`
   row via `persona_inquiry_id`.
2. **Claimant completes verification** in Persona's hosted flow (a webpage
   Persona serves — we never see or store the raw ID photo or selfie).
3. **Persona POSTs webhook events** to `/webhooks/persona` as the Inquiry
   progresses: `inquiry.created` → `inquiry.started` → `inquiry.approved`
   (or `.declined` / `.failed`).
4. **`PersonaWebhookController::handle`** verifies the `Persona-Signature`
   header (HMAC-SHA256 over `{timestamp}.{rawBody}`, constant-time compare),
   then checks the event ID against the `webhook_events` table — Persona
   documents at-least-once delivery, so duplicates are detected and skipped
   rather than reprocessed.
5. **`applyToClaimRecord`** looks up the `claims` row by `persona_inquiry_id`
   and flips `verification_status` to `verified` / `needs_resubmission` /
   `failed` based on the event type. *(See "Current status" below — this
   last step currently has a bug.)*

### Endpoints

| Method | Path | Purpose |
|---|---|---|
| `POST` | `/claims/{claim}/start-verification` | Creates a Persona Inquiry for a claim and stores the Inquiry ID |
| `POST` | `/webhooks/persona` | Receives Persona's verification-result callbacks (CSRF-exempt, signature-verified) |

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
  Fix: read the inquiry ID from `data.attributes.payload.data.id` in
  `applyToClaimRecord()`.

## Setup

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
   test documents, and confirm the webhook lands (see "Current status" for
   the claim-update caveat).
