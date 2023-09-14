<?php

namespace Thunk\Verbs\Examples\Subscriptions\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Thunk\Verbs\Examples\Subscriptions\Models\Plan;

class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition()
    {
        return [
            'name' => $this->faker->name,
        ];
    }
}
