<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ContactFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->name(), 'phone_e164' => '88017'.fake()->unique()->numerify('########'), 'source' => 'test', 'experiment_group' => 'call'];
    }
}
