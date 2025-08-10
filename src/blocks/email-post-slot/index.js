import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { registerBlockType } from '@wordpress/blocks';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';

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
        <div>
          <strong>Email Post Slot</strong>
          <div>Slot ID: {slotId || '— required —'}</div>
          <div>
            Image: {showImage ? 'On' : 'Off'} · Excerpt:{' '}
            {showExcerpt ? 'On' : 'Off'}
          </div>
          <div>Button: {ctaLabel || 'Read more'}</div>
        </div>
      </div>
    );
  },
});
