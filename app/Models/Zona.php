<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Zona extends Model
{
    protected $table = 'zonas';

    protected $primaryKey = 'id_zona';

    public $timestamps = false;

    protected $fillable = [
        'id_sede',
        'nombre',
    ];

    public function mesas()
    {
        return $this->hasMany(
            Mesa::class,
            'id_zona',
            'id_zona'
        );
    }

    public function asignaciones()
    {
        return $this->hasMany(
            AsignacionMesero::class,
            'id_zona',
            'id_zona'
        );
    }
}
