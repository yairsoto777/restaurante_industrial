<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Dish extends Model
{
    protected $table = 'platillos';
    protected $primaryKey = 'id_platillo';
    public $timestamps = false;
    protected $fillable = ['id_sede', 'id_categoria', 'nombre', 'descripcion', 'imagen_url'];
    public function category() { return $this->belongsTo(Category::class, 'id_categoria', 'id_categoria'); }
    public function recipeDetails() { return $this->hasMany(RecipeDetail::class, 'id_platillo', 'id_platillo'); }
    public function site() { return $this->belongsTo(Site::class, 'id_sede', 'id_sede'); }
}
