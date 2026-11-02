import './bootstrap';

// No inline scripts are allowed by our Content-Security-Policy, so page behaviour is declared with data attributes.

// <form data-confirm="Are you sure?"> asks before submitting.
document.addEventListener('submit', (event) => {
    const message = event.target instanceof HTMLFormElement ? event.target.dataset.confirm : null;
    if (message && !window.confirm(message)) {
        event.preventDefault();
    }
});

// <ul data-password-rules data-for="password" data-confirm="password_confirmation"> ticks each rule off as the person types.
// The server still checks everything; this only saves a round trip.
const passwordRuleChecks = {
    length: (p) => p.length >= 10,
    upper: (p) => /[A-Z]/.test(p),
    lower: (p) => /[a-z]/.test(p),
    number: (p) => /[0-9]/.test(p),
};
const updatePasswordRules = (list) => {
    const password = document.querySelector(`[name="${list.dataset.for}"]`)?.value ?? '';
    const confirmation = document.querySelector(`[name="${list.dataset.confirm}"]`)?.value ?? '';
    list.querySelectorAll('[data-rule]').forEach((item) => {
        const rule = item.dataset.rule;
        const ok = rule === 'match' ? password !== '' && password === confirmation : passwordRuleChecks[rule](password);
        item.classList.toggle('text-emerald-700', ok);
        item.querySelector('[data-mark]').textContent = ok ? '✓' : '○';
    });
};
document.addEventListener('input', (event) => {
    if (event.target instanceof HTMLInputElement) {
        document.querySelectorAll('[data-password-rules]').forEach((list) => {
            if (event.target.name === list.dataset.for || event.target.name === list.dataset.confirm) {
                updatePasswordRules(list);
            }
        });
    }
});

// <select data-autosubmit> submits its form when changed.
document.addEventListener('change', (event) => {
    if (event.target instanceof HTMLElement && event.target.matches('[data-autosubmit]') && event.target.form) {
        event.target.form.submit();
    }
});

// Navigation menus. Markup: [data-dropdown] > [data-dropdown-toggle] + [data-dropdown-panel]; [data-nav-toggle] opens the mobile menu.
const closeDropdowns = (except = null) => {
    document.querySelectorAll('[data-dropdown]').forEach((dropdown) => {
        if (dropdown === except) {
            return;
        }
        dropdown.querySelector('[data-dropdown-panel]')?.classList.remove('open');
        dropdown.querySelector('[data-dropdown-toggle]')?.setAttribute('aria-expanded', 'false');
    });
};

document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element)) {
        return;
    }

    const navToggle = event.target.closest('[data-nav-toggle]');
    if (navToggle) {
        const open = document.querySelector('[data-nav]')?.classList.toggle('open');
        navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        return;
    }

    const toggle = event.target.closest('[data-dropdown-toggle]');
    if (toggle) {
        const dropdown = toggle.closest('[data-dropdown]');
        closeDropdowns(dropdown);
        const open = dropdown.querySelector('[data-dropdown-panel]').classList.toggle('open');
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        return;
    }

    if (!event.target.closest('[data-dropdown-panel]')) {
        closeDropdowns();
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        closeDropdowns();
    }
});
