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
import { buildDuplicatePayload } from '../utils/templateDuplication';
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

// A type alias (not an interface) so WordPress notice callbacks accept it.
export type NoticeOptions = {
  id?: string;
};

interface UseTemplateEditorOptions {
  postId: number;
  postType: string;
  /**
   * Server-provided meta keys a duplicate copies. Without them duplication
   * refuses rather than copying an unclassified field.
   */
  duplicableMetaKeys?: readonly string[] | null;
  /** Receives CampaignBridge copy after a confirmed canonical save. */
  onSuccess?: (message: string) => void;
  /** Receives CampaignBridge copy for a failure; never raw server text. */
  onError?: (message: string, options?: NoticeOptions) => void;
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
  /** The server restored the revision but the editor could not load it. */
  needsReload?: boolean;
}

type CanonicalOperation = 'duplicate' | 'restore';

/**
 * Stable notice IDs: a repeated failure replaces its notice instead of
 * stacking another copy, and a later failure is announced again.
 */
export const EDITOR_NOTICE_IDS = {
  autosave: 'campaignbridge-autosave-failed',
  duplicate: 'campaignbridge-duplicate-failed',
  publish: 'campaignbridge-publish-failed',
  save: 'campaignbridge-save-failed',
} as const;

/**
 * Operator-facing editor messages. Server and exception text is never shown,
 * because it can carry internal details.
 */
export const editorMessages = {
  saved: () => __('Template saved.', 'campaignbridge'),
  published: () => __('Template published.', 'campaignbridge'),
  loadFailed: () =>
    __('Template could not be loaded. Please try again.', 'campaignbridge'),
  saveFailed: () =>
    __(
      'Template changes could not be saved. Please try again.',
      'campaignbridge'
    ),
  publishFailed: () =>
    __('Template could not be published. Please try again.', 'campaignbridge'),
  autosaveFailed: () =>
    __(
      'Your recovery copy could not be saved. Your changes are still in the editor.',
      'campaignbridge'
    ),
  duplicateFailed: () =>
    __('This template could not be duplicated.', 'campaignbridge'),
  duplicateUnsaved: () =>
    __('Save your changes before duplicating this template.', 'campaignbridge'),
  restoreFailed: () =>
    __(
      'This revision could not be restored. Please try again.',
      'campaignbridge'
    ),
  restoreUnsaved: () =>
    __('Save your changes before restoring a revision.', 'campaignbridge'),
  restoreRefreshFailed: () =>
    __(
      'The revision was restored, but the editor could not load it. Reload the editor to continue.',
      'campaignbridge'
    ),
};

/**
 * Bind the standalone email editor to WordPress's core-data entity lifecycle.
 *
 * WordPress owns parsing, transient input, persistent edits, undo levels, and
 * REST persistence. CampaignBridge only adds a short debounce and notices.
 */
