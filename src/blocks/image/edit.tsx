import {
  InspectorControls,
  MediaUpload,
  MediaUploadCheck,
  useBlockProps,
} from '@wordpress/block-editor';
import {
  Button,
  PanelBody,
  RangeControl,
  TextControl,
  ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

interface SelectedMedia {
  id: number;
  alt?: string;
  height?: number;
  url?: string;
  width?: number;
}

export default function Edit({ attributes, setAttributes }) {
  const {
    url = '',
    alt = '',
    decorative = false,
    width = 600,
    height = 400,
    linkUrl = '',
  } = attributes;

  const selectMedia = (media: SelectedMedia): void => {
    setAttributes({
      url: media.url || '',
      alt: media.alt || '',
      width: Number(media.width) || 600,
      height: Number(media.height) || 400,
    });
  };

  return (
    <div {...useBlockProps()}>
      <InspectorControls>
        <PanelBody title={__('Email image', 'campaignbridge')} initialOpen>
          <TextControl
            label={__('Image URL', 'campaignbridge')}
            type='url'
            value={url}
            onChange={value => setAttributes({ url: value })}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />
          <TextControl
            label={__('Alternative text', 'campaignbridge')}
            value={alt}
            disabled={decorative}
            onChange={value => setAttributes({ alt: value })}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />
          <ToggleControl
            label={__('Decorative image', 'campaignbridge')}
            checked={decorative}
            onChange={value =>
              setAttributes({ decorative: value, alt: value ? '' : alt })
            }
            __nextHasNoMarginBottom
          />
          <RangeControl
            label={__('Width', 'campaignbridge')}
            value={width}
            min={1}
            max={1200}
            onChange={value => setAttributes({ width: Number(value) || 600 })}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />
          <RangeControl
            label={__('Height', 'campaignbridge')}
            value={height}
            min={1}
            max={1200}
            onChange={value => setAttributes({ height: Number(value) || 400 })}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />
          <TextControl
            label={__('Link URL', 'campaignbridge')}
            type='url'
            value={linkUrl}
            onChange={value => setAttributes({ linkUrl: value })}
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />
        </PanelBody>
      </InspectorControls>
      {url ? (
        <img
          src={url}
          alt={decorative ? '' : alt}
          width={width}
          height={height}
          style={{ display: 'block', width: '100%', height: 'auto' }}
        />
      ) : (
        <MediaUploadCheck>
          <MediaUpload
            allowedTypes={['image']}
            onSelect={selectMedia}
            render={({ open }) => (
              <Button variant='primary' onClick={open}>
                {__('Select image', 'campaignbridge')}
              </Button>
            )}
          />
        </MediaUploadCheck>
      )}
    </div>
  );
}
