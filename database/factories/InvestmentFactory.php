<?php

namespace Database\Factories;

use App\Enums\InvestmentRateType;
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
        $rateType = fake()->randomElement([InvestmentRateType::Cdi, InvestmentRateType::Prefixed]);

        return [
            'user_id' => User::factory(),
            'type' => InvestmentType::FixedIncome,
            'name' => fake()->randomElement(['CDB Liquidez Diária', 'LCI Prefixo', 'Tesouro Selic', 'CDB 110% CDI']),
            'institution' => fake()->randomElement(['Nubank', 'XP', 'Itaú', 'BTG', 'Inter']),
            'amount' => fake()->numberBetween(10000, 5000000),
            'application_date' => fake()->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
            'rate_type' => $rateType,
            'interest_rate' => $rateType === InvestmentRateType::Cdi
                ? fake()->randomFloat(2, 90, 130)
                : fake()->randomFloat(2, 8, 18),
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
        return $this->state(fn () => Investment::variableIncomeAttributes(
            fake()->numberBetween(10000, 5000000)
        ));
    }

    public function abroad(): static
    {
        return $this->state(fn () => Investment::abroadAttributes(
            fake()->numberBetween(10000, 5000000)
        ));
    }

    public function cdi(float $rate = 100): static
    {
        return $this->state(fn () => [
            'rate_type' => InvestmentRateType::Cdi,
            'interest_rate' => $rate,
        ]);
    }

    public function prefixed(float $rate = 15): static
    {
        return $this->state(fn () => [
            'rate_type' => InvestmentRateType::Prefixed,
            'interest_rate' => $rate,
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
