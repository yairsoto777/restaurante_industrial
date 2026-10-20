<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Unit extends Model
{
    protected $table = 'unidades';
    protected $primaryKey = 'id_unidad';
    public $timestamps = false;
}
