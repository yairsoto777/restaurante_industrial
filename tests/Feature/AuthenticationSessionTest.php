<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AuthenticationSessionTest extends TestCase
{
    private string $sessionDirectory;
    private array $jar = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->withoutVite();
        $this->app->bind(ValidateCsrfToken::class, EnforceAuthenticationCsrf::class);
        $this->sessionDirectory = storage_path('framework/testing/auth-session-'.bin2hex(random_bytes(8)));
        mkdir($this->sessionDirectory, 0700, true);
        config(['session.driver'=>'file','session.files'=>$this->sessionDirectory,'session.lottery'=>[0,100]]);
        DB::statement('CREATE TABLE usuarios (id_usuario INTEGER PRIMARY KEY, nombre TEXT, correo TEXT, password TEXT, remember_token TEXT, temporary_password_expires_at TEXT, temporary_password_used_at TEXT)');
        DB::statement('CREATE TABLE roles (id_rol INTEGER PRIMARY KEY, nombre TEXT)');
        DB::statement('CREATE TABLE usuario_sede (id_usuario_sede INTEGER PRIMARY KEY, id_usuario INTEGER, id_rol INTEGER, id_sede INTEGER)');
        $roles=[1=>'Administrador',2=>'Gerente de sede',3=>'Recepcion de insumos',4=>'Personal de cocina',6=>'Mesero',7=>'Capitan de meseros',8=>'Cajero',12=>'Jefe de cocina',13=>'Jefe de almacen'];
        $password=Hash::make('Isolated-password-123!');
        foreach ($roles as $id=>$name) {
            DB::table('roles')->insert(['id_rol'=>$id,'nombre'=>$name]);
            DB::table('usuarios')->insert(['id_usuario'=>$id,'nombre'=>$name,'correo'=>'role'.$id.'@example.test','password'=>$password]);
            DB::table('usuario_sede')->insert(['id_usuario'=>$id,'id_rol'=>$id,'id_sede'=>1]);
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->sessionDirectory) && str_starts_with($this->sessionDirectory,storage_path('framework/testing/auth-session-'))) {
            foreach (glob($this->sessionDirectory.'/*') ?: [] as $file) if (is_file($file)) unlink($file);
            rmdir($this->sessionDirectory);
        }
        parent::tearDown();
    }

    private function browser(string $method,string $url,array $data=[],bool $json=false): TestResponse
    {
        // New guard/store for each HTTP request, retaining encrypted browser cookies.
        $this->app['auth']->forgetGuards();
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
        $response=$this->call($method,$url,$data,$this->jar,[],['HTTP_ACCEPT'=>$json?'application/json':'text/html']);
        foreach($response->headers->getCookies() as $cookie) $this->jar[$cookie->getName()]=$cookie->getValue();
        return $response;
    }

    public function test_switching_accounts_for_nine_roles_renews_session_and_token_without_clearing_cookies(): void
    {
        $this->assertSame('nutriliado_session',config('session.cookie'));
        foreach ([1,2,3,4,6,7,8,12,13] as $id) {
            $page=$this->browser('GET','/login')->assertOk();
            $this->assertStringContainsString('no-store',$page->headers->get('Cache-Control'));
            preg_match('/name="_token" value="([^"]+)"/',$page->getContent(),$token);
            $this->assertNotEmpty($token[1]);
            $anonymousId=$this->app['session.store']->getId();
            $this->browser('POST','/login',['_token'=>$token[1],'correo'=>'role'.$id.'@example.test','password'=>'Isolated-password-123!'])->assertRedirect(route('dashboard'));
            $this->assertAuthenticatedAs(\App\Models\User::findOrFail($id));
            $authenticatedId=$this->app['session.store']->getId();
            $this->assertNotSame($anonymousId,$authenticatedId);
            $logoutToken=$this->app['session.store']->token();
            $this->app['session.store']->put(['active_role_id'=>$id,'current_site_id'=>1]);
            $this->app['session.store']->save();
            $this->browser('POST','/logout',['_token'=>$logoutToken])->assertStatus(303)->assertRedirect(route('login'));
            $this->assertGuest();
            $this->assertNotSame($authenticatedId,$this->app['session.store']->getId());
            $this->assertNotSame($logoutToken,$this->app['session.store']->token());
            $this->assertNull($this->app['session.store']->get('active_role_id'));
            $this->assertNull($this->app['session.store']->get('current_site_id'));
            // This reproduces a form opened before logout: it must remain invalid.
            $this->browser('POST','/login',['_token'=>$token[1],'correo'=>'role'.$id.'@example.test','password'=>'Isolated-password-123!'])->assertStatus(419);
            // The browser refreshes the token using this same-origin GET before submit.
            $fresh=$this->browser('GET','/login',[],true)->assertOk();
            $this->assertStringContainsString('no-store',$fresh->headers->get('Cache-Control'));
            $this->browser('POST','/login',['_token'=>$fresh->json('csrf_token'),'correo'=>'role'.$id.'@example.test','password'=>'Isolated-password-123!'])->assertRedirect();
            $this->browser('POST','/logout',['_token'=>$this->app['session.store']->token()])->assertStatus(303);
        }
    }

    public function test_multirole_login_and_missing_csrf_remain_protected(): void
    {
        DB::table('usuario_sede')->insert(['id_usuario'=>7,'id_rol'=>6,'id_sede'=>2]);
        $token=$this->browser('GET','/login',[],true)->json('csrf_token');
        $credentials=['correo'=>'role7@example.test','password'=>'Isolated-password-123!'];
        $this->browser('POST','/login',$credentials)->assertStatus(419);
        $this->assertGuest();
        $this->browser('POST','/login',['_token'=>$token]+$credentials)->assertRedirect(route('roles.select'));
        $this->assertAuthenticatedAs(\App\Models\User::findOrFail(7));
        $this->browser('POST','/logout')->assertStatus(419);
        $this->assertAuthenticated();
        $this->browser('POST','/logout',['_token'=>$this->app['session.store']->token()])->assertStatus(303);
        $this->assertGuest();
    }
}

// The production CSRF middleware normally skips verification during PHPUnit.
// Override only that test shortcut so these checks exercise real token validation.
class EnforceAuthenticationCsrf extends ValidateCsrfToken
{
    protected function runningUnitTests() { return false; }
}
