<?php

namespace Tests\Feature;

use App\Mail\TemporaryPasswordMail;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TemporaryPasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['mail.default' => 'array']);

        if (! Schema::hasTable('usuarios')) {
            Schema::create('usuarios', function (Blueprint $table): void {
                $table->increments('id_usuario');
                $table->string('nombre', 100)->nullable();
                $table->string('apellido_p', 100)->nullable();
                $table->string('apellido_m', 100)->nullable();
                $table->string('correo', 150)->nullable();
                $table->string('password', 250)->nullable();
                $table->timestamp('temporary_password_expires_at')->nullable();
                $table->timestamp('temporary_password_used_at')->nullable();
                $table->rememberToken();
            });
        }
        if (! Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table): void {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function test_forgot_password_page_is_available(): void
    {
        $this->get(route('password.forgot'))->assertOk()->assertSee('Recuperar contraseña');
    }

    public function test_email_is_required_and_must_be_valid(): void
    {
        $this->post(route('password.send-temporary'), [])->assertSessionHasErrors(['correo']);
        $this->post(route('password.send-temporary'), ['correo' => 'correo-invalido'])
            ->assertSessionHasErrors(['correo']);
    }

    public function test_unknown_email_receives_generic_response(): void
    {
        Mail::fake();

        $this->post(route('password.send-temporary'), ['correo' => 'nadie@example.com'])
            ->assertSessionHas('status', 'Si el correo está registrado, recibirás una contraseña temporal.');

        Mail::assertNothingSent();
    }

    public function test_registered_user_receives_a_secure_temporary_password(): void
    {
        Mail::fake();
        $oldPassword = 'Anterior-Segura1!';
        $userId = DB::table('usuarios')->insertGetId([
            'nombre' => 'María',
            'apellido_p' => 'García',
            'correo' => 'maria@example.com',
            'password' => Hash::make($oldPassword),
        ], 'id_usuario');

        $this->post(route('password.send-temporary'), ['correo' => 'maria@example.com'])
            ->assertSessionHas('status', 'Si el correo está registrado, recibirás una contraseña temporal.');

        $temporaryPassword = null;
        Mail::assertSent(TemporaryPasswordMail::class, function (TemporaryPasswordMail $mail) use (&$temporaryPassword): bool {
            $temporaryPassword = $mail->temporaryPassword;
            return $mail->hasTo('maria@example.com');
        });

        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Za-km-z2-9]{12}$/', $temporaryPassword);
        $storedHash = DB::table('usuarios')->where('id_usuario', $userId)->value('password');
        $this->assertTrue(Hash::check($temporaryPassword, $storedHash));
        $this->assertFalse(Hash::check($oldPassword, $storedHash));
        $this->assertNotNull(DB::table('usuarios')->where('id_usuario', $userId)->value('temporary_password_expires_at'));

        $this->post(route('login.store'), ['correo' => 'maria@example.com', 'password' => $temporaryPassword])
            ->assertRedirect(route('password.change'));
        $this->post(route('password.change.store'), [
            'password' => 'FraseSeguraDeAcceso!',
            'password_confirmation' => 'FraseSeguraDeAcceso!',
        ])->assertRedirect(route('dashboard'));
        $this->assertNull(DB::table('usuarios')->where('id_usuario', $userId)->value('temporary_password_expires_at'));
    }

    public function test_activation_link_sets_password_once_and_rejects_reuse(): void
    {
        $user = User::create([
            'nombre' => 'Laura',
            'apellido_p' => 'Ramírez',
            'correo' => 'laura@example.com',
            'password' => Hash::make('Temporal1234!'),
        ]);
        $token = Password::broker()->createToken($user);

        $this->get(route('password.activation', ['token' => $token, 'correo' => $user->correo]))
            ->assertOk()
            ->assertSee('Establecer contraseña');

        $this->post(route('password.activation.store'), [
            'token' => $token,
            'correo' => $user->correo,
            'password' => 'Definitiva1234!',
            'password_confirmation' => 'Definitiva1234!',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Auth::attempt(['correo' => $user->correo, 'password' => 'Definitiva1234!']));
        Auth::logout();

        $this->post(route('password.activation.store'), [
            'token' => $token,
            'correo' => $user->correo,
            'password' => 'OtraDefinitiva1234!',
            'password_confirmation' => 'OtraDefinitiva1234!',
        ])->assertSessionHasErrors('correo');
    }

    public function test_expired_activation_link_is_rejected(): void
    {
        $user = User::create([
            'nombre' => 'Mario',
            'apellido_p' => 'Soto',
            'correo' => 'mario@example.com',
            'password' => Hash::make('Temporal1234!'),
        ]);
        $token = Password::broker()->createToken($user);
        DB::table('password_reset_tokens')->where('email', $user->correo)->update(['created_at' => now()->subMinutes(61)]);

        $this->post(route('password.activation.store'), [
            'token' => $token,
            'correo' => $user->correo,
            'password' => 'Definitiva1234!',
            'password_confirmation' => 'Definitiva1234!',
        ])->assertSessionHasErrors('correo');
    }
}
