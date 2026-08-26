@extends('layouts.public')

@section('title', $system->name . ' · Resumen')

@section('content')
<div class="pub-rank">

    <div class="pub-rank__head">
        <div class="pub-rank__eyebrow">Ranking @if($system->owner_label) · {{ $system->owner_label }}@endif</div>
        <h1 class="pub-rank__title">{{ $system->name }}</h1>
        <div class="pub-rank__sub">Resumen por categorías</div>
    </div>

    <div class="rk-cats rk-cats--public">
        <a href="{{ route('public.rankings.show', [$system, 'cat' => 'all']) }}" class="rk-cat">General</a>
        @foreach($categories as $c)
        <a href="{{ route('public.rankings.show', [$system, 'cat' => $c['key']]) }}" class="rk-cat">{{ $c['label'] }}</a>
        @endforeach
    </div>

    {{-- Combined --}}
    <div class="pub-rank__section">
        <div class="pub-rank__section-title">General</div>
        @include('public.rankings.partials.mini-board', ['board' => $combined])
    </div>

    {{-- Per category --}}
    @foreach($byCategory as $entry)
    <div class="pub-rank__section">
        <div class="pub-rank__section-title">
            {{ $entry['label'] }}
            <a href="{{ route('public.rankings.show', [$system, 'cat' => $entry['key']]) }}"
                class="pub-rank__section-link">ver todo</a>
        </div>
        @include('public.rankings.partials.mini-board', ['board' => $entry['board']])
    </div>
    @endforeach

</div>
@endsection