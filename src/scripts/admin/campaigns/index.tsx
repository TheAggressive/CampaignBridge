import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';
import { CampaignDetail } from './CampaignDetail';
import { CampaignsApp } from './CampaignsApp';
import { campaignFromUrl } from './view';

domReady(() => {
  const root = document.getElementById('campaignbridge-campaigns-root');
  const config = globalThis.campaignbridgeCampaigns;
  if (!root || !config) {
    return;
  }

  const campaign = campaignFromUrl(globalThis.location.search);
  root.replaceChildren();
  createRoot(root).render(
    campaign ? (
      <CampaignDetail id={campaign} config={config} />
    ) : (
      <CampaignsApp config={config} />
    )
  );
});
