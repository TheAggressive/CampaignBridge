import { Button, ToggleControl } from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { ReviewedArtifact } from './types';

const WIDTHS = { desktop: 680, mobile: 375 } as const;

/**
 * The reviewed artifact in a sandboxed frame. Scripts never run in it; the
 * frame shares the admin origin only so its height can follow the email.
 */
export function ReviewPreview({
  artifact,
}: {
  artifact: ReviewedArtifact;
}): JSX.Element {
  const [width, setWidth] = useState<keyof typeof WIDTHS>('desktop');
  const [sample, setSample] = useState(true);
  const frame = useRef<HTMLIFrameElement>(null);
  const html = sample && artifact.sample ? artifact.sample.html : artifact.html;

  const fit = () => {
    const body = frame.current?.contentDocument?.documentElement;
    if (frame.current && body) {
      frame.current.style.height = `${Math.min(body.scrollHeight + 4, 4000)}px`;
    }
  };

  // A width change reflows the email without reloading the frame.
  useEffect(() => {
    const timer = setTimeout(fit, 50);
    return () => clearTimeout(timer);
  }, [width]);

  return (
    <div className='campaignbridge-campaigns__preview'>
      <div className='campaignbridge-campaigns__preview-controls'>
        <div
          className='campaignbridge-campaigns__segmented'
          role='group'
          aria-label={__('Preview width', 'campaignbridge')}
        >
          {(['desktop', 'mobile'] as const).map(option => (
            <Button
              key={option}
              variant={width === option ? 'primary' : 'secondary'}
              aria-pressed={width === option}
              onClick={() => setWidth(option)}
            >
              {option === 'desktop'
                ? __('Desktop', 'campaignbridge')
                : __('Phone', 'campaignbridge')}
            </Button>
          ))}
        </div>
        {artifact.sample && (
          <ToggleControl
            label={__('Show sample personalization', 'campaignbridge')}
            help={
              sample
                ? __(
                    'Merge tags show made-up values. Each subscriber sees their own.',
                    'campaignbridge'
                  )
                : __(
                    'Merge tags show as written; the provider fills them in.',
                    'campaignbridge'
                  )
            }
            checked={sample}
            onChange={setSample}
            __nextHasNoMarginBottom
          />
        )}
      </div>
      <div className='campaignbridge-campaigns__preview-stage'>
        <iframe
          ref={frame}
          title={__('Reviewed email preview', 'campaignbridge')}
          className='campaignbridge-campaigns__preview-frame'
          sandbox='allow-same-origin'
          srcDoc={html}
          onLoad={fit}
          style={{ width: `${WIDTHS[width]}px` }}
        />
      </div>
    </div>
  );
}
