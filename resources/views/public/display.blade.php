<!DOCTYPE html>
<html lang="es" class="theme-dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $tournament->name }} — Pantalla</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="disp-body">
    <main class="disp"
        data-display-board
        data-data-url="{{ route('public.display.data', $tournament) }}"
        data-max="{{ $max }}"
        data-rotate="{{ $rotate }}"
        data-refresh="{{ $refresh }}">

        <header class="disp__top">
            <div class="disp__brand">
                <x-logo :height="38" />
                <span class="disp__ig"><i class="fa-brands fa-instagram"></i> @voleo.mx</span>
            </div>
            <div class="disp__title">
                <h1>{{ $tournament->name }}</h1>
                <div class="disp__venue" data-disp-venue></div>
            </div>
            <div class="disp__clock">
                <div class="disp__time" data-disp-clock>--:--</div>
                <div class="disp__date">{{ \Carbon\Carbon::now('America/Mexico_City')->translatedFormat('D d M') }}</div>
            </div>
        </header>

        <section class="disp__board">
            <div class="disp__row disp__row--head">
                <span class="disp__c-time">Hora</span>
                <span class="disp__c-court">Cancha</span>
                <span class="disp__c-cat">Categoría</span>
                <span class="disp__c-pairs">Partido</span>
                <span class="disp__c-status">Estado</span>
            </div>
            <div class="disp__rows" data-disp-rows>
                <div class="disp__loading">Cargando…</div>
            </div>
        </section>

        <footer class="disp__ticker" data-disp-ticker-wrap hidden>
            <span class="disp__ticker-label">Últimos resultados</span>
            <div class="disp__ticker-view"><div class="disp__ticker-track" data-disp-ticker></div></div>
        </footer>

        <div class="disp__pager" data-disp-pager hidden></div>
    </main>
</body>
</html>
