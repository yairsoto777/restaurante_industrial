<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmploymentApplication extends Model
{
    public const STATUS_PENDING = 'pendiente';

    public const STATUS_APPROVED = 'aprobada';

    public const STATUS_REJECTED = 'rechazada';

    protected $table = 'solicitudes_empleo';

    protected $primaryKey = 'id_solicitud';

    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'id_sede',
        'id_rol_solicitado',
        'mensaje',
        'estado',
        'fecha_solicitud',
        'fecha_respuesta',
        'id_usuario_responde',
        'observaciones_respuesta',
    ];

    protected function casts(): array
    {
        return [
            'fecha_solicitud' => 'datetime',
            'fecha_respuesta' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'id_sede', 'id_sede');
    }

    public function requestedRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'id_rol_solicitado', 'id_rol');
    }

    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario_responde', 'id_usuario');
    }
}
