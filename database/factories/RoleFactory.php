<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Role;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Role>
 */
class RoleFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Role::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->jobTitle(),
            'description' => fake()->sentence(),
            'is_exempt' => fake()->boolean(20),
            'access_level' => fake()->numberBetween(1, 5),
        ];
    }

    /**
     * Indicate that the role is for an admin.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Administrator',
            'description' => 'Full system administrator with all privileges',
            'is_exempt' => true,
            'access_level' => 5,
        ]);
    }

    /**
     * Indicate that the role is for a manager.
     */
    public function manager(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Manager',
            'description' => 'Company manager with employee management privileges',
            'is_exempt' => true,
            'access_level' => 4,
        ]);
    }

    /**
     * Indicate that the role is for a regular employee.
     */
    public function employee(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Employee',
            'description' => 'Regular employee with basic access',
            'is_exempt' => false,
            'access_level' => 1,
        ]);
    }
}
