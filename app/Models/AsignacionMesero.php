<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AsignacionMesero extends Model
{
    protected $table = 'zona_meseros';

    protected $primaryKey = 'id_zona_mesero';

    public $timestamps = false;

    protected $fillable = [
        'id_zona',
        'id_usuario_mesero',
        'fecha_inicio',
        'fecha_fin',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'fecha_inicio' => 'datetime',
        'fecha_fin' => 'datetime',
    ];

    public function zona()
    {
        return $this->belongsTo(
            Zona::class,
            'id_zona',
            'id_zona'
        );
    }

    public function mesero()
    {
        return $this->belongsTo(
            User::class,
            'id_usuario_mesero',
            'id_usuario'
        );
    }
}
