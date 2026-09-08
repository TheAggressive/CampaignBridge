import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Button, Modal, Spinner } from '@wordpress/components';
import {
  check,
  code,
  desktop,
  mobile,
  download,
  error,
  update,
} from '@wordpress/icons';
import { __, _n, sprintf } from '@wordpress/i18n';
import { formatPreviewSource } from '../utils/formatPreviewSource';
import type { EmailPreview } from '../hooks/useEmailPreview';

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

function formatByteSize(bytes: number): string {
  if (bytes < 1024) {
    return `${bytes} B`;
  }
  if (bytes < 1024 * 1024) {
    return `${(bytes / 1024).toFixed(1)} kB`;
  }
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function toFilenameSafe(slug: string): string {
  return (
    slug
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '')
      .slice(0, 80) || 'email'
  );
}

function SourcePane({
  html,
  title,
}: {
  html: string;
  title: string;
}): JSX.Element {
  const [copied, setCopied] = useState(false);
  const [copyError, setCopyError] = useState(false);
  const copyTimer = useRef<ReturnType<typeof setTimeout>>();
  useEffect(() => () => clearTimeout(copyTimer.current), []);
  const sourceLines = useMemo(
    () => formatPreviewSource(html).split('\n'),
    [html]
  );
  const byteSize = useMemo(() => new Blob([html]).size, [html]);

  const handleCopy = useCallback(() => {
    setCopyError(false);
    void (async () => {
      try {
        await navigator.clipboard.writeText(html);
        setCopied(true);
        clearTimeout(copyTimer.current);
        copyTimer.current = setTimeout(() => setCopied(false), 2000);
      } catch {
        setCopyError(true);
      }
    })();
  }, [html]);

  const handleDownload = useCallback(() => {
    const blob = new Blob([html], { type: 'text/html' });
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = `${toFilenameSafe(title)}.html`;
    document.body.appendChild(anchor);
    anchor.click();
    document.body.removeChild(anchor);
    URL.revokeObjectURL(url);
  }, [html, title]);

  return (
    <aside
      className='cb-editor__preview-source-pane'
      role='region'
      aria-label={__('Compiled HTML source', 'campaignbridge')}
    >
      <div className='cb-editor__preview-source-header'>
        <div className='cb-editor__preview-source-meta'>
          <span className='cb-editor__preview-source-title'>
            {__('Compiled HTML', 'campaignbridge')}
          </span>
          <span className='cb-editor__preview-source-badge'>
            {formatByteSize(byteSize)}
          </span>
        </div>
        <div className='cb-editor__preview-source-actions'>
          {copied ? (
            <span
              className='cb-editor__preview-source-copied'
              role='status'
              aria-live='polite'
            >
              <span className='cb-editor__preview-source-copied-icon'>
                {check}
              </span>
              {__('Copied!', 'campaignbridge')}
            </span>
          ) : (
            <Button
              variant='tertiary'
              icon={code}
              label={__('Copy HTML', 'campaignbridge')}
              onClick={handleCopy}
              className='cb-editor__preview-source-action'
            />
          )}
          <Button
            variant='tertiary'
            icon={download}
            label={__('Download .html', 'campaignbridge')}
            onClick={handleDownload}
            className='cb-editor__preview-source-action'
          />
        </div>
      </div>
      {copyError && (
        <p role='alert'>
          {__(
            'Could not copy. Select the source below or download the HTML.',
            'campaignbridge'
          )}
        </p>
      )}
      <div className='cb-editor__preview-source-body'>
        <pre className='cb-editor__preview-source-code'>
          <code>
            {sourceLines.map((line, index) => (
              <span className='cb-editor__preview-source-line' key={index}>
                <span
                  className='cb-editor__preview-source-line-number'
                  aria-hidden='true'
                >
                  {index + 1}
                </span>
                <span className='cb-editor__preview-source-line-text'>
                  {line || ' '}
                </span>
              </span>
            ))}
          </code>
        </pre>
      </div>
    </aside>
  );
}

/**
 * Renders the compiled email inside a sandboxed iframe whose height tracks
 * the full rendered document height. The preview workspace (not the iframe)
 * is the single vertical scroll surface — the iframe itself never scrolls.
 *
 * The frame is same-origin (via `sandbox="allow-same-origin"`), so the
 * `ResizeObserver` can read the inner
 * `body.scrollHeight` and resize the host iframe to match.
 */
function IframeResizeObserver({
  html,
  width,
  title,
}: {
  html: string;
  width: number;
  title: string;
}): JSX.Element {
  const iframeRef = useRef<HTMLIFrameElement | null>(null);
  const srcDoc = html;
  const [height, setHeight] = useState(600);
  const cleanup = useRef<(() => void) | undefined>();
  const observeDocument = useCallback(() => {
    cleanup.current?.();
    const frame = iframeRef.current;
    const body = frame?.contentDocument?.body;
    if (!frame || !body) return;
    const measure = () => {
      // Body bounds can shrink, unlike documentElement.scrollHeight which
      // includes the current iframe viewport height.
      const next = Math.ceil(
        Math.max(body.getBoundingClientRect().height, body.scrollHeight)
      );
      if (next > 0) setHeight(next);
    };
    measure();
    const observer =
      typeof ResizeObserver === 'undefined'
        ? undefined
        : new ResizeObserver(measure);
    observer?.observe(body);
    body.addEventListener('load', measure, true);
    cleanup.current = () => {
      observer?.disconnect();
      body.removeEventListener('load', measure, true);
    };
  }, []);
  useEffect(() => {
    observeDocument();
    return () => cleanup.current?.();
  }, [srcDoc, width, observeDocument]);

  return (
    <div
      className='cb-editor__preview-iframe'
      style={{
        width,
        maxWidth: '100%',
        margin: '0 auto',
        height: height > 0 ? height : 600,
      }}
    >
      <iframe
        ref={iframeRef}
        title={title}
        srcDoc={srcDoc}
        onLoad={observeDocument}
        sandbox='allow-same-origin'
        scrolling='no'
        style={{
          width: '100%',
          height: '100%',
          border: 0,
          display: 'block',
        }}
      />
    </div>
  );
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
                : statusTone === 'stale'
                  ? update
                  : statusTone === 'warning'
                    ? update
                    : error}
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
          <div ref={canvasRef} className='cb-editor__preview-canvas'>
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
                  <IframeResizeObserver
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
            <SourcePane html={preview.html} title={title || 'email'} />
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
