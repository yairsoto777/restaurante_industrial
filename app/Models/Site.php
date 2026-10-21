<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Site extends Model
{
    use HasFactory;

    protected $table = 'sedes';

    protected $primaryKey = 'id_sede';

    public $timestamps = false;

    protected $fillable = ['nombre', 'direccion', 'telefono', 'capacidad', 'logo_url', 'lema', 'color', 'id_tipo'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'usuario_sede', 'id_sede', 'id_usuario')
            ->withPivot('id_rol');
    }

    public function employmentApplications(): HasMany
    {
        return $this->hasMany(EmploymentApplication::class, 'id_sede', 'id_sede');
    }

    public function page(): HasOne { return $this->hasOne(SitePage::class, 'id_sede', 'id_sede'); }
}
