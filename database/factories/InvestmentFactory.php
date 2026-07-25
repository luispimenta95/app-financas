<?php

namespace Database\Factories;

use App\Enums\InvestmentType;
use App\Models\Investment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Investment>
 */
class InvestmentFactory extends Factory
{
    protected $model = Investment::class;

    public function definition(): array
    {
        $dailyLiquidity = fake()->boolean(70);

        return [
            'user_id' => User::factory(),
            'type' => InvestmentType::FixedIncome,
            'name' => fake()->randomElement(['CDB Liquidez Diária', 'LCI Prefixo', 'Tesouro Selic', 'CDB 110% CDI']),
            'institution' => fake()->randomElement(['Nubank', 'XP', 'Itaú', 'BTG', 'Inter']),
            'amount' => fake()->numberBetween(10000, 5000000),
            'application_date' => fake()->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
            'cdi_rate' => fake()->randomFloat(2, 90, 130),
            'daily_liquidity' => $dailyLiquidity,
            'maturity_date' => $dailyLiquidity
                ? null
                : fake()->dateTimeBetween('+1 month', '+3 years')->format('Y-m-d'),
        ];
    }

    public function fixedIncome(): static
    {
        return $this->state(fn () => [
            'type' => InvestmentType::FixedIncome,
        ]);
    }

    public function variableIncome(): static
    {
        return $this->state(fn () => [
            'type' => InvestmentType::VariableIncome,
            'cdi_rate' => null,
            'daily_liquidity' => true,
            'maturity_date' => null,
        ]);
    }

    public function withoutDailyLiquidity(?string $maturityDate = null): static
    {
        return $this->state(fn () => [
            'daily_liquidity' => false,
            'maturity_date' => $maturityDate ?? now()->addYear()->toDateString(),
        ]);
    }
}
