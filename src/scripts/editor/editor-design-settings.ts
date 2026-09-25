import { addFilter } from '@wordpress/hooks';
import { editorDesignConfig } from './editor-design-config';

const OVERRIDDEN_PATHS = new Set([
  'color.palette.theme',
  'color.palette.default',
  'color.palette.custom',
  'color.defaultPalette',
  'color.custom',
  'color.gradients.theme',
  'color.gradients.default',
  'color.gradients.custom',
  'color.defaultGradients',
  'color.customGradient',
  'typography.fontFamilies.theme',
  'typography.fontFamilies.default',
  'typography.fontFamilies.custom',
  'typography.customFontFamily',
  'typography.fontSizes.theme',
  'typography.fontSizes.default',
  'typography.fontSizes.custom',
  'typography.defaultFontSizes',
  'typography.customFontSize',
  'spacing.spacingSizes.theme',
  'spacing.spacingSizes.default',
  'spacing.spacingSizes.custom',
  'spacing.defaultSpacingSizes',
  'spacing.customSpacingSize',
]);

function valueAtPath(source: Record<string, unknown>, path: string): unknown {
  let value: unknown = source;
  for (const part of path.split('.')) {
    if (!value || typeof value !== 'object') return undefined;
    value = (value as Record<string, unknown>)[part];
  }
  return value;
}

/** Keep native controls on the email design after Core resolves site styles. */
export function emailDesignSetting(current: unknown, path: string): unknown {
  if (!OVERRIDDEN_PATHS.has(path)) return current;

  const value = valueAtPath(editorDesignConfig.features, path);
  return value === undefined ? current : value;
}

addFilter(
  'blockEditor.useSetting.before',
  'campaignbridge/email-design',
  emailDesignSetting
);
