<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicioOfrecido extends Model
{
    protected $table = 'servicios_ofrecidos';

    protected $primaryKey = 'id_servicio_ofrecido';

    protected $fillable = [
        'id_atencion',
        'descripcion',
        'comensales',
        'precio_unitario',
        'total',
        'estado',
        'finalizado_en',
        'pagado_en',
    ];

    protected function casts(): array
    {
        return [
            'comensales' => 'integer',
            'precio_unitario' => 'decimal:2',
            'total' => 'decimal:2',
            'finalizado_en' => 'datetime',
            'pagado_en' => 'datetime',
        ];
    }

    public function atencion(): BelongsTo
    {
        return $this->belongsTo(Atencion::class, 'id_atencion', 'id_atencion');
    }
}
