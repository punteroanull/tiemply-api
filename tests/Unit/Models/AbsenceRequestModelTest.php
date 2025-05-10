<?php

namespace Tests\Unit\Models;

use App\Models\AbsenceRequest;
use App\Models\AbsenceType;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AbsenceRequestModelTest extends TestCase
{
    use RefreshDatabase;

    private $employee;
    private $absenceType;

    protected function setUp(): void
    {
        parent::setUp();
        
        $user = User::factory()->create();
        $company = Company::factory()->create(['vacation_type' => 'business_days']);
        $this->employee = Employee::factory()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'remaining_vacation_days' => 20
        ]);
        
        $this->absenceType = AbsenceType::factory()->create([
            'code' => 'vacation',
            'affects_vacation_balance' => true
        ]);
    }

    /** @test */
    public function it_uses_uuid_as_primary_key()
    {
        $request = AbsenceRequest::factory()->create();
        
        $this->assertTrue(Str::isUuid($request->id));
        $this->assertFalse($request->getIncrementing());
        $this->assertEquals('string', $request->getKeyType());
    }

    /** @test */
    public function it_belongs_to_an_employee()
    {
        $request = AbsenceRequest::factory()->create([
            'employee_id' => $this->employee->id
        ]);

        $this->assertInstanceOf(Employee::class, $request->employee);
        $this->assertEquals($this->employee->id, $request->employee->id);
    }

    /** @test */
    public function it_belongs_to_an_absence_type()
    {
        $request = AbsenceRequest::factory()->create([
            'absence_type_id' => $this->absenceType->id
        ]);

        $this->assertInstanceOf(AbsenceType::class, $request->absenceType);
        $this->assertEquals($this->absenceType->id, $request->absenceType->id);
    }

    /** @test */
    public function it_belongs_to_a_reviewer()
    {
        $user = User::factory()->create();
        
        $request = AbsenceRequest::factory()->create([
            'reviewed_by' => $user->id
        ]);

        $this->assertInstanceOf(User::class, $request->reviewer);
        $this->assertEquals($user->id, $request->reviewer->id);
    }

    /** @test */
    public function it_calculates_days_count_for_business_days()
    {
        // Create company with business_days vacation type
        $company = Company::factory()->create(['vacation_type' => 'business_days']);
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        
        // Create a request spanning a week (including weekend)
        $request = AbsenceRequest::factory()->create([
            'employee_id' => $employee->id,
            'start_date' => '2023-05-08', // Monday
            'end_date' => '2023-05-14',   // Sunday
        ]);
        
        // Should only count 5 days (Monday-Friday)
        $this->assertEquals(5, $request->days_count);
    }

    /** @test */
    public function it_calculates_days_count_for_calendar_days()
    {
        // Create company with calendar_days vacation type
        $company = Company::factory()->create(['vacation_type' => 'calendar_days']);
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        
        // Create a request spanning a week
        $request = AbsenceRequest::factory()->create([
            'employee_id' => $employee->id,
            'start_date' => '2023-05-08', // Monday
            'end_date' => '2023-05-14',   // Sunday
        ]);
        
        // Should count all 7 days
        $this->assertEquals(7, $request->days_count);
    }

    /** @test */
    public function it_can_scope_to_pending_requests()
    {
        AbsenceRequest::factory()->create(['status' => 'pending']);
        AbsenceRequest::factory()->create(['status' => 'approved']);
        
        $pending = AbsenceRequest::pending()->get();
        
        $this->assertEquals(1, $pending->count());
        $this->assertEquals('pending', $pending->first()->status);
    }

    /** @test */
    public function it_can_scope_to_approved_requests()
    {
        AbsenceRequest::factory()->create(['status' => 'pending']);
        AbsenceRequest::factory()->create(['status' => 'approved']);
        
        $approved = AbsenceRequest::approved()->get();
        
        $this->assertEquals(1, $approved->count());
        $this->assertEquals('approved', $approved->first()->status);
    }

    /** @test */
    public function it_can_scope_to_rejected_requests()
    {
        AbsenceRequest::factory()->create(['status' => 'pending']);
        AbsenceRequest::factory()->create(['status' => 'rejected']);
        
        $rejected = AbsenceRequest::rejected()->get();
        
        $this->assertEquals(1, $rejected->count());
        $this->assertEquals('rejected', $rejected->first()->status);
    }

    /** @test */
    public function it_can_determine_if_request_is_pending()
    {
        $pendingRequest = AbsenceRequest::factory()->create(['status' => 'pending']);
        $approvedRequest = AbsenceRequest::factory()->create(['status' => 'approved']);
        
        $this->assertTrue($pendingRequest->isPending());
        $this->assertFalse($approvedRequest->isPending());
    }

    /** @test */
    public function it_can_determine_if_request_is_approved()
    {
        $pendingRequest = AbsenceRequest::factory()->create(['status' => 'pending']);
        $approvedRequest = AbsenceRequest::factory()->create(['status' => 'approved']);
        
        $this->assertFalse($pendingRequest->isApproved());
        $this->assertTrue($approvedRequest->isApproved());
    }

    /** @test */
    public function it_can_determine_if_request_is_rejected()
    {
        $pendingRequest = AbsenceRequest::factory()->create(['status' => 'pending']);
        $rejectedRequest = AbsenceRequest::factory()->create(['status' => 'rejected']);
        
        $this->assertFalse($pendingRequest->isRejected());
        $this->assertTrue($rejectedRequest->isRejected());
    }

    /** @test */
    public function it_determines_if_request_needs_minimum_notice()
    {
        // Vacation request for multiple days should need minimum notice
        $vacationRequest = AbsenceRequest::factory()->create([
            'employee_id' => $this->employee->id,
            'absence_type_id' => $this->absenceType->id,
            'start_date' => Carbon::tomorrow(),
            'end_date' => Carbon::tomorrow()->addDays(5)
        ]);
        
        // Single day vacation shouldn't require minimum notice
        $singleDayRequest = AbsenceRequest::factory()->create([
            'employee_id' => $this->employee->id,
            'absence_type_id' => $this->absenceType->id,
            'start_date' => Carbon::tomorrow(),
            'end_date' => Carbon::tomorrow()
        ]);
        
        // Non-vacation request shouldn't require minimum notice
        $otherType = AbsenceType::factory()->create(['code' => 'sick_leave']);
        $sickLeaveRequest = AbsenceRequest::factory()->create([
            'employee_id' => $this->employee->id,
            'absence_type_id' => $otherType->id,
            'start_date' => Carbon::tomorrow(),
            'end_date' => Carbon::tomorrow()->addDays(5)
        ]);
        
        $this->assertTrue($vacationRequest->needsMinimumNotice());
        $this->assertFalse($singleDayRequest->needsMinimumNotice());
        $this->assertFalse($sickLeaveRequest->needsMinimumNotice());
    }

    /** @test */
    public function it_determines_if_request_meets_consecutive_days_requirement()
    {
        // Create company with calendar_days vacation type
        $calendarDaysCompany = Company::factory()->create(['vacation_type' => 'calendar_days']);
        $calendarDaysEmployee = Employee::factory()->create(['company_id' => $calendarDaysCompany->id]);
        
        // Vacation request for 7+ days meets requirement
        $longRequest = AbsenceRequest::factory()->create([
            'employee_id' => $calendarDaysEmployee->id,
            'absence_type_id' => $this->absenceType->id,
            'start_date' => Carbon::tomorrow(),
            'end_date' => Carbon::tomorrow()->addDays(7)
        ]);
        
        // Vacation request for less than 7 days doesn't meet requirement
        $shortRequest = AbsenceRequest::factory()->create([
            'employee_id' => $calendarDaysEmployee->id,
            'absence_type_id' => $this->absenceType->id,
            'start_date' => Carbon::tomorrow(),
            'end_date' => Carbon::tomorrow()->addDays(5)
        ]);
        
        // For business days, there's no minimum consecutive days
        $businessDaysRequest = AbsenceRequest::factory()->create([
            'employee_id' => $this->employee->id, // Uses business_days company
            'absence_type_id' => $this->absenceType->id,
            'start_date' => Carbon::tomorrow(),
            'end_date' => Carbon::tomorrow()->addDays(3)
        ]);
        
        $this->assertTrue($longRequest->meetsConsecutiveDaysRequirement());
        $this->assertFalse($shortRequest->meetsConsecutiveDaysRequirement());
        $this->assertTrue($businessDaysRequest->meetsConsecutiveDaysRequirement());
    }

    /** @test */
    public function it_determines_if_employee_has_enough_vacation_days()
    {
        // Create employee with 10 remaining vacation days
        $employee = Employee::factory()->create([
            'remaining_vacation_days' => 10
        ]);
        
        // Request for 5 days (employee has enough)
        $validRequest = AbsenceRequest::factory()->create([
            'employee_id' => $employee->id,
            'absence_type_id' => $this->absenceType->id,
            'start_date' => '2023-05-08', // Monday
            'end_date' => '2023-05-12'    // Friday
        ]);
        
        // Request for 15 days (employee doesn't have enough)
        $invalidRequest = AbsenceRequest::factory()->create([
            'employee_id' => $employee->id,
            'absence_type_id' => $this->absenceType->id,
            'start_date' => '2023-05-08',  // Monday
            'end_date' => '2023-05-26'     // Friday in 3 weeks
        ]);
        
        // Non-vacation type request doesn't check balance
        $otherType = AbsenceType::factory()->create([
            'code' => 'sick_leave',
            'affects_vacation_balance' => false
        ]);
        
        $otherRequest = AbsenceRequest::factory()->create([
            'employee_id' => $employee->id,
            'absence_type_id' => $otherType->id,
            'start_date' => '2023-05-08',
            'end_date' => '2023-05-26'
        ]);
        
        $this->assertTrue($validRequest->hasEnoughVacationDays());
        $this->assertFalse($invalidRequest->hasEnoughVacationDays());
        $this->assertTrue($otherRequest->hasEnoughVacationDays());
    }

    /** @test */
    public function it_can_approve_request()
    {
        $reviewer = User::factory()->create();
        $request = AbsenceRequest::factory()->create([
            'employee_id' => $this->employee->id,
            'absence_type_id' => $this->absenceType->id,
            'status' => 'pending'
        ]);
        
        // Start DB transaction to prevent actual database changes
        $this->beginDatabaseTransaction();
        
        // Test approve method
        $result = $request->approve($reviewer->id);
        
        $this->assertTrue($result);
        $this->assertEquals('approved', $request->status);
        $this->assertEquals($reviewer->id, $request->reviewed_by);
        $this->assertNotNull($request->reviewed_at);
    }

    /** @test */
    public function it_can_reject_request()
    {
        $reviewer = User::factory()->create();
        $request = AbsenceRequest::factory()->create([
            'employee_id' => $this->employee->id,
            'absence_type_id' => $this->absenceType->id,
            'status' => 'pending'
        ]);
        
        $reason = 'Request rejected due to overlapping team absences';
        
        // Test reject method
        $result = $request->reject($reviewer->id, $reason);
        
        $this->assertTrue($result);
        $this->assertEquals('rejected', $request->status);
        $this->assertEquals($reviewer->id, $request->reviewed_by);
        $this->assertEquals($reason, $request->rejection_reason);
        $this->assertNotNull($request->reviewed_at);
    }

    /** @test */
    public function it_cannot_approve_or_reject_non_pending_requests()
    {
        $reviewer = User::factory()->create();
        
        // Create already approved request
        $approvedRequest = AbsenceRequest::factory()->create([
            'status' => 'approved',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now()
        ]);
        
        // Attempt to approve again
        $result = $approvedRequest->approve($reviewer->id);
        
        $this->assertFalse($result);
        
        // Attempt to reject
        $result = $approvedRequest->reject($reviewer->id, 'Changed my mind');
        
        $this->assertFalse($result);
    }
}