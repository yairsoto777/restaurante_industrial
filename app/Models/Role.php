<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $table = 'roles';

    protected $primaryKey = 'id_rol';

    public $timestamps = false;

    protected $fillable = ['nombre'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'usuario_sede', 'id_rol', 'id_usuario')
            ->withPivot('id_sede');
    }

    public function employmentApplications(): HasMany
    {
        return $this->hasMany(EmploymentApplication::class, 'id_rol_solicitado', 'id_rol');
    }
}
