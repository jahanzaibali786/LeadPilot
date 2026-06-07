<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

$basePath = __DIR__;

define('LARAVEL_START', microtime(true));

try {
    if (file_exists($maintenance = $basePath.'/storage/framework/maintenance.php')) {
        require $maintenance;
    }

    require $basePath.'/vendor/autoload.php';

    /** @var Application $app */
    $app = require_once $basePath.'/bootstrap/app.php';

    $app->handleRequest(Request::capture());
} catch (Throwable $exception) {
    $logDir = $basePath.'/storage/logs';

    if (is_dir($logDir) && is_writable($logDir)) {
        error_log(
            '['.date('Y-m-d H:i:s').'] '.$exception->getMessage().PHP_EOL.$exception->getTraceAsString().PHP_EOL,
            3,
            $logDir.'/early-bootstrap.log'
        );
    }

    http_response_code(500);
    echo 'Application error. Check storage/logs/early-bootstrap.log and storage/logs/laravel.log.';
}
