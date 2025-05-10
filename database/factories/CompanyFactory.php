<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Company;
/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Company>
 */
class CompanyFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Company::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $vacationType = fake()->randomElement(['business_days', 'calendar_days']);
        
        return [
            'name' => fake()->company(),
            'tax_id' => 'B' . fake()->unique()->numerify('#########'),
            'contact_email' => fake()->companyEmail(),
            'contact_person' => fake()->name(),
            'address' => fake()->address(),
            'phone' => fake()->phoneNumber(),
            'vacation_type' => $vacationType,
            'max_vacation_days' => $vacationType === 'business_days' 
                ? fake()->numberBetween(20, 25) 
                : fake()->numberBetween(28, 35),
        ];
    }

    /**
     * Indicate that the company uses business days for vacation.
     */
    public function businessDays(): static
    {
        return $this->state(fn (array $attributes) => [
            'vacation_type' => 'business_days',
            'max_vacation_days' => 22,
        ]);
    }

    /**
     * Indicate that the company uses calendar days for vacation.
     */
    public function calendarDays(): static
    {
        return $this->state(fn (array $attributes) => [
            'vacation_type' => 'calendar_days',
            'max_vacation_days' => 30,
        ]);
    }
}
