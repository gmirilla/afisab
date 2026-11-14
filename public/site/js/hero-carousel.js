const hero = document.querySelector('[data-hero]');
const slides = [...hero.querySelectorAll('[data-slide]')];
const controls = hero.querySelector('[data-hero-controls]');
const caption = hero.querySelector('[data-hero-caption]');
const rotationButton = controls.querySelector('[data-hero-action="toggle"]');
const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
let current = 0;
let timer;
let rotating = !reducedMotion.matches;
let hovering = false;
let resumeWhileInteracting = false;

function showSlide(index, announce = false) {
  slides[current].classList.remove('is-active');
  current = (index + slides.length) % slides.length;
  slides[current].classList.add('is-active');
  caption.setAttribute('aria-live', announce ? 'polite' : 'off');
  caption.textContent = slides[current].dataset.caption;
}

function schedule() {
  clearTimeout(timer);
  rotationButton.dataset.rotating = String(rotating);
  rotationButton.setAttribute('aria-label', rotating ? 'Pause image rotation' : 'Play image rotation');
  const interacting = hovering || hero.contains(document.activeElement);
  if (!rotating || document.hidden || (interacting && !resumeWhileInteracting)) return;
  timer = setTimeout(() => {
    showSlide(current + 1);
    schedule();
  }, 7000);
}

controls.addEventListener('click', (event) => {
  const button = event.target.closest('[data-hero-action]');
  if (!button) return;
  const action = button.dataset.heroAction;
  if (action === 'toggle') {
    rotating = !rotating;
    resumeWhileInteracting = rotating;
  } else {
    rotating = false;
    resumeWhileInteracting = false;
    showSlide(current + (action === 'next' ? 1 : -1), true);
  }
  schedule();
});

hero.addEventListener('pointerenter', (event) => {
  if (event.pointerType !== 'mouse') return;
  hovering = true;
  resumeWhileInteracting = false;
  schedule();
});
hero.addEventListener('pointerleave', (event) => {
  if (event.pointerType !== 'mouse') return;
  hovering = false;
  schedule();
});
hero.addEventListener('focusin', () => {
  resumeWhileInteracting = false;
  schedule();
});
hero.addEventListener('focusout', () => queueMicrotask(schedule));
document.addEventListener('visibilitychange', schedule);
reducedMotion.addEventListener('change', () => {
  rotating = !reducedMotion.matches;
  schedule();
});

controls.hidden = false;
schedule();
