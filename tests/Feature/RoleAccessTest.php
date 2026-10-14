<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_administrator_can_manage_users_and_sites(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.sites.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.settings'))->assertOk();
    }

    public function test_manager_cannot_access_global_administration(): void
    {
        $manager = User::factory()->create();

        $this->actingAs($manager)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('admin.sites.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('admin.settings'))->assertForbidden();
    }

    public function test_manager_only_sees_assigned_sites(): void
    {
        $manager = User::factory()->create();
        $assigned = Site::create(['name' => 'Norte', 'code' => 'NORTE', 'active' => true]);
        Site::create(['name' => 'Sur', 'code' => 'SUR', 'active' => true]);
        $manager->sites()->attach($assigned);

        $this->actingAs($manager)->get(route('manager.sites'))
            ->assertOk()
            ->assertSee('Norte')
            ->assertDontSee('Sur');
    }

    public function test_administrator_cannot_enter_manager_modules(): void
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->get(route('manager.module', 'inventario'))->assertForbidden();
    }
}
