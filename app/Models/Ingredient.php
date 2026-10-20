<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Ingredient extends Model
{
    protected $table = 'insumos';
    protected $primaryKey = 'id_insumo';
    public $timestamps = false;
    public function unit() { return $this->belongsTo(Unit::class, 'id_unidad_base', 'id_unidad'); }
}
