<?php

return [
    // Sandbox vs production key — Persona sandbox keys start with "sandbox_",
    // production keys start with "prod_" or "live_". Never hardcode; always .env.
    'api_key' => env('PERSONA_API_KEY'),

    'base_url' => env('PERSONA_BASE_URL', 'https://api.withpersona.com/api/v1'),

    // The Inquiry Template you configure in the Persona dashboard
    // (government ID + selfie + database checks, per the KYC flow).
    'template_id' => env('PERSONA_TEMPLATE_ID'),

    // Webhook signing secret, shown once when you create the webhook
    // in the Persona dashboard. Used to verify Persona-Signature header.
    'webhook_secret' => env('PERSONA_WEBHOOK_SECRET'),

    // How many times to retry a failed outbound API call before giving up.
    'max_retries' => 3,
];