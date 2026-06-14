@props(['items' => []])

{{-- Breadcrumb: pass an array of ['label' => ..., 'url' => ...]. The last item
     (or any without a url) renders as plain text (current page). --}}
@if(count($items))
<nav class="tc-breadcrumb" aria-label="breadcrumb">
    @foreach($items as $i => $item)
    @if(!empty($item['url']) && !$loop->last)
    <a href="{{ $item['url'] }}" class="tc-breadcrumb__link">{{ $item['label'] }}</a>
    <i class="fa-solid fa-chevron-right tc-breadcrumb__sep"></i>
    @else
    <span class="tc-breadcrumb__current" aria-current="page">{{ $item['label'] }}</span>
    @endif
    @endforeach
</nav>
@endif