{{-- Cross-table ("cruces") view of a group's round-robin. Rows = pairs, columns
     = same pairs (1..N); each cell = head-to-head result from the row pair's
     perspective. Diagonal is the pair itself (shaded). CRUCES/HORARIO list each
     pair's upcoming matches + times. Horizontal-scroll on mobile with the pair
     name column frozen. Expects $crossTables. --}}

@forelse($crossTables as $ct)
<div class="pub-card" style="margin-bottom:16px;">
    <div class="pub-card__head">{{ $ct['name'] }}</div>
    <div class="cruces-scroll">
        <table class="cruces">
            <thead>
                <tr>
                    <th class="cruces__rank">#</th>
                    <th class="cruces__pair">Parejas</th>
                    @foreach($ct['order'] as $i => $pid)
                    <th class="cruces__num">{{ $i + 1 }}</th>
                    @endforeach
                    <th class="cruces__cross">Cruces</th>
                    <th class="cruces__time">Horario</th>
                </tr>
            </thead>
            <tbody>
                @foreach($ct['order'] as $rowPos => $rowPid)
                <tr>
                    <td class="cruces__rank">{{ $rowPos + 1 }}</td>
                    <td class="cruces__pair">{{ $ct['names'][$rowPid] }}</td>

                    @foreach($ct['order'] as $colPos => $colPid)
                    @php $cell = $ct['cells'][$rowPid][$colPid] ?? null; @endphp
                    @if($rowPid === $colPid)
                    <td class="cruces__self"></td>
                    @elseif($cell && $cell['played'])
                    <td class="cruces__cell {{ $cell['won'] ? 'is-won' : 'is-lost' }}">{{ $cell['score'] }}</td>
                    @elseif($cell && !$cell['played'] && $cell['when'])
                    <td class="cruces__cell cruces__cell--sched">{{ $cell['when']->timezone('America/Mexico_City')->translatedFormat('D H:i') }}</td>
                    @else
                    <td class="cruces__cell cruces__cell--empty">—</td>
                    @endif
                    @endforeach

                    {{-- CRUCES: this pair's matchups (played + upcoming) --}}
                    <td class="cruces__cross">
                        @forelse($ct['schedule'][$rowPid] ?? [] as $u)
                        <span class="cruces__cross-pill {{ $u['played'] ? 'is-done' : '' }}">{{ $u['cross'] }}</span>
                        @empty
                        <span class="pub-muted">—</span>
                        @endforelse
                    </td>
                    {{-- HORARIO: times (+court) for those matchups; played ones dimmed + ✓ --}}
                    <td class="cruces__time">
                        @forelse($ct['schedule'][$rowPid] ?? [] as $u)
                        <span class="cruces__time-line {{ $u['played'] ? 'is-done' : '' }}">
                            @if($u['played'])<i class="fa-solid fa-check cruces__done-ic"></i>@endif
                            @if($u['when']){{ $u['when']->timezone('America/Mexico_City')->translatedFormat('D d M · H:i') }}@else<span class="pub-muted">Programado</span>@endif
                            @if($u['court']) · {{ $u['court'] }}@endif
                        </span>
                        @empty
                        <span class="pub-muted">—</span>
                        @endforelse
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@empty
<div class="pub-empty">No hay grupos para mostrar.</div>
@endforelse