<!DOCTYPE html>
<html lang="es-MX" data-theme="{{ request()->cookie('tc_theme', 'light') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>En mantenimiento · PadelCup</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" type="image/svg+xml" href="{{ asset('img/icons/favicon.svg') }}" />
    <style>
        :root { --ink:#1a1a2e; --muted:#6b7280; --bg:#f7f7fb; --card:#fff; --accent:#635bff; --border:#e5e7eb; }
        [data-theme="dark"] { --ink:#e8e8ef; --muted:#9ca3af; --bg:#0f0f1a; --card:#1a1a2e; --border:#2a2a3e; }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px;
               font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; background:var(--bg); color:var(--ink); }
        .err { text-align:center; max-width:460px; }
        .err__brand { display:inline-flex; align-items:center; gap:8px; font-weight:700; color:var(--accent); margin-bottom:28px; text-decoration:none; font-size:18px; }
        .err__code { font-size:72px; font-weight:800; line-height:1; letter-spacing:-2px; margin:0; color:var(--accent); }
        .err__icon { font-size:34px; color:var(--accent); margin-bottom:18px; }
        .err__title { font-size:22px; font-weight:700; margin:14px 0 8px; }
        .err__msg { color:var(--muted); font-size:15px; line-height:1.5; margin:0 0 26px; }
        .err__btns { display:flex; gap:10px; justify-content:center; flex-wrap:wrap; }
        .err__btn { display:inline-flex; align-items:center; gap:6px; padding:10px 18px; border-radius:8px; font-size:14px; font-weight:600; text-decoration:none; border:1px solid var(--border); color:var(--ink); background:var(--card); }
        .err__btn--primary { background:var(--accent); color:#fff; border-color:var(--accent); }
    </style>
</head>
<body>
    <div class="err">
        <a href="/" class="err__brand"><i class="fa-solid fa-table-tennis-paddle-ball"></i> PadelCup</a>
        <div class="err__icon"><i class="fa-solid fa-screwdriver-wrench"></i></div>
        <p class="err__code">503</p>
        <h1 class="err__title">En mantenimiento</h1>
        <p class="err__msg">Estamos haciendo mejoras. Volvemos en unos minutos, gracias por tu paciencia.</p>
        <div class="err__btns">
            <a href="/" class="err__btn err__btn--primary"><i class="fa-solid fa-house"></i> Inicio</a>
            <a href="{{ route('public.directory') }}" class="err__btn"><i class="fa-solid fa-trophy"></i> Ver torneos</a>
        </div>
    </div>
</body>
</html>
