<?php

namespace App\Support;

use App\Models\Feature;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class PlanCatalog
{
    /** @var array<string, array{key: string, label: string, description: string}>|null */
    private ?array $featuresCache = null;

    /** @var array<string, array<string, mixed>>|null */
    private ?array $plansCache = null;

    private ?bool $usesDatabase = null;

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

    public function forgetCache(): void
    {
        $this->featuresCache = null;
        $this->plansCache = null;
        $this->usesDatabase = null;
    }

    /**
     * @return array<string, array{key: string, label: string, description: string}>
     */
    public function features(): array
    {
        if ($this->featuresCache !== null) {
            return $this->featuresCache;
        }

        if ($this->usesDatabase()) {
            $normalized = [];

            Feature::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->each(function (Feature $feature) use (&$normalized): void {
                    $normalized[$feature->key] = [
                        'key' => $feature->key,
                        'label' => (string) $feature->label,
                        'description' => (string) ($feature->description ?? ''),
                    ];
                });

            return $this->featuresCache = $normalized;
        }

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

        return $this->featuresCache = $normalized;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function plans(): array
    {
        if ($this->plansCache !== null) {
            return $this->plansCache;
        }

        if ($this->usesDatabase()) {
            $normalized = [];

            Plan::query()
                ->where('is_active', true)
                ->with('features')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->each(function (Plan $plan) use (&$normalized): void {
                    $normalized[$plan->key] = [
                        'key' => $plan->key,
                        'name' => $plan->name,
                        'price_monthly' => $plan->price_monthly,
                        'price_label' => $plan->price_label,
                        'period' => $plan->period,
                        'description' => $plan->description,
                        'cta' => $plan->cta,
                        'featured' => $plan->featured,
                        'feature_keys' => $plan->features->pluck('key')->all(),
                        'highlights' => $plan->highlights ?? [],
                    ];
                });

            return $this->plansCache = $normalized;
        }

        /** @var array<string, array<string, mixed>> $plans */
        return $this->plansCache = config('financas.plans', []);
    }

    /**
     * Inclui planos inativos (útil no admin ao atribuir planos a usuários).
     *
     * @return array<string, string>
     */
    public function planOptions(bool $includeInactive = false): array
    {
        if ($includeInactive && $this->usesDatabase()) {
            return Plan::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->pluck('name', 'key')
                ->all();
        }

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
            if ($this->usesDatabase()) {
                $record = Plan::query()->where('key', $key)->with('features')->first();

                if ($record === null) {
                    return null;
                }

                return [
                    'key' => $record->key,
                    'name' => $record->name,
                    'price_monthly' => $record->price_monthly,
                    'price_label' => $record->price_label,
                    'period' => $record->period,
                    'description' => $record->description,
                    'cta' => $record->cta,
                    'featured' => $record->featured,
                    'feature_keys' => $record->features->pluck('key')->all(),
                    'highlights' => $record->highlights ?? [],
                ];
            }

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

        $plan = (string) ($user->plan ?: $this->defaultPlanKey());

        // Plano Pro (ou qualquer plano pago) é permanente: trial não limita nem rebaixa.
        if ($plan === $this->trialPlanKey() && $this->planExists($plan)) {
            return $plan;
        }

        if ($user->trial_ends_at instanceof Carbon && $user->trial_ends_at->isFuture()) {
            return $this->trialPlanKey();
        }

        return $this->planExists($plan) ? $plan : $this->defaultPlanKey();
    }

    public function isUnlimitedPlan(string $planKey): bool
    {
        return $planKey === $this->trialPlanKey();
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
     * Atributos iniciais no registro.
     *
     * O cliente só pode pedir o CTA de trial (ex.: ?plan=pro). Qualquer outro
     * valor é ignorado — planos permanentes vêm só do DEFAULT_PLAN / admin.
     *
     * @return array{plan: string, trial_ends_at: Carbon|null}
     */
    public function attributesForRegistration(?string $requestedPlan): array
    {
        $default = $this->defaultPlanKey();
        $trial = $this->trialPlanKey();

        $wantsTrial = is_string($requestedPlan)
            && $requestedPlan !== ''
            && $this->planExists($requestedPlan)
            && $requestedPlan === $trial;

        if ($wantsTrial) {
            // Dono com DEFAULT_PLAN=pro: Pro permanente, sem contagem de dias.
            if ($default === $trial) {
                return [
                    'plan' => $trial,
                    'trial_ends_at' => null,
                ];
            }

            if ($this->trialDays() > 0) {
                return [
                    'plan' => $default,
                    'trial_ends_at' => now()->addDays($this->trialDays()),
                ];
            }
        }

        return [
            'plan' => $default,
            'trial_ends_at' => null,
        ];
    }

    private function usesDatabase(): bool
    {
        if ($this->usesDatabase !== null) {
            return $this->usesDatabase;
        }

        try {
            return $this->usesDatabase = Schema::hasTable('plans')
                && Plan::query()->exists();
        } catch (\Throwable) {
            return $this->usesDatabase = false;
        }
    }
}
