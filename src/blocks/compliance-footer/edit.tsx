import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
  BoxControl,
  ColorPalette,
  Notice,
  PanelBody,
  TextControl,
  TextareaControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
  normalizeSpacing,
  toControlSpacing,
  type NormalizedSpacing,
} from '../shared/spacing';
import type { EmailBlockEditProps } from '../types';

interface ComplianceFooterAttributes {
  businessName?: string;
  address?: string;
  unsubscribeLabel?: string;
  padding?: NormalizedSpacing;
  textColor?: string;
}

export default function Edit({
  attributes,
  setAttributes,
}: EmailBlockEditProps<ComplianceFooterAttributes>): JSX.Element {
  const {
    businessName = '',
    address = '',
    unsubscribeLabel = 'Unsubscribe',
    padding = { top: 24, right: 0, bottom: 24, left: 0 },
    textColor = '#666666',
  } = attributes;
  const blockProps = useBlockProps({
    style: {
      padding: `${padding.top}px ${padding.right}px ${padding.bottom}px ${padding.left}px`,
      color: textColor,
      fontSize: '12px',
      lineHeight: '18px',
      textAlign: 'center' as const,
    },
  });

  return (
    <>
      <InspectorControls>
        <PanelBody
          title={__('Compliance footer', 'campaignbridge')}
          initialOpen
        >
          <TextControl
            label={__('Business name', 'campaignbridge')}
            value={businessName}
            onChange={value => setAttributes({ businessName: value })}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />
          <TextareaControl
            label={__('Postal address', 'campaignbridge')}
            help={__(
              'A physical mailing address is required before this campaign can be approved.',
              'campaignbridge'
            )}
            value={address}
            onChange={value => setAttributes({ address: value })}
            rows={2}
            __nextHasNoMarginBottom
          />
          <TextControl
            label={__('Unsubscribe link text', 'campaignbridge')}
            value={unsubscribeLabel}
            onChange={value => setAttributes({ unsubscribeLabel: value })}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />
          <BoxControl
            label={__('Padding', 'campaignbridge')}
            values={toControlSpacing(padding)}
            onChange={values =>
              setAttributes({ padding: normalizeSpacing(values) })
            }
            __next40pxDefaultSize
          />
          <p>{__('Text color', 'campaignbridge')}</p>
          <ColorPalette
            value={textColor}
            onChange={value => setAttributes({ textColor: value || '#666666' })}
          />
        </PanelBody>
      </InspectorControls>
      <div {...blockProps}>
        {businessName !== '' && <div>{businessName}</div>}
        <div>
          {address !== '' ? (
            address
          ) : (
            <em>{__('Add your postal address', 'campaignbridge')}</em>
          )}
        </div>
        <div>
          <u>{unsubscribeLabel}</u>
        </div>
        <Notice status='info' isDismissible={false}>
          {__(
            'The unsubscribe destination comes from this template’s settings, not from this block.',
            'campaignbridge'
          )}
        </Notice>
      </div>
    </>
  );
}
