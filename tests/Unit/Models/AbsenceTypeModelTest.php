<?php

namespace Tests\Unit\Models;

use App\Models\Absence;
use App\Models\AbsenceRequest;
use App\Models\AbsenceType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AbsenceTypeModelTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_uses_uuid_as_primary_key()
    {
        $absenceType = AbsenceType::factory()->create();
        
        $this->assertTrue(Str::isUuid($absenceType->id));
        $this->assertFalse($absenceType->getIncrementing());
        $this->assertEquals('string', $absenceType->getKeyType());
    }

    /** @test */
    public function it_has_many_absences()
    {
        $absenceType = AbsenceType::factory()->create();
        
        // Create absences associated with the type
        Absence::factory()->count(3)->create([
            'absence_type_id' => $absenceType->id
        ]);
        
        $this->assertEquals(3, $absenceType->absences->count());
        $this->assertInstanceOf(Absence::class, $absenceType->absences->first());
    }

    /** @test */
    public function it_has_many_absence_requests()
    {
        $absenceType = AbsenceType::factory()->create();
        
        // Create absence requests associated with the type
        AbsenceRequest::factory()->count(3)->create([
            'absence_type_id' => $absenceType->id
        ]);
        
        $this->assertEquals(3, $absenceType->absenceRequests->count());
        $this->assertInstanceOf(AbsenceRequest::class, $absenceType->absenceRequests->first());
    }

    /** @test */
    public function it_can_scope_to_vacation_type()
    {
        AbsenceType::factory()->create(['code' => 'vacation']);
        AbsenceType::factory()->create(['code' => 'sick_leave']);
        
        $vacationTypes = AbsenceType::vacation()->get();
        
        $this->assertEquals(1, $vacationTypes->count());
        $this->assertEquals('vacation', $vacationTypes->first()->code);
    }

    /** @test */
    public function it_can_scope_to_sick_leave_type()
    {
        AbsenceType::factory()->create(['code' => 'vacation']);
        AbsenceType::factory()->create(['code' => 'sick_leave']);
        
        $sickLeaveTypes = AbsenceType::sickLeave()->get();
        
        $this->assertEquals(1, $sickLeaveTypes->count());
        $this->assertEquals('sick_leave', $sickLeaveTypes->first()->code);
    }

    /** @test */
    public function it_can_determine_if_is_vacation_type()
    {
        $vacationType = AbsenceType::factory()->create(['code' => 'vacation']);
        $sickLeaveType = AbsenceType::factory()->create(['code' => 'sick_leave']);
        
        $this->assertTrue($vacationType->isVacation());
        $this->assertFalse($sickLeaveType->isVacation());
    }

    /** @test */
    public function it_can_determine_if_is_sick_leave_type()
    {
        $vacationType = AbsenceType::factory()->create(['code' => 'vacation']);
        $sickLeaveType = AbsenceType::factory()->create(['code' => 'sick_leave']);
        
        $this->assertFalse($vacationType->isSickLeave());
        $this->assertTrue($sickLeaveType->isSickLeave());
    }

    /** @test */
    public function it_can_determine_if_requires_approval()
    {
        $requiresApproval = AbsenceType::factory()->create(['requires_approval' => true]);
        $noApproval = AbsenceType::factory()->create(['requires_approval' => false]);
        
        $this->assertTrue($requiresApproval->requiresApproval());
        $this->assertFalse($noApproval->requiresApproval());
    }

    /** @test */
    public function it_can_determine_if_affects_vacation_balance()
    {
        $affectsBalance = AbsenceType::factory()->create(['affects_vacation_balance' => true]);
        $noEffect = AbsenceType::factory()->create(['affects_vacation_balance' => false]);
        
        $this->assertTrue($affectsBalance->affectsVacationBalance());
        $this->assertFalse($noEffect->affectsVacationBalance());
    }

    /** @test */
    public function it_can_determine_if_is_paid()
    {
        $paidType = AbsenceType::factory()->create(['is_paid' => true]);
        $unpaidType = AbsenceType::factory()->create(['is_paid' => false]);
        
        $this->assertTrue($paidType->isPaid());
        $this->assertFalse($unpaidType->isPaid());
    }
}