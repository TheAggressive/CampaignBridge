import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, RangeControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import type { EmailBlockEditProps } from '../types';

interface VideoAttributes {
  posterUrl?: string;
  posterAlt?: string;
  videoUrl?: string;
  label?: string;
  width?: number;
  height?: number;
}

export default function Edit({
  attributes,
  setAttributes,
}: EmailBlockEditProps<VideoAttributes>): JSX.Element {
  const posterUrl = attributes.posterUrl ?? '';
  const posterAlt = attributes.posterAlt ?? '';
  const label = attributes.label ?? 'Watch video';
  const width = attributes.width ?? 600;
  const height = attributes.height ?? 338;

  return (
    <>
      <InspectorControls>
        <PanelBody title={__('Video link', 'campaignbridge')} initialOpen>
          <TextControl
            label={__('Poster HTTPS URL', 'campaignbridge')}
            type='url'
            value={posterUrl}
            onChange={poster => setAttributes({ posterUrl: poster })}
            __nextHasNoMarginBottom
          />
          <TextControl
            label={__('Poster alternative text', 'campaignbridge')}
            value={posterAlt}
            maxLength={200}
            onChange={alt => setAttributes({ posterAlt: alt })}
            __nextHasNoMarginBottom
          />
          <TextControl
            label={__('Video HTTPS URL', 'campaignbridge')}
            type='url'
            value={attributes.videoUrl ?? ''}
            onChange={url => setAttributes({ videoUrl: url })}
            __nextHasNoMarginBottom
          />
          <TextControl
            label={__('Play link label', 'campaignbridge')}
            value={label}
            maxLength={80}
            onChange={nextLabel => setAttributes({ label: nextLabel })}
            __nextHasNoMarginBottom
          />
          <RangeControl
            label={__('Poster width', 'campaignbridge')}
            value={width}
            min={160}
            max={900}
            onChange={next => setAttributes({ width: next ?? 600 })}
            __nextHasNoMarginBottom
          />
          <RangeControl
            label={__('Poster height', 'campaignbridge')}
            value={height}
            min={90}
            max={1200}
            onChange={next => setAttributes({ height: next ?? 338 })}
            __nextHasNoMarginBottom
          />
        </PanelBody>
      </InspectorControls>
      <div {...useBlockProps()}>
        {posterUrl ? (
          <img
            src={posterUrl}
            alt={posterAlt}
            width={width}
            height={height}
            style={{ display: 'block', height: 'auto', maxWidth: '100%' }}
          />
        ) : (
          <p>
            {__('Add a poster HTTPS URL in block settings.', 'campaignbridge')}
          </p>
        )}
        <div
          style={{
            background: 'var(--wp--preset--color--brand, #1a6dcc)',
            color: 'var(--wp--preset--color--on-brand, #ffffff)',
            fontWeight: 700,
            padding: '12px 16px',
            textAlign: 'center',
          }}
        >
          <span aria-hidden='true'>▶</span> {label}
        </div>
      </div>
    </>
  );
}
