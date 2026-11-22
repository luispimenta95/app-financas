<?php

namespace App\Http\Controllers;

use App\Support\PlanCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class LandingController extends Controller
{
    public function __invoke(PlanCatalog $plans): View|RedirectResponse
    {
        abort_unless(financas_landing_enabled(), 404);

        if (auth()->check()) {
            return redirect()->to(financas_panel_url());
        }

        $trialDays = $plans->trialDays();
        $catalogPlans = collect($plans->plans())
            ->map(function (array $plan, string $key) use ($plans, $trialDays): array {
                $plan['key'] = $key;
                $plan['features'] = $plans->marketingFeaturesForPlan($key);

                if ($key === $plans->trialPlanKey() && blank($plan['cta'] ?? null)) {
                    $plan['cta'] = "Testar {$trialDays} dias grátis";
                }

                return $plan;
            })
            ->values()
            ->all();

        return view('landing', [
            'brand' => (string) config('financas.brand', 'Finanças'),
            'trialDays' => $trialDays,
            'plans' => $catalogPlans,
            'loginUrl' => financas_panel_url('login'),
            'registerUrl' => financas_panel_url('register'),
        ]);
    }
}
