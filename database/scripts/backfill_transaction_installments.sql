-- Numera apenas PARCELAS já salvas (is_installment = 1) em grupos de
-- 2 ou mais meses, com o título "Parcela X de Y".
-- Recorrências comuns (aluguel, assinatura, conta de luz) não são alteradas.
--
-- Pré-requisito: rode a migration
--   2026_08_22_140000_add_installment_columns_to_transactions_table
--
-- Antes de rodar, marque as transações que são parcela:
--   UPDATE transactions SET is_installment = 1
--   WHERE recurrence = 1 AND description IN ('Geladeira', 'Notebook');
--
-- Uso:
--   mysql -u USER -p DATABASE < database/scripts/backfill_transaction_installments.sql
--
-- O script é idempotente: pode ser executado de novo sem duplicar o sufixo.

START TRANSACTION;

UPDATE transactions AS t
INNER JOIN (
    SELECT
        numbered.id,
        numbered.installment_number,
        numbered.installment_total,
        numbered.base_description
    FROM (
        SELECT
            grouped.id,
            grouped.base_description,
            ROW_NUMBER() OVER (
                PARTITION BY grouped.user_id, grouped.base_description, grouped.account_id, grouped.category_id
                ORDER BY grouped.due_on ASC, grouped.created_at ASC, grouped.id ASC
            ) AS installment_number,
            COUNT(*) OVER (
                PARTITION BY grouped.user_id, grouped.base_description, grouped.account_id, grouped.category_id
            ) AS installment_total
        FROM (
            SELECT
                id,
                user_id,
                account_id,
                category_id,
                created_at,
                COALESCE(due_date, date) AS due_on,
                TRIM(REGEXP_REPLACE(description, ' - (Transação|Parcela) [0-9]+ de [0-9]+$', '')) AS base_description
            FROM transactions
            WHERE recurrence = 1
              AND is_installment = 1
        ) AS grouped
    ) AS numbered
    WHERE numbered.installment_total > 1
) AS series ON series.id = t.id
SET
    t.installment_number = series.installment_number,
    t.installment_total = series.installment_total,
    t.description = CONCAT(
        series.base_description,
        ' - Parcela ',
        series.installment_number,
        ' de ',
        series.installment_total
    ),
    t.updated_at = CURRENT_TIMESTAMP;

COMMIT;
