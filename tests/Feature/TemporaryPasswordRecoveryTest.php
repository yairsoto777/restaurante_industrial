<?php

namespace Tests\Feature;

use App\Mail\TemporaryPasswordMail;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
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
                $table->rememberToken();
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

    public function test_registered_user_receives_strong_temporary_password_and_can_use_it(): void
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

        $this->assertIsString($temporaryPassword);
        $this->assertMatchesRegularExpression('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[^A-Za-z0-9]).{12}$/', $temporaryPassword);

        $storedHash = DB::table('usuarios')->where('id_usuario', $userId)->value('password');
        $this->assertTrue(Hash::check($temporaryPassword, $storedHash));
        $this->assertFalse(Hash::check($oldPassword, $storedHash));
        $this->assertTrue(Auth::attempt(['correo' => 'maria@example.com', 'password' => $temporaryPassword]));
    }
}
