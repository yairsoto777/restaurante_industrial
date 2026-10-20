<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Service extends Model
{
    protected $table = 'servicios';

    protected $primaryKey = 'id_servicio';

    public $timestamps = false;

    protected $fillable = [
        'id_sede',
        'nombre',
        'fecha',
        'inicio_hora',
        'fin_hora',
        'capacidad',
        'estado',
        'estado_aprobacion',
        'id_usuario_registro',
        'id_usuario_revision',
        'revisado_en',
        'observaciones_revision',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'capacidad' => 'integer',
            'revisado_en' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'id_sede', 'id_sede');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario_registro', 'id_usuario');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario_revision', 'id_usuario');
    }
}
