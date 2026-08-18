@extends('layouts.app')

@section('title', 'Previsualizar parejas')

{{--
    Per-category pair import preview — PATCHED for #1 + #2.

    This category already EXISTS with its own preferred_group_size and
    group_format. So here we:
      #1  show a read-only breakdown of the group sizes the category will have
          AFTER this import (existing occupied slots + newly imported pairs),
          using the same distribution() rules as the tournament flow.
      #2  offer a Mexicano / Round-robin toggle that, on commit, updates the
          category's group_format (defaults to the category's current format).

    New controller inputs on commit:
      group_format = 'mex' | 'rr'   (see PlayerImportController patch)

    Requires two extra values from the controller (see controller patch):
      $existingPairs   int   pairs already in the category (occupied slots)
      $preferredSize   int   category->preferred_group_size (fallback 4)
      $currentFormat   string 'mex' | 'rr'  (category's current group_format)
--}}

@php
$exceedsCapacity = $remaining !== null && $rows->count() > $remaining;

// Defensive defaults if the controller wasn't patched yet (keeps the page working).
$existingPairs = $existingPairs ?? 0;
$preferredSize = $preferredSize ?? ($category->preferred_group_size ?: 4);
$currentFormat = $currentFormat ?? 'mex';
@endphp

@section('content')
<div class="page-head">
    <div>
        <h1>Previsualizar parejas</h1>
        <div class="page-sub">{{ $category->name }} · revisa duplicados antes de confirmar</div>
    </div>
</div>

