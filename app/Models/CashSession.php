<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashSession extends Model
{
    public const OPEN = 'abierto';

    public const CANCELLED = 'anulado';

    protected $table = 'cortes_caja';

    protected $primaryKey = 'id_corte_caja';

    public $timestamps = false;

    protected $fillable = [
        'id_sede', 'id_usuario_cajero', 'fecha_apertura', 'fondo_inicial',
        'efectivo_esperado', 'estado', 'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'fecha_apertura' => 'datetime',
            'fecha_cierre' => 'datetime',
            'fondo_inicial' => 'decimal:2',
            'total_efectivo' => 'decimal:2',
            'total_tarjeta' => 'decimal:2',
            'total_transferencia' => 'decimal:2',
            'total_otros' => 'decimal:2',
            'total_sistema' => 'decimal:2',
            'efectivo_esperado' => 'decimal:2',
            'efectivo_contado' => 'decimal:2',
            'diferencia' => 'decimal:2',
        ];
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario_cajero', 'id_usuario');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'id_sede', 'id_sede');
    }
}
