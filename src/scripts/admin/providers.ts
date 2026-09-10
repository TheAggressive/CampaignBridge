import domReady from '@wordpress/dom-ready';

domReady(() => {
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
