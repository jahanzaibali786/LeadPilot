<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('campaigns:work', function () {
    return Artisan::call('queue:work', [
        'connection' => 'database',
        '--queue' => 'default',
        '--stop-when-empty' => true,
        '--sleep' => 1,
        '--tries' => 2,
        '--timeout' => 180,
    ], $this->output);
})->purpose('Process queued LeadPilot campaign jobs and stop when empty');
