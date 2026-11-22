<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\PlanCatalog;
use Illuminate\Console\Command;

class SetUserPlanCommand extends Command
{
    protected $signature = 'financas:set-plan
        {email : E-mail do usuário}
        {plan : Chave do plano (ex: free, pro)}
        {--trial= : Dias de trial a partir de agora (opcional)}
        {--clear-trial : Remove trial_ends_at}';

    protected $description = 'Define o plano (e trial opcional) de um usuário';

    public function handle(PlanCatalog $plans): int
    {
        $email = (string) $this->argument('email');
        $plan = (string) $this->argument('plan');

        if (! $plans->planExists($plan)) {
            $this->error("Plano [{$plan}] não existe. Disponíveis: ".implode(', ', array_keys($plans->plans())));

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $this->error("Usuário [{$email}] não encontrado.");

            return self::FAILURE;
        }

        $user->plan = $plan;

        if ($this->option('clear-trial')) {
            $user->trial_ends_at = null;
        } elseif ($this->option('trial') !== null && $this->option('trial') !== '') {
            $days = max(0, (int) $this->option('trial'));
            $user->trial_ends_at = $days > 0 ? now()->addDays($days) : null;
        }

        $user->save();

        $this->info("Usuário {$user->email}: plan={$user->plan}, trial_ends_at=".($user->trial_ends_at?->toDateTimeString() ?? 'null').', efetivo='.$user->effectivePlanKey());

        return self::SUCCESS;
    }
}
