// Page detection utility
export const isCampaignBridgePage = () => {
  const screen = document.body.className;
  return (
    screen.includes('campaignbridge') ||
    screen.includes('toplevel_page_campaignbridge') ||
    screen.includes('campaignbridge_page_')
  );
};

// More specific page detection
export const getCampaignBridgePage = () => {
  const screen = document.body.className;
  if (screen.includes('toplevel_page_campaignbridge')) return 'templates';
  if (screen.includes('campaignbridge_page_campaignbridge-post-types'))
    return 'post-types';
  if (screen.includes('campaignbridge_page_campaignbridge-settings'))
    return 'settings';
  return null;
};

// Check if current page has specific functionality
export const hasTemplateFunctionality = () =>
  getCampaignBridgePage() === 'templates';
export const hasPostTypeFunctionality = () =>
  getCampaignBridgePage() === 'post-types';
export const hasSettingsFunctionality = () =>
  getCampaignBridgePage() === 'settings';

// URL parameter utilities
export const getQueryParam = (name) => {
  const params = new URLSearchParams(window.location.search);
  return params.get(name);
};

export const setQueryParam = (name, value) => {
  const url = new URL(window.location.href);
  if (value == null || value === '') {
    url.searchParams.delete(name);
  } else {
    url.searchParams.set(name, value);
  }
  history.replaceState({}, '', url.toString());
};

// Event delegation utility
export const on = (eventName, selector, handler) => {
  document.addEventListener(eventName, (event) => {
    const target = event.target?.closest(selector);
    if (target) handler(event, target);
  });
};

// HTML escaping utility
export const escapeHTML = (value) => {
  const div = document.createElement('div');
  div.textContent = value ?? '';
  return div.innerHTML;
};
