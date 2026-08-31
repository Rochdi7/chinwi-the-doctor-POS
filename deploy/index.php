<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// App lives one level up, outside the web root.
$app_base = __DIR__.'/../laravel';

if (file_exists($maintenance = $app_base.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $app_base.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $app_base.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
