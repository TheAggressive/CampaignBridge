import { useEffect, useRef, useState } from 'react';
import { Button, Modal, Spinner } from '@wordpress/components';
import { check, code, desktop, mobile, error, update } from '@wordpress/icons';
import { __, _n, sprintf } from '@wordpress/i18n';
import type { EmailPreview } from '../hooks/useEmailPreview';
import EmailPreviewFrame from './EmailPreviewFrame';
import EmailPreviewSourcePane from './EmailPreviewSourcePane';

const DESKTOP_WIDTH = 600;
const MOBILE_WIDTH = 390;

type Viewport = 'desktop' | 'mobile';

interface EmailPreviewModalProps {
  isOpen: boolean;
  onRequestClose: () => void;
  preview: EmailPreview;
  onRefresh: () => void;
  /** Template display title, used as a fallback for the download filename. */
  title?: string;
  /** Real email subject line (from template meta). */
  subject?: string;
  /** Whether the editor has unsaved edits newer than the current preview. */
  hasEdits?: boolean;
}

export default function EmailPreviewModal({
  isOpen,
  onRequestClose,
  preview,
  onRefresh,
  title,
  subject,
  hasEdits,
}: EmailPreviewModalProps): JSX.Element | null {
  const [viewport, setViewport] = useState<Viewport>('desktop');
  const canvasRef = useRef<HTMLDivElement | null>(null);
  const changeViewport = (device: Viewport) => {
    if (device === viewport) return;
    if (canvasRef.current) {
      canvasRef.current.scrollTop = 0;
      canvasRef.current.scrollLeft = 0;
    }
    setViewport(device);
  };
  const [diagnosticsOpen, setDiagnosticsOpen] = useState(false);
  const [sourceOpen, setSourceOpen] = useState(false);

  const isLoading = preview.status === 'loading';
  const hasErrors =
    preview.status === 'error' || preview.diagnostics.errors.length > 0;
  const hasWarnings = preview.diagnostics.warnings.length > 0;
  const hasDiagnostics = hasErrors || hasWarnings;
  const isStale = hasEdits && preview.status === 'success' && !isLoading;
  const statusTone = isLoading
    ? 'loading'
    : hasErrors
      ? 'error'
      : hasWarnings
        ? 'warning'
        : isStale
          ? 'stale'
          : preview.status === 'idle'
            ? 'idle'
            : 'ok';
  const statusText = isLoading
    ? __('Compiling…', 'campaignbridge')
    : hasErrors
      ? __('Compilation failed', 'campaignbridge')
      : hasWarnings
        ? __('Compiled with warnings', 'campaignbridge')
        : isStale
          ? __('Preview out of date', 'campaignbridge')
          : preview.status === 'idle'
            ? __('Preview not compiled', 'campaignbridge')
            : __('Preview up to date', 'campaignbridge');

  const errorHtml =
    preview.error ||
    __(
      'An error occurred while compiling the email preview.',
      'campaignbridge'
    );

  useEffect(() => {
    if (!isOpen) {
      setDiagnosticsOpen(false);
      setSourceOpen(false);
    }
  }, [isOpen]);

  useEffect(() => {
    if (!isOpen) {
      return;
    }

    const handler = (event: KeyboardEvent) => {
      const mod = event.metaKey || event.ctrlKey;
      if (!mod || !event.shiftKey) {
        return;
      }
      const key = event.key.toLowerCase();
      if (key === 'c') {
        event.preventDefault();
        setSourceOpen(open => !open);
      } else if (key === 'r' && !isLoading) {
        event.preventDefault();
        onRefresh();
      }
    };

    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [isOpen, onRefresh, isLoading]);

  if (!isOpen) {
    return null;
  }

  const diagnosticsList = hasDiagnostics ? (
    <ul
      className={
        'cb-editor__preview-diagnostics-list' +
        (hasErrors
          ? ' cb-editor__preview-diagnostics-list--error'
          : ' cb-editor__preview-diagnostics-list--warning')
      }
    >
      {preview.diagnostics.errors.map((diag, i) => (
        <li
          key={`error-${i}`}
          className='cb-editor__preview-diagnostics-errors'
        >
          <strong>{diag.code}</strong>: {diag.message}
        </li>
      ))}
      {preview.diagnostics.warnings.map((diag, i) => (
        <li
          key={`warning-${i}`}
          className='cb-editor__preview-diagnostics-warnings'
        >
          <strong>{diag.code}</strong>: {diag.message}
        </li>
      ))}
    </ul>
  ) : null;

  return (
    <Modal
      title={__('Email Preview', 'campaignbridge')}
      isFullScreen
      onRequestClose={onRequestClose}
      isDismissible
      shouldCloseOnClickOutside
      className='cb-editor__preview-modal-frame'
      headerActions={
        <>
          <Button
            variant='tertiary'
            icon={update}
            isBusy={isLoading}
            label={__('Refresh email preview', 'campaignbridge')}
            text={__('Refresh', 'campaignbridge')}
            onClick={onRefresh}
            disabled={isLoading}
            className='cb-editor__preview-header-action'
          />
          <Button
            variant='tertiary'
            icon={code}
            disabled={preview.status !== 'success'}
            label={__('View HTML source', 'campaignbridge')}
            text={__('Source', 'campaignbridge')}
            onClick={() => setSourceOpen(open => !open)}
            aria-pressed={sourceOpen}
            className='cb-editor__preview-header-action'
          />
        </>
      }
    >
      <div className='cb-editor__preview-modal'>
        <div className='cb-editor__preview-subject-row'>
          <p className='cb-editor__preview-subject' title={subject}>
            <span className='cb-editor__preview-subject-label'>
              {__('Subject:', 'campaignbridge')}
            </span>
            <span className='cb-editor__preview-subject-value'>
              {subject || __('No subject set', 'campaignbridge')}
            </span>
          </p>
        </div>
        <div className='cb-editor__preview-secondary'>
          <div
            className='cb-editor__preview-toggle'
            role='group'
            aria-label={__('Preview viewport', 'campaignbridge')}
          >
            {(['desktop', 'mobile'] as const).map(device => (
              <button
                key={device}
                type='button'
                className='cb-editor__preview-toggle-option'
                aria-label={
                  device === 'desktop'
                    ? __('Desktop', 'campaignbridge')
                    : __('Mobile', 'campaignbridge')
                }
                aria-pressed={viewport === device}
                onClick={() => changeViewport(device)}
              >
                <span className='cb-editor__preview-viewport-label'>
                  <span aria-hidden='true'>
                    {device === 'desktop' ? desktop : mobile}
                  </span>
                  {device === 'desktop'
                    ? __('Desktop', 'campaignbridge')
                    : __('Mobile', 'campaignbridge')}
                </span>
              </button>
            ))}
          </div>

          {hasWarnings && (
            <Button
              variant='tertiary'
              icon={update}
              label={
                diagnosticsOpen
                  ? __('Hide compilation warnings', 'campaignbridge')
                  : __('Show compilation warnings', 'campaignbridge')
              }
              className='cb-editor__preview-diagnostics-toggle cb-editor__preview-diagnostics-toggle--warning'
              onClick={() => setDiagnosticsOpen(open => !open)}
              aria-expanded={diagnosticsOpen}
            >
              {preview.diagnostics.warnings.length}
            </Button>
          )}

          <div
            className={
              'cb-editor__preview-status' +
              ' cb-editor__preview-status--' +
              statusTone
            }
            role='status'
            aria-live='polite'
          >
            <span className='cb-editor__preview-status-icon' aria-hidden='true'>
              {statusTone === 'ok'
                ? check
                : statusTone === 'error'
                  ? error
                  : update}
            </span>
            <span className='cb-editor__preview-status-label'>
              {statusText}
              {preview.compiledAt && (
                <small>
                  {__('Last updated', 'campaignbridge')}{' '}
                  <time dateTime={new Date(preview.compiledAt).toISOString()}>
                    {new Date(preview.compiledAt).toLocaleTimeString()}
                  </time>
                </small>
              )}
            </span>
          </div>
        </div>

        <div className='cb-editor__preview-body'>
          <div
            ref={canvasRef}
            className='cb-editor__preview-canvas'
            role='region'
            aria-label={__('Email preview canvas', 'campaignbridge')}
            tabIndex={0}
          >
            {preview.status === 'idle' && (
              <div className='cb-editor__preview-state'>
                <p>
                  {__(
                    'Compile the email to see a live preview.',
                    'campaignbridge'
                  )}
                </p>
                <Button
                  variant='primary'
                  icon={update}
                  onClick={onRefresh}
                  className='cb-editor__preview-state-action'
                >
                  {__('Compile preview', 'campaignbridge')}
                </Button>
              </div>
            )}

            {preview.status === 'loading' && (
              <div className='cb-editor__preview-state'>
                <Spinner />
                <p>{__('Compiling email preview…', 'campaignbridge')}</p>
              </div>
            )}

            {preview.status === 'error' && (
              <div
                className='cb-editor__preview-state cb-editor__preview-state--error'
                role='alert'
              >
                <span
                  className='cb-editor__preview-state-icon'
                  aria-hidden='true'
                >
                  {error}
                </span>
                <p className='cb-editor__preview-state-message'>{errorHtml}</p>
                {diagnosticsList && (
                  <div className='cb-editor__preview-diagnostics'>
                    {diagnosticsList}
                  </div>
                )}
                <Button
                  variant='primary'
                  icon={update}
                  onClick={onRefresh}
                  className='cb-editor__preview-state-action'
                >
                  {__('Try again', 'campaignbridge')}
                </Button>
              </div>
            )}

            {preview.status === 'success' && (
              <>
                {hasWarnings && diagnosticsOpen && (
                  <div
                    className='cb-editor__preview-diagnostics cb-editor__preview-diagnostics--inline'
                    role='status'
                  >
                    <div className='cb-editor__preview-diagnostics-header'>
                      <span
                        className='cb-editor__preview-diagnostics-count cb-editor__preview-diagnostics-count--warning'
                        aria-hidden='true'
                      >
                        {update}
                      </span>
                      <span className='cb-editor__preview-diagnostics-heading'>
                        {sprintf(
                          /* translators: %d: number of compilation warnings */
                          _n(
                            'Compiled with %d warning',
                            'Compiled with %d warnings',
                            preview.diagnostics.warnings.length,
                            'campaignbridge'
                          ),
                          preview.diagnostics.warnings.length
                        )}
                      </span>
                      <Button
                        variant='tertiary'
                        onClick={() => setDiagnosticsOpen(false)}
                        label={__(
                          'Hide compilation warnings',
                          'campaignbridge'
                        )}
                      >
                        {__('Hide', 'campaignbridge')}
                      </Button>
                    </div>
                    {diagnosticsList}
                  </div>
                )}
                <div className='cb-editor__preview-email'>
                  <EmailPreviewFrame
                    html={preview.html}
                    width={
                      viewport === 'mobile'
                        ? MOBILE_WIDTH
                        : (preview.width ?? DESKTOP_WIDTH)
                    }
                    title={__('Email preview', 'campaignbridge')}
                  />
                </div>
              </>
            )}
          </div>

          {sourceOpen && preview.status === 'success' && (
            <EmailPreviewSourcePane
              html={preview.html}
              title={title || 'email'}
            />
          )}
        </div>
        <footer
          className={
            'cb-editor__preview-footer cb-editor__preview-status--' + statusTone
          }
        >
          <span className='cb-editor__preview-footer-icon' aria-hidden='true'>
            {statusTone === 'ok' ? check : hasErrors ? error : update}
          </span>
          <div>
            <span>
              {statusTone === 'ok'
                ? __('Compiled successfully', 'campaignbridge')
                : statusText}
            </span>
            <small>
              {hasErrors
                ? __('Resolve the errors and try again.', 'campaignbridge')
                : hasWarnings
                  ? __('Review compilation warnings above.', 'campaignbridge')
                  : preview.status === 'success'
                    ? __('No issues detected', 'campaignbridge')
                    : __(
                        'Compile the current template to preview it.',
                        'campaignbridge'
                      )}
            </small>
          </div>
          <div className='cb-editor__preview-footer-meta'>
            {title && (
              <span>
                {__('Template:', 'campaignbridge')} {title}
              </span>
            )}
            {preview.durationMs !== undefined && (
              <small>
                {sprintf(
                  __('Compiled in %s s', 'campaignbridge'),
                  (preview.durationMs / 1000).toFixed(1)
                )}
              </small>
            )}
          </div>
        </footer>
      </div>
    </Modal>
  );
}
