{{-- resources/views/components/stat-tile.blade.php
     Dashboard stat tile. Usage:
       <x-stat-tile icon="fa-people-group" label="Parejas inscritas"
                    value="64" delta="8 esta semana" trend="up" accent />
     trend: up | down | none --}}
@props([
'icon' => 'fa-circle',
'label' => '',
'value' => '',
'delta' => null,
'trend' => 'none',
'accent' => false,
])

@php
$deltaClass = match ($trend) {
'up' => 'stat-tile__delta--up',
'down' => 'stat-tile__delta--down',
default => '',
};
$deltaIcon = match ($trend) {
'up' => 'fa-arrow-up',
'down' => 'fa-triangle-exclamation',
default => '',
};
@endphp

<div {{ $attributes->merge(['class' => 'stat-tile']) }}>
    <div class="stat-tile__ico {{ $accent ? 'stat-tile__ico--accent' : '' }}">
        <i class="fa-solid {{ $icon }}"></i>
    </div>
    <div class="stat-tile__label">{{ $label }}</div>
    <div class="stat-tile__value">{{ $value }}</div>
    @if($delta)
    <div class="stat-tile__delta {{ $deltaClass }}">
        @if($deltaIcon)<i class="fa-solid {{ $deltaIcon }}"></i>@endif
        {{ $delta }}
    </div>
    @endif
</div>