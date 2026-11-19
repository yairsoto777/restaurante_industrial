<?php
// One isolated functional test on the existing demonstration request.
// All updates are rolled back; no requests, clients or users are created.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Admin\{CommercialContactController, SuperadminWorkspaceController};
use App\Http\Middleware\EnsureUserHasRole;
use App\Models\{CommercialContact, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;

$check = function (bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
};
$check(DB::selectOne('SELECT DATABASE() AS db')->db === 'ar_menu_equipo', 'Unexpected database.');
$check(strtolower(DB::selectOne("SHOW TABLE STATUS LIKE 'contactos'")->Engine) === 'innodb', 'Transactional table required.');
config(['session.driver'=>'array','cache.default'=>'array']);
$session = app('session')->driver('array');
$session->start();
$admin = User::with('roles')->get()->first(fn ($user) => $user->isSuperAdministrator());
$check($admin !== null, 'Existing Superadministrator required.');
Auth::setUser($admin);
app('view')->share('errors', new ViewErrorBag);
$controller = app(CommercialContactController::class);
$roleGate = app(EnsureUserHasRole::class);
$makeRequest = function (array $data, string $method='PATCH') use ($app,$session,$admin) {
    $url = $method==='GET' ? 'http://localhost/administracion/general' : 'http://localhost/administracion/contactos/910003';
    $request = Request::create($url,$method,$data);
    $request->headers->set('referer','http://localhost/administracion/general?modulo=solicitudes&seleccion=910003');
    $request->setLaravelSession($session);
    $request->setUserResolver(fn () => $admin);
    $route = app('router')->getRoutes()->match($request);
    $request->setRouteResolver(fn () => $route);
    $app->instance('request',$request);
    app('url')->setRequest($request);
    return $request;
};
$before = (array)DB::table('contactos')->where('id_contacto',910003)->first();
$check(!empty($before), 'Request #910003 not found.');
$counts = ['contactos'=>DB::table('contactos')->count(),'clientes'=>DB::table('clientes')->count()];
$request = $makeRequest([]);
$check(in_array('role:super-administrator',$request->route()->gatherMiddleware(),true), 'Superadministrator middleware missing.');
$request->setUserResolver(fn () => null);
try {
    $roleGate->handle($request,fn () => throw new RuntimeException('Unauthorized update permitted.'),'super-administrator');
    throw new RuntimeException('Expected HTTP 403.');
} catch (Symfony\Component\HttpKernel\Exception\HttpException $error) {
    $check($error->getStatusCode()===403,'Unexpected authorization response.');
}

DB::beginTransaction();
try {
    $contact = CommercialContact::whereKey(910003)->lockForUpdate()->firstOrFail();
    $check(in_array($contact->estado,['nuevo','contactado','rechazado'],true),'Request already converted; test must not change approved records.');
    $captured = ($contact->observaciones_respuesta ?? '')."\r\nSeguimiento de comprobación aislada.\r\n\r\n\r\nSiguiente paso: Marea de Coral Centro. <dato literal>";
    $expected = str_replace(["\r\n","\r"],"\n",$captured);
    foreach (['nuevo','contactado','rechazado'] as $state) {
        $request = $makeRequest(['estado'=>$state,'observaciones_respuesta'=>$captured]);
        $response = $roleGate->handle($request,fn ($r) => $controller->update($r,$contact),'super-administrator');
        $check($response->getStatusCode()===302,'Follow-up did not redirect.');
        $stored = CommercialContact::findOrFail(910003);
        $check($stored->estado===$state && $stored->observaciones_respuesta===$expected,'Saved state or multiline text differs.');
        $check((int)$stored->id_superadmin_responde===(int)$admin->id_usuario,'Responder not saved.');
        if ($state!=='nuevo') $check($stored->fecha_respuesta!==null,'Response date missing.');
        $view = app(SuperadminWorkspaceController::class)->index($makeRequest(['modulo'=>'solicitudes','seleccion'=>910003],'GET'));
        $selected = $view->getData()['selected'];
        $check($selected->estado===$state && $selected->observaciones_respuesta===$expected,'Reopened request differs from database.');
        $check(str_contains($view->render(),'&lt;dato literal&gt;'),'Captured text not safely escaped in view.');
        echo 'OK #910003: '.$state.', SQL save, multiline content and reopened request.'.PHP_EOL;
        $contact = $stored;
    }
    // Omitting observations must leave the existing text untouched.
    $request = $makeRequest(['estado'=>'contactado']);
    $roleGate->handle($request,fn ($r) => $controller->update($r,$contact),'super-administrator');
    $check(CommercialContact::findOrFail(910003)->observaciones_respuesta===$expected,'Omitted notes were lost.');
    $request = $makeRequest(['estado'=>'aprobado']);
    try { $controller->update($request,$contact); throw new RuntimeException('Direct approval allowed.'); }
    catch (ValidationException $error) { $check(isset($error->errors()['estado']),'Approval guard failed.'); }
    $approved = clone $contact;
    $approved->estado = 'aprobado';
    try { $controller->update($makeRequest(['estado'=>'nuevo']),$approved); throw new RuntimeException('Approved rollback allowed.'); }
    catch (ValidationException $error) { $check(isset($error->errors()['estado']),'Approved-state guard failed.'); }
    echo 'OK role authorization, retained notes and existing approval rules.'.PHP_EOL;
} finally {
    DB::rollBack();
}
$after = (array)DB::table('contactos')->where('id_contacto',910003)->first();
$check($before===$after,'Original request was not restored exactly.');
$check(DB::table('contactos')->where('id_contacto',910003)->count()===1,'Request missing or duplicated.');
foreach ($counts as $table=>$count) $check(DB::table($table)->count()===$count,'Unexpected row count change.');
echo 'OK rollback: #910003 intact, original fields unchanged; no requests or clients created.'.PHP_EOL;
