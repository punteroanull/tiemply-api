<?php

namespace Tests\Unit\Controllers;

use App\Http\Controllers\WorkLogController;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Mockery;

class WorkLogControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $controller;
    protected $employee;
    protected $user;

    public function setUp(): void
    {
        parent::setUp();
        
        $this->controller = new WorkLogController();
        
        // Create a user and employee for testing
        $this->user = User::factory()->create();
        $this->employee = Employee::factory()->create([
            'user_id' => $this->user->id,
            'contract_start_time' => '09:00',
            'contract_end_time' => '17:00'
        ]);
        
        // Mock Gate facade to always authorize actions
        Gate::shouldReceive('authorize')->andReturn(true);
    }

    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testCheckIn()
    {
        // Mock Gate::denies() para que siempre devuelva false
        Gate::shouldReceive('denies')
            ->once()
            ->with('createFor', Mockery::on(function ($args) {
                return $args[0] === WorkLog::class && $args[1]->id === $this->employee->id;
            }))
            ->andReturn(false);

        $request = new Request([
            'employee_id' => $this->employee->id,
            'category' => 'shift_start',
            'notes' => 'Test check-in'
        ]);
        
        $request->setUserResolver(function () {
            return $this->user;
        });
        
        $response = $this->controller->checkIn($request);
        
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertArrayHasKey('work_log', json_decode($response->getContent(), true));
        $this->assertEquals('check_in', json_decode($response->getContent(), true)['work_log']['type']);
    }

    public function testCheckOut()
    {
        // Mock Gate::denies() para que siempre devuelva false
        Gate::shouldReceive('denies')
            ->once()
            ->with('createFor', Mockery::on(function ($args) {
                return $args[0] === WorkLog::class && $args[1]->id === $this->employee->id;
            }))
            ->andReturn(false);

        // Primero crea un registro de check-in
        WorkLog::create([
            'employee_id' => $this->employee->id,
            'date' => Carbon::today()->toDateString(),
            'time' => Carbon::now()->subHours(1)->toTimeString(),
            'type' => 'check_in',
            'category' => 'shift_start',
        ]);
        
        $request = new Request([
            'employee_id' => $this->employee->id,
            'category' => 'shift_end',
            'notes' => 'Test check-out'
        ]);
        
        $request->setUserResolver(function () {
            return $this->user;
        });
        
        $response = $this->controller->checkOut($request);
        
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertArrayHasKey('work_log', json_decode($response->getContent(), true));
        $this->assertEquals('check_out', json_decode($response->getContent(), true)['work_log']['type']);
    }

    public function testGetEmployeeStatus()
    {
        // Create check-in record
        WorkLog::create([
            'employee_id' => $this->employee->id,
            'date' => Carbon::today()->toDateString(),
            'time' => Carbon::now()->subHours(1)->toTimeString(),
            'type' => 'check_in',
            'category' => 'shift_start',
        ]);
        
        $response = $this->controller->getEmployeeStatus($this->employee->id);
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('in', $data['status']);
        $this->assertEquals($this->employee->id, $data['employee']['id']);
    }

    public function testDailyReport()
    {
        $today = Carbon::today()->toDateString();

        // Create some work logs for today
        WorkLog::create([
            'employee_id' => $this->employee->id,
            'date' => $today,
            'time' => '09:00:00',
            'type' => 'check_in',
            'category' => 'shift_start',
        ]);
        
        WorkLog::create([
            'employee_id' => $this->employee->id,
            'date' => $today,
            'time' => '17:00:00',
            'type' => 'check_out',
            'category' => 'shift_end',
        ]);
        
        $response = $this->controller->dailyReport($this->employee->id);
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        //dd($data);
        $this->assertEquals($this->employee->id, $data['employee']['id']);
        $this->assertEquals('completed', $data['status']);
        $this->assertArrayHasKey('total_work_time', $data);
    }

    public function testWeeklyReport()
    {
        // Create some work logs for this week
        WorkLog::create([
            'employee_id' => $this->employee->id,
            'date' => Carbon::today()->toDateString(),
            'time' => '09:00:00',
            'type' => 'check_in',
            'category' => 'shift_start',
        ]);
        
        WorkLog::create([
            'employee_id' => $this->employee->id,
            'date' => Carbon::today()->toDateString(),
            'time' => '17:00:00',
            'type' => 'check_out',
            'category' => 'shift_end',
        ]);
        
        $response = $this->controller->weeklyReport($this->employee->id);
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals($this->employee->id, $data['employee']['id']);
        $this->assertArrayHasKey('days', $data);
        $this->assertArrayHasKey('summary', $data);
    }

    public function testMonthlyReport()
    {
        // Create some work logs for this month
        WorkLog::create([
            'employee_id' => $this->employee->id,
            'date' => Carbon::today()->toDateString(),
            'time' => '09:00:00',
            'type' => 'check_in',
            'category' => 'shift_start',
        ]);
        
        WorkLog::create([
            'employee_id' => $this->employee->id,
            'date' => Carbon::today()->toDateString(),
            'time' => '17:00:00',
            'type' => 'check_out',
            'category' => 'shift_end',
        ]);
        
        $year = Carbon::now()->year;
        $month = Carbon::now()->month;
        
        $response = $this->controller->monthlyReport($this->employee->id, $year, $month);
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals($this->employee->id, $data['employee']['id']);
        $this->assertEquals($year, $data['year']);
        $this->assertEquals($month, $data['month']);
        $this->assertArrayHasKey('days', $data);
        $this->assertArrayHasKey('summary', $data);
    }
}