<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AbsenceType>
 */
class AbsenceTypeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = AbsenceType::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'code' => fake()->unique()->word(),
            'description' => fake()->sentence(),
            'requires_approval' => fake()->boolean(80),
            'affects_vacation_balance' => fake()->boolean(30),
            'is_paid' => fake()->boolean(70),
        ];
    }

    /**
     * Configure the factory for a vacation type.
     */
    public function vacation(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Vacation',
            'code' => 'vacation',
            'description' => 'Paid time off for rest and leisure',
            'requires_approval' => true,
            'affects_vacation_balance' => true,
            'is_paid' => true,
        ]);
    }

    /**
     * Configure the factory for a sick leave type.
     */
    public function sickLeave(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Sick Leave',
            'code' => 'sick_leave',
            'description' => 'Time off due to illness or injury',
            'requires_approval' => false,
            'affects_vacation_balance' => false,
            'is_paid' => true,
        ]);
    }

    /**
     * Configure the factory for unpaid leave.
     */
    public function unpaidLeave(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Unpaid Leave',
            'code' => 'unpaid_leave',
            'description' => 'Time off without pay',
            'requires_approval' => true,
            'affects_vacation_balance' => false,
            'is_paid' => false,
        ]);
    }

    /**
     * Configure the factory for maternity/paternity leave.
     */
    public function parentalLeave(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Parental Leave',
            'code' => 'parental_leave',
            'description' => 'Time off for the birth or adoption of a child',
            'requires_approval' => true,
            'affects_vacation_balance' => false,
            'is_paid' => true,
        ]);
    }
}
