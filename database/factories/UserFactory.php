<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = User::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $employeeRole = Role::where('name', 'Employee')->first();
        
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'identification_number' => fake()->unique()->numerify('########') . fake()->randomLetter(),
            'birth_date' => fake()->dateTimeBetween('-60 years', '-18 years')->format('Y-m-d'),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'role_id' => $employeeRole ? $employeeRole->id : null,
            'registered_at' => fake()->dateTimeBetween('-1 year', 'now'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Configure the factory for an admin user.
     */
    public function admin(): static
    {
        $adminRole = Role::where('name', 'Administrator')->first();
        
        return $this->state(fn (array $attributes) => [
            'role_id' => $adminRole ? $adminRole->id : null,
        ]);
    }

    /**
     * Configure the factory for a manager user.
     */
    public function manager(): static
    {
        $managerRole = Role::where('name', 'Manager')->first();
        
        return $this->state(fn (array $attributes) => [
            'role_id' => $managerRole ? $managerRole->id : null,
        ]);
    }

    /**
     * Configure the factory for an employee user.
     */
    public function employee(): static
    {
        $employeeRole = Role::where('name', 'Employee')->first();
        
        return $this->state(fn (array $attributes) => [
            'role_id' => $employeeRole ? $employeeRole->id : null,
        ]);
    }
}
