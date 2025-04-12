<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Employee>
 */
class EmployeeFactory extends Factory
{
/**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Employee::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Generate random start time between 7:00 and 10:00
        $startHour = fake()->numberBetween(7, 10);
        $startMinute = fake()->randomElement(['00', '15', '30', '45']);
        $startTime = sprintf('%02d:%s', $startHour, $startMinute);
        
        // Generate end time 8 hours after start time
        $endTime = Carbon::createFromFormat('H:i', $startTime)->addHours(8)->format('H:i');
        
        return [
            'company_id' => Company::factory(),
            'user_id' => User::factory(),
            'contract_start_time' => $startTime,
            'contract_end_time' => $endTime,
            'remaining_vacation_days' => fake()->numberBetween(10, 25),
            'active' => true,
        ];
    }

    /**
     * Configure the factory to use existing company and user IDs.
     */
    public function forCompanyAndUser(string $companyId, string $userId): static
    {
        return $this->state(fn (array $attributes) => [
            'company_id' => $companyId,
            'user_id' => $userId,
        ]);
    }

    /**
     * Configure the factory for a standard 9 to 5 schedule.
     */
    public function standardSchedule(): static
    {
        return $this->state(fn (array $attributes) => [
            'contract_start_time' => '09:00',
            'contract_end_time' => '17:00',
            'remaining_vacation_days' => 22,
        ]);
    }

    /**
     * Configure the factory for a morning shift.
     */
    public function morningShift(): static
    {
        return $this->state(fn (array $attributes) => [
            'contract_start_time' => '06:00',
            'contract_end_time' => '14:00',
        ]);
    }

    /**
     * Configure the factory for an afternoon shift.
     */
    public function afternoonShift(): static
    {
        return $this->state(fn (array $attributes) => [
            'contract_start_time' => '14:00',
            'contract_end_time' => '22:00',
        ]);
    }
}
