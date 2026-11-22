<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class PlanCatalog
{
    public function featuresEnforced(): bool
    {
        return (bool) config('financas.features_enforced', true);
    }

    public function defaultPlanKey(): string
    {
        return (string) config('financas.default_plan', 'free');
    }

    public function trialDays(): int
    {
        return (int) config('financas.trial_days', 14);
    }

    public function trialPlanKey(): string
    {
        return (string) config('financas.trial_plan', 'pro');
    }

    /**
     * @return array<string, array{key: string, label: string, description: string}>
     */
    public function features(): array
    {
        /** @var array<string, array{label?: string, description?: string}> $features */
        $features = config('financas.features', []);

        $normalized = [];

        foreach ($features as $key => $feature) {
            $normalized[$key] = [
                'key' => $key,
                'label' => (string) ($feature['label'] ?? $key),
                'description' => (string) ($feature['description'] ?? ''),
            ];
        }

        return $normalized;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function plans(): array
    {
        /** @var array<string, array<string, mixed>> $plans */
        return config('financas.plans', []);
    }

    /**
     * @return array<string, string>
     */
    public function planOptions(): array
    {
        return collect($this->plans())
            ->mapWithKeys(fn (array $plan, string $key): array => [
                $key => (string) ($plan['name'] ?? $key),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function plan(string $key): ?array
    {
        $plan = $this->plans()[$key] ?? null;

        if (! is_array($plan)) {
            return null;
        }

        $plan['key'] = $key;

        return $plan;
    }

    public function planExists(string $key): bool
    {
        return $this->plan($key) !== null;
    }

    /**
     * @return list<string>
     */
    public function featureKeysForPlan(string $planKey): array
    {
        $plan = $this->plan($planKey);

        if ($plan === null) {
            return [];
        }

        $keys = Arr::wrap($plan['feature_keys'] ?? []);

        if (in_array('*', $keys, true)) {
            return array_keys($this->features());
        }

        return array_values(array_filter(
            $keys,
            fn (mixed $key): bool => is_string($key) && $key !== '',
        ));
    }

    /**
     * @return list<string>
     */
    public function marketingFeaturesForPlan(string $planKey): array
    {
        $plan = $this->plan($planKey);

        if ($plan === null) {
            return [];
        }

        $highlights = Arr::wrap($plan['highlights'] ?? []);

        if ($highlights !== []) {
            return array_values(array_map('strval', $highlights));
        }

        $catalog = $this->features();

        return collect($this->featureKeysForPlan($planKey))
            ->map(fn (string $key): string => $catalog[$key]['label'] ?? $key)
            ->values()
            ->all();
    }

    public function effectivePlanKey(?User $user): string
    {
        if ($user === null) {
            return $this->defaultPlanKey();
        }

        if ($user->trial_ends_at instanceof Carbon && $user->trial_ends_at->isFuture()) {
            return $this->trialPlanKey();
        }

        $plan = (string) ($user->plan ?: $this->defaultPlanKey());

        return $this->planExists($plan) ? $plan : $this->defaultPlanKey();
    }

    public function userHasFeature(?User $user, string $feature): bool
    {
        if (! $this->featuresEnforced()) {
            return true;
        }

        if ($user === null) {
            return false;
        }

        return in_array(
            $feature,
            $this->featureKeysForPlan($this->effectivePlanKey($user)),
            true,
        );
    }

    /**
     * Atributos iniciais no registro (landing ?plan=pro inicia trial).
     *
     * @return array{plan: string, trial_ends_at: Carbon|null}
     */
    public function attributesForRegistration(?string $requestedPlan): array
    {
        $requested = $requestedPlan && $this->planExists($requestedPlan)
            ? $requestedPlan
            : $this->defaultPlanKey();

        if ($requested === $this->trialPlanKey() && $this->trialDays() > 0) {
            return [
                'plan' => $this->defaultPlanKey(),
                'trial_ends_at' => now()->addDays($this->trialDays()),
            ];
        }

        return [
            'plan' => $requested,
            'trial_ends_at' => null,
        ];
    }
}
