@extends('layouts.app')

@section('title', 'Previsualizar importación · '.$tournament->name)

@section('content')
<div class="page-head">
    <div>
        <h1>Previsualizar importación</h1>
        <div class="page-sub">{{ $tournament->name }}</div>
    </div>
    <a href="{{ route('tournaments.import.form', $tournament) }}" class="btn btn-soft"><i class="fa-solid fa-arrow-left me-1"></i> Cambiar archivo</a>
</div>

@if(!empty($errors) && count($errors))
<div class="alert py-2 px-3 mb-3" style="font-size:13px;border-radius:var(--radius);background:color-mix(in srgb, var(--warning,#f5a623) 12%, var(--surface));color:var(--text);border:1px solid color-mix(in srgb, var(--warning,#f5a623) 35%, transparent);">
    <strong>{{ count($errors) }} filas con problemas (se omitirán):</strong>
    @foreach(array_slice($errors, 0, 8) as $e)<div>{{ $e }}</div>@endforeach
    @if(count($errors) > 8)<div>… y {{ count($errors) - 8 }} más.</div>@endif
</div>
@endif

@php
$totalPairs = collect($preview)->sum('pairs');
$newCats = collect($preview)->where('exists', false)->count();
$existingCats = collect($preview)->where('exists', true)->count();
@endphp

<div class="row g-3 mb-2">
    <div class="col-6 col-lg-3"><x-stat-tile icon="fa-people-group" label="Parejas a importar" value="{{ $totalPairs }}" accent /></div>
    <div class="col-6 col-lg-3"><x-stat-tile icon="fa-layer-group" label="Categorías" value="{{ count($preview) }}" /></div>
    <div class="col-6 col-lg-3"><x-stat-tile icon="fa-plus" label="Nuevas" value="{{ $newCats }}" /></div>
    <div class="col-6 col-lg-3"><x-stat-tile icon="fa-check" label="Existentes" value="{{ $existingCats }}" /></div>
</div>

<form method="POST" action="{{ route('tournaments.import.commit', $tournament) }}" data-import-form>
    @csrf

    <div class="tc-card mb-3">
        <div class="tc-card__head">
            <h3>Configuración por categoría</h3>
            <p style="font-size:12px;color:var(--text-muted);margin:4px 0 0;">
                Ajusta el tamaño de grupo, cuántas parejas avanzan y clasificados extra. Los grupos de 4 juegan Mexicano (2 rondas); los de 3, todos contra todos. Las cifras de partidos se calculan en vivo.
            </p>
        </div>
        <div style="overflow-x:auto;">
            <table class="tc-table" data-import-table>
                <thead>
                    <tr>
                        <th>Categoría</th>
                        <th>Estado</th>
                        <th>Parejas</th>
                        <th>Grupo</th>
                        <th>Avanzan</th>
                        <th>Extra</th>
                        <th>Grupos</th>
                        <th>Part. grupos</th>
                        <th>Part. elim.</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($preview as $row)
                    <tr data-cat-row data-pairs="{{ $row['pairs'] }}">
                        <td style="font-weight:600;">
                            {{ $row['category'] }}
                            <input type="hidden" name="settings[{{ $row['category'] }}][_]" value="1">
                        </td>
                        <td>
                            @if($row['exists'])
                            <x-pill variant="neutral" dot>Existente</x-pill>
                            @else
                            <x-pill variant="accent" dot>Nueva</x-pill>
                            @endif
                        </td>
                        <td>{{ $row['pairs'] }}</td>
                        <td>
                            <select name="settings[{{ $row['category'] }}][size]" data-f="size" class="form-select form-select-sm" style="width:64px;border-radius:var(--radius);">
                                <option value="3" selected>3</option>
                                <option value="4">4</option>
                            </select>
                        </td>
                        <td>
                            <input type="number" name="settings[{{ $row['category'] }}][advance]" data-f="advance" value="1" min="1" max="2" class="form-control form-control-sm" style="width:60px;border-radius:var(--radius);">
                        </td>
                        <td>
                            <input type="number" name="settings[{{ $row['category'] }}][extra]" data-f="extra" value="0" min="0" max="3" class="form-control form-control-sm" style="width:60px;border-radius:var(--radius);">
                        </td>
                        <td data-out="groups" class="pub-mono">—</td>
                        <td data-out="gmatches" class="pub-mono">—</td>
                        <td data-out="ematches" class="pub-mono">—</td>
                        <td data-out="total" class="pub-mono" style="font-weight:700;">—</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr style="font-weight:700;border-top:2px solid var(--border);">
                        <td colspan="6" style="text-align:right;">Totales del torneo</td>
                        <td data-total="groups" class="pub-mono">—</td>
                        <td data-total="gmatches" class="pub-mono">—</td>
                        <td data-total="ematches" class="pub-mono">—</td>
                        <td data-total="total" class="pub-mono">—</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div data-import-warnings style="padding:0 14px 12px;"></div>
    </div>

    <label style="display:flex;align-items:center;gap:8px;font-size:13px;margin-bottom:14px;cursor:pointer;">
        <input type="checkbox" name="auto_generate" value="1" checked>
        Generar grupos y llaves automáticamente al importar
    </label>

    <button type="submit" class="btn btn-accent"><i class="fa-solid fa-file-import me-1"></i> Confirmar e importar</button>
    <a href="{{ route('tournaments.import.form', $tournament) }}" class="btn btn-soft">Cancelar</a>
