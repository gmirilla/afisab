// Enquiry forms: the "Add another commodity" button reveals the next spare row, and the name box for "Other" shows only when
// Other is chosen. Without JavaScript three rows are visible and the name box is always shown, so the form still works.
(() => {
  const updateOther = (select) => {
    const input = select.closest('.trade-field')?.querySelector('[data-other-input]');
    if (input) input.hidden = select.value !== 'Other';
  };
  const updateAdd = () => {
    const button = document.querySelector('[data-add-row]');
    if (button) button.hidden = document.querySelector('[data-extra-row][hidden]') === null;
  };
  document.querySelectorAll('[data-commodity-select]').forEach(updateOther);
  updateAdd();
  document.addEventListener('change', (event) => {
    if (event.target instanceof HTMLSelectElement && event.target.matches('[data-commodity-select]')) updateOther(event.target);
  });
  document.addEventListener('click', (event) => {
    if (event.target instanceof Element && event.target.closest('[data-add-row]')) {
      const next = document.querySelector('[data-extra-row][hidden]');
      if (next) next.hidden = false;
      updateAdd();
    }
  });
  // After a refused submission, bring the list of problems into view for people using a keyboard or screen reader.
  document.getElementById('enquiry-errors')?.focus();
  document.getElementById('enquiry-sent')?.focus();
})();
