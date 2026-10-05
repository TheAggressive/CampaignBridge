import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';
import { CampaignsApp } from './CampaignsApp';

domReady(() => {
  const root = document.getElementById('campaignbridge-campaigns-root');
  const config = globalThis.campaignbridgeCampaigns;
  if (!root || !config) {
    return;
  }

  root.replaceChildren();
  createRoot(root).render(<CampaignsApp config={config} />);
});
