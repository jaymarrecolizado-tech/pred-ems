<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
            'is_active' => true,
        ];
    }

    public function admin(): static
    {
        return $this->afterCreating(function ($user) {
            $user->roles()->attach(
                Role::firstOrCreate(['name' => 'admin'], ['label' => 'Administrator'])->id
            );
        });
    }

    public function hr(): static
    {
        return $this->afterCreating(function ($user) {
            $user->roles()->attach(
                Role::firstOrCreate(['name' => 'hr'], ['label' => 'Human Resources'])->id
            );
        });
    }
}
