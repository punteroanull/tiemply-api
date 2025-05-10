<?php

namespace Tests\Unit\Http\Controllers;

use App\Models\AbsenceType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\TestCase;

class AbsenceTypeControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_list_all_absence_types()
    {
        $user = User::factory()->create(); // Crea un usuario
        $this->actingAs($user); // Autentica al usuario

        AbsenceType::factory()->count(3)->create();

        $response = $this->getJson(route('absence-types.index'));

        $response->assertStatus(200);
        $response->assertJsonCount(3);
    }

    /** @test */
    public function it_can_store_a_new_absence_type()
    {
        $user = User::factory()->create(); // Crea un usuario
        $this->actingAs($user); // Autentica al usuario
        Gate::shouldReceive('authorize')
            ->once()
            ->with('create', AbsenceType::class)
            ->andReturn(true);

        $requestData = [
            'name' => 'Sick Leave',
            'code' => 'sick_leave',
            'description' => 'Leave for sickness',
            'requires_approval' => true,
            'affects_vacation_balance' => false,
            'is_paid' => true,
        ];

        $response = $this->postJson(route('absence-types.store'), $requestData);

        $response->assertStatus(201);
        $response->assertJsonFragment([
            'name' => 'Sick Leave',
            'code' => 'sick_leave',
        ]);

        $this->assertDatabaseHas('absence_types', [
            'name' => 'Sick Leave',
            'code' => 'sick_leave',
        ]);
    }

    /** @test */
    public function it_can_show_a_specific_absence_type()
    {
        $user = User::factory()->create();
        $this->actingAs($user); // Autentica al usuario
        
        $absenceType = AbsenceType::factory()->create();

        $response = $this->getJson(route('absence-types.show', $absenceType));

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'id' => $absenceType->id,
            'name' => $absenceType->name,
        ]);
    }

    /** @test */
    public function it_can_update_an_absence_type()
    {
        $user = User::factory()->create();
        $this->actingAs($user); // Autentica al usuario
        $absenceType = AbsenceType::factory()->create();

        Gate::shouldReceive('authorize')
            ->once()
            ->with('update', $absenceType)
            ->andReturn(true);

        $updateData = [
            'name' => 'Updated Name',
            'code' => 'updated_code',
        ];

        $response = $this->putJson(route('absence-types.update', $absenceType), $updateData);
        dd($response->json());

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'name' => 'Updated Name',
            'code' => 'updated_code',
        ]);

        $this->assertDatabaseHas('absence_types', [
            'id' => $absenceType->id,
            'name' => 'Updated Name',
            'code' => 'updated_code',
        ]);
    }

    /** @test */
    public function it_cannot_delete_an_absence_type_with_associations()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $absenceType = AbsenceType::factory()->create();

        // Crea un empleado válido
        $employee = \App\Models\Employee::factory()->create();

        // Simula asociaciones
        $absenceType->absences()->create([
            'id' => Str::uuid(),
            'employee_id' => $employee->id, // Usa el ID del empleado creado
            'date' => now(),
        ]);

        Gate::shouldReceive('authorize')
            ->once()
            ->with('delete', $absenceType)
            ->andReturn(true);

        $response = $this->deleteJson(route('absence-types.destroy', $absenceType));

        $response->assertStatus(409);
        $response->assertJsonFragment([
            'message' => 'Cannot delete absence type because it has associated absences or requests.',
        ]);

        $this->assertDatabaseHas('absence_types', [
            'id' => $absenceType->id,
        ]);
    }

    /** @test */
    public function it_can_delete_an_absence_type_without_associations()
    {  
        $user = User::factory()->create();
        $this->actingAs($user);
        $absenceType = AbsenceType::factory()->create();

                // Crea un empleado válido
        $employee = \App\Models\Employee::factory()->create();

        // Simula asociaciones
        $absenceType->absences()->create([
            'id' => Str::uuid(),
            'employee_id' => $employee->id, // Usa el ID del empleado creado
            'date' => now(),
        ]);
        
        Gate::shouldReceive('authorize')
            ->once()
            ->with('delete', $absenceType)
            ->andReturn(true);

        $response = $this->deleteJson(route('absence-types.destroy', $absenceType));

        $response->assertStatus(204);
        $this->assertDatabaseMissing('absence_types', [
            'id' => $absenceType->id,
        ]);
    }
}