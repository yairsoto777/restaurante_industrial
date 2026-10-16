<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Position extends Model
{
    protected $table = 'puestos';

    protected $primaryKey = 'id_puesto';

    public $timestamps = false;

    protected $fillable = ['nombre'];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'id_puesto', 'id_puesto');
    }
}
