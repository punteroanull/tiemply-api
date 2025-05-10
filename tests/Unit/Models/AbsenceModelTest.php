<?php

namespace Tests\Unit\Models;

use App\Models\Absence;
use App\Models\AbsenceRequest;
use App\Models\AbsenceType;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;


class AbsenceModelTest extends TestCase
{
    use RefreshDatabase;

    private $employee;
    private $absenceType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->employee = Employee::factory()->create();
        $this->absenceType = AbsenceType::factory()->create([
            'code' => 'vacation',
        ]);
    }

    /** @test */
    public function it_belongs_to_an_employee()
    {
        $absence = Absence::factory()->create([
            'employee_id' => $this->employee->id,
        ]);

        $this->assertInstanceOf(Employee::class, $absence->employee);
        $this->assertEquals($this->employee->id, $absence->employee->id);
    }

    /** @test */
    public function it_belongs_to_an_absence_type()
    {
        $absence = Absence::factory()->create([
            'absence_type_id' => $this->absenceType->id,
        ]);

        $this->assertInstanceOf(AbsenceType::class, $absence->absenceType);
        $this->assertEquals($this->absenceType->id, $absence->absenceType->id);
    }

    /** @test */
    public function it_can_be_linked_to_an_absence_request()
    {
        $absenceRequest = AbsenceRequest::factory()->create([
            'employee_id' => $this->employee->id,
        ]);

        $absence = Absence::factory()->create([
            'employee_id' => $this->employee->id,
            'request_id' => $absenceRequest->id,
        ]);

        $this->assertInstanceOf(AbsenceRequest::class, $absence->request);
        $this->assertEquals($absenceRequest->id, $absence->request->id);
    }

    /** @test */
    public function it_calculates_duration_in_hours_for_partial_absences()
    {
        $absence = Absence::factory()->create([
            'is_partial' => true,
            'start_time' => Carbon::parse('09:00'),
            'end_time' => Carbon::parse('17:00'),
        ]);

        $this->assertEquals(8, $absence->duration_hours);
    }

    /** @test */
    public function it_checks_if_absence_is_vacation()
    {
        $absence = Absence::factory()->create([
            'absence_type_id' => $this->absenceType->id,
        ]);

        $this->assertTrue($absence->isVacation());
    }

    /** @test */
    public function it_checks_if_absence_is_full_day()
    {
        $absence = Absence::factory()->create([
            'is_partial' => false,
        ]);

        $this->assertTrue($absence->isFullDay());
    }

    /** @test */
    public function it_filters_absences_by_type()
    {
        Absence::factory()->create([
            'absence_type_id' => $this->absenceType->id,
        ]);

        $absences = Absence::ofType('vacation')->get();

        $this->assertCount(1, $absences);
        $this->assertEquals('vacation', $absences->first()->absenceType->code);
    }

    /** @test */
    public function it_filters_absences_by_date_range()
    {
        Absence::factory()->create([
            'date' => Carbon::parse('2023-05-01'),
        ]);

        Absence::factory()->create([
            'date' => Carbon::parse('2023-05-10'),
        ]);

        $absences = Absence::dateRange('2023-05-01', '2023-05-05')->get();

        $this->assertCount(1, $absences);
        $this->assertEquals('2023-05-01', $absences->first()->date->toDateString());
    }
}