const header = document.querySelector('[data-header]');
const nav = header.querySelector('#primary-nav');
const toggle = header.querySelector('.menu-toggle');
const groups = [...nav.querySelectorAll('.nav-group')];
const narrow = matchMedia('(max-width: 1039px)');

function closeGroups(except) {
  for (const group of groups) {
    if (group !== except) group.open = false;
  }
}

function setMenu(open, restoreFocus = false) {
  const active = narrow.matches && open;
  toggle.setAttribute('aria-expanded', String(active));
  toggle.firstElementChild.textContent = active ? 'Close' : 'Menu';
  nav.hidden = narrow.matches && !active;
  if (!active) closeGroups();
  if (restoreFocus) toggle.focus();
}

toggle.addEventListener('click', () => setMenu(toggle.getAttribute('aria-expanded') === 'false'));
narrow.addEventListener('change', () => {
  const focusInNav = nav.contains(document.activeElement);
  const focusOnToggle = document.activeElement === toggle;
  setMenu(false, narrow.matches && focusInNav);
  if (!narrow.matches && focusOnToggle) nav.querySelector('a').focus();
});

for (const group of groups) {
  group.addEventListener('toggle', () => {
    if (group.open) closeGroups(group);
  });
}

nav.addEventListener('click', (event) => {
  const link = event.target.closest('a');
  if (!link) return;
  const target = link.hash && link.pathname === location.pathname
    ? document.getElementById(link.hash.slice(1)) : null;
  setMenu(false);
  if (target) {
    // Let the anchor update browser history and scroll; move focus out of the closed menu.
    target.setAttribute('tabindex', '-1');
    target.focus({preventScroll: true});
  }
});

document.addEventListener('keydown', (event) => {
  if (event.key !== 'Escape') return;
  const openGroup = groups.find((group) => group.open);
  if (openGroup) {
    openGroup.open = false;
    openGroup.querySelector('summary').focus();
  } else if (toggle.getAttribute('aria-expanded') === 'true') {
    setMenu(false, true);
  }
});

document.addEventListener('click', (event) => {
  if (!header.contains(event.target)) setMenu(false);
});

const utility = document.querySelector('.utility');
if ('IntersectionObserver' in window && utility) {
  const observer = new IntersectionObserver(([entry]) => {
    header.classList.toggle('is-stuck', !entry.isIntersecting);
  });
  observer.observe(utility);
}

document.documentElement.classList.add('js-nav');
toggle.hidden = false;
setMenu(false);
