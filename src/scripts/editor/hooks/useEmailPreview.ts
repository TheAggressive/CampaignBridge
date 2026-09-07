import apiFetch from '@wordpress/api-fetch';
import { serialize } from '@wordpress/blocks';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { useCallback, useState } from '@wordpress/element';
import type { EmailPreviewResponse } from '../types';

const PREVIEW_PATH = '/campaignbridge/v1/preview';

export type PreviewStatus = 'idle' | 'loading' | 'success' | 'error';

export interface EmailPreview {
  status: PreviewStatus;
  html: string;
  diagnostics: {
    errors: Array<{ code: string; message: string }>;
    warnings: Array<{ code: string; message: string }>;
  };
  error: string | null;
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
}

/**
 * Compile the current unsaved editor content via the server-side
 * `POST /preview` endpoint and expose the resulting HTML and
 * diagnostics to the preview UI.
 *
 * Must be rendered inside the `BlockEditorProvider` scoped data registry so
 * the `core/block-editor` selectors resolve against the active template.
 */
export function useEmailPreview(postId: number): UseEmailPreview {
  const [preview, setPreview] = useState<EmailPreview>(INITIAL_STATE);

  const blocks = useSelect(select => select(blockEditorStore).getBlocks(), []);

  const requestPreview = useCallback(async () => {
    setPreview({
      ...INITIAL_STATE,
      status: 'loading',
      diagnostics: EMPTY_DIAGNOSTICS,
    });

    try {
      const serialized = serialize(blocks);

      const response = await apiFetch<EmailPreviewResponse>({
        path: PREVIEW_PATH,
        method: 'POST',
        data: {
          template_id: postId,
          content: serialized,
          metadata: {},
        },
      });

      setPreview({
        status: 'success',
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
    } catch (reason) {
      const message =
        reason instanceof Error
          ? reason.message
          : 'Unable to compile the email preview.';

      setPreview({
        ...INITIAL_STATE,
        status: 'error',
        error: message,
      });
    }
  }, [blocks, postId]);

  const resetPreview = useCallback(() => {
    setPreview(INITIAL_STATE);
  }, []);

  return { preview, requestPreview, resetPreview };
}

export default useEmailPreview;
