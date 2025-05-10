<?php

namespace Tests\Unit\Models;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RoleModelTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_uses_uuid_as_primary_key()
    {
        $role = Role::factory()->create();
        
        $this->assertTrue(Str::isUuid($role->id));
        $this->assertFalse($role->getIncrementing());
        $this->assertEquals('string', $role->getKeyType());
    }

    /** @test */
    public function it_has_many_users()
    {
        $role = Role::factory()->create();
        
        User::factory()->count(3)->create([
            'role_id' => $role->id
        ]);
        
        $this->assertEquals(3, $role->users->count());
        $this->assertInstanceOf(User::class, $role->users->first());
    }

    /** @test */
    public function it_can_determine_if_is_admin_role()
    {
        $adminRole = Role::factory()->create(['name' => 'Administrator']);
        $otherRole = Role::factory()->create(['name' => 'Employee']);
        
        $this->assertTrue($adminRole->isAdmin());
        $this->assertFalse($otherRole->isAdmin());
    }

    /** @test */
    public function it_can_determine_if_is_manager_role()
    {
        $managerRole = Role::factory()->create(['name' => 'Manager']);
        $otherRole = Role::factory()->create(['name' => 'Employee']);
        
        $this->assertTrue($managerRole->isManager());
        $this->assertFalse($otherRole->isManager());
    }

    /** @test */
    public function it_can_determine_if_is_employee_role()
    {
        $employeeRole = Role::factory()->create(['name' => 'Employee']);
        $otherRole = Role::factory()->create(['name' => 'Manager']);
        
        $this->assertTrue($employeeRole->isEmployee());
        $this->assertFalse($otherRole->isEmployee());
    }

    /** @test */
    public function it_can_determine_if_is_exempt()
    {
        $exemptRole = Role::factory()->create(['is_exempt' => true]);
        $nonExemptRole = Role::factory()->create(['is_exempt' => false]);
        
        $this->assertTrue($exemptRole->isExempt());
        $this->assertFalse($nonExemptRole->isExempt());
    }

    /** @test */
    public function it_can_check_access_level()
    {
        $highLevelRole = Role::factory()->create(['access_level' => 5]);
        $midLevelRole = Role::factory()->create(['access_level' => 3]);
        $lowLevelRole = Role::factory()->create(['access_level' => 1]);
        
        $this->assertTrue($highLevelRole->hasAccessLevel(5));
        $this->assertTrue($highLevelRole->hasAccessLevel(3));
        
        $this->assertFalse($midLevelRole->hasAccessLevel(5));
        $this->assertTrue($midLevelRole->hasAccessLevel(3));
        $this->assertTrue($midLevelRole->hasAccessLevel(1));
        
        $this->assertFalse($lowLevelRole->hasAccessLevel(2));
        $this->assertTrue($lowLevelRole->hasAccessLevel(1));
    }
}