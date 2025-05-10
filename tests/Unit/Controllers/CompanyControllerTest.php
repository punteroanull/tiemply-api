<?php

namespace Tests\Unit\Controllers;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompanyControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private $admin;
    private $manager;
    private $employee;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create roles
        $adminRole = Role::factory()->create(['name' => 'Administrator', 'access_level' => 5]);
        $managerRole = Role::factory()->create(['name' => 'Manager', 'access_level' => 4]);
        $employeeRole = Role::factory()->create(['name' => 'Employee', 'access_level' => 1]);
        
        // Create users with different roles
        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);
        $this->manager = User::factory()->create(['role_id' => $managerRole->id]);
        $this->employee = User::factory()->create(['role_id' => $employeeRole->id]);
    }

    /** @test */
    public function admins_can_see_all_companies()
    {
        // Create multiple companies
        Company::factory()->count(3)->create();
        
        // Authenticate as admin
        Sanctum::actingAs($this->admin);
        
        $response = $this->getJson('/api/companies');
        
        $response->assertStatus(200)
            ->assertJsonCount(3);
    }

    /** @test */
    public function managers_can_only_see_their_companies()
    {
        // Create companies
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();
        
        // Associate manager with company1
        Employee::factory()->create([
            'company_id' => $company1->id,
            'user_id' => $this->manager->id
        ]);
        
        // Authenticate as manager
        Sanctum::actingAs($this->manager);
        
        $response = $this->getJson('/api/companies');
        dd($response->json());
        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment(['id' => $company1->id]);
    }

    /** @test */
    public function employees_can_only_see_their_companies()
    {
        // Create companies
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();
        
        // Associate employee with company1
        Employee::factory()->create([
            'company_id' => $company1->id,
            'user_id' => $this->employee->id
        ]);
        
        // Authenticate as employee
        Sanctum::actingAs($this->employee);
        
        $response = $this->getJson('/api/companies');
        
        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment(['id' => $company1->id]);
    }

    /** @test */
    public function only_admins_can_create_companies()
    {
        $companyData = [
            'name' => 'Test Company',
            'tax_id' => 'B12345678',
            'contact_email' => 'contact@test.com',
            'contact_person' => 'Contact Person',
            'address' => 'Test Address, 123',
            'phone' => '123456789',
            'vacation_type' => 'business_days',
            'max_vacation_days' => 22
        ];
        
        // Test with admin
        Sanctum::actingAs($this->admin);
        
        $response = $this->postJson('/api/companies', $companyData);
        
        $response->assertStatus(201)
            ->assertJsonFragment([
                'name' => 'Test Company',
                'tax_id' => 'B12345678'
            ]);
            
        // Test with manager
        Sanctum::actingAs($this->manager);
        
        $response = $this->postJson('/api/companies', $companyData);
        
        $response->assertForbidden();
        
        // Test with employee
        Sanctum::actingAs($this->employee);
        
        $response = $this->postJson('/api/companies', $companyData);
        
        $response->assertForbidden();
    }

    /** @test */
    public function company_creation_requires_valid_data()
    {
        Sanctum::actingAs($this->admin);
        
        $response = $this->postJson('/api/companies', [
            'name' => '',  // Empty name
            'tax_id' => 'B12345678',
            'vacation_type' => 'invalid_type' // Invalid enum value
        ]);
        
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'vacation_type']);
    }

    /** @test */
    public function admins_can_view_any_company()
    {
        $company = Company::factory()->create();
        
        Sanctum::actingAs($this->admin);
        
        $response = $this->getJson("/api/companies/{$company->id}");
        
        $response->assertStatus(200)
            ->assertJsonFragment([
                'id' => $company->id,
                'name' => $company->name
            ]);
    }

    /** @test */
    public function managers_can_view_associated_companies()
    {
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();
        
        // Associate manager with company1
        Employee::factory()->create([
            'company_id' => $company1->id,
            'user_id' => $this->manager->id
        ]);
        
        Sanctum::actingAs($this->manager);
        
        // Manager can view associated company
        $response = $this->getJson("/api/companies/{$company1->id}");
        $response->assertStatus(200);
        
        // Manager cannot view non-associated company
        $response = $this->getJson("/api/companies/{$company2->id}");
        $response->assertForbidden();
    }

    /** @test */
    public function only_admins_can_update_companies()
    {
        $company = Company::factory()->create([
            'name' => 'Old Name',
            'max_vacation_days' => 22
        ]);
        
        $updateData = [
            'name' => 'New Name',
            'max_vacation_days' => 25
        ];
        
        // Test with admin
        Sanctum::actingAs($this->admin);
        
        $response = $this->patchJson("/api/companies/{$company->id}", $updateData);
        
        $response->assertStatus(200)
            ->assertJsonFragment([
                'name' => 'New Name',
                'max_vacation_days' => 25
            ]);
            
        // Test with manager who is not associated with the company
        Sanctum::actingAs($this->manager);
        
        $response = $this->patchJson("/api/companies/{$company->id}", $updateData);
        
        $response->assertForbidden();
    }

    /** @test */
    public function managers_can_update_associated_companies()
    {
        $company = Company::factory()->create([
            'name' => 'Old Name',
            'max_vacation_days' => 22
        ]);
        
        // Associate manager with company
        Employee::factory()->create([
            'company_id' => $company->id,
            'user_id' => $this->manager->id
        ]);
        
        $updateData = [
            'name' => 'New Name',
            'max_vacation_days' => 25
        ];
        
        Sanctum::actingAs($this->manager);
        
        $response = $this->patchJson("/api/companies/{$company->id}", $updateData);
        
        $response->assertStatus(200)
            ->assertJsonFragment([
                'name' => 'New Name',
                'max_vacation_days' => 25
            ]);
    }

    /** @test */
    public function only_admins_can_delete_companies()
    {
        $company = Company::factory()->create();
        
        // Test with manager
        Sanctum::actingAs($this->manager);
        
        $response = $this->deleteJson("/api/companies/{$company->id}");
        
        $response->assertForbidden();
        
        // Test with admin
        Sanctum::actingAs($this->admin);
        
        $response = $this->deleteJson("/api/companies/{$company->id}");
        
        $response->assertStatus(204);
        $this->assertDatabaseMissing('companies', ['id' => $company->id]);
    }

    /** @test */
    public function company_employees_endpoint_returns_employees()
    {
        $company = Company::factory()->create();
        
        // Create employees for the company
        Employee::factory()->count(3)->create([
            'company_id' => $company->id
        ]);
        
        Sanctum::actingAs($this->admin);
        
        $response = $this->getJson("/api/companies/{$company->id}/employees");
        
        $response->assertStatus(200)
            ->assertJsonCount(3);
    }

    /** @test */
    public function managers_can_only_access_employees_of_associated_companies()
    {
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();
        
        // Create employees for both companies
        Employee::factory()->count(3)->create([
            'company_id' => $company1->id
        ]);
        
        Employee::factory()->count(2)->create([
            'company_id' => $company2->id
        ]);
        
        // Associate manager with company1
        Employee::factory()->create([
            'company_id' => $company1->id,
            'user_id' => $this->manager->id
        ]);
        
        Sanctum::actingAs($this->manager);
        
        // Manager can access employees of associated company
        $response = $this->getJson("/api/companies/{$company1->id}/employees");
        $response->assertStatus(200)
            ->assertJsonCount(4); // 3 employees + the manager
        
        // Manager cannot access employees of non-associated company
        $response = $this->getJson("/api/companies/{$company2->id}/employees");
        $response->assertForbidden();
    }
}