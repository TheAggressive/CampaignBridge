import {
  InspectorControls,
  useBlockProps,
  useInnerBlocksProps,
} from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import type { EmailBlockEditProps } from '../types';

interface ListAttributes {
  ordered?: boolean;
}

/**
 * Edit view for the `campaignbridge/list` block.
 *
 * A list is a container that holds one or more `campaignbridge/list-item`
 * children (restricted via `allowedBlocks`). The "Numbered" toggle switches
 * between an unordered (`<ul>`) and an ordered (`<ol>`) email list; the choice
 * is persisted in the `ordered` attribute and applied by the renderer at
 * compile time. Content lives on the child items, so this block saves nothing
 * itself (see `save: () => null`).
 */
export default function Edit({
  attributes,
  setAttributes,
}: EmailBlockEditProps<ListAttributes>): JSX.Element {
  const { ordered = false } = attributes;

  const blockProps = useBlockProps();
  const innerBlocksProps = useInnerBlocksProps(blockProps, {
    allowedBlocks: ['campaignbridge/list-item'],
    templateLock: false,
    template: [['campaignbridge/list-item']],
  });

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Email list', 'campaignbridge')} initialOpen>
          <ToggleControl
            label={__('Numbered list', 'campaignbridge')}
            help={__(
              'When enabled, the list renders as a numbered list in the email.',
              'campaignbridge'
            )}
            checked={ordered}
            onChange={value => setAttributes({ ordered: value })}
            __nextHasNoMarginBottom
          />
        </PanelBody>
      </InspectorControls>
      <div {...innerBlocksProps} />
    </>
  );
}
