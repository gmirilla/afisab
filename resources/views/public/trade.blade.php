@push('scripts')<script defer src="{{ asset('site/js/catalogue.js') }}"></script>@endpush
<x-site-layout title="Commodities" description="Browse agricultural commodities by crop type and request availability, specifications and a supply quote.">
<section class="trade-banner">
      <div class="wrap trade-banner-grid">
        <div>
          <nav class="breadcrumb" aria-label="Breadcrumb"><a href="{{ route('home') }}">Home</a><span aria-hidden="true">/</span><span aria-current="page">Commodities</span></nav>
          <p class="eyebrow">AFISAB COMMODITY CATALOGUE</p>
          <h1 class="display">Find the crop<br><em>you need.</em></h1>
          <p>Browse grains, oilseeds, tree crops and cash crops. Ask our team to confirm availability, specifications and supply terms for your order.</p>
        </div>
        <div class="trade-mosaic" aria-hidden="true">
          <img src="{{ asset('site/assets') }}/images/maize-720.webp" width="720" height="480" alt="">
          <img src="{{ asset('site/assets') }}/images/sesame-seeds-400.webp" width="400" height="267" alt="">
          <img src="{{ asset('site/assets') }}/images/shea-nuts-400.webp" width="400" height="267" alt="">
        </div>
      </div>
    </section>
    <section class="trade-listing" aria-label="Browse commodities">
      <div class="wrap">
        <form class="catalogue-controls" data-catalogue-filters hidden>
          <div class="catalogue-toolbar">
            <div class="trade-field"><label for="catalogue-search">Search commodities</label><div class="catalogue-search-wrap"><svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><circle cx="10.5" cy="10.5" r="5.5"/><path d="m15 15 4 4"/></svg><input type="search" name="q" id="catalogue-search" maxlength="80" placeholder="Try sesame, maize or fonio…" aria-controls="catalogue-grid"></div></div>
            <div class="trade-field"><label for="catalogue-sort">Sort by</label><select name="sort" id="catalogue-sort"><option value="">Featured first</option><option value="asc">Name: A–Z</option><option value="desc">Name: Z–A</option></select></div>
            <button type="button" class="trade-reset" data-catalogue-reset>Clear filters</button>
          </div>
          <fieldset class="catalogue-categories">
            <legend class="filter-label">Commodity category</legend>
            <div class="category-options">
              <span class="category-option"><input type="radio" name="category" id="filter-all" value="" checked><label for="filter-all">All Commodities</label></span>
              <span class="category-option"><input type="radio" name="category" id="filter-grains" value="grains"><label for="filter-grains">Grains</label></span>
              <span class="category-option"><input type="radio" name="category" id="filter-oilseeds" value="oilseeds"><label for="filter-oilseeds">Oilseeds</label></span>
              <span class="category-option"><input type="radio" name="category" id="filter-tree-crops" value="tree-crops"><label for="filter-tree-crops">Tree Crops</label></span>
              <span class="category-option"><input type="radio" name="category" id="filter-cash-crops" value="cash-crops"><label for="filter-cash-crops">Cash Crops</label></span>
            </div>
          </fieldset>
        </form>
        <p class="catalogue-count" data-catalogue-count role="status" aria-live="polite">{{ count($items) }} commodities</p>
        <div class="commodities-grid" id="catalogue-grid" data-catalogue-grid>
          @foreach($items as $slug => $c)@include('public._commodity-card', ['slug' => $slug, 'c' => $c, 'catalogue' => true])@endforeach
        </div>
        <div class="catalogue-empty" data-catalogue-empty hidden>
          <h2>No commodities match</h2>
          <p>Try a different commodity name or clear the category filter.</p>
          <button type="button" class="trade-reset" data-catalogue-reset>Clear filters</button>
        </div>
        <button type="button" class="button button--forest catalogue-more" data-catalogue-more hidden>Show more commodities <svg class="icon icon--arrow" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12h15m-6-6 6 6-6 6"/></svg></button>
      </div>
    </section>
</x-site-layout>
