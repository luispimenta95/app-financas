# AGENTS.md — app-financas

Guia para agentes (e humanos) evitarem retrabalho neste repositório.

## Onde está o código que roda de verdade

- **Produção/homelab:** `/mnt/Servidor/homelab/app-financas`
- **Container:** `app-financas` (Compose em `/mnt/Servidor/homelab/docker-compose.yml`)
- Volume montado: `/mnt/Servidor/homelab/app-financas` → `/var/www/html`
- URL típica: `http://192.168.101.13:8080`

**Não edite** `~/app-financas` achando que é o deploy — é cópia antiga/desatualizada.

Após mudanças de PHP/config/views no container:

```bash
docker exec app-financas php artisan migrate --force
docker exec app-financas php artisan optimize:clear
```

Se a alteração “não refletir” (OPcache/FPM):

```bash
cd /mnt/Servidor/homelab && docker compose up -d --force-recreate app-financas
```

Testes:

```bash
docker exec app-financas php artisan test --filter=NomeDoTeste
```

## Stack

- Laravel 11 + Filament 3 + Livewire 3 + Pest
- Dinheiro **sempre em centavos** (int). UI com `Leandrocfe\FilamentPtbrFormFields\Money`
- Tenant por `user_id` (scopes). UUIDs nos models principais
- Painel app: `DashPanelProvider` (path `/` ou `/app` se `LANDING_ENABLED=true`)
- Painel admin: `AdminPanelProvider` em `/admin`

## Planos e funcionalidades

- Catálogo ativo no **banco** (`plans`, `plan_features`, pivot `feature_plan`)
- `App\Support\PlanCatalog` lê o banco; `config/financas.php` é **fallback** (testes/sem migrate)
- Feature keys estáveis em `App\Support\PlanFeature`
- Gate de UI/recursos: `financas_user_can(PlanFeature::...)` / `$user->hasFeature(...)`
- Env relevantes (ver `.env.example`):
  - `ADMIN_EMAIL` — único e-mail com acesso a `/admin`
  - `FEATURES_ENFORCED`, `DEFAULT_PLAN`, `TRIAL_PLAN`, `TRIAL_DAYS`
  - `LANDING_ENABLED`

### Regras de produto (não quebrar)

1. **Usuário comum NÃO edita o próprio plano** no perfil. Perfil só mostra plano em leitura. Upgrade futuro = landing + pagamento.
2. **Só o admin** (`ADMIN_EMAIL` / `User::isAppAdmin()`) acessa `/admin`.
3. No admin: CRUD de **Funcionalidades**, **Planos** (checkbox de features) e **Usuários** (atribuir plano/trial).
4. Entrada no admin: menu do avatar → **Administração** (visível só para o admin).
5. Ao salvar plano/feature no admin, chamar `PlanCatalog::forgetCache()`.
6. Preço do plano na UI admin é em **reais**; gravação em centavos.

Migration/seed inicial do catálogo: `2026_11_22_160000_create_plan_catalog_tables.php`.

## Importação de boleto PDF

- Feature: `PlanFeature::BOLETO_PDF` (`boleto_pdf`) — liberada no Pro
- Parser: `App\Services\Transactions\BoletoParser`
- Importer: `App\Services\Transactions\BoletoImporter`
- Tela: `UploadBoleto` — rota `/enviar-boleto/{record?}`
- Disponível para **qualquer despesa** (lista, edição, visualização) + botão geral “Enviar boleto”

### Comportamento

- Cria ou **atualiza** lançamento pela linha digitável (ou chave `ref:...` quando não há linha em texto)
- Ao atualizar lançamento **já existente**, **preserva a descrição** (ex.: “Conta de Água”)
- Atualiza `amount`, `due_date`, `attachment`, tipo despesa
- Model `App\Models\Transactions\Boleto` 1:1 com `Transaction`

### Lições do mundo real (Caesb e similares)

- Muitos PDFs de concessionária **não têm linha digitável em texto** (só código de barras em imagem).
- **Não** varrer todos os dígitos do PDF e inventar linha 47/48 — gera falso positivo e valor zerado/absurdos.
- Preferir valores impressos: `TOTAL A PAGAR`, `VALOR TOTAL`, `DATA DE VENCIMENTO`.
- Se não houver linha confiável: chave sintética `ref:{emissor}:{inscricao}:{YYYY-MM}` (ex.: Caesb).
- Texto de valor colado em data é comum: `40,4427/11/2026`.
- Cobrança bancária = 47 dígitos; arrecadação/concessionária = 48 (começa com `8`).

Testes de referência: `tests/Feature/BoletoParserTest.php`.

### Cuidado com dados reais

- Importar boleto em lançamento errado (ex.: previsão futura da mesma “Conta de Água”) bagunça saldo.
- Saldo atual de finalizadas usa `COALESCE(payment_date, due_date, date)` — marcar `finished` com `payment_date` no mês errado distorce o widget.
- Antes de “corrigir” Conta de Água / recorrências: listar IDs, `due_date`, `amount`, `finished`, `payment_date`.

## Fatura cartão e contracheque

- Já existentes: `CREDIT_CARD_PDF`, `PAYSLIP_PDF`
- Extração de texto PDF: `CreditCardStatementTextExtractor` (pdftotext + smalot/pdfparser)
- Mesmo padrão de página upload + importer + feature gate

## Filament / UI

- Nunca editar views em `vendor/filament/...` como solução permanente. Se estiver corrompida, restaurar do tag instalado (ex. v3.3.x) e limpar views.
- Menu do usuário: `MenuItem` **não** tem `getName()` (API de Action). Blade oficial não chama `getName()` em `MenuItem`.
- Favicon/logo: `public/favicon.ico`, `public/images/logo.svg` — painéis usam `->favicon()` / `->brandLogo()`.

## Deploy checklist rápido

1. Trabalhar em `/mnt/Servidor/homelab/app-financas`
2. `migrate --force` se houver migration nova
3. `optimize:clear` (e recreate do container se OPcache segurar código velho)
4. Confirmar `ADMIN_EMAIL` no `.env`
5. Não commitar `.env`; `.env.example` sim
6. Evitar commit de PDFs grandes em `docs/` sem pedido explícito

## Comandos úteis

```bash
# Plano de um usuário
docker exec app-financas php artisan financas:set-plan email@exemplo.com pro --clear-trial

# Rotas admin
docker exec app-financas php artisan route:list --path=admin

# Rotas boleto
docker exec app-financas php artisan route:list --path=enviar-boleto
```
