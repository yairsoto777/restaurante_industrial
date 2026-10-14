<?php

namespace App\Enums;

enum UserRole: string
{
    case Administrator = 'administrator';
    case Manager = 'manager';

    public function label(): string
    {
        return match ($this) {
            self::Administrator => 'Administrador',
            self::Manager => 'Gerente',
        };
    }
}