</form>

<script>
    (function() {
        // Mirror of GroupGenerationService::distribution() + match-count rules.
        function distribution(n, pref) {
            if (n <= 0) return [];
            if (n <= 5 && n < 2 * pref) return [n];
            let groups = Math.max(1, Math.ceil(n / pref));
            while (groups > 1 && (n / groups) < 3) groups--;
            const base = Math.floor(n / groups),
                rem = n % groups;
            const sizes = Array(groups).fill(base);
            for (let i = 0; i < rem; i++) sizes[i]++;
            sizes.sort((a, b) => b - a);
            return sizes;
        }

        function groupMatches(sizes) {
            return sizes.reduce((t, s) => t + (s === 4 ? 4 : (s < 2 ? 0 : s * (s - 1) / 2)), 0);
        }

        const table = document.querySelector('[data-import-table]');
        if (!table) return;
        const warnBox = document.querySelector('[data-import-warnings]');

        function recalc() {
            let tG = 0,
                tGM = 0,
                tEM = 0,
                tT = 0;
            const warnings = [];

            table.querySelectorAll('[data-cat-row]').forEach((row) => {
                const pairs = parseInt(row.dataset.pairs, 10) || 0;
                const size = parseInt(row.querySelector('[data-f="size"]').value, 10) || 3;
                const advance = Math.max(1, Math.min(2, parseInt(row.querySelector('[data-f="advance"]').value, 10) || 1));
                const extra = Math.max(0, Math.min(3, parseInt(row.querySelector('[data-f="extra"]').value, 10) || 0));

                const sizes = distribution(pairs, size);
                const groups = sizes.length;
                const gm = groupMatches(sizes);
                const quals = groups * advance + extra;
                const em = Math.max(0, quals - 1);
                const total = gm + em;

                row.querySelector('[data-out="groups"]').textContent = groups;
                row.querySelector('[data-out="gmatches"]').textContent = gm;
                row.querySelector('[data-out="ematches"]').textContent = em;
                row.querySelector('[data-out="total"]').textContent = total;

                const catName = row.querySelector('td').childNodes[0].textContent.trim();
                if (pairs < 2) warnings.push(`«${catName}»: solo ${pairs} pareja(s), no se puede generar.`);
                else if (sizes.length === 1 && sizes[0] >= 5) warnings.push(`«${catName}»: ${pairs} parejas caben en un solo grupo de ${sizes[0]} (sin llave real).`);
                else if (quals < 2) warnings.push(`«${catName}»: solo ${quals} clasificado(s); revisa "avanzan/extra".`);

                tG += groups;
                tGM += gm;
                tEM += em;
                tT += total;
            });

            table.querySelector('[data-total="groups"]').textContent = tG;
            table.querySelector('[data-total="gmatches"]').textContent = tGM;
            table.querySelector('[data-total="ematches"]').textContent = tEM;
            table.querySelector('[data-total="total"]').textContent = tT;

            if (warnBox) {
                warnBox.innerHTML = warnings.length ?
                    '<div style="font-size:12px;color:var(--warning,#b8860b);">' + warnings.map((w) => '⚠ ' + w).join('<br>') + '</div>' :
                    '';
            }
        }

        table.addEventListener('input', recalc);
        table.addEventListener('change', recalc);
        recalc();
    })();
</script>
@endsection