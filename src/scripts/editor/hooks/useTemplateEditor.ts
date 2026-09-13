import type { Block } from '@wordpress/blocks';
import apiFetch from '@wordpress/api-fetch';
import {
  store as coreStore,
  useEntityBlockEditor,
  useEntityRecord,
} from '@wordpress/core-data';
import { dispatch, resolveSelect, select, useSelect } from '@wordpress/data';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { SaveStatus } from '../types';
import { TEMPLATE_LIST_QUERY } from './useTemplates';

const AUTOSAVE_DELAY_MS = 2000;

interface TemplateRecord {
  id: number;
  title: string | { raw?: string; rendered?: string };
  status: string;
  content: string;
  excerpt?: string;
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

export interface DuplicateResult {
  success: boolean;
  id?: number;
  /** Operator-facing message; absent when a request is simply ignored. */
  error?: string;
}

export interface RestoreResult {
  success: boolean;
  /** Operator-facing message; absent when a request is simply ignored. */
  error?: string;
}

type CanonicalOperation = 'duplicate' | 'restore';

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
  // A ref blocks a second call in the same tick; the state drives the UI.
  const operationRef = useRef<CanonicalOperation | null>(null);
  const [pendingOperation, setPendingOperation] =
    useState<CanonicalOperation | null>(null);

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
  const editedExcerpt = edits?.excerpt;
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

  /**
   * Duplicate and restore act on the saved template. They refuse while
   * core-data reports unsaved canonical edits (a recovery autosave does not
   * clear those), while a save is running, or while another of them runs.
   * State is read from core-data at call time so no caller can bypass it.
   */
  const canonicalOperationBlocker = useCallback(():
    'busy' | 'unsaved' | null => {
    const core = select(coreStore) as any;

    if (
      operationRef.current ||
      core.isSavingEntityRecord('postType', postType, postId)
    ) {
      return 'busy';
    }

    return core.hasEditsForEntityRecord('postType', postType, postId)
      ? 'unsaved'
      : null;
  }, [postId, postType]);

  const runCanonicalOperation = useCallback(
    async <T>(
      operation: CanonicalOperation,
      task: () => Promise<T>
    ): Promise<T> => {
      operationRef.current = operation;
      setPendingOperation(operation);
      try {
        return await task();
      } finally {
        operationRef.current = null;
        setPendingOperation(null);
      }
    },
    []
  );

  const saveNow = useCallback(async () => {
    if (!hasEdits) {
      return true;
    }

    if (isSaving || operationRef.current) {
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
    if (isSaving || operationRef.current) {
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

  const duplicate = useCallback(async (): Promise<DuplicateResult> => {
    const blocker = canonicalOperationBlocker();
    if ('busy' === blocker) {
      return { success: false };
    }
    if ('unsaved' === blocker) {
      return {
        success: false,
        error: __(
          'Save your changes before duplicating this template.',
          'campaignbridge'
        ),
      };
    }

    const failed: DuplicateResult = {
      success: false,
      error: __('This template could not be duplicated.', 'campaignbridge'),
    };

    // The template is clean, so the saved record is the canonical state the
    // operator sees.
    const saved = (select(coreStore) as any).getRawEntityRecord(
      'postType',
      postType,
      postId
    ) as TemplateRecord | undefined;
    if (!saved) {
      return failed;
    }

    const title =
      typeof saved.title === 'string'
        ? saved.title
        : saved.title?.raw || saved.title?.rendered || 'Untitled';

    return runCanonicalOperation('duplicate', async () => {
      try {
        const newTemplate = await apiFetch<{ id: number }>({
          path: `/wp/v2/${postType}`,
          method: 'POST',
          data: {
            status: 'draft',
            content: saved.content,
            title: `${title} (Copy)`,
            meta: saved.meta,
          },
        });
        // Invalidate the template list resolver so useTemplates re-fetches.
        dispatch(coreStore).invalidateResolution('getEntityRecords', [
          'postType',
          postType,
          TEMPLATE_LIST_QUERY,
        ]);
        return { success: true, id: newTemplate.id };
      } catch {
        return failed;
      }
    });
  }, [canonicalOperationBlocker, postId, postType, runCanonicalOperation]);

  const restoreRevision = useCallback(
    async (revisionId: number): Promise<RestoreResult> => {
      const blocker = canonicalOperationBlocker();
      if ('busy' === blocker) {
        return { success: false };
      }
      if ('unsaved' === blocker) {
        return {
          success: false,
          error: __(
            'Save your changes before restoring a revision.',
            'campaignbridge'
          ),
        };
      }

      return runCanonicalOperation('restore', async () => {
        try {
          await apiFetch({
            path: `/campaignbridge/v1/templates/${postId}/revisions/${revisionId}/restore`,
            method: 'POST',
          });
        } catch (err: unknown) {
          const message =
            err && typeof err === 'object' && 'message' in err
              ? String((err as { message: string }).message)
              : __('Failed to restore revision.', 'campaignbridge');
          return { success: false, error: message };
        }

        const core = dispatch(coreStore) as any;
        core.invalidateResolution('getEntityRecord', [
          'postType',
          postType,
          postId,
        ]);
        await (resolveSelect(coreStore) as any).getEntityRecord(
          'postType',
          postType,
          postId
        );

        // The editor keeps parsed blocks as a transient edit that core-data
        // prefers over the refetched record, which would leave the replaced
        // content on screen. The template was clean before the restore and
        // the history modal blocks editing during it; clear only if that
        // still holds.
        if (
          !(select(coreStore) as any).hasEditsForEntityRecord(
            'postType',
            postType,
            postId
          )
        ) {
          core.clearEntityRecordEdits('postType', postType, postId);
        }

        return { success: true };
      });
    },
    [canonicalOperationBlocker, postType, postId, runCanonicalOperation]
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
    isOperationPending: pendingOperation !== null,
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
