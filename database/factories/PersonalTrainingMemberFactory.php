<?php

namespace Database\Factories;

use App\Models\PersonalTrainingMember;
use App\Models\Trainer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PersonalTrainingMember> */
class PersonalTrainingMemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'member_name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'trainer_id' => Trainer::factory(),
            'start_date' => today(),
            'end_date' => today()->addMonthNoOverflow()->subDay(),
            'monthly_fee' => '2500.00',
        ];
    }
}
