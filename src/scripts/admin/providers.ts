import domReady from '@wordpress/dom-ready';

domReady(() => {
  // Destructive actions confirm first; the server still checks the nonce and capability.
  document
    .querySelectorAll<HTMLFormElement>('form[data-confirm]')
    .forEach(form => {
      form.addEventListener('submit', event => {
        if (!window.confirm(form.dataset.confirm ?? '')) {
          event.preventDefault();
        }
      });
    });

  const provider = document.querySelector<HTMLSelectElement>(
    '[name="providers[provider]"]'
  );
  const mailchimpFields = document.querySelectorAll<HTMLElement>(
    '[data-mailchimp-field]'
  );
  if (!provider || mailchimpFields.length === 0) return;

  const updateVisibility = () => {
    const visible = provider.value === 'mailchimp';
    mailchimpFields.forEach(container => {
      container.hidden = !visible;
      container
        .querySelectorAll<HTMLInputElement>('input, select, textarea')
        .forEach(control => {
          control.disabled = !visible;
        });
    });
  };

  provider.addEventListener('change', updateVisibility);
  updateVisibility();
});
