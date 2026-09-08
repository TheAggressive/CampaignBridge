import { useBlockProps } from '@wordpress/block-editor';
import type { EmailBlockEditProps } from '../types';

export default function Edit({
  attributes,
}: EmailBlockEditProps<{ width?: number }>): JSX.Element {
  return (
    <hr
      {...useBlockProps({ style: { width: `${attributes.width ?? 100}%` } })}
    />
  );
}
