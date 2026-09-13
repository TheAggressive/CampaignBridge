import type { Block } from '@wordpress/blocks';
import apiFetch from '@wordpress/api-fetch';
import {
  store as coreStore,
  useEntityBlockEditor,
  useEntityRecord,
} from '@wordpress/core-data';
import { dispatch, useSelect } from '@wordpress/data';
import { useCallback, useEffect, useRef } from '@wordpress/element';
import type { SaveStatus } from '../types';
import { TEMPLATE_LIST_QUERY } from './useTemplates';

const AUTOSAVE_DELAY_MS = 2000;

interface TemplateRecord {
  id: number;
  title: string | { raw?: string; rendered?: string };
  status: string;
  content: string;
  meta?: Record<string, unknown>;
}

type ChangeOptions = Record<string, unknown>;
type ChangeHandler = (
  blocks: Block[],

  options?: ChangeOptions
) => void;

interface UseTemplateEditorOptions {
  postId: number;
  postType: string;
  onSave?: () => void;

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

  const { isAutosaving, isSaving, loadError, saveError } = useSelect(
    select => {
      const core = select(coreStore) as any;

      return {
        isAutosaving: core.isAutosavingEntityRecord(
          'postType',
          postType,
          postId
        ),
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
  const wasAutosavingRef = useRef(false);
  const lastSaveErrorRef = useRef<unknown>(null);
  const lastAutosaveSourceRef = useRef<readonly unknown[] | null>(null);

  // WordPress-native autosave: POSTs to /autosaves endpoint, preserves status,
  // does not create a revision. The useEntityRecord save function accepts
  // options (isAutosave, throwOnError) even though the type omits them.
  const autosave = useCallback(async () => {
    await (save as (opts?: { isAutosave?: boolean }) => Promise<void>)({
      isAutosave: true,
    });
  }, [save]);

  // The autosaved fields. Published templates stay dirty after an autosave and
  // a failed autosave keeps its edits, so dirty state alone would re-arm the
  // timer after every save attempt. Only a change to these values schedules
  // another autosave; selection changes and save-time content serialization
  // do not.
  const editedTitle = edits?.title;
  const editedExcerpt = (edits as Record<string, unknown> | undefined)?.excerpt;
  const editedMeta = edits?.meta;

  useEffect(() => {
    if (!hasEdits || isResolving || isSaving) {
      return;
    }

    const source = [rawBlocks, editedTitle, editedExcerpt, editedMeta];
    const lastSource = lastAutosaveSourceRef.current;
    if (lastSource && source.every((value, i) => value === lastSource[i])) {
      return;
    }

    const timer = window.setTimeout(() => {
      lastAutosaveSourceRef.current = source;
      void autosave().catch(() => {
        // The core-data error selector drives the visible error state.
      });
    }, AUTOSAVE_DELAY_MS);

    return () => window.clearTimeout(timer);
  }, [
    autosave,
    editedExcerpt,
    editedMeta,
    editedTitle,
    hasEdits,
    isResolving,
    isSaving,
    rawBlocks,
  ]);

  useEffect(() => {
    // Autosave is a background recovery write, not the operator's Save.
    if (
      wasSavingRef.current &&
      !wasAutosavingRef.current &&
      !isSaving &&
      !saveError &&
      !hasEdits
    ) {
      onSave?.();
    }

    wasSavingRef.current = isSaving;
    wasAutosavingRef.current = isAutosaving;
  }, [hasEdits, isAutosaving, isSaving, onSave, saveError]);

  useEffect(() => {
    if (saveError && saveError !== lastSaveErrorRef.current) {
      // Use a safe operator-facing message; never surface raw server error text.
      onError?.('Template changes could not be saved. Please try again.');
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

  const publish = useCallback(async () => {
    if (isSaving) {
      return false;
    }

    try {
      dispatch(coreStore).editEntityRecord('postType', postType, postId, {
        status: 'publish',
      });
      await save();
      return true;
    } catch {
      // The core-data error selector drives notices and retry state.
      return false;
    }
  }, [postType, postId, isSaving, save]);

  const duplicate = useCallback(async (): Promise<number | null> => {
    if (!record) {
      return null;
    }

    const title =
      typeof record.title === 'string'
        ? record.title
        : record.title.raw || record.title.rendered || 'Untitled';

    try {
      const newTemplate = await apiFetch<{ id: number }>({
        path: `/wp/v2/${postType}`,
        method: 'POST',
        data: {
          status: 'draft',
          content: record.content,
          title: `${title} (Copy)`,
          meta: record.meta,
        },
      });
      // Invalidate the template list resolver so useTemplates re-fetches.
      dispatch(coreStore).invalidateResolution('getEntityRecords', [
        'postType',
        postType,
        TEMPLATE_LIST_QUERY,
      ]);
      return newTemplate.id;
    } catch {
      return null;
    }
  }, [record, postType]);

  const restoreRevision = useCallback(
    async (
      revisionId: number
    ): Promise<{ success: boolean; error?: string }> => {
      try {
        await apiFetch({
          path: `/campaignbridge/v1/templates/${postId}/revisions/${revisionId}/restore`,
          method: 'POST',
        });
        // Invalidate the entity record so the editor re-fetches restored content.
        dispatch(coreStore).invalidateResolution('getEntityRecord', [
          'postType',
          postType,
          postId,
        ]);
        return { success: true };
      } catch (err: unknown) {
        const message =
          err && typeof err === 'object' && 'message' in err
            ? String((err as { message: string }).message)
            : 'Failed to restore revision.';
        return { success: false, error: message };
      }
    },
    [postType, postId]
  );

  const saveStatus: SaveStatus = saveError
    ? 'error'
    : isSaving
      ? 'saving'
      : hasEdits
        ? 'dirty'
        : 'saved';

  return {
    blocks: (rawBlocks ?? []) as Block[],
    duplicate,
    hasEdits,
    isResolving: isResolving || !hasStarted,
    loadError,
    onChange,
    onInput,
    publish,
    record,
    restoreRevision,
    saveNow,
    saveStatus,
  };
}
