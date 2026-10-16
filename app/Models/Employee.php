<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Employee extends Model
{
    protected $table = 'empleados';

    protected $primaryKey = 'id_empleado';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'id_sede',
        'id_puesto',
        'id_usuario',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'id_sede', 'id_sede');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'id_puesto', 'id_puesto');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }
}
