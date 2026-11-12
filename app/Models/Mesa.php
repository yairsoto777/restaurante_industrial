<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mesa extends Model
{
    protected $table = 'mesas';

    protected $primaryKey = 'id_mesa';

    public $timestamps = false;

    protected $fillable = [
        'id_zona',
        'numero',
        'capacidad',
        'estado_fisico',
        'observaciones',
    ];

    public function zona()
    {
        return $this->belongsTo(
            Zona::class,
            'id_zona',
            'id_zona'
        );
    }

    public function atenciones(): HasMany
    {
        return $this->hasMany(Atencion::class, 'id_mesa', 'id_mesa');
    }
}
