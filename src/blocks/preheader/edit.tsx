import { useBlockProps } from '@wordpress/block-editor';
import { TextareaControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import type { EmailBlockEditProps } from '../types';

/** Mirrors Preheader_Renderer::MAX_LENGTH. */
const MAX_LENGTH = 150;

interface PreheaderAttributes {
  content?: string;
}

export default function Edit({
  attributes,
  setAttributes,
}: EmailBlockEditProps<PreheaderAttributes>): JSX.Element {
  const { content = '' } = attributes;
  const blockProps = useBlockProps({ className: 'cb-preheader-edit' });
  const remaining = MAX_LENGTH - content.length;

  return (
    <div {...blockProps}>
      <TextareaControl
        label={__('Preview text', 'campaignbridge')}
        help={
          remaining < 0
            ? sprintf(
                /* translators: %d: number of characters over the limit. */
                __('%d characters over the limit.', 'campaignbridge'),
                Math.abs(remaining)
              )
            : sprintf(
                /* translators: %d: number of characters still available. */
                __(
                  'Hidden in the email body. %d characters left.',
                  'campaignbridge'
                ),
                remaining
              )
        }
        value={content}
        onChange={value => setAttributes({ content: value })}
        rows={2}
        __nextHasNoMarginBottom
      />
    </div>
  );
}
