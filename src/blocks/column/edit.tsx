import {
  InspectorControls,
  store as blockEditorStore,
  useBlockProps,
  useInnerBlocksProps,
} from '@wordpress/block-editor';
import { PanelBody, RangeControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { columnWidths } from '../columns/layout';
import { __ } from '@wordpress/i18n';
import type { EmailBlockEditProps } from '../types';
import { COLUMN_ALLOWED_BLOCKS } from './config';

interface ColumnAttributes {
  width?: number;
  backgroundColor?: string;
}

export default function Edit({
  attributes,
  setAttributes,
  clientId,
}: EmailBlockEditProps<ColumnAttributes>): JSX.Element {
  const { width } = attributes;
  const proportion = useSelect(
    select => {
      const store = select(blockEditorStore);
      const parent = store.getBlockRootClientId(clientId);
      const siblings = store.getBlocks(parent ?? undefined);
      const index = siblings.findIndex(block => block.clientId === clientId);
      return (
        columnWidths(
          siblings.map(block => block.attributes.width as number | undefined)
        )[index] ?? 1
      );
    },
    [clientId]
  );
  const blockProps = useBlockProps({
    style: {
      // An unset width shares the row evenly, matching the compiler default.
      flexBasis: 0,
      flexGrow: proportion,
      minWidth: 0,
      overflowWrap: 'break-word',
    },
  });
  const innerBlocksProps = useInnerBlocksProps(blockProps, {
    allowedBlocks: COLUMN_ALLOWED_BLOCKS,
    templateLock: false,
  });

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Email column', 'campaignbridge')} initialOpen>
          <RangeControl
            label={__('Width', 'campaignbridge')}
            help={__(
              'Percentage of the available column space. Automatic columns share the remainder.',
              'campaignbridge'
            )}
            value={width}
            onChange={value => setAttributes({ width: value })}
            min={1}
            max={100}
            step={1}
            allowReset
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />
        </PanelBody>
      </InspectorControls>
      <div {...innerBlocksProps} />
    </>
  );
}
