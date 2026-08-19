<?php

namespace Database\Factories;

use App\Models\Enrollment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-1 month', '+1 month');
        $packageMonths = fake()->randomElement([1, 3, 6, 12, 15]);

        return [
            'user_id' => null,
            'reference_code' => 'IF-'.now()->format('Ymd').'-'.fake()->unique()->bothify('??##??'),
            'email' => fake()->safeEmail(),
            'full_name' => fake()->name(),
            'address' => fake()->address(),
            'mobile_number' => fake()->phoneNumber(),
            'emergency_contact' => fake()->phoneNumber(),
            'date_of_birth' => fake()->dateTimeBetween('-65 years', '-18 years'),
            'package_months' => $packageMonths,
            'membership_package' => $packageMonths.' '.($packageMonths === 1 ? 'Month' : 'Months'),
            'freezing_enabled' => false,
            'freezing_days' => null,
            'payment_mode' => fake()->randomElement(['gpay', 'card', 'cash']),
            'amount_paid' => fake()->randomFloat(2, 500, 30000),
            'membership_start_date' => $startDate,
            'membership_end_date' => (clone $startDate)->modify('+'.$packageMonths.' months -1 day'),
            'has_balance' => false,
            'remaining_balance' => null,
            'balance_due_date' => null,
            'terms_accepted' => true,
        ];
    }
}
