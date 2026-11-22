<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Finanças — organize receitas, despesas e investimentos com projeção do mês e leitura de PDF.">
    <title>{{ $brand }} — controle financeiro com clareza</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/logo.svg') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Manrope:wght@400;500;650;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}">
</head>
<body class="landing">
    <div class="landing-shell">
        <header class="landing-nav">
            <a class="brand" href="{{ url('/') }}">
                <img src="{{ asset('images/logo.svg') }}" alt="" width="28" height="28">
                {{ $brand }}
            </a>
            <div class="nav-actions">
                <a class="btn btn-ghost" href="{{ $loginUrl }}">Entrar</a>
                <a class="btn btn-primary" href="{{ $registerUrl }}">Começar grátis</a>
            </div>
        </header>

        <section class="hero" aria-label="Apresentação">
            <div class="hero-copy">
                <p class="hero-brand">{{ $brand }}</p>
                <h1 class="hero-headline">Seu mês financeiro, sem planilha e sem surpresa.</h1>
                <p class="hero-lead">
                    Lance, projete e acompanhe investimentos em um só lugar — com trial de {{ $trialDays }} dias no Pro.
                </p>
                <div class="hero-cta">
                    <a class="btn btn-amber" href="{{ $registerUrl }}">Começar grátis</a>
                    <a class="btn btn-ghost" href="#planos">Ver planos</a>
                </div>
            </div>
        </section>

        <section class="section" aria-labelledby="como-funciona">
            <div class="section-head">
                <h2 id="como-funciona">Feito para o dia a dia brasileiro</h2>
                <p>Do cartão ao salário, do vencimento à projeção — com a linguagem que você já usa.</p>
            </div>
            <ul class="feature-list">
                <li>
                    <strong>Projeção do mês</strong>
                    <span>Saldo esperado com pendências, aportes e resgates no mesmo painel.</span>
                </li>
                <li>
                    <strong>Parcelas e recorrência</strong>
                    <span>Contas que se repetem e compras parceladas sem perder o fio.</span>
                </li>
                <li>
                    <strong>Fatura e contracheque em PDF</strong>
                    <span>No Pro, leia lançamentos direto do arquivo — menos digitação.</span>
                </li>
                <li>
                    <strong>Investimentos</strong>
                    <span>Renda fixa, variável e exterior com marcos de patrimônio.</span>
                </li>
            </ul>
        </section>

        <section class="section" id="planos" aria-labelledby="planos-titulo">
            <div class="section-head">
                <h2 id="planos-titulo">Planos claros, trial sem cartão escondido</h2>
                <p>Comece no Gratuito. Quando precisar de PDF e investimentos avançados, teste o Pro por {{ $trialDays }} dias.</p>
            </div>

            <div class="plans">
                @foreach ($plans as $plan)
                    <article class="plan {{ !empty($plan['featured']) ? 'is-featured' : '' }}">
                        @if (!empty($plan['featured']))
                            <div class="plan-eyebrow">Mais escolhido · {{ $trialDays }} dias grátis</div>
                        @else
                            <div class="plan-eyebrow">Para começar</div>
                        @endif

                        <h3>{{ $plan['name'] }}</h3>

                        <div class="plan-price">
                            <strong>{{ $plan['price_label'] }}</strong>
                            <span>{{ $plan['period'] }}</span>
                        </div>

                        <p>{{ $plan['description'] }}</p>

                        <ul>
                            @foreach ($plan['features'] as $feature)
                                <li>{{ $feature }}</li>
                            @endforeach
                        </ul>

                        <a
                            class="btn {{ !empty($plan['featured']) ? 'btn-primary' : 'btn-ghost' }}"
                            href="{{ $registerUrl }}?plan={{ urlencode($plan['key']) }}"
                        >
                            {{ $plan['cta'] ?? 'Começar' }}
                        </a>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="closing" aria-labelledby="cta-final">
            <h2 id="cta-final">Pronto para ver o mês com clareza?</h2>
            <p>Crie sua conta em menos de um minuto. O plano Pro inclui {{ $trialDays }} dias para experimentar.</p>
            <div class="closing-actions">
                <a class="btn btn-primary" href="{{ $registerUrl }}">Criar conta grátis</a>
                <a class="btn btn-ghost" href="{{ $loginUrl }}">Já tenho conta</a>
            </div>
        </section>

        <footer class="landing-footer">
            <span>{{ $brand }}</span>
            <span>Controle financeiro pessoal · trial de {{ $trialDays }} dias no Pro</span>
        </footer>
    </div>
</body>
</html>
