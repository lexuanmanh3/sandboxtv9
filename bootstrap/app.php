<?php

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
|
| The first thing we will do is create a new Laravel application instance
| which serves as the "glue" for all the components of Laravel, and is
| the IoC container for the system binding all of the various parts.
|
*/

$app = new Illuminate\Foundation\Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
);

/*
|--------------------------------------------------------------------------
| Bind Important Interfaces
|--------------------------------------------------------------------------
|
| Next, we need to bind some important interfaces into the container so
| we will be able to resolve them when needed. The kernels serve the
| incoming requests to this application from both the web and CLI.
|
*/

$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    App\Http\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);

/*
|--------------------------------------------------------------------------
| Return The Application
|--------------------------------------------------------------------------
|
| This script returns the application instance. The instance is given to
| the calling script so we can separate the building of the instances
| from the actual running of the application and sending responses.
|
*/

// Workaround cho Hostinger shared hosting: composer dump-autoload khong the
// chay do thieu bien moi truong HOME, nen cac class moi them vao App\Models
// khong tu dong load qua PSR-4. Require truc tiep file model o day de dam
// bao class B2bIpRejection (va cac class tuong tu trong tuong lai) luon san
// sang truoc khi Laravel boot kernel va compile Blade view.
//
// Ly do ky thuat: Laravel compile view qua ViewServiceProvider, khi gap
// @php \App\Models\B2bIpRejection::... trong blade, PSR-4 autoloader cua
// composer can class nay trong classmap. Tren moi truong dev/local,
// `composer dump-autoload` tu dong cap nhat classmap. Tren Hostinger shared
// hosting, lenh nay that bai vi composer can HOME env var (khong duoc thiet
// lap mac dinh), nen classmap bi "dong bang" va class moi khong duoc load.
//
// TODO: Khi chuyen sang VPS/co quyen SSH, chay:
//     composer dump-autoload --optimize
// roi xoa dong require_once ben duoi.
require_once __DIR__ . '/../app/Models/B2bIpRejection.php';

return $app;
