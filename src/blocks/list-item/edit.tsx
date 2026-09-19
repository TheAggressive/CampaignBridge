import { RichText, useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import type { EmailBlockEditProps } from '../types';

interface ListItemAttributes {
  content?: string;
}

export default function Edit({
  attributes,
  setAttributes,
}: EmailBlockEditProps<ListItemAttributes>): JSX.Element {
  const { content = '' } = attributes;

  return (
    <RichText
      {...useBlockProps()}
      tagName='li'
      value={content}
      allowedFormats={[
        'core/bold',
        'core/italic',
        'core/strikethrough',
        'core/link',
      ]}
      placeholder={__('Write a list item…', 'campaignbridge')}
      onChange={value => setAttributes({ content: value })}
    />
  );
}
