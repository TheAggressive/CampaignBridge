import {
  registerBlockBindingsSource,
  registerBlockVariation,
} from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

import {
  BRAND_BINDING_SOURCE,
  brandBinding,
} from '../../blocks/shared/brand-bindings';

export const BRAND_DATA_SOURCE = BRAND_BINDING_SOURCE;

interface BrandLogo {
  url: string;
  alt: string;
  width: number;
  height: number;
  link_url: string;
}

interface BindingArgs {
  field?: string;
}

interface GetValuesArgs {
  select: (store: unknown) => unknown;
  bindings: Record<string, { args?: BindingArgs }>;
}

function logo(select: GetValuesArgs['select']): BrandLogo | null {
  const settings = (
    select('core/block-editor') as {
      getSettings: () => Record<string, unknown>;
    }
  ).getSettings();
  const value = settings.campaignbridgeBrandLogo;

  return value && typeof value === 'object' ? (value as BrandLogo) : null;
}

/** Read-only Brand Kit values used only for the authoring canvas. */
export const BRAND_DATA_BINDING_SOURCE = {
  name: BRAND_DATA_SOURCE,
  label: __('Brand Kit', 'campaignbridge'),
  getValues({ select, bindings }: GetValuesArgs) {
    const asset = logo(select);
    const values: Record<string, string | undefined> = {};
    for (const [attribute, binding] of Object.entries(bindings)) {
      switch (binding.args?.field) {
        case 'logoUrl':
          values[attribute] = asset?.url;
          break;
        case 'logoAlt':
          values[attribute] = asset?.alt;
          break;
        case 'logoLink':
          values[attribute] = asset?.link_url;
          break;
        default:
          values[attribute] = undefined;
      }
    }

    return values;
  },
};

export const BRAND_LOGO_VARIATION = {
  name: 'campaignbridge-brand-logo',
  title: __('Brand Logo', 'campaignbridge'),
  description: __(
    'The Site Logo frozen in the CampaignBridge Brand Kit.',
    'campaignbridge'
  ),
  scope: ['inserter' as const],
  attributes: {
    width: 240,
    align: 'center',
    linkDestination: 'custom',
    metadata: {
      bindings: {
        url: brandBinding('core/image', 'url'),
        alt: brandBinding('core/image', 'alt'),
        href: brandBinding('core/image', 'href'),
      },
    },
  },
};

registerBlockBindingsSource(BRAND_DATA_BINDING_SOURCE);
registerBlockVariation('core/image', BRAND_LOGO_VARIATION);
