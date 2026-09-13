<?php

namespace Database\Factories;

use App\Models\InvestmentMilestone;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvestmentMilestone>
 */
class InvestmentMilestoneFactory extends Factory
{
    protected $model = InvestmentMilestone::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'amount' => fake()->randomElement([500000, 1000000, 2500000, 5000000, 10000000]),
            'achieved_on' => fake()->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
        ];
    }
}
