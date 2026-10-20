<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Category extends Model
{
    protected $table = 'categorias';
    protected $primaryKey = 'id_categoria';
    public $timestamps = false;
    protected $fillable = ['nombre'];
    public function dishes() { return $this->hasMany(Dish::class, 'id_categoria', 'id_categoria'); }
}
