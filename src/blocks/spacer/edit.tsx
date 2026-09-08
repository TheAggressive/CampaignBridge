import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
export default function Edit(): JSX.Element {
  return (
    <div
      {...useBlockProps()}
      aria-label={__('Email spacer', 'campaignbridge')}
    />
  );
}
