<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\EmploymentRoles;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Auth\Passwords\CanResetPassword as CanResetPasswordTrait;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

class User extends Authenticatable implements CanResetPassword
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, CanResetPasswordTrait;

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
        'temporary_password_expires_at',
        'temporary_password_used_at',
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
            'temporary_password_expires_at' => 'datetime',
            'temporary_password_used_at' => 'datetime',
        ];
    }

    public function mustChangePassword(): bool
    {
        return $this->temporary_password_expires_at !== null
            && $this->temporary_password_used_at === null;
    }

    public function getNameAttribute(): string
    {
        return trim(implode(' ', array_filter([$this->nombre, $this->apellido_p, $this->apellido_m])));
    }

    public function getEmailForPasswordReset(): string
    {
        return $this->correo;
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
        return $this->roleNames()
            ->contains(fn (string $assignedRole): bool => EmploymentRoles::matches($assignedRole, $role));
    }

    public function isAdministrator(): bool
    {
        return $this->hasRole('Administrador');
    }

    public function isSuperAdministrator(): bool
    {
        return $this->roleNames()->contains(
            fn (string $role): bool => EmploymentRoles::isSuperAdministrator($role),
        );
    }

    public function canAdminister(): bool
    {
        return $this->isSuperAdministrator() || $this->isAdministrator();
    }

    public function isManager(): bool
    {
        return $this->roleNames()->contains(
            fn (string $role): bool => EmploymentRoles::isManager($role),
        );
    }

    public function isCommonUser(): bool
    {
        $roles = $this->roleNames();

        return $roles->isEmpty()
            || $roles->every(fn (string $role): bool => EmploymentRoles::isUser($role));
    }

    public function isManagerAtSite(int $siteId): bool
    {
        return in_array($siteId, $this->managerSiteIds(), true);
    }

    public function managerSiteIds(): array
    {
        return $this->roles()
            ->get()
            ->filter(fn (Role $role): bool => EmploymentRoles::isManager($role->nombre))
            ->pluck('pivot.id_sede')
            ->map(fn ($siteId): int => (int) $siteId)
            ->unique()
            ->values()
            ->all();
    }

    public function roleLabel(): string
    {
        $roles = $this->roleNames();

        foreach (EmploymentRoles::publicNames() as $publicRole) {
            $assignedRole = $roles->first(
                fn (string $role): bool => EmploymentRoles::matches($role, $publicRole),
            );

            if ($assignedRole !== null) {
                return EmploymentRoles::displayName($assignedRole);
            }
        }

        $nonUserRole = $roles->first(
            fn (string $role): bool => ! EmploymentRoles::isUser($role),
        );

        return $nonUserRole !== null
            ? EmploymentRoles::displayName($nonUserRole)
            : EmploymentRoles::USER;
    }

    public function employmentApplications(): HasMany
    {
        return $this->hasMany(EmploymentApplication::class, 'id_usuario', 'id_usuario');
    }

    public function employmentApplicationResponses(): HasMany
    {
        return $this->hasMany(EmploymentApplication::class, 'id_usuario_responde', 'id_usuario');
    }

    private function roleNames(): Collection
    {
        return $this->relationLoaded('roles')
            ? $this->roles->pluck('nombre')
            : $this->roles()->pluck('roles.nombre');
    }
}
