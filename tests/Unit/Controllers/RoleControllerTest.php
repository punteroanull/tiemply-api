<?php

namespace Tests\Unit\Models;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_check_if_role_is_admin()
    {
        $role = Role::factory()->create(['name' => 'Administrator']);

        $this->assertTrue($role->isAdmin());
        $this->assertFalse($role->isManager());
        $this->assertFalse($role->isEmployee());
    }

    /** @test */
    public function it_can_check_if_role_is_manager()
    {
        $role = Role::factory()->create(['name' => 'Manager']);

        $this->assertTrue($role->isManager());
        $this->assertFalse($role->isAdmin());
        $this->assertFalse($role->isEmployee());
    }

    /** @test */
    public function it_can_check_if_role_is_employee()
    {
        $role = Role::factory()->create(['name' => 'Employee']);

        $this->assertTrue($role->isEmployee());
        $this->assertFalse($role->isAdmin());
        $this->assertFalse($role->isManager());
    }

    /** @test */
    public function it_can_check_if_role_is_exempt()
    {
        $role = Role::factory()->create(['is_exempt' => true]);

        $this->assertTrue($role->isExempt());

        $role->is_exempt = false;
        $this->assertFalse($role->isExempt());
    }

    /** @test */
    public function it_can_check_access_level()
    {
        $role = Role::factory()->create(['access_level' => 5]);

        $this->assertTrue($role->hasAccessLevel(5));
        $this->assertTrue($role->hasAccessLevel(3));
        $this->assertFalse($role->hasAccessLevel(6));
    }

    /** @test */
    public function it_has_many_users()
    {
        $role = Role::factory()->create();
        $users = User::factory()->count(3)->create(['role_id' => $role->id]);

        $this->assertCount(3, $role->users);
        $this->assertTrue($role->users->contains($users->first()));
    }
}