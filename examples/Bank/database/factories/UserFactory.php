<?php

namespace Thunk\Verbs\Examples\Bank\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Thunk\Verbs\Examples\Bank\Models\User;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition()
    {
        return [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'password' => bcrypt('password'),
        ];
    }
}
