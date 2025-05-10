<?php

namespace Tests\Unit\Controllers;

use App\Http\Controllers\AbsenceRequestController;
use App\Models\AbsenceRequest;
use App\Models\AbsenceType;
use App\Models\Employee;
use App\Models\User;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;
use Carbon\Carbon;
use Mockery;

class AbsenceRequestControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $controller;
    protected $user;
    protected $company;
    protected $employee;
    protected $absenceType;
    protected $manager;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->controller = new AbsenceRequestController();
        
        // Mock Gate facade
        Gate::shouldReceive('authorize')->andReturn(true);
        
        // Create test data
        $this->user = User::factory()->create();
        $this->manager = User::factory()->create();
        $this->company = Company::factory()->create([
            'vacation_type' => 'business_days', 
            'max_vacation_days' => 22
        ]);
        $this->employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'remaining_vacation_days' => 20
        ]);
        $this->absenceType = AbsenceType::factory()->create([
            'requires_approval' => true, 
            'affects_vacation_balance' => true
        ]);
    }

    /** @test */
    public function it_can_list_absence_requests()
    {
        // Create some absence requests
        AbsenceRequest::factory()->count(3)->create([
            'employee_id' => $this->employee->id,
            'absence_type_id' => $this->absenceType->id
        ]);
        
        $request = new Request();
        
        $response = $this->controller->index($request);
        
        $this->assertEquals(200, $response->status());
        $this->assertCount(3, $response->getData(true));
    }

    /** @test */
    public function it_can_filter_requests_by_status()
    {
        // Create absence requests with different statuses
        AbsenceRequest::factory()->create([
            'employee_id' => $this->employee->id,
            'absence_type_id' => $this->absenceType->id,
            'status' => 'pending'
        ]);
        
        AbsenceRequest::factory()->create([
            'employee_id' => $this->employee->id,
            'absence_type_id' => $this->absenceType->id,
            'status' => 'approved'
        ]);
        
        AbsenceRequest::factory()->create([
            'employee_id' => $this->employee->id,
            'absence_type_id' => $this->absenceType->id,
            'status' => 'rejected'
        ]);
        
        $request = new Request(['status' => 'pending']);
        
        $response = $this->controller->index($request);
        
        $this->assertEquals(200, $response->status());
        $this->assertCount(1, $response->getData(true));
        $this->assertEquals('pending', $response->getData(true)[0]['status']);
    }

    /** @test */
    public function it_can_store_a_new_absence_request()
    {
        $startDate = Carbon::tomorrow();
        $endDate = Carbon::tomorrow()->addDays(5);
        
        $requestData = [
            'employee_id' => $this->employee->id,
            'absence_type_id' => $this->absenceType->id,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'is_partial' => false,
            'notes' => 'Vacation request'
        ];
        
        // Mock Gate::denies()
        Gate::shouldReceive('denies')
            ->once()
            ->with('createFor', Mockery::on(function ($args) {
                return $args[0] === AbsenceRequest::class && $args[1]->id === $this->employee->id;
            }))
            ->andReturn(false); // Simula que el acceso no está denegado 

        $request = new Request($requestData);
        
        $response = $this->controller->store($request);
        
        $this->assertEquals(201, $response->status());
        $this->assertEquals($this->employee->id, $response->getData(true)['employee_id']);
        $this->assertEquals($this->absenceType->id, $response->getData(true)['absence_type_id']);
        $this->assertEquals('pending', $response->getData(true)['status']);
        
        $this->assertDatabaseHas('absence_requests', [
            'employee_id' => $this->employee->id,
            'absence_type_id' => $this->absenceType->id,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
        ]);
    }

    /** @test */
    public function it_auto_approves_requests_that_dont_require_approval()
    {
        // Create an absence type that doesn't require approval
        $noApprovalType = AbsenceType::factory()->create([
            'requires_approval' => false, 
            'affects_vacation_balance' => false
        ]);
        
        $startDate = Carbon::tomorrow();
        $endDate = Carbon::tomorrow()->addDays(1);
        
        $requestData = [
            'employee_id' => $this->employee->id,
            'absence_type_id' => $noApprovalType->id,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'is_partial' => false
        ];
        
        Gate::shouldReceive('denies')
            ->once()
            ->with('createFor', Mockery::on(function ($args) {
                return $args[0] === AbsenceRequest::class && $args[1]->id === $this->employee->id;
            }))
            ->andReturn(false); // Simula que el acceso no está denegado
        $request = new Request($requestData);
        
        $response = $this->controller->store($request);
        
        $this->assertEquals(201, $response->status());
        $this->assertEquals('approved', $response->getData(true)['status']);
        
        // Check that absence records were created
        $this->assertDatabaseHas('absences', [
            'employee_id' => $this->employee->id,
            'absence_type_id' => $noApprovalType->id,
            'date' => $endDate->toDateString(),
        ]);
    }

    /** @test */
    public function it_validates_vacation_balance()
    {
        // Update employee to have few vacation days left
        $this->employee->remaining_vacation_days = 2;
        $this->employee->save();
        
        $startDate = Carbon::tomorrow();
        $endDate = Carbon::tomorrow()->addDays(10); // Request more days than available
        
        $requestData = [
            'employee_id' => $this->employee->id,
            'absence_type_id' => $this->absenceType->id,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'is_partial' => false
        ];
        
        Gate::shouldReceive('denies')
            ->once()
            ->with('createFor', Mockery::on(function ($args) {
                return $args[0] === AbsenceRequest::class && $args[1]->id === $this->employee->id;
            }))
            ->andReturn(false); // Simula que el acceso no está denegado

        $request = new Request($requestData);
        
        $response = $this->controller->store($request);
        
        $this->assertEquals(422, $response->status());
        $this->assertStringContainsString('Not enough vacation days', $response->getData(true)['message']);
    }

    /** @test */
    public function it_can_show_an_absence_request()
    {
        $absenceRequest = AbsenceRequest::factory()->create([
            'employee_id' => $this->employee->id,
            'absence_type_id' => $this->absenceType->id
        ]);
        
        $response = $this->controller->show($absenceRequest);
        
        $this->assertEquals(200, $response->status());
        $this->assertEquals($absenceRequest->id, $response->getData(true)['id']);
    }

    /** @test */
    public function it_can_update_a_pending_absence_request()
    {
        $absenceRequest = AbsenceRequest::factory()->create([
            'employee_id' => $this->employee->id,
            'absence_type_id' => $this->absenceType->id,
            'status' => 'pending',
            'notes' => 'Original notes'
        ]);
        
        $updateData = [
            'notes' => 'Updated notes'
        ];
        
        $request = new Request($updateData);
        
        $response = $this->controller->update($request, $absenceRequest);
        
        $this->assertEquals(200, $response->status());
        $this->assertEquals('Updated notes', $response->getData(true)['notes']);
        
        $this->assertDatabaseHas('absence_requests', [
            'id' => $absenceRequest->id,
            'notes' => 'Updated notes'
        ]);
    }

    /** @test */
    public function it_cannot_update_approved_or_rejected_request()
    {
        $absenceRequest = AbsenceRequest::factory()->create([
            'employee_id' => $this->employee->id,
            'absence_type_id' => $this->absenceType->id,
            'status' => 'approved',
            'notes' => 'Original notes'
        ]);
        
        $updateData = [
            'notes' => 'Updated notes'
        ];
        
        $request = new Request($updateData);
        
        $response = $this->controller->update($request, $absenceRequest);
        
        $this->assertEquals(422, $response->status());
        $this->assertStringContainsString('Only pending requests', $response->getData(true)['message']);
    }

    /** @test */
    public function it_can_approve_a_request()
    {
        $absenceRequest = AbsenceRequest::factory()->create([
            'employee_id' => $this->employee->id,
            'absence_type_id' => $this->absenceType->id,
            'status' => 'pending',
            'start_date' => Carbon::tomorrow()->toDateString(),
            'end_date' => Carbon::tomorrow()->addDays(2)->toDateString()
        ]);
        
        // This is testing the route handler, not a direct method call
        $request = new Request([
            'request_id' => $absenceRequest->id
        ]);
        
        $response = $this->controller->approve($request);
        
        // Refresh the model to get updated data
        $absenceRequest->refresh();
        
        $this->assertEquals(200, $response->status());
        $this->assertEquals('approved', $absenceRequest->status);
        
        // Check that absences were created
        $this->assertDatabaseHas('absences', [
            'employee_id' => $this->employee->id,
            'absence_type_id' => $this->absenceType->id,
            'request_id' => $absenceRequest->id
        ]);
        
        // Check that vacation balance was updated
        $this->employee->refresh();
        // This test could fail if the next day is a weekend or holiday
        $this->assertLessThan(20, $this->employee->remaining_vacation_days);
    }

    /** @test */
    public function it_can_reject_a_request()
    {
        $absenceRequest = AbsenceRequest::factory()->create([
            'employee_id' => $this->employee->id,
            'absence_type_id' => $this->absenceType->id,
            'status' => 'pending'
        ]);
        
        $request = new Request([
            'request_id' => $absenceRequest->id,
            'rejection_reason' => 'Business needs'
        ]);
        
        $response = $this->controller->reject($request);
        
        // Refresh the model to get updated data
        $absenceRequest->refresh();
        
        $this->assertEquals(200, $response->status());
        $this->assertEquals('rejected', $absenceRequest->status);
        $this->assertEquals('Business needs', $absenceRequest->rejection_reason);
    }

    /** @test */
    public function it_can_delete_a_pending_request()
    {
        $absenceRequest = AbsenceRequest::factory()->create([
            'employee_id' => $this->employee->id,
            'absence_type_id' => $this->absenceType->id,
            'status' => 'pending'
        ]);
        
        $response = $this->controller->destroy($absenceRequest);
        
        $this->assertEquals(204, $response->status());
        $this->assertDatabaseMissing('absence_requests', ['id' => $absenceRequest->id]);
    }

    /** @test */
    public function it_cannot_delete_approved_or_rejected_request()
    {
        $absenceRequest = AbsenceRequest::factory()->create([
            'employee_id' => $this->employee->id,
            'absence_type_id' => $this->absenceType->id,
            'status' => 'approved'
        ]);
        
        $response = $this->controller->destroy($absenceRequest);
        
        $this->assertEquals(422, $response->status());
        $this->assertStringContainsString('Only pending requests', $response->getData(true)['message']);
    }

    /** @test */
    public function it_can_get_requests_by_employee()
    {
        // Create absence requests for our test employee
        AbsenceRequest::factory()->count(3)->create([
            'employee_id' => $this->employee->id,
            'absence_type_id' => $this->absenceType->id
        ]);
        
        $response = $this->controller->byEmployee($this->employee);
        
        $this->assertEquals(200, $response->status());
        $this->assertCount(3, $response->getData(true));
    }

    /** @test */
    public function it_can_get_pending_requests_by_employee()
    {
        // Create absence requests with different statuses
        AbsenceRequest::factory()->create([
            'employee_id' => $this->employee->id,
            'absence_type_id' => $this->absenceType->id,
            'status' => 'pending'
        ]);
        
        AbsenceRequest::factory()->create([
            'employee_id' => $this->employee->id,
            'absence_type_id' => $this->absenceType->id,
            'status' => 'approved'
        ]);
        
        $response = $this->controller->pendingByEmployee($this->employee);
        
        $this->assertEquals(200, $response->status());
        $this->assertCount(1, $response->getData(true));
        $this->assertEquals('pending', $response->getData(true)[0]['status']);
    }

    /** @test */
    public function it_can_get_requests_by_company()
    {
        // Create absence requests for our test employee
        AbsenceRequest::factory()->count(3)->create([
            'employee_id' => $this->employee->id,
            'absence_type_id' => $this->absenceType->id
        ]);
        
        $response = $this->controller->byCompany($this->company);
        
        $this->assertEquals(200, $response->status());
        $this->assertCount(3, $response->getData(true));
    }

    /** @test */
    public function it_can_get_pending_requests_by_company()
    {
        // Create absence requests with different statuses
        AbsenceRequest::factory()->create([
            'employee_id' => $this->employee->id,
            'absence_type_id' => $this->absenceType->id,
            'status' => 'pending'
        ]);
        
        AbsenceRequest::factory()->create([
            'employee_id' => $this->employee->id,
            'absence_type_id' => $this->absenceType->id,
            'status' => 'approved'
        ]);
        
        $response = $this->controller->pendingByCompany($this->company);
        
        $this->assertEquals(200, $response->status());
        $this->assertCount(1, $response->getData(true));
        $this->assertEquals('pending', $response->getData(true)[0]['status']);
    }
}