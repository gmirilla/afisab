@props(['slug', 'c', 'catalogue' => false])
@php $img = asset('site/assets').'/images/'.$slug; @endphp
<article class="commodity-card {{ $catalogue ? 'trade-catalogue-card' : '' }}" @if($catalogue) data-commodity data-name="{{ $c['name'] }}" data-category="{{ $c['category'] }}" data-keywords="{{ $c['keywords'] }}" @endif>
    <a class="commodity-photo" href="{{ route('public.commodity', $slug) }}" aria-label="View {{ $c['name'] }} details">
      <img src="{{ $img }}-720.webp" srcset="{{ $img }}-400.webp 400w, {{ $img }}-720.webp 720w" sizes="(min-width: 1320px) 390px, (min-width: 900px) 30vw, (min-width: 600px) 46vw, 92vw" width="720" height="480" alt="{{ $c['card_alt'] }}" decoding="async" loading="lazy">
    </a>
    <div class="commodity-body">
      <p class="commodity-category">{{ $c['category_label'] }}</p>
      @if($catalogue)<h2><a href="{{ route('public.commodity', $slug) }}">{{ $c['name'] }}</a></h2>@else<h3><a href="{{ route('public.commodity', $slug) }}">{{ $c['name'] }}</a></h3>@endif
      <p>{{ $catalogue ? $c['card'] : $c['summary'] }}</p>
      @if($catalogue)<p class="stock-status">{{ $c['availability'] }}</p>@endif
      <div class="commodity-actions"><a class="text-link" href="{{ route('public.commodity', $slug) }}">View Details<svg class="icon icon--arrow" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12h15m-6-6 6 6-6 6"/></svg></a><a class="quiet-link" href="{{ route('public.buyers', ['commodity' => $c['db_name']]) }}#enquiry" aria-label="{{ $catalogue ? 'Request quote for' : 'Enquire about' }} {{ $c['name'] }}">{{ $catalogue ? 'Request Quote' : 'Enquire' }} <svg class="icon icon--diagonal" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 18 18 6M8 6h10v10"/></svg></a></div>
    </div>
  </article>
