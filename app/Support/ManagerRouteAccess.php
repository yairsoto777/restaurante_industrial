<?php

namespace App\Support;

final class ManagerRouteAccess
{
    public static function allows(string $name, string $method): bool
    {
        if (in_array($name, ['logout', 'active-role.update', 'roles.update', 'sites.select', 'sites.select.update'], true)) return true;
        if (str_starts_with($name, 'password.')) return true;
        if (in_array($name, ['manager.sites', 'manager.sites.save'], true)) return true;
        foreach (['manager.site-page.', 'manager.employees.', 'manager.applications.', 'manager.notifications.'] as $prefix) {
            if (str_starts_with($name, $prefix)) return true;
        }
        if (in_array($name, ['manager.services.approve', 'manager.services.reject'], true)) return $method === 'PATCH';
        return in_array($method, ['GET', 'HEAD'], true) && in_array($name, [
            'dashboard', 'manager.services.index', 'inventory.index', 'receptions.index',
            'receptions.orders.show', 'receptions.receipts.show', 'receptions.review.show',
        ], true);
    }
}
