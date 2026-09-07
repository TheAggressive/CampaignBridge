import { RichText, useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import type { EmailBlockEditProps } from '../types';

interface TextAttributes {
  content?: string;
  align?: 'left' | 'center' | 'right';
  textColor?: string;
  fontSize?: number;
}

export default function Edit({
  attributes,
  setAttributes,
}: EmailBlockEditProps<TextAttributes>): JSX.Element {
  const { content = '' } = attributes;

  return (
    <RichText
      {...useBlockProps()}
      tagName='p'
      value={content}
      allowedFormats={[
        'core/bold',
        'core/italic',
        'core/strikethrough',
        'core/link',
      ]}
      placeholder={__('Write email text…', 'campaignbridge')}
      onChange={value => setAttributes({ content: value })}
    />
  );
}
