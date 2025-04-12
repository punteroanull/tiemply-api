<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Absence>
 */
class AbsenceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Absence::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'absence_type_id' => AbsenceType::factory(),
            'request_id' => null, // Not linked to a request by default
            'date' => fake()->dateTimeBetween('-3 months', '+1 month')->format('Y-m-d'),
            'is_partial' => false,
            'start_time' => null,
            'end_time' => null,
            'notes' => fake()->optional(0.5)->sentence(), // 50% chance of having notes
        ];
    }

    /**
     * Configure the factory for a linked absence request.
     */
    public function fromRequest(string $requestId): static
    {
        return $this->state(function (array $attributes) use ($requestId) {
            $request = AbsenceRequest::find($requestId);
            
            if (!$request) {
                return $attributes;
            }
            
            return [
                'employee_id' => $request->employee_id,
                'absence_type_id' => $request->absence_type_id,
                'request_id' => $requestId,
                'is_partial' => $request->is_partial,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'notes' => $request->notes,
            ];
        });
    }

    /**
     * Configure the factory for a partial day absence.
     */
    public function partialDay(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_partial' => true,
            'start_time' => fake()->randomElement(['09:00', '10:00', '11:00', '12:00']),
            'end_time' => fake()->randomElement(['13:00', '14:00', '15:00', '16:00', '17:00']),
        ]);
    }

    /**
     * Configure the factory for a vacation absence.
     */
    public function vacation(): static
    {
        return $this->state(function (array $attributes) {
            $vacationType = AbsenceType::where('code', 'vacation')->first();
            
            return [
                'absence_type_id' => $vacationType ? $vacationType->id : AbsenceType::factory()->vacation(),
                'notes' => 'Vacation day',
            ];
        });
    }

    /**
     * Configure the factory for a sick leave absence.
     */
    public function sickLeave(): static
    {
        return $this->state(function (array $attributes) {
            $sickLeaveType = AbsenceType::where('code', 'sick_leave')->first();
            
            return [
                'absence_type_id' => $sickLeaveType ? $sickLeaveType->id : AbsenceType::factory()->sickLeave(),
                'notes' => 'Sick day due to illness',
            ];
        });
    }

    /**
     * Configure the factory for a specific employee.
     */
    public function forEmployee(string $employeeId): static
    {
        return $this->state(fn (array $attributes) => [
            'employee_id' => $employeeId,
        ]);
    }
}