@if(count($errors))
<div class="alert py-2 px-3 mb-3" style="font-size:13px;border-radius:var(--radius);background:var(--warning-soft);color:var(--warning-text);">
    <strong>{{ count($errors) }} filas con problemas (se omitirán):</strong>
    <ul class="mb-0 mt-1">@foreach($errors as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

@if($exceedsCapacity)
<div class="alert py-2 px-3 mb-3" style="font-size:13px;border-radius:var(--radius);background:var(--warning-soft);color:var(--warning-text);">
    <i class="fa-solid fa-triangle-exclamation me-1"></i>
    La categoría tiene espacio para {{ $remaining }} parejas más, pero el archivo trae {{ $rows->count() }}.
    Las que excedan el cupo se omitirán. Aumenta el cupo si quieres incluirlas todas.
</div>
@endif

@if($rows->isEmpty())
<div class="tc-card">
    <div class="tc-card__body" style="color:var(--text-muted);">
        No hay parejas válidas para importar. <a href="{{ route('pairs.import.form', [$tournament, $category]) }}">Volver</a>.
    </div>
</div>
@else
<form method="POST" action="{{ route('pairs.import.commit', [$tournament, $category]) }}">
    @csrf

    {{-- #1 + #2 — group preview + format toggle -------------------------------}}
    <div class="tc-card mb-3" data-group-preview
        data-existing="{{ $existingPairs }}"
        data-importing="{{ $rows->count() }}"
        data-remaining="{{ $remaining === null ? '' : $remaining }}"
        data-preferred="{{ $preferredSize }}">
        <div class="tc-card__head">
            <h3>Grupos resultantes</h3>
            <p style="font-size:12px;color:var(--text-muted);margin:4px 0 0;">
                Estimación de los grupos que tendrá la categoría después de importar. Los grupos de 3 y 5 juegan todos contra todos; los de 4 según el formato elegido.
            </p>
        </div>
        <div class="tc-card__body">
            <div style="display:flex;flex-wrap:wrap;gap:20px;align-items:flex-end;">
                <div>
                    <div style="font-size:11px;color:var(--text-faint);text-transform:uppercase;letter-spacing:.05em;">Parejas totales</div>
                    <div data-gp="total-pairs" class="pub-mono" style="font-size:20px;font-weight:700;">—</div>
                </div>
                <div>
                    <div style="font-size:11px;color:var(--text-faint);text-transform:uppercase;letter-spacing:.05em;">Grupos</div>
                    <div data-gp="groups" class="pub-mono" style="font-size:20px;font-weight:700;">—</div>
                </div>
                <div>
                    <div style="font-size:11px;color:var(--text-faint);text-transform:uppercase;letter-spacing:.05em;">Distribución</div>
                    <div data-gp="dist" class="pub-mono" style="font-size:20px;font-weight:700;">—</div>
                </div>
                <div>
                    <div style="font-size:11px;color:var(--text-faint);text-transform:uppercase;letter-spacing:.05em;">Part. de grupos</div>
                    <div data-gp="gmatches" class="pub-mono" style="font-size:20px;font-weight:700;">—</div>
                </div>

                <div style="margin-left:auto;">
                    <label style="font-size:11px;color:var(--text-faint);text-transform:uppercase;letter-spacing:.05em;display:block;margin-bottom:3px;">
                        Formato (grupos de 4)
                    </label>
                    <select name="group_format" data-gp="format" class="form-select form-select-sm" style="width:150px;border-radius:var(--radius);">
                        <option value="mex" @selected($currentFormat==='mex' )>Mexicano</option>
                        <option value="rr" @selected($currentFormat==='rr' )>Todos contra todos</option>
                    </select>
                </div>
            </div>
            <div data-gp="note" style="font-size:12px;color:var(--text-faint);margin-top:10px;"></div>
        </div>
    </div>

    <div class="tc-card">
        <div class="tc-card__head">
            <h3>{{ $rows->count() }} parejas</h3>
            <button type="submit" class="btn btn-accent btn-sm">Confirmar importación</button>
        </div>
        <div class="tc-table-wrap">
            <table class="tc-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Jugador 1</th>
                        <th>Jugador 2</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $i => $row)
                    <tr>
                        <td style="color:var(--text-faint);">{{ $i + 1 }}</td>
                        @foreach(['player1', 'player2'] as $slot)
                        <td>
                            <div style="font-weight:500;">{{ $row[$slot]['name'] }}</div>
                            <div style="font-size:11px;color:var(--text-faint);">
                                {{ $row[$slot]['email'] ?? '—' }}{{ $row[$slot]['phone'] ? ' · '.$row[$slot]['phone'] : '' }}
                            </div>
                            <input type="hidden" name="rows[{{ $i }}][{{ $slot }}][name]" value="{{ $row[$slot]['name'] }}">
                            <input type="hidden" name="rows[{{ $i }}][{{ $slot }}][email]" value="{{ $row[$slot]['email'] }}">
                            <input type="hidden" name="rows[{{ $i }}][{{ $slot }}][phone]" value="{{ $row[$slot]['phone'] }}">
                            @if(count($row[$slot]['possible_duplicates']))
                            <select name="rows[{{ $i }}][{{ $slot }}][link_player_id]" class="form-select form-select-sm mt-1" style="border-radius:var(--radius);max-width:260px;">
                                @foreach($row[$slot]['possible_duplicates'] as $dupe)
                                <option value="{{ $dupe['id'] }}" @selected($loop->first)>Vincular: {{ $dupe['name'] }}{{ $dupe['email'] ? ' ('.$dupe['email'].')' : '' }}</option>
                                @endforeach
                                <option value="">Crear nuevo</option>
                            </select>
                            @endif
                        </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="d-flex gap-2 mt-3">
        <button type="submit" class="btn btn-accent">Confirmar importación</button>
        <a href="{{ route('pairs.import.form', [$tournament, $category]) }}" class="btn btn-soft">Cancelar</a>
    </div>
</form>

<script>
    (function() {
        const box = document.querySelector('[data-group-preview]');
        if (!box) return;

        // Same distribution() as GroupGenerationService (and the tournament flow).
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

        function groupMatches(sizes, isMex) {
            return sizes.reduce((t, s) => {
                if (s < 2) return t;
                if (s === 4 && isMex) return t + 4;
                return t + (s * (s - 1) / 2);
            }, 0);
        }

        function breakdown(sizes) {
            if (!sizes.length) return '—';
            const c = {};
            sizes.forEach((s) => {
                c[s] = (c[s] || 0) + 1;
            });
            return Object.keys(c).sort((a, b) => b - a).map((s) => `${c[s]}×${s}`).join(' · ');
        }

        const existing = parseInt(box.dataset.existing, 10) || 0;
        const importing = parseInt(box.dataset.importing, 10) || 0;
        const remainingRaw = box.dataset.remaining;
        const remaining = remainingRaw === '' ? null : (parseInt(remainingRaw, 10) || 0);
        const preferred = parseInt(box.dataset.preferred, 10) || 4;

        // If capacity caps the import, only that many will actually land.
        const effectiveImport = remaining === null ? importing : Math.min(importing, remaining);
        const totalPairs = existing + effectiveImport;

        const fmtSel = box.querySelector('[data-gp="format"]');

        function render() {
            const isMex = (fmtSel.value || 'mex') === 'mex';
            const sizes = distribution(totalPairs, preferred);
            box.querySelector('[data-gp="total-pairs"]').textContent = totalPairs;
            box.querySelector('[data-gp="groups"]').textContent = sizes.length || '—';
            box.querySelector('[data-gp="dist"]').textContent = breakdown(sizes);
            box.querySelector('[data-gp="gmatches"]').textContent = groupMatches(sizes, isMex);

            const has4 = sizes.includes(4);
            const note = box.querySelector('[data-gp="note"]');
            const parts = [];
            if (existing > 0) parts.push(`${existing} ya en la categoría + ${effectiveImport} a importar`);
            if (remaining !== null && importing > remaining) parts.push(`${importing - remaining} se omitirán por cupo`);
            if (!has4) parts.push('el formato solo afecta a grupos de 4; esta distribución no tiene ninguno');
            note.textContent = parts.length ? '· ' + parts.join(' · ') : '';
            fmtSel.style.opacity = has4 ? '1' : '0.5';
        }

        fmtSel.addEventListener('change', render);
        render();
    })();
</script>
@endif
@endsection