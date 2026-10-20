<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class RecipeDetail extends Model
{
    protected $table = 'receta_detalles';
    protected $primaryKey = 'id_receta';
    public $timestamps = false;
    protected $fillable = ['id_platillo', 'id_insumo', 'cantidad_por_porcion', 'rendimiento'];
    protected function casts(): array { return ['cantidad_por_porcion' => 'decimal:3', 'rendimiento' => 'decimal:3']; }
    public function ingredient() { return $this->belongsTo(Ingredient::class, 'id_insumo', 'id_insumo'); }
}
