<?php

use App\Models\Feature;
use App\Models\Plan;
use App\Support\PlanCatalog;
use App\Support\PlanFeature;

test('sync de features no plano atualiza o catálogo efetivo', function () {
    $plan = Plan::query()->where('key', 'free')->firstOrFail();
    $investments = Feature::query()->where('key', PlanFeature::INVESTMENTS)->firstOrFail();

    expect($plan->features()->where('key', PlanFeature::INVESTMENTS)->exists())->toBeFalse();

    $plan->features()->syncWithoutDetaching([$investments->id]);
    app(PlanCatalog::class)->forgetCache();

    expect(app(PlanCatalog::class)->featureKeysForPlan('free'))
        ->toContain(PlanFeature::INVESTMENTS);

    $plan->features()->detach($investments->id);
    app(PlanCatalog::class)->forgetCache();

    expect(app(PlanCatalog::class)->featureKeysForPlan('free'))
        ->not->toContain(PlanFeature::INVESTMENTS);
});
