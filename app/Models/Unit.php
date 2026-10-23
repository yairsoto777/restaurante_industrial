<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected $table = 'unidades';

    protected $primaryKey = 'id_unidad';

    public $timestamps = false;

    protected $fillable = [
        'codigo',
        'nombre',
        'permite_fracciones',
    ];

    protected function casts(): array
    {
        return [
            'permite_fracciones' => 'boolean',
        ];
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(Ingredient::class, 'id_unidad_base', 'id_unidad');
    }
}
