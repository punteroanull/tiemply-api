<?php

namespace Tests\Unit\Models;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_uses_uuid_as_primary_key()
    {
        $user = User::factory()->create();
        
        $this->assertTrue(Str::isUuid($user->id));
        $this->assertFalse($user->getIncrementing());
        $this->assertEquals('string', $user->getKeyType());
    }

    /** @test */
    public function it_belongs_to_a_role()
    {
        $role = Role::factory()->create();
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->assertInstanceOf(Role::class, $user->role);
        $this->assertEquals($role->id, $user->role->id);
    }

    /** @test */
    public function it_has_many_employee_records()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        
        $employee = Employee::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id
        ]);

        $this->assertInstanceOf(Employee::class, $user->employeeRecords->first());
        $this->assertEquals(1, $user->employeeRecords->count());
    }

    /** @test */
    public function it_can_determine_if_user_is_admin()
    {
        $adminRole = Role::factory()->create(['name' => 'Administrator']);
        $regularRole = Role::factory()->create(['name' => 'Employee']);
        
        $admin = User::factory()->create(['role_id' => $adminRole->id]);
        $regular = User::factory()->create(['role_id' => $regularRole->id]);
        
        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($regular->isAdmin());
    }

    /** @test */
    public function it_can_determine_if_user_is_manager()
    {
        $managerRole = Role::factory()->create(['name' => 'Manager']);
        $regularRole = Role::factory()->create(['name' => 'Employee']);
        
        $manager = User::factory()->create(['role_id' => $managerRole->id]);
        $regular = User::factory()->create(['role_id' => $regularRole->id]);
        
        $this->assertTrue($manager->isManager());
        $this->assertFalse($regular->isManager());
    }

    /** @test */
    public function it_can_check_if_user_belongs_to_a_company()
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        
        Employee::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id
        ]);
        
        $this->assertTrue($user->belongsToCompany($company->id));
        $this->assertFalse($user->belongsToCompany($otherCompany->id));
    }

    /** @test */
    public function it_can_get_companies_user_belongs_to()
    {
        $user = User::factory()->create();
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();
        
        Employee::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company1->id
        ]);
        
        Employee::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company2->id
        ]);
        
        $companies = $user->getCompanies()->get();
        
        $this->assertEquals(2, $companies->count());
        $this->assertTrue($companies->contains($company1));
        $this->assertTrue($companies->contains($company2));
    }
}