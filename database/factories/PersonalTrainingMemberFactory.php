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
            'client_name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'trainer_id' => Trainer::factory(),
            'start_date' => today(),
            'end_date' => today()->addMonthNoOverflow()->subDay(),
            'payment_mode' => fake()->randomElement(['gpay', 'card', 'cash']),
            'total_client_amount' => '2500.00',
            'gym_amount' => '0.00',
            'remark' => null,
        ];
    }
}
