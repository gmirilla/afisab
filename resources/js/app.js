import './bootstrap';

// No inline scripts are allowed by our Content-Security-Policy, so page behaviour is declared with data attributes.

// <form data-confirm="Are you sure?"> asks before submitting.
document.addEventListener('submit', (event) => {
    const message = event.target instanceof HTMLFormElement ? event.target.dataset.confirm : null;
    if (message && !window.confirm(message)) {
        event.preventDefault();
    }
});

// <select data-autosubmit> submits its form when changed.
document.addEventListener('change', (event) => {
    if (event.target instanceof HTMLElement && event.target.matches('[data-autosubmit]') && event.target.form) {
        event.target.form.submit();
    }
});
