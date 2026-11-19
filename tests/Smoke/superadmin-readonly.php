<?php
// Explicit read-only smoke check against the configured local commercial database.
// Never invokes migrations, login persistence, mail delivery or write endpoints.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Admin\SuperadminWorkspaceController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};
use Illuminate\Support\{MessageBag, ViewErrorBag};

DB::connection()->beforeExecuting(function ($query) {
    if (!preg_match('/^\s*(select|show|describe|explain)\b/i', $query)) {
        throw new RuntimeException('Read-only check blocked a database write.');
    }
});
if (DB::selectOne('SELECT DATABASE() AS db')->db !== 'ar_menu_equipo') {
    throw new RuntimeException('Expected local ar_menu_equipo database.');
}
config(['session.driver'=>'array','cache.default'=>'array']);
$session = app('session')->driver('array');
$session->start();
$admin = User::with('roles')->get()->first(fn ($user) => $user->isSuperAdministrator());
if (!$admin) throw new RuntimeException('No existing Superadministrator available.');
Auth::setUser($admin);
app('url')->forceRootUrl('http://localhost');
app('view')->share('errors', new ViewErrorBag);
$controller = app(SuperadminWorkspaceController::class);
$directory = storage_path('app/private/superadmin-preview');
if (!is_dir($directory)) mkdir($directory, 0777, true);

$makeRequest = function (array $query) use ($app, $admin, $session) {
    $request = Request::create('http://localhost/administracion/general', 'GET', $query);
    $request->setLaravelSession($session);
    $request->setUserResolver(fn () => $admin);
    $route = app('router')->getRoutes()->match($request);
    $request->setRouteResolver(fn () => $route);
    $app->instance('request', $request);
    app('url')->setRequest($request);
    return $request;
};
$modules = ['panel','solicitudes','clientes','sedes','usuarios','configuracion'];
foreach ($modules as $module) {
    $request = $makeRequest(['modulo'=>$module]);
    $view = $controller->index($request);
    $html = $view->render();
    if (!str_contains($html, 'data-module="'.$module.'"')) throw new RuntimeException('Missing module '.$module);
    if (!str_contains($html, 'data-feed="http://localhost/administracion/general/notificaciones"')) throw new RuntimeException('Wrong notification feed');
    // Offline preview: convert only public assets, keeping application links intact.
    $html = preg_replace_callback('~(src|href)="http://localhost/([^"?]+)(?:\?[^\"]*)?"~', function ($m) {
        $path = public_path(rawurldecode($m[2]));
        return is_file($path) ? $m[1].'="file:///'.str_replace('\\','/',$path).'"' : $m[0];
    }, $html);
    // Preview intentionally never contacts operational endpoints or marks notifications read.
    $html = str_replace('<head>', '<head><script>window.fetch=async()=>({ok:true,json:async()=>({items:[],unread:0})});</script>', $html);
    file_put_contents($directory.'/'.$module.'.html', $html);
    echo 'OK render '.$module.'; rows '.($view->getData()['rows']->total()).PHP_EOL;
}
$view = $controller->index($makeRequest(['modulo'=>'solicitudes','q'=>'__no_commercial_match_8bcf__']));
if ($view->getData()['rows']->total() !== 0 || $view->getData()['selected'] !== null) throw new RuntimeException('Request filters failed');
echo 'OK request search and empty selection'.PHP_EOL;
$request = $makeRequest(['modulo'=>'usuarios','estado'=>'Pendiente de activación']);
$view = $controller->index($request);
if ($view->getData()['rows']->getCollection()->contains(fn ($user) => !$user->mustChangePassword())) throw new RuntimeException('Activation filter failed');
echo 'OK pending activation filter'.PHP_EOL;
$generalView=$controller->index($makeRequest(['modulo'=>'usuarios','q'=>$admin->correo]));
$generalHtml=$generalView->render();
if(!str_contains($generalHtml,'user-edit-'.$admin->id_usuario) || !str_contains($generalHtml,'user-access-'.$admin->id_usuario)) throw new RuntimeException('General account controls missing');
$generalHtml=preg_replace_callback('~(src|href)="http://localhost/([^"?]+)(?:\?[^\"]*)?"~',function($m){$path=public_path(rawurldecode($m[2]));return is_file($path)?$m[1].'="file:///'.str_replace('\\','/',$path).'"':$m[0];},$generalHtml);
$generalHtml=str_replace('<head>','<head><script>window.fetch=async()=>({ok:true,json:async()=>({items:[],unread:0})});</script>',$generalHtml);
file_put_contents($directory.'/usuarios-general.html',$generalHtml);
$secondPage=$controller->index($makeRequest(['modulo'=>'usuarios','page'=>2]))->getData()['rows'];
if($secondPage->currentPage()!==2 || $secondPage->count()!==min(10,max(0,$secondPage->total()-10))) throw new RuntimeException('Pagination failed');
echo 'OK general account controls and second page'.PHP_EOL;
$feed=$controller->notifications($makeRequest([]))->getData(true);
if(!isset($feed['items'],$feed['unread'])) throw new RuntimeException('Notification feed failed');
echo 'OK notification feed (read only)'.PHP_EOL;
$session->flash('_old_input', ['_sa_drawer'=>'user-new','correo'=>'invalid']);
app('view')->share('errors',(new ViewErrorBag)->put('default', new MessageBag(['correo'=>['Correo de prueba inválido.']])));
$html = $controller->index($makeRequest(['modulo'=>'usuarios']))->render();
if (!str_contains($html,'data-reopen="user-new"') || !str_contains($html,'Correo de prueba inválido.')) throw new RuntimeException('Validation recovery failed');
echo 'OK inline validation and drawer recovery'.PHP_EOL;
$request = $makeRequest(['modulo'=>'panel']);
$request->setUserResolver(fn () => null);
try { $controller->index($request); throw new RuntimeException('Missing access control'); }
catch (Symfony\Component\HttpKernel\Exception\HttpException $error) { if ($error->getStatusCode() !== 403) throw $error; }
echo 'OK unauthorized access rejected; no database writes executed'.PHP_EOL;
