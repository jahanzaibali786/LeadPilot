<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Campaign Queue Connection
    |--------------------------------------------------------------------------
    |
    | Campaigns use Laravel's database queue. Run php artisan campaigns:work
    | locally and configure the same command under Supervisor/systemd in production.
    |
    */
    'campaign_queue_connection' => env('CAMPAIGN_QUEUE_CONNECTION', 'database'),

    'stale_pending_seconds' => (int) env('CAMPAIGN_STALE_PENDING_SECONDS', 30),

    /*
    | One Google Text Search query returns at most 60 places. For larger lead
    | targets, run distinct related queries and deduplicate their Place IDs.
    */
    'google_places_max_queries' => (int) env('GOOGLE_PLACES_MAX_QUERIES', 10),
];
