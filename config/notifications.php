<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bulk Sending
    |--------------------------------------------------------------------------
    |
    | chunk_size: Number of recipients per ProcessBulkNotificationJob.
    | Larger values reduce queue overhead but increase memory usage per job.
    |
    */

    'chunk_size' => (int) env('NOTIFICATIONS_CHUNK_SIZE', 500),

    /*
    |--------------------------------------------------------------------------
    | Idempotency
    |--------------------------------------------------------------------------
    |
    | ttl: Redis lock TTL in seconds for deduplication keys.
    | After expiry, the same idempotency_key can be reused.
    | DB UNIQUE constraint provides permanent protection regardless of TTL.
    |
    */

    'idempotency_ttl' => (int) env('NOTIFICATIONS_IDEMPOTENCY_TTL', 86400),

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker
    |--------------------------------------------------------------------------
    |
    | failure_threshold: Consecutive failures before the circuit opens.
    | cooldown_seconds: Time before a half-open probe is allowed.
    |
    | Tune based on provider SLA: aggressive (3/10s) for critical paths,
    | conservative (10/60s) for best-effort channels.
    |
    */

    'circuit_breaker' => [
        'failure_threshold' => (int) env('NOTIFICATIONS_CB_FAILURE_THRESHOLD', 5),
        'cooldown_seconds' => (int) env('NOTIFICATIONS_CB_COOLDOWN_SECONDS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiter
    |--------------------------------------------------------------------------
    |
    | max_per_second: Maximum gateway calls per second per provider.
    | Match to your provider's rate limit (e.g., Twilio: 100 msg/s).
    |
    */

    'rate_limiter' => [
        'max_per_second' => (int) env('NOTIFICATIONS_RATE_LIMIT', 100),
    ],

];
