import {
  InnerBlocks,
  InspectorControls,
  useBlockProps,
} from '@wordpress/block-editor';
import { registerBlockType } from '@wordpress/blocks';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';

const TEMPLATE = [
  ['core/paragraph', { content: '{{image}}' }],
  ['core/heading', { level: 3, content: '{{title}}' }],
  ['core/paragraph', { content: '{{excerpt}}' }],
  ['core/paragraph', { content: '{{button}}' }],
];

registerBlockType('campaignbridge/email-post-slot', {
  edit({ attributes, setAttributes }) {
    const {
      slotId,
      showImage = true,
      showExcerpt = true,
      ctaLabel = 'Read more',
    } = attributes;
    const props = useBlockProps({ className: 'cb-email-post-slot' });
    return (
      <div {...props}>
        <InspectorControls>
          <PanelBody title="Slot Settings" initialOpen>
            <TextControl
              label="Slot ID"
              help="Unique key used to map a post to this slot. Required."
              value={slotId || ''}
              onChange={(v) =>
                setAttributes({
                  slotId: (v || '').replace(/[^a-z0-9_-]/gi, '').toLowerCase(),
                })
              }
            />
            <ToggleControl
              label="Show featured image"
              checked={!!showImage}
              onChange={(v) => setAttributes({ showImage: !!v })}
            />
            <ToggleControl
              label="Show excerpt"
              checked={!!showExcerpt}
              onChange={(v) => setAttributes({ showExcerpt: !!v })}
            />
            <TextControl
              label="Button label"
              value={ctaLabel || ''}
              onChange={(v) => setAttributes({ ctaLabel: v })}
            />
          </PanelBody>
        </InspectorControls>
        <div className="cb-slot-meta" style={{ marginBottom: '8px' }}>
          <strong>Email Post Slot</strong> · Slot ID:{' '}
          <code>{slotId || 'required'}</code>
          <div style={{ color: '#555', marginTop: '4px' }}>
            Tokens: <code>{'{{title}}'}</code> <code>{'{{image}}'}</code>{' '}
            <code>{'{{excerpt}}'}</code> <code>{'{{link}}'}</code>{' '}
            <code>{'{{cta_label}}'}</code> <code>{'{{button}}'}</code>
          </div>
        </div>
        <InnerBlocks template={TEMPLATE} templateLock={false} />
      </div>
    );
  },
});
