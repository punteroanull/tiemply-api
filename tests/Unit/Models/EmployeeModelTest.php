<?php

namespace Tests\Unit\Models;

use App\Models\Absence;
use App\Models\AbsenceRequest;
use App\Models\AbsenceType;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkLog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmployeeModelTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_uses_uuid_as_primary_key()
    {
        $employee = Employee::factory()->create();
        
        $this->assertTrue(Str::isUuid($employee->id));
        $this->assertFalse($employee->getIncrementing());
        $this->assertEquals('string', $employee->getKeyType());
    }

    /** @test */
    public function it_belongs_to_a_company()
    {
        $company = Company::factory()->create();
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $this->assertInstanceOf(Company::class, $employee->company);
        $this->assertEquals($company->id, $employee->company->id);
    }

    /** @test */
    public function it_belongs_to_a_user()
    {
        $user = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $employee->user);
        $this->assertEquals($user->id, $employee->user->id);
    }

    /** @test */
    public function it_has_many_work_logs()
    {
        $employee = Employee::factory()->create();
        
        // Create work logs
        WorkLog::factory()->count(3)->create([
            'employee_id' => $employee->id
        ]);
        
        $this->assertEquals(3, $employee->workLogs()->count());
        $this->assertInstanceOf(WorkLog::class, $employee->workLogs->first());
    }

    /** @test */
    public function it_has_many_absences()
    {
        $employee = Employee::factory()->create();
        
        // Create absences
        Absence::factory()->count(2)->create([
            'employee_id' => $employee->id
        ]);
        
        $this->assertEquals(2, $employee->absences()->count());
        $this->assertInstanceOf(Absence::class, $employee->absences->first());
    }

    /** @test */
    public function it_has_many_absence_requests()
    {
        $employee = Employee::factory()->create();
        
        // Create absence requests
        AbsenceRequest::factory()->count(2)->create([
            'employee_id' => $employee->id
        ]);
        
        $this->assertEquals(2, $employee->absenceRequests()->count());
        $this->assertInstanceOf(AbsenceRequest::class, $employee->absenceRequests->first());
    }

    /** @test */
    public function it_calculates_scheduled_hours_attribute()
    {
        $employee = Employee::factory()->create([
            'contract_start_time' => '09:00',
            'contract_end_time' => '17:00'
        ]);
        
        $this->assertEquals(8, $employee->scheduled_hours);
    }

    /** @test */
    public function it_returns_null_for_scheduled_hours_when_times_not_set()
    {
        $employee = Employee::factory()->create([
            'contract_start_time' => null,
            'contract_end_time' => null
        ]);
        
        $this->assertNull($employee->scheduled_hours);
    }

    /** @test */
    public function it_can_get_work_logs_for_specific_date()
    {
        $employee = Employee::factory()->create();
        $today = Carbon::today()->format('Y-m-d');
        $yesterday = Carbon::yesterday()->format('Y-m-d');
        
        // Create work logs for today
        WorkLog::factory()->count(2)->create([
            'employee_id' => $employee->id,
            'date' => $today
        ]);
        
        // Create work log for yesterday
        WorkLog::factory()->create([
            'employee_id' => $employee->id,
            'date' => $yesterday
        ]);
        
        $todayLogs = $employee->getWorkLogsForDate($today);
        
        $this->assertEquals(2, $todayLogs->count());
        $this->assertEquals($today, $todayLogs->first()->date->format('Y-m-d'));
    }

    /** @test */
    public function it_calculates_used_vacation_days_in_a_year()
    {
        $employee = Employee::factory()->create();
        $vacationType = AbsenceType::factory()->create(['code' => 'vacation']);
        $otherType = AbsenceType::factory()->create(['code' => 'sick_leave']);
        
        $currentYear = date('Y');
        $lastYear = (int)$currentYear - 1;
        
        // Create vacation absences for current year
        Absence::factory()->count(3)->create([
            'employee_id' => $employee->id,
            'absence_type_id' => $vacationType->id,
            'date' => Carbon::createFromDate($currentYear, 1, 1)->addDays(rand(1, 300))
        ]);
        
        // Create non-vacation absence for current year
        Absence::factory()->create([
            'employee_id' => $employee->id,
            'absence_type_id' => $otherType->id,
            'date' => Carbon::createFromDate($currentYear, 6, 15)
        ]);
        
        // Create vacation absence for last year
        Absence::factory()->create([
            'employee_id' => $employee->id,
            'absence_type_id' => $vacationType->id,
            'date' => Carbon::createFromDate($lastYear, 7, 10)
        ]);
        
        $this->assertEquals(3, $employee->getUsedVacationDays($currentYear));
        $this->assertEquals(1, $employee->getUsedVacationDays($lastYear));
    }
}