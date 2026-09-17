import { useCallback, useEffect, useRef, useState } from 'react';

interface EmailPreviewFrameProps {
  html: string;
  width: number;
  title: string;
}

/**
 * Render compiled email HTML in a non-scripted frame sized to its document.
 * The surrounding preview workspace remains the only scroll surface.
 */
export default function EmailPreviewFrame({
  html,
  width,
  title,
}: EmailPreviewFrameProps): JSX.Element {
  const iframeRef = useRef<HTMLIFrameElement | null>(null);
  const [height, setHeight] = useState(600);
  const cleanup = useRef<(() => void) | undefined>();
  const observeDocument = useCallback(() => {
    cleanup.current?.();
    const frame = iframeRef.current;
    const body = frame?.contentDocument?.body;
    if (!frame || !body) return;
    const measure = () => {
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
  }, [html, width, observeDocument]);

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
        srcDoc={html}
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
