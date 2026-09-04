/**
 * Controls shared by the email blocks.
 *
 * Each post-binding block mirrors the control surface of its static twin, so
 * the shared pieces live here rather than being restated in every block.
 */

import { ColorPalette, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export type EmailAlignment = 'left' | 'center' | 'right';

interface AlignmentSelectProps {
  value: EmailAlignment;
  onChange: (value: EmailAlignment) => void;
  label?: string;
}

/** Portable horizontal alignment, matching Renderer_Support::alignment_attribute. */
export function AlignmentSelect({
  value,
  onChange,
  label,
}: AlignmentSelectProps): JSX.Element {
  return (
    <SelectControl
      label={label ?? __('Alignment', 'campaignbridge')}
      value={value}
      options={[
        { label: __('Left', 'campaignbridge'), value: 'left' },
        { label: __('Center', 'campaignbridge'), value: 'center' },
        { label: __('Right', 'campaignbridge'), value: 'right' },
      ]}
      onChange={next => onChange(next as EmailAlignment)}
      __next40pxDefaultSize
      __nextHasNoMarginBottom
    />
  );
}

interface EmailColorProps {
  label: string;
  value: string;
  fallback: string;
  onChange: (value: string) => void;
}

/**
 * A colour the compiler will accept.
 *
 * The renderers require a six-digit hex value, so clearing the swatch restores
 * the documented default instead of persisting an empty string that would fail
 * compilation.
 */
export function EmailColor({
  label,
  value,
  fallback,
  onChange,
}: EmailColorProps): JSX.Element {
  return (
    <>
      <p>{label}</p>
      <ColorPalette
        value={value}
        onChange={next => onChange(next || fallback)}
      />
    </>
  );
}
