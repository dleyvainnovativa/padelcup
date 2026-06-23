{{-- Platform ad carousel (16:9). Expects $ads (collection of App\Models\Ad). --}}
@if(isset($ads) && $ads->isNotEmpty())
<div class="pub-ads" aria-label="Publicidad">
    <div class="pub-ads__label">Publicidad</div>
    <div class="pub-ads__track" data-ad-carousel>
        @foreach($ads as $ad)
            <div class="pub-ad">
                @if($ad->link_url)
                    <a href="{{ route('ads.click', $ad) }}" target="_blank" rel="noopener sponsored">
                        <img src="{{ $ad->imageUrl() }}" alt="{{ $ad->title }}" loading="lazy">
                    </a>
                @else
                    <img src="{{ $ad->imageUrl() }}" alt="{{ $ad->title }}" loading="lazy">
                @endif
            </div>
        @endforeach
    </div>
</div>
@endif
