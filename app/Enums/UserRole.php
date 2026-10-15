<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdministrator = 'super-administrator';
    case Administrator = 'administrator';
    case Manager = 'manager';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdministrator => 'Super Administrador',
            self::Administrator => 'Administrador',
            self::Manager => 'Gerente',
        };
    }
}
