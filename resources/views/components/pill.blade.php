{{-- resources/views/components/pill.blade.php
     Status pill. Usage:
       <x-pill variant="ok" dot>Pagada</x-pill>
       <x-pill variant="warn">Pendiente</x-pill>
     Variants: ok | warn | bad | accent | neutral --}}
@props([
'variant' => 'neutral',
'dot' => false,
])

<span {{ $attributes->merge(['class' => "pill pill--{$variant}"]) }}>
    @if($dot)<span class="dot"></span>@endif
    {{ $slot }}
</span>