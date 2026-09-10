import domReady from '@wordpress/dom-ready';

interface GeneralSettingsConfig {
  unsaved: string;
  saving: string;
  previewOn: string;
  previewOff: string;
  resetConfirm: string;
}

declare global {
  interface Window {
    campaignbridgeGeneralSettings?: GeneralSettingsConfig;
  }
}

function field<T extends HTMLInputElement | HTMLTextAreaElement>(name: string) {
  return document.querySelector<T>(
    `[name="general_settings[${name}]"]:not([type="hidden"])`
  );
}

domReady(() => {
  const config = window.campaignbridgeGeneralSettings;
  const form = document.querySelector<HTMLFormElement>(
    '.campaignbridge-general__main > form'
  );
  if (!config || !form) return;

  const status = form.querySelector<HTMLElement>(
    '.campaignbridge-general__save-status'
  );
  const updatePreview = () => {
    const mappings = [
      ['from_name', 'from-name'],
      ['from_email', 'from-email'],
      ['cta_label', 'cta-label'],
      ['default_footer', 'footer'],
    ] as const;

    mappings.forEach(([name, target]) => {
      const input = field(name);
      const output = document.querySelector<HTMLElement>(
        `[data-preview="${target}"]`
      );
      if (input && output) output.textContent = input.value;
    });

    const previewToggle = field<HTMLInputElement>('enable_preview_text');
    const previewState = document.querySelector<HTMLElement>(
      '[data-preview="preview-text"]'
    );
    if (previewToggle && previewState) {
      previewState.textContent = previewToggle.checked
        ? config.previewOn
        : config.previewOff;
    }
  };

  form.addEventListener('input', () => {
    if (status) {
      status.textContent = config.unsaved;
      status.classList.add('is-dirty');
    }
    updatePreview();
  });
  form.addEventListener('change', updatePreview);
  form.addEventListener('submit', () => {
    if (status) status.textContent = config.saving;
  });

  document
    .querySelector<HTMLFormElement>('[data-reset-settings]')
    ?.addEventListener('submit', event => {
      if (!window.confirm(config.resetConfirm)) event.preventDefault();
    });
});
