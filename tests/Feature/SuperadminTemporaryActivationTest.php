<?php

namespace Tests\Feature;

use App\Mail\SuperadminActivationMail;
use App\Models\User;
use App\Support\PasswordSession;
use Illuminate\Support\Facades\{DB, Hash, Mail, Password};
use Tests\TestCase;

class SuperadminTemporaryActivationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite',config('database.default'));
        $this->assertSame(':memory:',config('database.connections.sqlite.database'));
        $this->withoutVite();
        Mail::fake();
        $definitions=[
            'usuarios'=>'id_usuario INTEGER PRIMARY KEY, nombre TEXT, apellido_p TEXT, apellido_m TEXT, correo TEXT UNIQUE, password TEXT, temporary_password_expires_at TEXT, temporary_password_used_at TEXT, remember_token TEXT',
            'roles'=>'id_rol INTEGER PRIMARY KEY, nombre TEXT',
            'sedes'=>'id_sede INTEGER PRIMARY KEY, nombre TEXT, direccion TEXT, telefono TEXT, capacidad INTEGER, id_tipo INTEGER, id_cliente INTEGER',
            'usuario_sede'=>'id_usuario_sede INTEGER PRIMARY KEY, id_usuario INTEGER, id_sede INTEGER, id_rol INTEGER',
            'password_reset_tokens'=>'email TEXT PRIMARY KEY, token TEXT, created_at TEXT',
        ];
        foreach($definitions as $table=>$columns)DB::connection()->getPdo()->exec('CREATE TABLE '.$table.' ('.$columns.')');
        DB::table('roles')->insert([['id_rol'=>1,'nombre'=>'Superadministrador'],['id_rol'=>2,'nombre'=>'Gerente de sede'],['id_rol'=>3,'nombre'=>'Cajero']]);
        DB::table('sedes')->insert(['id_sede'=>1,'nombre'=>'Sede aislada','direccion'=>'Solo memoria']);
        foreach([1,2,3] as $id)DB::table('usuarios')->insert(['id_usuario'=>$id,'nombre'=>'Usuario '.$id,'apellido_p'=>'Prueba','correo'=>'user'.$id.'@example.test','password'=>Hash::make('AnteriorDePrueba123!'),'temporary_password_expires_at'=>$id===2?now()->addHour():null]);
        DB::table('usuario_sede')->insert([['id_usuario_sede'=>1,'id_usuario'=>1,'id_sede'=>1,'id_rol'=>1],['id_usuario_sede'=>2,'id_usuario'=>2,'id_sede'=>1,'id_rol'=>2],['id_usuario_sede'=>3,'id_usuario'=>2,'id_sede'=>1,'id_rol'=>3],['id_usuario_sede'=>4,'id_usuario'=>3,'id_sede'=>1,'id_rol'=>3]]);
    }

    private function asAccount(int $id): self
    {
        $user=User::findOrFail($id);
        $this->actingAs($user)->withSession(['auth_password_fingerprint'=>PasswordSession::fingerprint($user)]);
        return $this;
    }

    public function test_temporary_activation_forces_a_different_password_and_invalidates_previous_credentials(): void
    {
        $unrelated=(array)DB::table('usuarios')->where('id_usuario',3)->first();
        $roles=DB::table('usuario_sede')->orderBy('id_usuario_sede')->get()->toJson();
        $oldToken=Password::broker()->createToken(User::findOrFail(2));
        $oldFingerprint=PasswordSession::fingerprint(User::findOrFail(2));
        $temporary='TemporalAislada123!';
        $url=route('superadmin.users.activation',2);
        $this->asAccount(1)->post($url,['metodo'=>'temporal','password'=>'corta'])
            ->assertSessionHasErrors('password')->assertSessionMissing('_old_input.password');
        $response=$this->post($url,['metodo'=>'temporal','password'=>$temporary]);
        $response->assertOk()->assertViewIs('superadmin.activation-result')->assertSee($temporary);
        $this->assertStringContainsString('no-store',$response->headers->get('Cache-Control'));
        $this->assertFalse(str_contains(serialize(session()->all()),$temporary));
        $target=User::findOrFail(2);
        $this->assertTrue(Hash::check($temporary,$target->password));
        $this->assertTrue($target->mustChangePassword());
        $this->assertFalse(Password::broker()->tokenExists($target,$oldToken));
        $this->assertSame($roles,DB::table('usuario_sede')->orderBy('id_usuario_sede')->get()->toJson());
        $this->assertSame($unrelated,(array)DB::table('usuarios')->where('id_usuario',3)->first());
        Mail::assertNothingSent();

        // An earlier session, including one from before the feature, cannot use the new credentials.
        $this->actingAs($target)->withSession(['auth_password_fingerprint'=>$oldFingerprint])->get(route('dashboard'))->assertRedirect(route('login'));
        $this->post(route('login.store'),['correo'=>$target->correo,'password'=>$temporary])->assertRedirect(route('password.change'));
        $this->get(route('dashboard'))->assertRedirect(route('password.change'));
        $this->get(route('roles.select'))->assertRedirect(route('password.change'));
        $this->post(route('roles.update'),['id_rol'=>3,'id_sede'=>1])->assertRedirect(route('password.change'));
        $this->get(route('password.change'))->assertOk()->assertSee('Cambiar contraseña')->assertSee('NUTRILIADO');
        $this->post(route('password.change.store'),['password'=>$temporary,'password_confirmation'=>$temporary])->assertSessionHasErrors('password');
        $this->post(route('password.change.store'),['password'=>'DefinitivaAislada123!','password_confirmation'=>'DiferenteAislada123!'])->assertSessionHasErrors('password');
        $temporaryFingerprint=PasswordSession::fingerprint(User::findOrFail(2));
        $sessionBefore=session()->getId();
        $this->post(route('password.change.store'),['password'=>'DefinitivaAislada123!','password_confirmation'=>'DefinitivaAislada123!'])->assertRedirect(route('dashboard'));
        $target=User::findOrFail(2);
        $this->assertFalse($target->mustChangePassword());
        $this->assertTrue(Hash::check('DefinitivaAislada123!',$target->password));
        $this->assertFalse(Hash::check($temporary,$target->password));
        $this->assertNotSame($sessionBefore,session()->getId());
        $this->get(route('roles.select'))->assertOk();
        $this->actingAs($target)->withSession(['auth_password_fingerprint'=>$temporaryFingerprint])->get(route('roles.select'))->assertRedirect(route('login'));
        $this->post(route('login.store'),['correo'=>$target->correo,'password'=>$temporary])->assertSessionHasErrors('correo');
        $this->post(route('login.store'),['correo'=>$target->correo,'password'=>'DefinitivaAislada123!'])->assertRedirect(route('roles.select'));
        $this->assertDatabaseCount('usuarios',3);
        $this->assertDatabaseCount('sedes',1);
        $this->assertSame($unrelated,(array)DB::table('usuarios')->where('id_usuario',3)->first());
    }

    public function test_generated_password_expiry_link_compatibility_and_permissions(): void
    {
        $url=route('superadmin.users.activation',2);
        $response=$this->asAccount(1)->post($url,['metodo'=>'temporal','generar'=>'1']);
        $response->assertOk();
        $generated=$response->viewData('temporary');
        $this->assertSame(20,strlen($generated));
        $this->assertTrue(Hash::check($generated,User::findOrFail(2)->password));
        $second=$this->post($url,['metodo'=>'temporal','generar'=>'1']);
        $this->assertNotSame($generated,$second->viewData('temporary'));
        $this->assertFalse(Hash::check($generated,User::findOrFail(2)->password));
        // Link activation still uses the existing broker and mail class.
        $this->post($url,['metodo'=>'enlace'])->assertRedirect();
        Mail::assertSent(SuperadminActivationMail::class,1);
        $token=Password::broker()->createToken(User::findOrFail(2));
        $this->post(route('logout'));
        $this->post(route('password.activation.store'),['correo'=>'user2@example.test','token'=>$token,'password'=>'DefinitivaPorEnlace123!','password_confirmation'=>'DefinitivaPorEnlace123!'])->assertRedirect(route('login'));
        $this->assertFalse(User::findOrFail(2)->mustChangePassword());
        $this->asAccount(3)->post($url,['metodo'=>'temporal','generar'=>'1'])->assertForbidden();
        $this->asAccount(1)->post(route('superadmin.users.activation',3),['metodo'=>'temporal','generar'=>'1'])->assertForbidden();
        $this->asAccount(1)->post($url,['metodo'=>'temporal','password'=>'TemporalExpirable123!'])->assertOk();
        $this->post(route('logout'));
        $this->travel(31)->minutes();
        $this->post(route('login.store'),['correo'=>'user2@example.test','password'=>'TemporalExpirable123!'])->assertSessionHasErrors('correo');
        $this->assertGuest();
    }
}
