import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { CORE_EMAIL_BLOCK_NAMES } from '../../blocks/shared/nesting';

/**
 * Constrains the supported WordPress Core blocks to the email-safe subset.
 *
 * Users author with the real Core blocks; this filter only narrows their
 * native design tools to the values the CampaignBridge compiler can
 * deterministically render. The server-side normalizer remains authoritative
 * and rejects anything that still reaches it.
 */

type Support = boolean | string[] | Record<string, unknown> | undefined;
type Supports = Record<string, Support>;

interface BlockStyle {
  name: string;
  label: string;
  isDefault?: boolean;
}

interface BlockSettings {
  supports?: Supports;
  styles?: BlockStyle[];
  attributes?: Record<string, Record<string, unknown>>;
  allowedBlocks?: string[];
  [key: string]: unknown;
}

/** Keep only the listed feature flags of one support group. */
export function restrictSupport(
  support: Support,
  allowed: readonly string[]
): Support {
  if (!allowed.length || !support || typeof support !== 'object') {
    return allowed.length ? support : false;
  }

  const result: Record<string, unknown> = {};
  for (const [key, value] of Object.entries(support)) {
    if (key === '__experimentalSkipSerialization') {
      result[key] = value;
    } else if (key === '__experimentalDefaultControls') {
      result[key] = Object.fromEntries(
        Object.entries(value as Record<string, unknown>).filter(([control]) =>
          allowed.includes(control)
        )
      );
    } else {
      result[key] = allowed.includes(key) ? value : false;
    }
  }
  // Core enables these colour controls implicitly when the group exists.
  if ('text' in result || 'background' in result || 'gradients' in result) {
    for (const key of ['text', 'background']) {
      if (!(key in result)) result[key] = allowed.includes(key);
    }
  }

  return result;
}

/** Design tools removed from every supported Core block. */
const UNSUPPORTED_EVERYWHERE: Supports = {
  anchor: false,
  customClassName: false,
  html: false,
  shadow: false,
  filter: false,
  visibility: false,
  __experimentalBorder: false,
};

/** Per-block email-safe support subsets, mirroring the PHP renderers. */
const SUPPORTS: Record<string, Record<string, readonly string[]>> = {
  'core/paragraph': {
    color: ['text', 'background'],
    typography: [
      'fontSize',
      'lineHeight',
      'textAlign',
      '__experimentalFontFamily',
      // Core's Appearance control writes fontStyle and fontWeight together, so
      // both are offered or neither is; the compiler accepts the same pair.
      '__experimentalFontStyle',
      '__experimentalFontWeight',
    ],
    spacing: ['margin', 'padding'],
    align: [],
  },
  'core/heading': {
    color: ['text'],
    typography: [
      'fontSize',
      'lineHeight',
      'textAlign',
      '__experimentalFontFamily',
      '__experimentalFontStyle',
      '__experimentalFontWeight',
    ],
    spacing: ['margin'],
    align: [],
  },
  'core/image': {
    color: [],
    spacing: ['margin'],
  },
  'core/buttons': {
    color: [],
    spacing: [],
    typography: [],
    align: [],
  },
  'core/button': {
    color: ['text', 'background'],
    typography: ['__experimentalFontFamily'],
    spacing: [],
    dimensions: [],
  },
  'core/list': { color: [], spacing: [], typography: [] },
  'core/list-item': { color: [], spacing: [], typography: [] },
  'core/separator': {
    color: ['background', 'enableContrastChecker'],
    spacing: [],
    align: [],
  },
  'core/spacer': { spacing: [] },
};

/** Block styles the compiler cannot express are removed; email-only ones are added. */
function styles(name: string, current: BlockStyle[] = []): BlockStyle[] {
  switch (name) {
    case 'core/image':
      return current.filter(style => style.name !== 'rounded');
    case 'core/separator':
      return current.filter(style => style.name !== 'dots');
    case 'core/button':
      return current.some(style => style.name === 'ghost')
        ? current
        : [
            ...current,
            { name: 'ghost', label: __('Text link', 'campaignbridge') },
          ];
    default:
      return current;
  }
}

/** Narrow one Core block registration to the CampaignBridge email subset. */
export function constrainCoreEmailBlock(
  settings: BlockSettings,
  name: string
): BlockSettings {
  if (!CORE_EMAIL_BLOCK_NAMES.includes(name)) {
    return settings;
  }

  const original = settings.supports ?? {};
  const supports: Supports = { ...original, ...UNSUPPORTED_EVERYWHERE };
  for (const [group, allowed] of Object.entries(SUPPORTS[name] ?? {})) {
    supports[group] = restrictSupport(original[group], allowed);
  }
  if (name === 'core/image') {
    supports.align = ['left', 'center', 'right'];
  }
  if (name === 'core/buttons' && typeof original.layout === 'object') {
    supports.layout = {
      ...original.layout,
      allowOrientation: false,
      allowVerticalAlignment: false,
      allowSizingOnChildren: false,
    };
  }

  const next: BlockSettings = {
    ...settings,
    supports,
    styles: styles(name, settings.styles),
  };
  // Nested lists are outside the v1 email grammar.
  if (name === 'core/list-item') {
    next.allowedBlocks = [];
  }
  if (name === 'core/heading' && settings.attributes?.levelOptions) {
    next.attributes = {
      ...settings.attributes,
      levelOptions: {
        ...settings.attributes.levelOptions,
        default: [1, 2, 3, 4],
      },
    };
  }

  return next;
}

addFilter(
  'blocks.registerBlockType',
  'campaignbridge/core-email-blocks',
  constrainCoreEmailBlock
);
