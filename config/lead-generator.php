<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Campaign Queue Connection
    |--------------------------------------------------------------------------
    |
    | The "background" driver starts the campaign in a separate PHP process
    | after the HTTP response is sent, so a persistent queue worker is not
    | required. Production servers using Supervisor may change this to
    | "database" or "redis".
    |
    */
    'campaign_queue_connection' => env('CAMPAIGN_QUEUE_CONNECTION', 'background'),
];