export function useTemplateEditor({
  postId,
  postType,
  duplicableMetaKeys,
  onSuccess,
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
  const lastAutosaveSourceRef = useRef<readonly unknown[] | null>(null);
  // A ref blocks a second call in the same tick; the state drives the UI.
  const operationRef = useRef<CanonicalOperation | null>(null);
  const [pendingOperation, setPendingOperation] =
    useState<CanonicalOperation | null>(null);
  // Set when the server restored a revision the editor could not load. The
  // editor then holds replaced content, so nothing may be written from it.
  const needsReloadRef = useRef(false);
  const [needsReload, setNeedsReload] = useState(false);

  // WordPress-native autosave: POSTs to /autosaves endpoint, preserves status,
  // does not create a revision. The useEntityRecord save function accepts
  // options (isAutosave, throwOnError) even though the type omits them.
  const autosave = useCallback(async () => {
    try {
      await (save as (opts?: { isAutosave?: boolean }) => Promise<void>)({
        isAutosave: true,
      });
    } catch {
      // A background recovery write: the edits stay in the editor.
      onError?.(editorMessages.autosaveFailed(), {
        id: EDITOR_NOTICE_IDS.autosave,
      });
    }
  }, [onError, save]);

  // The autosaved fields. Published templates stay dirty after an autosave and
  // a failed autosave keeps its edits, so dirty state alone would re-arm the
  // timer after every save attempt. Only a change to these values schedules
  // another autosave; selection changes and save-time content serialization
  // do not.
  const editedTitle = edits?.title;
  const editedExcerpt = edits?.excerpt;
  const editedMeta = edits?.meta;

  useEffect(() => {
    if (!hasEdits || isResolving || isSaving || needsReload) {
      return;
    }

    const source = [rawBlocks, editedTitle, editedExcerpt, editedMeta];
    const lastSource = lastAutosaveSourceRef.current;
    if (lastSource && source.every((value, i) => value === lastSource[i])) {
      return;
    }

    const timer = window.setTimeout(() => {
      lastAutosaveSourceRef.current = source;
      void autosave();
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
    needsReload,
    rawBlocks,
  ]);

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
   * clear those), while a save is running, while another of them runs, or
   * while the editor must reload. State is read from core-data at call time
   * so no caller can bypass it.
   */
  const canonicalOperationBlocker = useCallback(():
    'busy' | 'unsaved' | null => {
    const core = select(coreStore) as any;

    if (
      operationRef.current ||
      needsReloadRef.current ||
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

    if (isSaving || operationRef.current || needsReloadRef.current) {
      return false;
    }

    try {
      await save();
      onSuccess?.(editorMessages.saved());
      return true;
    } catch {
      // core-data keeps the edits, so the operator can retry.
      onError?.(editorMessages.saveFailed(), { id: EDITOR_NOTICE_IDS.save });
      return false;
    }
  }, [hasEdits, isSaving, onError, onSuccess, save]);

  const publish = useCallback(async () => {
    if (isSaving || operationRef.current || needsReloadRef.current) {
      return false;
    }

    const core = dispatch(coreStore);
    const savedStatus = (
      (select(coreStore) as any).getRawEntityRecord(
        'postType',
        postType,
        postId
      ) as TemplateRecord | undefined
    )?.status;

    try {
      core.editEntityRecord('postType', postType, postId, {
        status: 'publish',
      });
      await save();
      onSuccess?.(editorMessages.published());
      return true;
    } catch {
      // Drop the unsaved status edit so neither the editor nor a later Save
      // treats the template as published. Other edits stay for retry.
      if (savedStatus) {
        core.editEntityRecord('postType', postType, postId, {
          status: savedStatus,
        });
      }
      onError?.(editorMessages.publishFailed(), {
        id: EDITOR_NOTICE_IDS.publish,
      });
      return false;
    }
  }, [isSaving, onError, onSuccess, postId, postType, save]);

  const duplicate = useCallback(async (): Promise<DuplicateResult> => {
    const blocker = canonicalOperationBlocker();
    if ('busy' === blocker) {
      return { success: false };
    }
    if ('unsaved' === blocker) {
      return { success: false, error: editorMessages.duplicateUnsaved() };
    }

    const failed: DuplicateResult = {
      success: false,
      error: editorMessages.duplicateFailed(),
    };

    // The template is clean, so the saved record is the canonical state the
    // operator sees.
    const saved = (select(coreStore) as any).getRawEntityRecord(
      'postType',
      postType,
      postId
    ) as TemplateRecord | undefined;
    if (!saved || !Array.isArray(duplicableMetaKeys)) {
      return failed;
    }

    const payload = buildDuplicatePayload(saved, duplicableMetaKeys);

    return runCanonicalOperation('duplicate', async () => {
      try {
        const newTemplate = await apiFetch<{ id: number }>({
          path: `/wp/v2/${postType}`,
          method: 'POST',
          data: { ...payload },
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
  }, [
    canonicalOperationBlocker,
    duplicableMetaKeys,
    postId,
    postType,
    runCanonicalOperation,
  ]);

  const restoreRevision = useCallback(
    async (revisionId: number): Promise<RestoreResult> => {
      const blocker = canonicalOperationBlocker();
      if ('busy' === blocker) {
        return { success: false };
      }
      if ('unsaved' === blocker) {
        return { success: false, error: editorMessages.restoreUnsaved() };
      }

      return runCanonicalOperation('restore', async () => {
        try {
          await apiFetch({
            path: `/campaignbridge/v1/templates/${postId}/revisions/${revisionId}/restore`,
            method: 'POST',
          });
        } catch {
          return { success: false, error: editorMessages.restoreFailed() };
        }

        // The server has restored the revision; from here a failure is a
        // refresh failure, not a restore failure.
        const core = dispatch(coreStore) as any;
        try {
          core.invalidateResolution('getEntityRecord', [
            'postType',
            postType,
            postId,
          ]);
          const refreshed = await (
            resolveSelect(coreStore) as any
          ).getEntityRecord('postType', postType, postId);
          if (!refreshed) {
            throw new Error('The restored template did not load.');
          }
        } catch {
          needsReloadRef.current = true;
          setNeedsReload(true);
          return { success: true, needsReload: true };
        }

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
    needsReload,
    onChange,
    onInput,
    publish,
    record,
    restoreRevision,
    saveNow,
    saveStatus,
  };
}
