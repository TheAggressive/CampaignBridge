import type { Block } from '@wordpress/blocks';
import {
  store as coreStore,
  useEntityBlockEditor,
  useEntityRecord,
} from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { useCallback, useEffect, useRef } from '@wordpress/element';

const AUTOSAVE_DELAY_MS = 2000;

interface TemplateRecord {
  id: number;
  status: string;
  content: string;
  meta?: Record<string, unknown>;
}

type ChangeOptions = Record<string, unknown>;
type ChangeHandler = (
  // eslint-disable-next-line no-unused-vars -- Parameter names document the core callback contract.
  blocks: Block[],
  // eslint-disable-next-line no-unused-vars -- Parameter names document the core callback contract.
  options?: ChangeOptions
) => void;

interface UseTemplateEditorOptions {
  postId: number;
  postType: string;
  onSave?: () => void;
  // eslint-disable-next-line no-unused-vars -- Parameter name documents the callback contract.
  onError?: (message: string) => void;
}

/**
 * Bind the standalone email editor to WordPress's core-data entity lifecycle.
 *
 * WordPress owns parsing, transient input, persistent edits, undo levels, and
 * REST persistence. CampaignBridge only adds a short debounce and notices.
 */
export function useTemplateEditor({
  postId,
  postType,
  onSave,
  onError,
}: UseTemplateEditorOptions) {
  const entity = useEntityRecord<TemplateRecord>('postType', postType, postId);
  const { edits, hasEdits, hasStarted, isResolving, record, save } = entity;
  const [rawBlocks, rawOnInput, rawOnChange] = useEntityBlockEditor(
    'postType',
    postType,
    { id: postId } as any
  );

  const { isSaving, loadError, saveError } = useSelect(
    select => {
      const core = select(coreStore) as any;

      return {
        isSaving: core.isSavingEntityRecord('postType', postType, postId),
        loadError: core.getResolutionError('getEntityRecord', [
          'postType',
          postType,
          postId,
        ]),
        saveError: core.getLastEntitySaveError('postType', postType, postId),
      };
    },
    [postId, postType]
  );

  const onInput = rawOnInput as ChangeHandler;
  const onChange = rawOnChange as ChangeHandler;
  const wasSavingRef = useRef(false);
  const lastSaveErrorRef = useRef<unknown>(null);

  useEffect(() => {
    if (!hasEdits || isResolving || isSaving) {
      return;
    }

    const timer = window.setTimeout(() => {
      void save().catch(() => {
        // The core-data error selector drives the visible error state.
      });
    }, AUTOSAVE_DELAY_MS);

    return () => window.clearTimeout(timer);
  }, [edits, hasEdits, isResolving, isSaving, save]);

  useEffect(() => {
    if (wasSavingRef.current && !isSaving && !saveError && !hasEdits) {
      onSave?.();
    }

    wasSavingRef.current = isSaving;
  }, [hasEdits, isSaving, onSave, saveError]);

  useEffect(() => {
    if (saveError && saveError !== lastSaveErrorRef.current) {
      const message =
        saveError instanceof Error
          ? saveError.message
          : 'Failed to save template changes.';
      onError?.(message);
    }

    lastSaveErrorRef.current = saveError;
  }, [onError, saveError]);

  useEffect(() => {
    if (!hasEdits) {
      return;
    }

    const warnBeforeUnload = (event: BeforeUnloadEvent) => {
      event.preventDefault();
      event.returnValue = '';
    };

    window.addEventListener('beforeunload', warnBeforeUnload);
    return () => window.removeEventListener('beforeunload', warnBeforeUnload);
  }, [hasEdits]);

  const saveNow = useCallback(async () => {
    if (!hasEdits) {
      return true;
    }

    if (isSaving) {
      return false;
    }

    try {
      await save();
      return true;
    } catch {
      // The core-data error selector drives notices and retry state.
      return false;
    }
  }, [hasEdits, isSaving, save]);

  const saveStatus = saveError
    ? 'error'
    : isSaving
      ? 'saving'
      : hasEdits
        ? 'dirty'
        : 'saved';

  return {
    blocks: (rawBlocks ?? []) as Block[],
    hasEdits,
    isResolving: isResolving || !hasStarted,
    loadError,
    onChange,
    onInput,
    record,
    saveNow,
    saveStatus,
  };
}
