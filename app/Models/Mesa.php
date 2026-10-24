<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
    ];

    public function zona()
    {
        return $this->belongsTo(
            Zona::class,
            'id_zona',
            'id_zona'
        );
    }
}
