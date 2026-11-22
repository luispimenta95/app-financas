<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class LandingController extends Controller
{
    public function __invoke(): View|RedirectResponse
    {
        abort_unless(financas_landing_enabled(), 404);

        if (auth()->check()) {
            return redirect()->to(financas_panel_url());
        }

        $trialDays = (int) config('financas.trial_days', 14);
        $plans = collect(config('financas.plans', []))
            ->map(function (array $plan) use ($trialDays): array {
                if (($plan['key'] ?? null) === 'pro' && blank($plan['cta'] ?? null)) {
                    $plan['cta'] = "Testar {$trialDays} dias grátis";
                }

                return $plan;
            })
            ->values()
            ->all();

        return view('landing', [
            'brand' => (string) config('financas.brand', 'Finanças'),
            'trialDays' => $trialDays,
            'plans' => $plans,
            'loginUrl' => financas_panel_url('login'),
            'registerUrl' => financas_panel_url('register'),
        ]);
    }
}
