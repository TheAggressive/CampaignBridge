import { useMemo, useState } from 'react';
import { Button, Modal, Spinner, Notice } from '@wordpress/components';
import { desktop, mobile } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';
import type { EmailPreview } from '../hooks/useEmailPreview';

const DESKTOP_WIDTH = 680;
const MOBILE_WIDTH = 390;

type Viewport = 'desktop' | 'mobile';

interface EmailPreviewModalProps {
  isOpen: boolean;
  onRequestClose: () => void;
  preview: EmailPreview;
  onRefresh: () => void;
}

function buildSrcDoc(html: string): string {
  return [
    '<!DOCTYPE html>',
    '<html>',
    '<head>',
    '<meta charset="utf-8" />',
    '<meta name="viewport" content="width=device-width, initial-scale=1.0" />',
    '<style>',
    'html, body { margin: 0; padding: 0; background: #f4f4f4; }',
    'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }',
    '</style>',
    '</head>',
    '<body>',
    html,
    '</body>',
    '</html>',
  ].join('\n');
}

function SandboxIframe({
  html,
  width,
  title,
}: {
  html: string;
  width: number;
  title: string;
}): JSX.Element {
  const srcDoc = useMemo(() => buildSrcDoc(html), [html]);

  return (
    <iframe
      title={title}
      srcDoc={srcDoc}
      sandbox=''
      style={{
        width: `${width}px`,
        height: '75vh',
        border: 'none',
        display: 'block',
        margin: '0 auto',
      }}
    />
  );
}

export default function EmailPreviewModal({
  isOpen,
  onRequestClose,
  preview,
  onRefresh,
}: EmailPreviewModalProps): JSX.Element | null {
  const [viewport, setViewport] = useState<Viewport>('desktop');

  if (!isOpen) {
    return null;
  }

  const isLoading = preview.status === 'loading';
  const hasErrors = preview.diagnostics.errors.length > 0;
  const hasWarnings = preview.diagnostics.warnings.length > 0;

  return (
    <Modal
      title={__('Email Preview', 'campaignbridge')}
      size='large'
      onRequestClose={onRequestClose}
      isDismissible
      shouldCloseOnClickOutside
    >
      <div className='cb-editor__preview-modal'>
        <div className='cb-editor__preview-toolbar'>
          <div className='cb-editor__preview-viewport-toggle'>
            <Button
              variant={viewport === 'desktop' ? 'primary' : 'secondary'}
              icon={desktop}
              onClick={() => setViewport('desktop')}
              className='cb-editor__preview-viewport-btn'
            >
              {__('Desktop', 'campaignbridge')}
            </Button>
            <Button
              variant={viewport === 'mobile' ? 'primary' : 'secondary'}
              icon={mobile}
              onClick={() => setViewport('mobile')}
              className='cb-editor__preview-viewport-btn'
            >
              {__('Mobile', 'campaignbridge')}
            </Button>
          </div>

          <Button
            variant='secondary'
            onClick={onRefresh}
            disabled={isLoading}
            className='cb-editor__preview-refresh'
          >
            {__('Refresh', 'campaignbridge')}
          </Button>
        </div>

        <div className='cb-editor__preview-body'>
          {isLoading && (
            <div className='cb-editor__preview-loading'>
              <Spinner />
              <p>{__('Compiling preview…', 'campaignbridge')}</p>
            </div>
          )}

          {preview.status === 'idle' && (
            <div className='cb-editor__preview-empty'>
              <p>
                {__(
                  'Click "Refresh" to compile the preview.',
                  'campaignbridge'
                )}
              </p>
            </div>
          )}

          {preview.status === 'error' && (
            <Notice status='error'>
              {preview.error ??
                __(
                  'An error occurred while compiling the preview.',
                  'campaignbridge'
                )}
            </Notice>
          )}

          {preview.status === 'success' && (
            <SandboxIframe
              html={preview.html}
              width={viewport === 'mobile' ? MOBILE_WIDTH : DESKTOP_WIDTH}
              title={__('Email preview', 'campaignbridge')}
            />
          )}
        </div>

        {preview.status === 'success' && (hasErrors || hasWarnings) && (
          <div className='cb-editor__preview-diagnostics'>
            {hasErrors && (
              <Notice status='error' isDismissible={false}>
                {preview.diagnostics.errors.map((diag, i) => (
                  <p key={i}>
                    <strong>{diag.code}</strong>: {diag.message}
                  </p>
                ))}
              </Notice>
            )}
            {hasWarnings && (
              <Notice status='warning' isDismissible={false}>
                {preview.diagnostics.warnings.map((diag, i) => (
                  <p key={i}>
                    <strong>{diag.code}</strong>: {diag.message}
                  </p>
                ))}
              </Notice>
            )}
          </div>
        )}
      </div>
    </Modal>
  );
}
