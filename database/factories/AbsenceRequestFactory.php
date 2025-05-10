<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\AbsenceRequest;
use App\Models\AbsenceType;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Carbon;


/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AbsenceRequest>
 */
class AbsenceRequestFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = AbsenceRequest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('+1 week', '+2 months')->format('Y-m-d');
        $endDate = Carbon::parse($startDate)->addDays(fake()->numberBetween(1, 10))->format('Y-m-d');
        
        return [
            'employee_id' => Employee::factory(),
            'absence_type_id' => AbsenceType::factory(),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'is_partial' => false,
            'start_time' => null,
            'end_time' => null,
            'status' => 'pending',
            'reviewed_by' => null,
            'notes' => fake()->optional(0.7)->sentence(), // 70% chance of having notes
            'rejection_reason' => null,
            'reviewed_at' => null,
        ];
    }

    /**
     * Configure the factory for an approved request.
     */
    public function approved(): static
    {
        return $this->state(function (array $attributes) {
            // Find a manager to be the reviewer
            $manager = User::whereHas('role', function ($query) {
                $query->where('name', 'Manager');
            })->first();
            
            return [
                'status' => 'approved',
                'reviewed_by' => $manager ? $manager->id : null,
                'reviewed_at' => Carbon::now(),
            ];
        });
    }

    /**
     * Configure the factory for a rejected request.
     */
    public function rejected(): static
    {
        return $this->state(function (array $attributes) {
            // Find a manager to be the reviewer
            $manager = User::whereHas('role', function ($query) {
                $query->where('name', 'Manager');
            })->first();
            
            return [
                'status' => 'rejected',
                'reviewed_by' => $manager ? $manager->id : null,
                'reviewed_at' => Carbon::now(),
                'rejection_reason' => fake()->randomElement([
                    'Too many employees already on leave during this period.',
                    'Insufficient vacation days remaining.',
                    'Critical project deadlines during requested period.',
                    'Request received too late.',
                    'Need more information to approve this request.'
                ]),
            ];
        });
    }

    /**
     * Configure the factory for a partial day request.
     */
    public function partialDay(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_partial' => true,
            'start_time' => fake()->randomElement(['09:00', '10:00', '11:00', '12:00']),
            'end_time' => fake()->randomElement(['13:00', '14:00', '15:00', '16:00', '17:00']),
            'end_date' => $attributes['start_date'], // Same day for partial requests
        ]);
    }

    /**
     * Configure the factory for a vacation request.
     */
    public function vacation(): static
    {
        return $this->state(function (array $attributes) {
            $vacationType = AbsenceType::where('code', 'vacation')->first();
            
            return [
                'absence_type_id' => $vacationType ? $vacationType->id : AbsenceType::factory()->vacation(),
                'notes' => 'Vacation time off',
            ];
        });
    }

    /**
     * Configure the factory for a sick leave request.
     */
    public function sickLeave(): static
    {
        return $this->state(function (array $attributes) {
            $sickLeaveType = AbsenceType::where('code', 'sick_leave')->first();
            $startDate = fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d');
            $endDate = Carbon::parse($startDate)->addDays(fake()->numberBetween(1, 5))->format('Y-m-d');
            
            return [
                'absence_type_id' => $sickLeaveType ? $sickLeaveType->id : AbsenceType::factory()->sickLeave(),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'notes' => 'Sick leave due to illness',
            ];
        });
    }
}
