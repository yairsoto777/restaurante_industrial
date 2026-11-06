<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CapitanDemoSeeder extends Seeder
{
    public function run(): void
    {
        $sedeId = (int) (DB::table('sedes')->where('id_sede', 4)->value('id_sede')
            ?? DB::table('sedes')->value('id_sede'));

        if (!$sedeId) {
            $this->command?->warn('No hay sedes disponibles para cargar datos del capitán.');
            return;
        }

        DB::transaction(function () use ($sedeId): void {
            $now = now();
            $captainId = $this->user('capitan.demo@restaurante.local', 'Capitán', 'Demo', 'NUTRILIADO');
            $this->assignRole($captainId, $sedeId, 7);

            $waiters = [
                ['ana.martinez@restaurante.local', 'Ana', 'Martínez', 'López'],
                ['bruno.sanchez@restaurante.local', 'Bruno', 'Sánchez', 'Ruiz'],
                ['carla.gomez@restaurante.local', 'Carla', 'Gómez', 'Díaz'],
            ];

            $waiterIds = [];
            foreach ($waiters as [$email, $name, $lastName, $secondLastName]) {
                $waiterId = $this->user($email, $name, $lastName, $secondLastName);
                $this->assignRole($waiterId, $sedeId, 6);
                $waiterIds[] = $waiterId;
            }

            $zones = [];
            foreach (['Terraza', 'Salón principal', 'Comedor ejecutivo'] as $zoneName) {
                $zones[$zoneName] = DB::table('zonas')->where('id_sede', $sedeId)->where('nombre', $zoneName)->value('id_zona');
                if (!$zones[$zoneName]) {
                    $zones[$zoneName] = DB::table('zonas')->insertGetId(['id_sede' => $sedeId, 'nombre' => $zoneName]);
                }
            }

            $tableIds = [];
            $tableNumber = 1;
            foreach ($zones as $zoneName => $zoneId) {
                for ($index = 0; $index < 4; $index++) {
                    $existingTableId = DB::table('mesas')->where('id_zona', $zoneId)->where('numero', $tableNumber)->value('id_mesa');
                    $tableIds[] = $existingTableId ?? DB::table('mesas')->insertGetId([
                        'id_zona' => $zoneId, 'numero' => $tableNumber, 'capacidad' => 4,
                        'estado_fisico' => 'Disponible',
                    ]);
                    $tableNumber++;
                }
            }

            foreach (array_values($zones) as $index => $zoneId) {
                $this->upsert('zona_meseros', ['id_zona' => $zoneId, 'id_usuario_mesero' => $waiterIds[$index]], [
                    'fecha_inicio' => $now, 'fecha_fin' => null, 'activo' => 1,
                ]);
            }

            $serviceId = DB::table('servicios')->where('id_sede', $sedeId)->whereDate('fecha', today())->where('nombre', 'Comida del día')->value('id_servicio');
            if (!$serviceId) {
                $serviceId = DB::table('servicios')->insertGetId([
                    'id_sede' => $sedeId, 'nombre' => 'Comida del día', 'fecha' => today(),
                    'inicio_hora' => '08:00:00', 'fin_hora' => '16:00:00', 'capacidad' => 150,
                    'estado' => 'Programado', 'estado_aprobacion' => 'Aprobado',
                    'id_usuario_registro' => $captainId, 'id_usuario_revision' => $captainId,
                    'revisado_en' => $now,
                ]);
            }

            $groups = [];
            foreach ([['Personal administrativo', 45, '08:00:00', '10:00:00'], ['Turno operativo', 60, '12:00:00', '14:00:00']] as [$name, $people, $start, $end]) {
                $groups[] = DB::table('grupos')->where('id_servicio', $serviceId)->where('nombre', $name)->value('id_grupo')
                    ?? DB::table('grupos')->insertGetId(['id_servicio' => $serviceId, 'nombre' => $name, 'inicio_hora' => $start, 'fin_hora' => $end, 'comensales_pre' => $people]);
            }

            foreach (array_slice($tableIds, 0, 6) as $index => $tableId) {
                $attentionId = DB::table('atenciones')->where('id_servicio', $serviceId)->where('id_mesa', $tableId)->value('id_atencion');
                if (!$attentionId) {
                    $attentionId = DB::table('atenciones')->insertGetId([
                        'id_servicio' => $serviceId, 'id_mesa' => $tableId, 'id_usuario_mesero' => $waiterIds[$index % 3],
                        'estado' => $index < 4 ? 'En atención' : 'Cerrada', 'id_grupo' => $groups[$index % 2],
                        'comensales' => 2 + ($index % 3), 'inicio_hora' => sprintf('%02d:30:00', 9 + $index),
                        'fin_hora' => $index < 4 ? null : sprintf('%02d:30:00', 11 + $index),
                    ]);
                }
                $this->upsert('comandas', ['id_atencion' => $attentionId], [
                    'id_usuario_mesero' => $waiterIds[$index % 3], 'fecha_apertura' => $now,
                    'fecha_cierre' => $index < 4 ? null : $now, 'estado' => $index < 4 ? 'En preparación' : 'Entregada',
                ]);
            }

            if (!DB::table('incidencias')->where('id_sede', $sedeId)->where('descripcion', 'Like', 'Revisar montaje%')->exists()) {
                DB::table('incidencias')->insert(['id_sede' => $sedeId, 'id_usuario' => $captainId, 'fecha' => $now, 'descripcion' => 'Revisar montaje y limpieza de la mesa 3 antes del siguiente servicio.', 'estado' => 'Pendiente']);
            }
        });
    }

    private function user(string $email, string $name, string $lastName, string $secondLastName): int
    {
        $id = DB::table('usuarios')->where('correo', $email)->value('id_usuario');
        if (!$id) {
            $id = DB::table('usuarios')->insertGetId(['nombre' => $name, 'apellido_p' => $lastName, 'apellido_m' => $secondLastName, 'correo' => $email, 'password' => Hash::make('Demo12345!')]);
        }
        return (int) $id;
    }

    private function assignRole(int $userId, int $siteId, int $roleId): void
    {
        DB::table('usuario_sede')->updateOrInsert(['id_usuario' => $userId, 'id_sede' => $siteId, 'id_rol' => $roleId], []);
    }

    private function upsert(string $table, array $keys, array $values): void
    {
        DB::table($table)->updateOrInsert($keys, $values);
    }
}
