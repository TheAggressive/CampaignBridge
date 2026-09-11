import { useCallback, useEffect, useRef, useState } from '@wordpress/element';

/**
 * Provides a visually hidden aria-live region for announcing dynamic changes
 * to assistive technology users.
 *
 * The region uses `aria-live="polite"` so announcements are queued and read
 * when the user is not actively interacting with other content.
 *
 * @return {Object} `region` – JSX element to render inside the block.
 *                  `announce` – Call with a message to trigger an announcement.
 */
export function useAnnouncement(): {
  region: JSX.Element;
  announce: (message: string) => void;
} {
  const [message, setMessage] = useState('');
  const timerRef = useRef<number | null>(null);
  const frameRef = useRef<number | null>(null);

  const clearPendingAnnouncement = useCallback(() => {
    if (frameRef.current !== null) {
      window.cancelAnimationFrame(frameRef.current);
      frameRef.current = null;
    }

    if (timerRef.current !== null) {
      window.clearTimeout(timerRef.current);
      timerRef.current = null;
    }
  }, []);

  useEffect(
    () => () => {
      clearPendingAnnouncement();
    },
    [clearPendingAnnouncement]
  );

  const announce = useCallback(
    (text: string) => {
      clearPendingAnnouncement();

      // Set a temporary empty string first so that re-announcing the same
      // text is still picked up by screen readers (the live region must
      // detect a content change).
      setMessage('');

      // Use requestAnimationFrame to ensure the DOM has flushed the empty
      // state before we set the new message.
      frameRef.current = window.requestAnimationFrame(() => {
        frameRef.current = null;
        setMessage(text);
        timerRef.current = window.setTimeout(() => {
          setMessage('');
          timerRef.current = null;
        }, 1000);
      });
    },
    [clearPendingAnnouncement]
  );

  const region = (
    <div
      aria-live='polite'
      aria-atomic='true'
      role='status'
      style={{
        border: 'none',
        clip: 'rect(1px, 1px, 1px, 1px)',
        height: '1px',
        overflow: 'hidden',
        padding: '0',
        position: 'absolute',
        whiteSpace: 'nowrap',
        width: '1px',
      }}
    >
      {message}
    </div>
  );

  return { region, announce };
}
