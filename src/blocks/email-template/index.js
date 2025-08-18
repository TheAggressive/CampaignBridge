/**
 * Email Template Block - Main container for email campaign design
 *
 * @package CampaignBridge
 */

import {
  __experimentalFontFamilyControl as FontFamilyControl,
  __experimentalFontSizeControl as FontSizeControl,
  InspectorControls,
  __experimentalLineHeightControl as LineHeightControl,
  __experimentalPanelColorGradientSettings as PanelColorGradientSettings,
  __experimentalPanelSpacingSettings as PanelSpacingSettings,
  useBlockProps,
  __experimentalUseCustomUnits as useCustomUnits,
  __experimentalUseGradient as useGradient,
  useInnerBlocksProps,
  __experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
} from '@wordpress/block-editor';
import {
  __experimentalNumberControl as NumberControl,
  PanelBody,
  TextControl,
} from '@wordpress/components';
import { compose } from '@wordpress/compose';
import { withDispatch, withSelect } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import { __ } from '@wordpress/i18n';

/**
 * Email Template Block Component
 */
function EmailTemplateBlock({
  attributes,
  setAttributes,
  clientId,
  hasInnerBlocks,
  isSelected,
}) {
  const {
    templateName,
    emailWidth,
    backgroundColor,
    textColor,
    fontFamily,
    maxWidth,
    padding,
  } = attributes;

  // Block props with email-specific styling
  const blockProps = useBlockProps({
    className: 'cb-email-template',
    style: {
      backgroundColor,
      color: textColor,
      fontFamily,
      maxWidth: `${maxWidth}px`,
      margin: '0 auto',
      padding: `${padding.top}px ${padding.right}px ${padding.bottom}px ${padding.left}px`,
    },
  });

  // Inner blocks configuration
  const innerBlocksProps = useInnerBlocksProps(
    { className: 'cb-email-content' },
    {
      allowedBlocks: [
        'campaignbridge/email-post-title',
        'campaignbridge/email-post-excerpt',
        'campaignbridge/email-post-image',
        'campaignbridge/email-post-button',
        'campaignbridge/email-post-slot',
        'core/paragraph',
        'core/heading',
        'core/image',
        'core/buttons',
        'core/columns',
        'core/group',
        'core/spacer',
        'core/separator',
      ],
      template: [
        [
          'core/heading',
          { level: 1, content: __('Email Campaign Title', 'campaignbridge') },
        ],
        [
          'core/paragraph',
          {
            content: __(
              'Start building your email campaign by adding content blocks below.',
              'campaignbridge'
            ),
          },
        ],
        ['campaignbridge/email-post-slot', { slotKey: 'main_content' }],
      ],
      templateLock: false,
    }
  );

  // Color and gradient settings
  const colorGradientSettings = useMultipleOriginColorsAndGradients();
  const { gradientValue, setGradient } = useGradient();

  // Custom units for responsive design
  const units = useCustomUnits();

  return (
    <>
      <InspectorControls>
        <PanelBody
          title={__('Email Template Settings', 'campaignbridge')}
          initialOpen={true}
        >
          <TextControl
            label={__('Template Name', 'campaignbridge')}
            value={templateName}
            onChange={(value) => setAttributes({ templateName: value })}
            help={__(
              'Name your email template for easy identification.',
              'campaignbridge'
            )}
          />

          <NumberControl
            label={__('Email Width (px)', 'campaignbridge')}
            value={emailWidth}
            onChange={(value) => setAttributes({ emailWidth: value })}
            min={300}
            max={800}
            step={10}
            help={__(
              'Standard email width is 600px for best compatibility.',
              'campaignbridge'
            )}
          />

          <NumberControl
            label={__('Max Width (px)', 'campaignbridge')}
            value={maxWidth}
            onChange={(value) => setAttributes({ maxWidth: value })}
            min={300}
            max={1200}
            step={10}
            help={__('Maximum width for responsive design.', 'campaignbridge')}
          />
        </PanelBody>

        <PanelColorGradientSettings
          title={__('Color Settings', 'campaignbridge')}
          settings={[
            {
              colorValue: backgroundColor,
              onColorChange: (value) =>
                setAttributes({ backgroundColor: value }),
              label: __('Background Color', 'campaignbridge'),
            },
            {
              colorValue: textColor,
              onColorChange: (value) => setAttributes({ textColor: value }),
              label: __('Text Color', 'campaignbridge'),
            },
          ]}
          gradients={colorGradientSettings.gradients}
          gradientValue={gradientValue}
          onGradientChange={setGradient}
          {...colorGradientSettings}
        />

        <PanelSpacingSettings
          title={__('Spacing Settings', 'campaignbridge')}
          values={padding}
          onChange={(value) => setAttributes({ padding: value })}
          units={units}
          splitValues={false}
        />

        <PanelBody
          title={__('Typography', 'campaignbridge')}
          initialOpen={false}
        >
          <FontFamilyControl
            value={fontFamily}
            onChange={(value) => setAttributes({ fontFamily: value })}
          />

          <FontSizeControl
            value={attributes.fontSize}
            onChange={(value) => setAttributes({ fontSize: value })}
            units={units}
          />

          <LineHeightControl
            value={attributes.lineHeight}
            onChange={(value) => setAttributes({ lineHeight: value })}
          />
        </PanelBody>
      </InspectorControls>

      <div {...blockProps}>
        {/* Email Template Header */}
        {isSelected && (
          <div className="cb-email-template-header">
            <div className="cb-email-template-info">
              <span className="cb-email-template-name">{templateName}</span>
              <span className="cb-email-template-dimensions">
                {emailWidth}px × Responsive
              </span>
            </div>
          </div>
        )}

        {/* Email Content Container */}
        <div
          className="cb-email-content-wrapper"
          style={{
            width: `${emailWidth}px`,
            maxWidth: '100%',
            margin: '0 auto',
          }}
        >
          <div {...innerBlocksProps} />
        </div>

        {/* Email Template Footer */}
        {isSelected && (
          <div className="cb-email-template-footer">
            <div className="cb-email-template-actions">
              <button
                className="button button-primary cb-export-html"
                onClick={() => {
                  // Export functionality is handled by EmailExportService
                  // This button will trigger the export via event delegation
                }}
              >
                {__('Export HTML', 'campaignbridge')}
              </button>
              <button
                className="button button-secondary cb-preview-email"
                onClick={() => {
                  // Preview functionality is handled by EmailExportService
                  // This button will trigger the preview via event delegation
                }}
              >
                {__('Preview Email', 'campaignbridge')}
              </button>
            </div>
          </div>
        )}
      </div>
    </>
  );
}

// Export the block with data integration
export default compose([
  withSelect((select, { clientId }) => {
    const { getBlock } = select(editorStore);
    const block = getBlock(clientId);

    return {
      hasInnerBlocks:
        block && block.innerBlocks && block.innerBlocks.length > 0,
    };
  }),
  withDispatch((dispatch) => {
    return {
      setAttributes: dispatch(editorStore).updateBlockAttributes,
    };
  }),
])(EmailTemplateBlock);
