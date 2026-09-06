<?php

namespace Database\Factories;

use App\Models\Trainer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Trainer> */
class TrainerFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->name(), 'phone' => null];
    }
}
