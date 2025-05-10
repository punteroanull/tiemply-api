<?php

namespace Tests\Unit\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $regularUser;

    public function setUp(): void
    {
        parent::setUp();

        // Crear roles
        $adminRole = Role::factory()->create(['name' => 'Administrator']);
        $employeeRole = Role::factory()->create(['name' => 'Employee']);

        // Crear usuarios
        $this->adminUser = User::factory()->create(['role_id' => $adminRole->id]);
        $this->regularUser = User::factory()->create(['role_id' => $employeeRole->id]);
    }

    /** @test */
    public function it_can_list_all_users()
    {
        // Crea 3 usuarios adicionales
        $users = User::factory()->count(3)->create();

        // Autentica como administrador
        $response = $this->actingAs($this->adminUser)->getJson(route('users.index'));

        // Verifica que la respuesta contenga exactamente 5 usuarios (3 creados + 2 del setUp)
        $response->assertStatus(200)
            ->assertJsonCount(5) // Cambia a 5 para incluir adminUser y regularUser
            ->assertJsonStructure([
                '*' => ['id', 'name', 'email', 'role', 'created_at', 'updated_at']
            ]);
    }

    /** @test */
    public function it_can_create_a_new_user()
    {
        $role = Role::factory()->create();
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'identification_number' => '12345678A',
            'birth_date' => '1990-01-01',
            'phone' => '123456789',
            'address' => 'Test Address, 123',
            'role_id' => $role->id,
        ];

        $response = $this->actingAs($this->adminUser)->postJson(route('users.store'), $userData);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Test User', 'email' => 'test@example.com']);

        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }

    /** @test */
    public function it_can_show_a_user()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($this->adminUser)->getJson(route('users.show', $user->id));

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $user->id, 'name' => $user->name]);
    }

    /** @test */
    public function it_can_update_a_user()
    {
        $user = User::factory()->create();
        $updateData = [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ];

        $response = $this->actingAs($this->adminUser)->patchJson(route('users.update', $user->id), $updateData);

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Updated Name', 'email' => 'updated@example.com']);

        $this->assertDatabaseHas('users', ['email' => 'updated@example.com']);
    }

    /** @test */
    public function it_can_delete_a_user()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($this->adminUser)->deleteJson(route('users.destroy', $user->id));

        $response->assertStatus(204);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    /** @test */
    public function it_cannot_delete_a_user_with_active_employee_records()
    {
        $user = User::factory()->create();
        $company = \App\Models\Company::factory()->create(); // Crea una compañía válida

        $user->employeeRecords()->create([
            'active' => true,
            'company_id' => $company->id, // Proporciona un company_id válido
        ]);

        $response = $this->actingAs($this->adminUser)->deleteJson(route('users.destroy', $user->id));

        $response->assertStatus(409)
            ->assertJsonFragment(['message' => 'User cannot be deleted because they are an active employee in one or more companies.']);

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }
}