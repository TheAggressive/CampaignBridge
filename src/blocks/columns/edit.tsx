import {
  InspectorControls,
  BlockControls,
  BlockVerticalAlignmentToolbar,
  store as blockEditorStore,
  useBlockProps,
  useInnerBlocksProps,
} from '@wordpress/block-editor';
import { PanelBody, RangeControl, ToggleControl } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { createBlock, type Block } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { EMAIL_BLOCK_NESTING } from '../shared/nesting';
import type { EmailBlockEditProps } from '../types';

interface ColumnsAttributes {
  isStackedOnMobile?: boolean;
  verticalAlign?: 'top' | 'middle' | 'bottom';
}

export default function Edit({
  attributes,
  setAttributes,
  clientId,
}: EmailBlockEditProps<ColumnsAttributes>): JSX.Element {
  const { verticalAlign = 'top', isStackedOnMobile = true } = attributes;
  const columns = useSelect(
    select => select(blockEditorStore).getBlocks(clientId),
    [clientId]
  );
  const { replaceInnerBlocks } = useDispatch(blockEditorStore);
  const changeCount = (count: number | undefined) => {
    if (!count) return;
    const next: Block[] = columns.slice(0, count).map(column => ({
      ...column,
      attributes: { ...column.attributes, width: undefined },
    }));
    while (next.length < count) next.push(createBlock('campaignbridge/column'));
    void replaceInnerBlocks(clientId, next, false);
  };
  const blockProps = useBlockProps({
    className: isStackedOnMobile ? undefined : 'is-not-stacked-on-mobile',
    style: {
      display: 'flex',
      alignItems:
        verticalAlign === 'middle'
          ? 'center'
          : verticalAlign === 'bottom'
            ? 'flex-end'
            : 'flex-start',
    },
  });
  const innerBlocksProps = useInnerBlocksProps(blockProps, {
    allowedBlocks: [...EMAIL_BLOCK_NESTING.columns],
    templateLock: false,
    orientation: 'horizontal',
    renderAppender: () => null,
  });

  return (
    <>
      <BlockControls>
        <BlockVerticalAlignmentToolbar
          value={verticalAlign === 'middle' ? 'center' : verticalAlign}
          onChange={value =>
            setAttributes({
              verticalAlign:
                value === 'center'
                  ? 'middle'
                  : value === 'bottom'
                    ? 'bottom'
                    : 'top',
            })
          }
        />
      </BlockControls>
      <InspectorControls>
        <PanelBody title={__('Email columns', 'campaignbridge')} initialOpen>
          <RangeControl
            label={__('Columns', 'campaignbridge')}
            value={columns.length}
            min={1}
            max={6}
            onChange={changeCount}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />
          <ToggleControl
            label={__('Stack on mobile', 'campaignbridge')}
            checked={isStackedOnMobile}
            onChange={value => setAttributes({ isStackedOnMobile: value })}
            __nextHasNoMarginBottom
          />
        </PanelBody>
      </InspectorControls>
      <div {...innerBlocksProps} />
    </>
  );
}
