<!DOCTYPE html>
<html lang="es-MX" data-theme="{{ request()->cookie('tc_theme', 'light') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Algo salió mal · Voleo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" type="image/svg+xml" href="{{ asset('img/icons/favicon.svg') }}" />
    <style>
        :root { --ink:#1a1a2e; --muted:#6b7280; --bg:#f7f7fb; --card:#fff; --accent:#0e3b2e; --on-accent:#fff; --border:#e5e7eb; }
        [data-theme="dark"] { --ink:#e8e8ef; --muted:#9ca3af; --bg:#0f0f1a; --card:#1a1a2e; --border:#2a2a3e; --accent:#d9f27a; --on-accent:#0f0f1a; }
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
        .err__btn--primary { background:var(--accent); color:var(--on-accent); border-color:var(--accent); }
    
        .err__toggle { position:fixed; top:18px; right:18px; width:38px; height:38px; border-radius:9px;
            border:1px solid var(--border); background:var(--card); color:var(--muted); cursor:pointer;
            display:grid; place-items:center; font-size:15px; }
        .err__toggle:hover { color:var(--ink); }
        .err__brand { color:var(--ink); }
    </style>
</head>
<body>
    
    <button class="err__toggle" id="themeToggle" aria-label="Cambiar tema" title="Cambiar tema"><i class="fa-solid fa-moon"></i></button>
<div class="err">
        <a href="/" class="err__brand"><svg height="26" viewBox="0 0 1365 398" xmlns="http://www.w3.org/2000/svg" style="width:auto;vertical-align:middle;fill-rule:evenodd;clip-rule:evenodd;" role="img" aria-label="Voleo">
    <g transform="matrix(1,0,0,1,-1478.153171,-1510.333408)">
        <g id="Background" transform="matrix(1.40332,0,0,1.40332,1446,1481)">
            <path d="M932.92,263.522C937.839,225.414 978.945,229.053 990.232,248.648C1010.371,283.608 963.56,313.382 939.901,285.159C933.084,277.027 932.942,265.34 932.92,263.522Z" style="fill:var(--voleo-dot, #eaf89a);"/>
            <path d="M910.04,192.484C899.785,326.489 696.149,338.604 707.978,200.558C712.527,147.474 769.302,87.302 845.522,103.398C877.494,110.149 904.046,135.984 908.765,168.458C910.504,180.43 910.275,180.404 910.04,192.484ZM797.534,253.024C865.713,257.64 883.985,150.063 817.5,145.495C806.094,144.712 778.995,150.12 765.274,179.397C751.417,208.964 760.652,247.331 797.534,253.024Z" style="fill:currentColor;"/>
            <g transform="matrix(0.712596,0,0,0.712596,0,0)">
                <path d="M980.872,244.861C980.905,249.393 981.128,279.392 973.16,300.994C972.004,304.129 970.671,303.703 890.413,304.361C781.686,305.252 781.178,305.368 780.706,306.477C777.977,312.887 791.695,379.628 865.017,359.533C869.433,358.322 909.695,332.206 948.351,357.95C954.96,362.351 950.743,364.047 945.414,370.02C941.506,374.402 895.039,434.643 806.321,419.599C696.104,400.909 676.911,249.791 774.966,175.604C846.303,121.631 969.075,133.446 980.872,244.861ZM907.243,256.855C913.86,256.714 913.819,256.742 914.389,256.702C920.658,256.264 915.879,208.77 881.845,200.567C815.877,184.668 784.85,253.457 786.989,257.352C788.43,259.977 876.845,256.62 907.243,256.855Z" style="fill:currentColor;"/>
            </g>
            <path d="M425.5,298.038C421.765,297.707 419.55,299.221 420.169,295.447C420.284,294.748 443.18,184.379 443.838,181.584C452.276,145.768 450.342,145.42 458.822,109.584C460.848,101.024 464.247,78.032 466.517,77.576C478.385,75.197 510.589,92.436 503.287,124.442C495.352,159.228 474.484,263.323 472.06,275.416C467.792,296.706 467.306,297.152 465.485,297.35C463.693,297.545 429.17,297.962 425.5,298.038Z" style="fill:currentColor;"/>
            <g transform="matrix(0.712596,0,0,0.712596,0,0)">
                <path d="M466.614,148.831C634.657,156.179 620.7,392.869 452.467,423.964C360.266,441.005 282.072,374.237 304.48,273.081C319.307,206.144 381.669,146.556 466.614,148.831ZM446.99,211.834C362.313,221.031 342.156,351.596 431.505,362.886C470.982,367.874 528.392,318.774 509.868,251.963C504.262,231.743 484.327,211.526 453.966,211.609C451.639,211.616 449.316,211.828 446.99,211.834Z" style="fill:currentColor;"/>
            </g>
            <g transform="matrix(0.712596,0,0,0.712596,0,0)">
                <path d="M204.822,101.756C213.777,23.692 298.76,13.785 322.59,48.059C342.872,77.231 326.283,96.73 338.942,95.015C449.786,80.005 533.854,29.531 538.397,30.969C541.508,31.953 547.909,59.975 527.541,86.756C495.306,129.139 372.416,140.564 331.831,146.33C324.063,147.433 325.939,150.417 313.108,174.429C308.862,182.376 192.857,419.103 190.058,421.589C188.172,423.263 101.285,423.239 100.607,422.702C97.449,420.2 61.873,237.427 49.405,207.159C36.409,175.61 32.09,177.615 32.154,174.674C32.272,169.178 74.848,162.046 99.868,180.951C147.475,216.924 136.83,337.954 148.196,362.685C153.071,373.291 161.435,351.583 173.116,327.579C259.229,150.604 262.634,148.07 258.731,147.244C233.049,141.812 207.37,143.214 204.822,101.756ZM261.751,112.171C298.597,107.184 299.702,69.953 282.82,65.111C252.667,56.464 221.138,110.547 261.751,112.171Z" style="fill:currentColor;"/>
            </g>
        </g>
    </g>
</svg></a>
        <div class="err__icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <p class="err__code">500</p>
        <h1 class="err__title">Algo salió mal</h1>
        <p class="err__msg">Ocurrió un error de nuestro lado. Ya estamos en ello — intenta de nuevo en un momento.</p>
        <div class="err__btns">
            <a href="/" class="err__btn err__btn--primary"><i class="fa-solid fa-house"></i> Inicio</a>
            <a href="{{ route('public.directory') }}" class="err__btn"><i class="fa-solid fa-trophy"></i> Ver torneos</a>
        </div>
    </div>
    <script>
        (function(){
            var COOKIE='tc_theme', root=document.documentElement, btn=document.getElementById('themeToggle');
            function icon(t){ btn.querySelector('i').className = t==='dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon'; }
            icon(root.getAttribute('data-theme')||'light');
            btn.addEventListener('click', function(){
                var t = (root.getAttribute('data-theme')==='dark') ? 'light' : 'dark';
                root.setAttribute('data-theme', t);
                document.cookie = COOKIE+'='+t+'; path=/; max-age='+(60*60*24*365)+'; SameSite=Lax';
                icon(t);
            });
        })();
    </script>
</body>
</html>
