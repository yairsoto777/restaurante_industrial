<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercialContact extends Model
{
    public const STATUS_NEW = 'nuevo';

    public const STATUS_CONTACTED = 'contactado';

    public const STATUS_APPROVED = 'aprobado';

    public const STATUS_REJECTED = 'rechazado';

    protected $table = 'contactos';

    protected $primaryKey = 'id_contacto';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'apellido_p',
        'apellido_m',
        'correo',
        'empresa',
        'preferencia',
        'telefono',
        'mensaje',
        'estado',
        'fecha_contacto',
        'fecha_respuesta',
        'id_superadmin_responde',
        'observaciones_respuesta',
    ];

    protected function casts(): array
    {
        return [
            'fecha_contacto' => 'datetime',
            'fecha_respuesta' => 'datetime',
        ];
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_NEW => 'Nuevo',
            self::STATUS_CONTACTED => 'Contactado',
            self::STATUS_APPROVED => 'Aprobado',
            self::STATUS_REJECTED => 'Rechazado',
        ];
    }

    public function statusLabel(): string
    {
        return self::statuses()[$this->estado] ?? ucfirst(str_replace('_', ' ', (string) $this->estado));
    }

    public function fullName(): string
    {
        return implode(' ', array_filter([
            $this->nombre,
            $this->apellido_p,
            $this->apellido_m,
        ], fn (mixed $value): bool => filled($value)));
    }

    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_superadmin_responde', 'id_usuario');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'correo', 'correo');
    }
}
