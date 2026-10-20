<?php

namespace Database\Seeders;

use App\Models\Position;
use Illuminate\Database\Seeder;

class PositionSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'Auxiliar de cocina',
            'Cocinero',
            'Jefe de cocina',
            'Encargado de comedor',
            'Recepcionista de insumos',
            'Almacenista',
            'Jefe de almacén',
            'Mesero',
            'Capitán de meseros',
            'Cajero',
        ] as $nombre) {
            Position::query()->firstOrCreate(['nombre' => $nombre]);
        }
    }
}
