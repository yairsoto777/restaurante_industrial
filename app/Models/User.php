<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $table = 'usuarios';

    protected $primaryKey = 'id_usuario';

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'apellido_p',
        'apellido_m',
        'correo',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function getNameAttribute(): string
    {
        return trim(implode(' ', array_filter([$this->nombre, $this->apellido_p, $this->apellido_m])));
    }

    public function sites(): BelongsToMany
    {
        return $this->belongsToMany(Site::class, 'usuario_sede', 'id_usuario', 'id_sede')
            ->withPivot('id_rol');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'usuario_sede', 'id_usuario', 'id_rol')
            ->withPivot('id_sede');
    }

    public function hasRole(string $role): bool
    {
        return $this->roles()->whereRaw('LOWER(roles.nombre) = ?', [mb_strtolower($role)])->exists();
    }

    public function isAdministrator(): bool
    {
        return $this->hasRole('Administrador');
    }

    public function isManager(): bool
    {
        return $this->hasRole('Gerente');
    }

    public function roleLabel(): string
    {
        return $this->roles()->value('roles.nombre') ?? 'Sin rol';
    }
}
