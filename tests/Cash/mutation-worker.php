<?php

// Test-only actors against a verified disposable schema. Never a payment endpoint.
ini_set('display_errors', '0');
ini_set('log_errors', '0');
require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Http\Controllers\CashController;
use App\Models\CashSession;
use App\Models\User;
use App\Support\SiteContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Cash\CashTestCase;

try {
    if (getenv('CASH_TEST_ALLOW_MYSQL') !== '1' || ! preg_match('/\Agi_cash_test_[a-f0-9]{16}\z/', getenv('CASH_TEST_DATABASE') ?: '')) {
        exit(2);
    }
    $bootstrap = new class('worker') extends CashTestCase {};
    $app = $bootstrap->createApplication();
    $action = $argv[1];
    $user = User::findOrFail((int) $argv[2]);
    $cut = CashSession::findOrFail((int) $argv[3]);
    $data = ['revision' => $argv[4], 'motivo' => 'Prueba de concurrencia aislada', 'confirmacion' => '1', 'fondo_inicial' => $action === 'correct-two' ? '2.22' : '1.11'];
    $request = Request::create('/caja/prueba-aislada', 'POST', $data);
    $store = $app->make('session')->driver();
    $store->start();
    $store->put('current_site_id', (int) $cut->id_sede);
    $request->setLaravelSession($store);
    $app->instance('request', $request);
    $request->setUserResolver(fn () => $user);
    $controller = $app->make(CashController::class);
    $context = $app->make(SiteContext::class);
    fwrite(STDOUT, "READY\n");
    fflush(STDOUT);
    try {
        if ($action === 'payment') {
            $result = DB::transaction(function () use ($user, $cut): string {
                User::whereKey($user->getKey())->lockForUpdate()->firstOrFail();
                $locked = CashSession::whereKey($cut->getKey())->lockForUpdate()->firstOrFail();
                if ($locked->estado !== 'abierto') {
                    return 'REJECTED';
                }
                DB::table('pagos')->insert(['id_cuenta' => 999, 'id_usuario' => $user->getKey(), 'id_corte_caja' => $locked->getKey(), 'metodo_pago' => 'efectivo', 'monto' => '1.00', 'estado' => 'confirmado']);

                return 'APPLIED';
            }, 3);
            echo $result."\n";
        } else {
            match ($action) {
                'cancel' => $controller->cancelOpening($request, $cut, $context),
                'correct-one', 'correct-two' => $controller->updateFund($request, $cut, $context),
                'open' => $controller->open($request, $context),
                default => throw new RuntimeException('Unknown isolated action.'),
            };
            echo "APPLIED\n";
        }
    } catch (ValidationException $exception) {
        $message = $exception->errors()['correccion'][0] ?? $exception->errors()['apertura'][0] ?? '';
        if (! str_starts_with($message, 'El turno cambió') && ! str_starts_with($message, 'Solo se puede corregir')
            && ! str_starts_with($message, 'La apertura tiene pagos') && ! str_starts_with($message, 'Ya tienes una caja abierta')) {
            throw $exception;
        }
        echo "REJECTED\n";
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Isolated mutation worker failed: '.get_class($exception).' ('.$exception->getCode().").\n");
    exit(1);
}
