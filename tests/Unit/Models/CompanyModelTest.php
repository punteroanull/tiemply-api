<?php

namespace Tests\Unit\Models;

use App\Models\Absence;
use App\Models\AbsenceRequest;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CompanyModelTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_uses_uuid_as_primary_key()
    {
        $company = Company::factory()->create();
        
        $this->assertTrue(Str::isUuid($company->id));
        $this->assertFalse($company->getIncrementing());
        $this->assertEquals('string', $company->getKeyType());
    }

    /** @test */
    public function it_has_many_employees()
    {
        $company = Company::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        Employee::factory()->create([
            'company_id' => $company->id,
            'user_id' => $user1->id
        ]);
        
        Employee::factory()->create([
            'company_id' => $company->id,
            'user_id' => $user2->id
        ]);
        
        $this->assertEquals(2, $company->employees->count());
        $this->assertInstanceOf(Employee::class, $company->employees->first());
    }

    /** @test */
    public function it_has_access_to_users_through_employees()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        
        Employee::factory()->create([
            'company_id' => $company->id,
            'user_id' => $user->id
        ]);
        
        $this->assertEquals(1, $company->users()->count());
        $this->assertEquals($user->id, $company->users()->first()->id);
    }

    /** @test */
    public function it_has_many_absences_through_employees()
    {
        // Create test data
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'user_id' => $user->id
        ]);
        
        // Create two absences for the employee
        Absence::factory()->count(2)->create([
            'employee_id' => $employee->id
        ]);
        
        // Check if company can access absences
        $this->assertEquals(2, $company->absences()->count());
        $this->assertInstanceOf(Absence::class, $company->absences()->first());
    }

    /** @test */
    public function it_has_many_absence_requests_through_employees()
    {
        // Create test data
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'user_id' => $user->id
        ]);
        
        // Create two absence requests for the employee
        AbsenceRequest::factory()->count(2)->create([
            'employee_id' => $employee->id
        ]);
        
        // Check if company can access absence requests
        $this->assertEquals(2, $company->absenceRequests()->count());
        $this->assertInstanceOf(AbsenceRequest::class, $company->absenceRequests()->first());
    }

    /** @test */
    public function it_can_determine_if_uses_business_days()
    {
        $businessDaysCompany = Company::factory()->create(['vacation_type' => 'business_days']);
        $calendarDaysCompany = Company::factory()->create(['vacation_type' => 'calendar_days']);
        
        $this->assertTrue($businessDaysCompany->usesBusinessDays());
        $this->assertFalse($calendarDaysCompany->usesBusinessDays());
    }

    /** @test */
    public function it_can_determine_if_uses_calendar_days()
    {
        $businessDaysCompany = Company::factory()->create(['vacation_type' => 'business_days']);
        $calendarDaysCompany = Company::factory()->create(['vacation_type' => 'calendar_days']);
        
        $this->assertFalse($businessDaysCompany->usesCalendarDays());
        $this->assertTrue($calendarDaysCompany->usesCalendarDays());
    }
}