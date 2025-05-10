<?php

namespace Tests\Unit\Http\Controllers;

use App\Models\Absence;
use App\Models\AbsenceType;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;
use Mockery;

class AbsenceControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_returns_absences_filtered_by_company_id()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $absenceType = AbsenceType::factory()->create();

        $absence = Absence::factory()->create([
            'employee_id' => $employee->id,
            'absence_type_id' => $absenceType->id,
            'date' => now()->toDateString(),
        ]);

        Gate::shouldReceive('authorize')
            ->once()
            ->with('viewAnyForCompany', [Absence::class, $company->id])
            ->andReturn(true);

        $response = $this->getJson(route('absences.index', ['company_id' => $company->id]));

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'id' => $absence->id,
            'employee_id' => $employee->id,
            'absence_type_id' => $absenceType->id,
        ]);
    }

    /** @test */
    public function it_returns_absences_filtered_by_employee_id()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $employee = Employee::factory()->create();
        $absenceType = AbsenceType::factory()->create();

        $absence = Absence::factory()->create([
            'employee_id' => $employee->id,
            'absence_type_id' => $absenceType->id,
            'date' => now()->toDateString(),
        ]);

        Gate::shouldReceive('authorize')
            ->once()
            ->with('view', Mockery::on(function ($arg) use ($employee) {
                return $arg->id === $employee->id;
            }))
            ->andReturn(true);

        $response = $this->getJson(route('absences.index', ['employee_id' => $employee->id]));

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'id' => $absence->id,
            'employee_id' => $employee->id,
            'absence_type_id' => $absenceType->id,
        ]);
    }

    /** @test */
    public function it_returns_absences_filtered_by_date_range()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $employee = Employee::factory()->create();
        $absenceType = AbsenceType::factory()->create();

        $absence1 = Absence::factory()->create([
            'employee_id' => $employee->id,
            'absence_type_id' => $absenceType->id,
            'date' => now()->subDays(2)->toDateString(),
        ]);

        $absence2 = Absence::factory()->create([
            'employee_id' => $employee->id,
            'absence_type_id' => $absenceType->id,
            'date' => now()->toDateString(),
        ]);

        Gate::shouldReceive('authorize')
            ->once()
            ->with('viewAny', Absence::class)
            ->andReturn(true);

        $response = $this->getJson(route('absences.index', [
            'start_date' => now()->subDays(3)->toDateString(),
            'end_date' => now()->toDateString(),
        ]));

        $response->assertStatus(200);
        $response->assertJsonFragment(['id' => $absence1->id]);
        $response->assertJsonFragment(['id' => $absence2->id]);
    }
}