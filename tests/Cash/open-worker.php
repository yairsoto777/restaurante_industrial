<?php

// Only launched by the isolated MariaDB concurrency test. No production fallback.
ini_set('display_errors', '0');
ini_set('log_errors', '0');
require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Http\Controllers\CashController;
use App\Models\User;
use App\Support\SiteContext;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\Cash\CashTestCase;

try {
    if (getenv('CASH_TEST_ALLOW_MYSQL') !== '1' || ! preg_match('/\Agi_cash_test_[a-f0-9]{16}\z/', getenv('CASH_TEST_DATABASE') ?: '')) {
        exit(2);
    }
    $bootstrap = new class('worker') extends CashTestCase {};
    $app = $bootstrap->createApplication();
    $request = Request::create('/caja/aperturas', 'POST', ['fondo_inicial' => '10.00']);
    $session = $app->make('session')->driver();
    $session->start();
    $session->put('current_site_id', (int) $argv[2]);
    $request->setLaravelSession($session);
    $user = User::findOrFail((int) $argv[1]);
    $app->instance('request', $request);
    $request->setUserResolver(fn () => $user);
    fwrite(STDOUT, "READY\n");
    fflush(STDOUT);
    try {
        $app->make(CashController::class)->open($request, $app->make(SiteContext::class));
        echo "OPENED\n";
    } catch (ValidationException $exception) {
        if (($exception->errors()['apertura'][0] ?? null) !== 'Ya tienes una caja abierta en esta sede. Consulta el turno vigente.') {
            throw $exception;
        }
        echo "DUPLICATE\n";
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Isolated concurrency worker failed: '.get_class($exception).' ('.$exception->getCode().").\n");
    exit(1);
}
