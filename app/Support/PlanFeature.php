<?php

namespace App\Support;

/**
 * Chaves estáveis das funcionalidades vendáveis.
 * Configure quais entram em cada plano em config/financas.php.
 */
final class PlanFeature
{
    public const TRANSACTIONS = 'transactions';

    public const EXPORT_TRANSACTIONS = 'export_transactions';

    public const DASHBOARD_PROJECTION = 'dashboard_projection';

    public const CREDIT_CARD_PDF = 'credit_card_pdf';

    public const PAYSLIP_PDF = 'payslip_pdf';

    public const INVESTMENTS = 'investments';

    public const INVESTMENT_MILESTONES = 'investment_milestones';

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return [
            self::TRANSACTIONS,
            self::EXPORT_TRANSACTIONS,
            self::DASHBOARD_PROJECTION,
            self::CREDIT_CARD_PDF,
            self::PAYSLIP_PDF,
            self::INVESTMENTS,
            self::INVESTMENT_MILESTONES,
        ];
    }
}
