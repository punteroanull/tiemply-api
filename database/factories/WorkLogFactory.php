<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WorkLog>
 */
class WorkLogFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = WorkLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d');
        $time = fake()->dateTimeBetween('06:00:00', '21:00:00')->format('H:i:s');
        
        return [
            'employee_id' => Employee::factory(),
            'date' => $date,
            'time' => $time,
            'type' => fake()->randomElement(['check_in', 'check_out']),
            'ip_address' => fake()->ipv4(),
            'location' => fake()->optional(0.3)->latitude() . ',' . fake()->longitude(), // 30% chance of having location
            'notes' => fake()->optional(0.1)->sentence(), // 10% chance of having notes
        ];
    }

    /**
     * Configure the factory for a check-in log.
     */
    public function checkIn(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'check_in',
            'time' => fake()->dateTimeBetween('07:00:00', '10:00:00')->format('H:i:s'),
        ]);
    }

    /**
     * Configure the factory for a check-out log.
     */
    public function checkOut(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'check_out',
            'time' => fake()->dateTimeBetween('16:00:00', '20:00:00')->format('H:i:s'),
        ]);
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

    /**
     * Configure the factory for a specific date.
     */
    public function forDate(string $date): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => $date,
        ]);
    }
}
