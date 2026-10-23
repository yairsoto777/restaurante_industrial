<?php

namespace Tests\Cash;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

abstract class CashTestCase extends TestCase
{
    public function createApplication()
    {
        // Set isolation BEFORE Laravel boots or reads the real .env.
        $database = getenv('CASH_TEST_DATABASE') ?: ':memory:';
        $mysql = $database !== ':memory:';
        if ($mysql && (getenv('CASH_TEST_ALLOW_MYSQL') !== '1' || ! preg_match('/\Agi_cash_test_[a-f0-9]{16}\z/', $database))) {
            throw new RuntimeException('Unsafe cash test database: application boot refused.');
        }
        $settings = [
            'APP_ENV' => 'testing', 'APP_DEBUG' => 'false',
            'APP_KEY' => 'base64:'.base64_encode(str_repeat('t', 32)),
            'APP_CONFIG_CACHE' => sys_get_temp_dir().'/gi-cash-no-config-'.getmypid().'.php',
            'APP_ROUTES_CACHE' => sys_get_temp_dir().'/gi-cash-no-routes-'.getmypid().'.php',
            'DB_CONNECTION' => $mysql ? 'mysql' : 'sqlite', 'DB_DATABASE' => $database, 'DB_URL' => '',
            'DB_HOST' => $mysql ? (getenv('CASH_TEST_HOST') ?: '127.0.0.1') : '127.0.0.1',
            'DB_PORT' => getenv('CASH_TEST_PORT') ?: '3306',
            'DB_USERNAME' => $mysql ? (getenv('CASH_TEST_USERNAME') ?: '') : '',
            'DB_PASSWORD' => $mysql ? (getenv('CASH_TEST_PASSWORD') ?: '') : '',
            'DB_SOCKET' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array', 'LOG_CHANNEL' => 'null',
            'BCRYPT_ROUNDS' => '4',
        ];
        foreach ($settings as $key => $value) {
            putenv($key.'='.$value);
            $_ENV[$key] = $_SERVER[$key] = $value;
        }
        foreach (['APP_CONFIG_CACHE', 'APP_ROUTES_CACHE'] as $key) {
            if (file_exists($settings[$key])) {
                throw new RuntimeException('Unexpected cached application state: tests refused.');
            }
        }
        $app = parent::createApplication();
        if (! $app->environment('testing') || config('database.connections.'.config('database.default').'.database') !== $database) {
            throw new RuntimeException('Cash test connection isolation failed.');
        }
        if ($mysql && DB::selectOne('SELECT DATABASE() AS name')->name !== $database) {
            throw new RuntimeException('Unexpected physical database: tests refused.');
        }
        if (! $mysql && (DB::getDriverName() !== 'sqlite' || DB::selectOne('PRAGMA database_list')->file !== '')) {
            throw new RuntimeException('SQLite tests require a physical in-memory connection.');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        // No RefreshDatabase, migrations, seeders, or fallback to base_comedor.
        foreach (['bitacora', 'pagos', 'cuentas', 'atenciones', 'servicios', 'cortes_caja', 'usuario_sede', 'roles', 'sedes', 'usuarios'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::create('usuarios', function (Blueprint $table): void {
            $table->increments('id_usuario');
            $table->string('nombre')->nullable();
            $table->string('apellido_p')->nullable();
            $table->string('apellido_m')->nullable();
            $table->string('correo')->nullable()->unique();
            $table->string('password')->nullable();
            $table->timestamp('temporary_password_expires_at')->nullable();
            $table->timestamp('temporary_password_used_at')->nullable();
            $table->rememberToken();
        });
        Schema::create('roles', function (Blueprint $table): void {
            $table->integer('id_rol')->primary();
            $table->string('nombre')->nullable();
        });
        Schema::create('sedes', function (Blueprint $table): void {
            $table->increments('id_sede');
            $table->string('nombre');
        });
        Schema::create('usuario_sede', function (Blueprint $table): void {
            $table->increments('id_usuario_sede');
            $table->integer('id_usuario');
            $table->integer('id_sede')->nullable();
            $table->integer('id_rol');
        });
        Schema::create('cortes_caja', function (Blueprint $table): void {
            $table->increments('id_corte_caja');
            $table->integer('id_sede');
            $table->integer('id_usuario_cajero');
            $table->dateTime('fecha_apertura')->useCurrent();
            $table->dateTime('fecha_cierre')->nullable();
            foreach (['fondo_inicial', 'total_efectivo', 'total_tarjeta', 'total_transferencia', 'total_otros', 'total_sistema', 'efectivo_esperado'] as $column) {
                $table->decimal($column, 12, 2)->default(0);
            }
            $table->decimal('efectivo_contado', 12, 2)->nullable();
            $table->decimal('diferencia', 12, 2)->nullable();
            $table->string('estado', 20)->default('abierto');
            $table->text('observaciones')->nullable();
        });
        Schema::create('bitacora', function (Blueprint $table): void {
            $table->increments('id_bitacora');
            $table->integer('id_usuario')->nullable();
            $table->integer('id_sede')->nullable();
            $table->string('accion', 30);
            $table->string('tabla_afectada', 100);
            $table->integer('id_registro')->nullable();
            $table->longText('datos_anteriores')->nullable();
            $table->longText('datos_nuevos')->nullable();
            $table->text('descripcion')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->dateTime('fecha')->useCurrent();
        });
        Schema::create('servicios', function (Blueprint $table): void {
            $table->increments('id_servicio');
            $table->integer('id_sede');
        });
        Schema::create('atenciones', function (Blueprint $table): void {
            $table->increments('id_atencion');
            $table->integer('id_servicio');
        });
        Schema::create('cuentas', function (Blueprint $table): void {
            $table->increments('id_cuenta');
            $table->integer('id_atencion')->unique();
        });
        Schema::create('pagos', function (Blueprint $table): void {
            $table->increments('id_pago');
            $table->integer('id_cuenta');
            $table->integer('id_usuario');
            $table->integer('id_corte_caja')->nullable();
            $table->string('metodo_pago', 30);
            $table->decimal('monto', 12, 2);
            $table->dateTime('fecha_pago')->useCurrent();
            $table->string('referencia', 100)->nullable();
            $table->string('estado', 30)->default('confirmado');
        });
        if (DB::getDriverName() === 'mysql' && DB::selectOne("SELECT COUNT(*) AS incompatible FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' AND ENGINE <> 'InnoDB'")->incompatible > 0) {
            throw new RuntimeException('Cash concurrency tests require InnoDB tables.');
        }
        DB::table('sedes')->insert([['id_sede' => 1, 'nombre' => 'Sede prueba uno'], ['id_sede' => 2, 'nombre' => 'Sede prueba dos']]);
        DB::table('roles')->insert([
            ['id_rol' => 1, 'nombre' => 'Cajero'], ['id_rol' => 2, 'nombre' => 'Gerente de sede'],
            ['id_rol' => 3, 'nombre' => 'Administrador'], ['id_rol' => 4, 'nombre' => 'Mesero'],
            ['id_rol' => 5, 'nombre' => 'Superadministrador'],
        ]);
    }
}
