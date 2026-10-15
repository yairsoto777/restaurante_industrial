<?php

namespace App\Support;

use App\Models\Role;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class EmploymentRoles
{
    public const USER = 'Usuario';

    public const MANAGER = 'Gerente de sede';

    public const SUPER_ADMINISTRATOR = 'Superadministrador';

    private const PUBLIC_ROLE_DETAILS = [
        'personal de cocina' => [
            'label' => 'Personal de cocina',
            'category' => 'Cocina',
            'icon' => 'utensils',
            'description' => 'Apoya en la preparación de alimentos, consulta servicios, menús, entregas e inventario y registra las actividades operativas que le correspondan.',
        ],
        'jefe de cocina' => [
            'label' => 'Jefe de cocina',
            'category' => 'Cocina',
            'icon' => 'dish',
            'description' => 'Coordina la operación de cocina, organiza al personal, administra servicios y menús, supervisa inventario y autoriza procesos relacionados con cocina.',
        ],
        'recepcion de insumos' => [
            'label' => 'Recepción de insumos',
            'category' => 'Almacén',
            'icon' => 'box',
            'description' => 'Recibe los productos de proveedores, revisa cantidades, condiciones, lotes y caducidades, y registra incidencias cuando existe alguna anomalía.',
        ],
        'jefe de almacen' => [
            'label' => 'Jefe de almacén',
            'category' => 'Almacén',
            'icon' => 'warehouse',
            'description' => 'Administra pedidos, inventarios, recepciones y movimientos de almacén. También revisa y autoriza requisiciones de insumos solicitadas por cocina.',
        ],
        'mesero' => [
            'label' => 'Mesero',
            'category' => 'Servicio',
            'icon' => 'plates',
            'description' => 'Atiende mesas durante los servicios, registra comandas y da seguimiento a la atención de los comensales dentro de la zona que tenga asignada.',
        ],
        'capitan de meseros' => [
            'label' => 'Capitán de meseros',
            'category' => 'Servicio',
            'icon' => 'group',
            'description' => 'Coordina a los meseros, organiza las zonas de atención y supervisa el servicio de mesas durante la operación.',
        ],
        'cajero' => [
            'label' => 'Cajero',
            'category' => 'Caja',
            'icon' => 'cash',
            'description' => 'Gestiona cuentas y cobros, registra pagos y realiza la apertura y cierre de caja correspondientes a su turno.',
        ],
    ];

    private const ALIASES = [
        'gerente' => 'gerente de sede',
        'gerente de sede' => 'gerente de sede',
        'super administrador' => 'superadministrador',
        'superadministrador' => 'superadministrador',
    ];

    public static function publicNames(): array
    {
        return array_column(self::PUBLIC_ROLE_DETAILS, 'label');
    }

    public static function publicRoles(): Collection
    {
        $order = array_flip(array_keys(self::PUBLIC_ROLE_DETAILS));

        return Role::query()
            ->get()
            ->filter(fn (Role $role): bool => self::isPublic($role->nombre))
            ->unique(fn (Role $role): string => self::canonicalKey($role->nombre))
            ->sortBy(fn (Role $role): int => $order[self::canonicalKey($role->nombre)])
            ->values();
    }

    public static function publicRoleIds(): array
    {
        return self::publicRoles()
            ->map(fn (Role $role): int => (int) $role->getKey())
            ->all();
    }

    public static function publicOpportunities(): Collection
    {
        return self::publicRoles()->map(function (Role $role): array {
            $details = self::PUBLIC_ROLE_DETAILS[self::canonicalKey($role->nombre)];

            return [
                'id_rol' => (int) $role->getKey(),
                'nombre' => self::displayName($role->nombre),
                'categoria' => $details['category'],
                'icono' => $details['icon'],
                'descripcion' => $details['description'],
            ];
        });
    }

    public static function displayName(string $role): string
    {
        $key = self::canonicalKey($role);

        return self::PUBLIC_ROLE_DETAILS[$key]['label'] ?? match ($key) {
            'usuario' => self::USER,
            'superadministrador' => self::SUPER_ADMINISTRATOR,
            default => trim($role),
        };
    }

    public static function isPublic(string $role): bool
    {
        return array_key_exists(self::canonicalKey($role), self::PUBLIC_ROLE_DETAILS);
    }

    public static function isUser(string $role): bool
    {
        return self::canonicalKey($role) === 'usuario';
    }

    public static function isManager(string $role): bool
    {
        return self::canonicalKey($role) === 'gerente de sede';
    }

    public static function isSuperAdministrator(string $role): bool
    {
        return self::canonicalKey($role) === 'superadministrador';
    }

    public static function matches(string $actualRole, string $expectedRole): bool
    {
        return self::canonicalKey($actualRole) === self::canonicalKey($expectedRole);
    }

    private static function canonicalKey(string $role): string
    {
        $normalized = Str::of(Str::ascii($role))
            ->lower()
            ->squish()
            ->toString();

        return self::ALIASES[$normalized] ?? $normalized;
    }
}
