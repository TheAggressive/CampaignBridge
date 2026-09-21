import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { Button, PanelBody, TextControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import type { EmailBlockEditProps } from '../types';

/** Mirrors Navigation_Renderer bounds. The server remains authoritative. */
const MAX_ITEMS = 5;
const MAX_LABEL_LENGTH = 24;

interface NavigationItem {
  label: string;
  url: string;
}

interface NavigationAttributes {
  items?: NavigationItem[];
}

export default function Edit({
  attributes,
  setAttributes,
}: EmailBlockEditProps<NavigationAttributes>): JSX.Element {
  const items = attributes.items ?? [{ label: '', url: '' }];
  const updateItem = (
    index: number,
    field: keyof NavigationItem,
    value: string
  ) => {
    setAttributes({
      items: items.map((item, position) =>
        position === index ? { ...item, [field]: value } : item
      ),
    });
  };

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Navigation links', 'campaignbridge')} initialOpen>
          {items.map((item, index) => (
            <div key={index}>
              <TextControl
                label={sprintf(
                  /* translators: %d: link position. */
                  __('Link %d label', 'campaignbridge'),
                  index + 1
                )}
                value={item.label}
                maxLength={MAX_LABEL_LENGTH}
                onChange={value => updateItem(index, 'label', value)}
                __nextHasNoMarginBottom
              />
              <TextControl
                label={sprintf(
                  /* translators: %d: link position. */
                  __('Link %d HTTPS URL', 'campaignbridge'),
                  index + 1
                )}
                type='url'
                value={item.url}
                onChange={value => updateItem(index, 'url', value)}
                __nextHasNoMarginBottom
              />
              <Button
                variant='tertiary'
                isDestructive
                disabled={items.length <= 1}
                onClick={() =>
                  setAttributes({
                    items: items.filter((_, position) => position !== index),
                  })
                }
              >
                {__('Remove link', 'campaignbridge')}
              </Button>
            </div>
          ))}
          <Button
            variant='secondary'
            disabled={items.length >= MAX_ITEMS}
            onClick={() =>
              setAttributes({ items: [...items, { label: '', url: '' }] })
            }
          >
            {__('Add link', 'campaignbridge')}
          </Button>
        </PanelBody>
      </InspectorControls>
      <div {...useBlockProps()}>
        <strong>{__('Email navigation', 'campaignbridge')}</strong>
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: '12px' }}>
          {items.map((item, index) => (
            <span key={index}>
              {item.label ||
                sprintf(
                  /* translators: %d: link position. */
                  __('Link %d', 'campaignbridge'),
                  index + 1
                )}
            </span>
          ))}
        </div>
      </div>
    </>
  );
}
