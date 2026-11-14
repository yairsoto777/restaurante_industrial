<?php
namespace Tests\Feature;

use App\Support\ManagerRouteAccess;

class ManagerNavigationAccessTest extends RoleAccessTest
{
    public function test_manager_cannot_open_operational_modules_directly(): void
    {
        $manager = $this->createUser('Gerente de sede', 9, 1);
        $this->actingAs($manager)->withSession(['current_site_id' => 1, 'active_role_id' => 9]);
        foreach (['tables.index','groups.index','menus.index','dishes.index','incidents.index','orders.index','cash.index','kitchen.index'] as $name) {
            $this->get(route($name))->assertForbidden();
        }
        $this->post(route('manager.services.store'), [])->assertForbidden();
    }

    public function test_supervision_and_role_switch_are_allowed_by_policy(): void
    {
        foreach (['receptions.orders.show','receptions.index','inventory.index','manager.notifications.index','manager.services.index'] as $name) {
            $this->assertTrue(ManagerRouteAccess::allows($name, 'GET'));
        }
        $this->assertTrue(ManagerRouteAccess::allows('roles.update','POST'));
        $this->assertTrue(ManagerRouteAccess::allows('manager.services.approve','PATCH'));
        $this->assertTrue(ManagerRouteAccess::allows('manager.services.reject','PATCH'));
        $this->assertFalse(ManagerRouteAccess::allows('receptions.store','POST'));
        $this->assertFalse(ManagerRouteAccess::allows('inventory.movements.store','POST'));
    }
}
