(() => {
  const controls = document.querySelector('[data-catalogue-filters]');
  const grid = document.querySelector('[data-catalogue-grid]');
  const cards = [...grid.querySelectorAll('[data-commodity]')];
  const results = document.querySelector('[data-catalogue-count]');
  const empty = document.querySelector('[data-catalogue-empty]');
  const more = document.querySelector('[data-catalogue-more]');
  const search = controls.elements.q;
  const categories = [...controls.querySelectorAll('[name="category"]')];
  const sort = controls.elements.sort;
  const pageSize = 9;
  const collator = new Intl.Collator('en', { sensitivity: 'base' });
  const fold = (str) => str.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
  let limit = pageSize;

  function state() {
    return { q: search.value.trim(), category: controls.elements.category.value, sort: sort.value };
  }

  function restore() {
    const params = new URLSearchParams(location.search);
    search.value = (params.get('q') || '').slice(0, search.maxLength);
    const category = categories.find((input) => input.value === params.get('category'));
    (category || categories[0]).checked = true;
    sort.value = [...sort.options].some((option) => option.value === params.get('sort'))
      ? params.get('sort') : '';
    const requested = Number(params.get('show'));
    limit = Number.isInteger(requested) && requested >= pageSize
      ? Math.min(requested, Math.max(cards.length, pageSize)) : pageSize;
  }

  function remember({q, category, sort: order}) {
    const url = new URL(location.href);
    for (const [key, value] of Object.entries({q, category, sort: order, show: limit > pageSize ? limit : ''})) {
      if (value) url.searchParams.set(key, value);
      else url.searchParams.delete(key);
    }
    if (url.href !== location.href) {
      // Some browsers restrict history changes for files. Filtering still works locally.
      try { history.replaceState(history.state, '', url); } catch {}
    }
  }

  function render({focusNext = false, updateUrl = true} = {}) {
    const filters = state();
    const words = fold(filters.q).split(/\s+/).filter(Boolean);
    const sorted = [...cards];
    if (filters.sort) {
      const direction = filters.sort === 'desc' ? -1 : 1;
      sorted.sort((a, b) => direction * collator.compare(a.dataset.name, b.dataset.name));
    }
    let total = 0;
    let firstNew;
    const ordered = document.createDocumentFragment();
    for (const card of sorted) {
      const terms = fold(`${card.dataset.name} ${card.dataset.keywords || ''}`);
      const matches = (!filters.category || card.dataset.category === filters.category)
        && words.every((word) => terms.includes(word));
      const show = matches && ++total <= limit;
      if (focusNext && show && card.hidden && !firstNew) firstNew = card;
      card.hidden = !show;
      ordered.append(card);
    }
    grid.append(ordered);
    const visible = Math.min(total, limit);
    results.textContent = total > 1 ? `Showing ${visible} of ${total} commodities` : `${total} ${total === 1 ? 'commodity' : 'commodities'} found`;
    empty.hidden = total !== 0;
    more.hidden = visible === total;
    if (updateUrl) remember(filters);
    if (firstNew) firstNew.querySelector('h2 a').focus();
  }

  function reset() {
    controls.reset();
    limit = pageSize;
    render();
    search.focus();
  }

  search.addEventListener('input', () => { limit = pageSize; render(); });
  controls.addEventListener('change', (event) => {
    if (event.target === search) return;
    limit = pageSize;
    render();
  });
  controls.addEventListener('submit', (event) => { event.preventDefault(); render(); });
  document.querySelectorAll('[data-catalogue-reset]').forEach((button) => button.addEventListener('click', reset));
  more.addEventListener('click', () => { limit += pageSize; render({focusNext:true}); });
  window.addEventListener('popstate', () => { restore(); render({updateUrl:false}); });
  restore();
  render({updateUrl:false});
  controls.hidden = false;
})();
