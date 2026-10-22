<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// En el hosting la app vive fuera de la carpeta publica:
//   /home/penguin2/tracking-forcex-app   (codigo, .env, storage, vendor)
//   /home/penguin2/gsc-business.com      (esta carpeta: index.php, build, images, flags)
$appRoot = __DIR__.'/../tracking-forcex-app';

if (file_exists($maintenance = $appRoot.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $appRoot.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $appRoot.'/bootstrap/app.php';

// La carpeta publica es esta (Vite lee aqui build/manifest.json; aqui estan images y flags).
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
