<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        Site::firstOrCreate(['code' => 'MATRIZ'], ['name' => 'Sede Matriz', 'address' => 'Dirección pendiente']);

        User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@restaurante.local')],
            [
                'name' => env('ADMIN_NAME', 'Administrador'),
                'password' => env('ADMIN_PASSWORD', 'Cambiar123!'),
                'role' => UserRole::Administrator,
                'active' => true,
            ]
        );
    }
}
