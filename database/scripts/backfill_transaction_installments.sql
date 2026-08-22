-- Atualiza APENAS as 10 parcelas "Celular Sabrina".
-- Ordem pelo vencimento: Transação 1 de 10 ... Transação 10 de 10.
--
-- Este UPDATE usa só colunas já existentes e pode ser colado no MySQL agora.
-- Idempotente: pode rodar de novo sem duplicar o sufixo.

START TRANSACTION;

UPDATE transactions AS t
INNER JOIN (
    SELECT
        id,
        ROW_NUMBER() OVER (
            ORDER BY COALESCE(due_date, date) ASC, created_at ASC, id ASC
        ) AS installment_number,
        COUNT(*) OVER () AS installment_total
    FROM transactions
    WHERE user_id = 'a14484a7-0a61-409d-9097-77bc139108d5'
      AND description LIKE '%Celular Sabrina%'
) AS series ON series.id = t.id
SET
    t.description = CONCAT(
        'Celular Sabrina - Transação ',
        series.installment_number,
        ' de ',
        series.installment_total
    ),
    t.updated_at = CURRENT_TIMESTAMP;

SELECT
    t.id,
    t.due_date,
    t.finished,
    t.description
FROM transactions t
WHERE t.user_id = 'a14484a7-0a61-409d-9097-77bc139108d5'
  AND t.description LIKE '%Celular Sabrina%'
ORDER BY COALESCE(t.due_date, t.date) ASC, t.created_at ASC, t.id ASC;

COMMIT;

-- Depois da migration 2026_08_22_140000_add_installment_columns_to_transactions_table,
-- marque as colunas de parcela:
--
-- UPDATE transactions AS t
-- INNER JOIN (
--     SELECT
--         id,
--         ROW_NUMBER() OVER (
--             ORDER BY COALESCE(due_date, date) ASC, created_at ASC, id ASC
--         ) AS installment_number,
--         COUNT(*) OVER () AS installment_total
--     FROM transactions
--     WHERE user_id = 'a14484a7-0a61-409d-9097-77bc139108d5'
--       AND description LIKE '%Celular Sabrina%'
-- ) AS series ON series.id = t.id
-- SET
--     t.is_installment = 1,
--     t.installment_number = series.installment_number,
--     t.installment_total = series.installment_total,
--     t.updated_at = CURRENT_TIMESTAMP;
