import apiFetch from '@wordpress/api-fetch';
import { serialize } from '@wordpress/blocks';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import {
  useCallback,
  useEffect,
  useMemo,
  useRef,
  useState,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const PREVIEW_PATH = '/campaignbridge/v1/preview';

interface EmailPreviewResponse {
  html: string;
  diagnostics: Array<{
    severity: string;
    code: string;
    message: string;
  }>;
}

export type PreviewStatus = 'idle' | 'loading' | 'success' | 'error';

export interface EmailPreview {
  status: PreviewStatus;
  html: string;
  diagnostics: {
    errors: Array<{ code: string; message: string }>;
    warnings: Array<{ code: string; message: string }>;
  };
  error: string | null;
  width?: number;
  compiledAt?: number;
  durationMs?: number;
  content?: string;
  title?: string;
}

const EMPTY_DIAGNOSTICS = {
  errors: [] as Array<{ code: string; message: string }>,
  warnings: [] as Array<{ code: string; message: string }>,
};

const INITIAL_STATE: EmailPreview = {
  status: 'idle',
  html: '',
  diagnostics: EMPTY_DIAGNOSTICS,
  error: null,
};

export interface UseEmailPreview {
  preview: EmailPreview;
  requestPreview: () => Promise<void>;
  resetPreview: () => void;
  isStale: boolean;
}

/**
 * Compile the current unsaved editor content via the server-side
 * `POST /preview` endpoint and expose the resulting HTML and
 * diagnostics to the preview UI.
 *
 * Reads the `core/block-editor` store owned by WordPress's native post editor.
 */
export function useEmailPreview(
  postId: number,
  title: string
): UseEmailPreview {
  const [preview, setPreview] = useState<EmailPreview>(INITIAL_STATE);

  const requestId = useRef(0);
  useEffect(() => {
    requestId.current++;
    setPreview(INITIAL_STATE);
    return () => {
      // Invalidate whichever request is pending at cleanup, not the mount request.
      // eslint-disable-next-line react-hooks/exhaustive-deps
      requestId.current++;
    };
  }, [postId]);

  const blocks = useSelect(select => select(blockEditorStore).getBlocks(), []);
  const serializedContent = useMemo(() => serialize(blocks), [blocks]);

  const requestPreview = useCallback(async () => {
    const id = ++requestId.current;
    const started = performance.now();
    setPreview({
      ...INITIAL_STATE,
      status: 'loading',
      diagnostics: EMPTY_DIAGNOSTICS,
    });

    try {
      const response = await apiFetch<EmailPreviewResponse>({
        path: PREVIEW_PATH,
        method: 'POST',
        data: {
          template_id: postId,
          content: serializedContent,
          metadata: { title },
        },
      });

      if (id !== requestId.current) return;
      const errors = (response.diagnostics ?? []).filter(
        d => d.severity === 'error'
      );
      const layout = blocks[0]?.attributes?.layout as
        { contentSize?: string; wideSize?: string } | undefined;
      const legacyWidth = blocks[0]?.attributes?.maxWidth;
      setPreview({
        status: errors.length ? 'error' : 'success',
        width:
          Number.parseFloat(layout?.contentSize ?? layout?.wideSize ?? '') *
            (/r?em$/.test(layout?.contentSize ?? layout?.wideSize ?? '')
              ? 16
              : 1) || (typeof legacyWidth === 'number' ? legacyWidth : 600),
        compiledAt: Date.now(),
        durationMs: performance.now() - started,
        content: serializedContent,
        title,
        html: response.html,
        diagnostics: {
          errors: (response.diagnostics ?? []).filter(
            d => d.severity === 'error'
          ),
          warnings: (response.diagnostics ?? []).filter(
            d => d.severity === 'warning'
          ),
        },
        error: null,
      });
    } catch {
      if (id !== requestId.current) return;

      // Server and exception text can carry internal details.
      setPreview({
        ...INITIAL_STATE,
        status: 'error',
        error: __(
          'The email preview could not be generated. Please try again.',
          'campaignbridge'
        ),
      });
    }
  }, [blocks, postId, serializedContent, title]);

  const resetPreview = useCallback(() => {
    requestId.current++;
    setPreview(INITIAL_STATE);
  }, []);

  return {
    preview,
    requestPreview,
    resetPreview,
    isStale:
      preview.content !== undefined &&
      (preview.content !== serializedContent || preview.title !== title),
  };
}

export default useEmailPreview;
