import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Button } from '@wordpress/components';
import { check, code, download } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';
import { formatPreviewSource } from '../utils/formatPreviewSource';

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

interface EmailPreviewSourcePaneProps {
  html: string;
  title: string;
}

/** Copyable and downloadable source for the current compiled artifact. */
export default function EmailPreviewSourcePane({
  html,
  title,
}: EmailPreviewSourcePaneProps): JSX.Element {
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
