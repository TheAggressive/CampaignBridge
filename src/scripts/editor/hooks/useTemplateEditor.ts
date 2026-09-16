import type { Block } from '@wordpress/blocks';
import apiFetch from '@wordpress/api-fetch';
import {
  store as coreStore,
  useEntityBlockEditor,
  useEntityRecord,
} from '@wordpress/core-data';
import { dispatch, select, useSelect } from '@wordpress/data';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import type { SaveStatus } from '../types';
import { getRecoveryEdits } from '../utils/autosaveRecovery';
import type { RecoveryRecord } from '../utils/autosaveRecovery';
import { buildDuplicatePayload } from '../utils/templateDuplication';
import { useAutosaveRecovery } from './useAutosaveRecovery';
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
  revisionedMetaKeys?: readonly string[];
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
      'Autosave failed. Your changes are still in the editor.',
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
  revisionedMetaKeys = [],
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

  const { isSaving, isAutosaving, loadError, saveError } = useSelect(
    select => {
      const core = select(coreStore) as any;

      return {
        isSaving: core.isSavingEntityRecord('postType', postType, postId),
        isAutosaving: core.isAutosavingEntityRecord(
          'postType',
          postType,
          postId
        ),
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
  // core-data checks for edits before it takes its per-record save lock, so
  // two Save or Publish calls in the same tick would each send a canonical
  // save. This ref admits one until it settles.
  const savingRef = useRef(false);
  // A duplicate that finishes after the operator left this template must not
  // navigate them away from where they went.
  const mountedRef = useRef(true);
  useEffect(() => {
    mountedRef.current = true;
    return () => {
      mountedRef.current = false;
    };
  }, []);

  const canRestoreAutosave = useCallback(() => {
    const core = select(coreStore) as any;
    return !(
      operationRef.current ||
      savingRef.current ||
      core.isSavingEntityRecord('postType', postType, postId) ||
      core.hasEditsForEntityRecord('postType', postType, postId)
    );
  }, [postId, postType]);
  const recovery = useAutosaveRecovery(
    postType,
    postId,
    Boolean(record) && !isResolving,
    canRestoreAutosave
  );
  const recoveryBlocked = recovery.blocksPersistence;

  // Completion feedback only; dirty state and persistence remain in core-data.
  const [autosavedSource, setAutosavedSource] = useState<
    readonly unknown[] | null
  >(null);

  // WordPress-native autosave: POSTs to /autosaves endpoint, preserves status,
  // does not create a revision. The useEntityRecord save function accepts
  // options (isAutosave, throwOnError) even though the type omits them.
  const autosave = useCallback(
    async (source: readonly unknown[]) => {
      try {
        await (save as (opts?: { isAutosave?: boolean }) => Promise<void>)({
          isAutosave: true,
        });
        setAutosavedSource(source);
      } catch {
        setAutosavedSource(null);
        // A background recovery write: the edits stay in the editor.
        onError?.(editorMessages.autosaveFailed(), {
          id: EDITOR_NOTICE_IDS.autosave,
        });
      }
    },
    [onError, save]
  );

  // The autosaved fields. Published templates stay dirty after an autosave and
  // a failed autosave keeps its edits, so dirty state alone would re-arm the
  // timer after every save attempt. Only a change to these values schedules
  // another autosave; selection changes and save-time content serialization
  // do not.
  const editedTitle = edits?.title;
  const editedExcerpt = edits?.excerpt;
  const editedMeta = edits?.meta;

  useEffect(() => {
    if (!hasEdits || isResolving || isSaving || recoveryBlocked) {
      return;
    }

    const source = [rawBlocks, editedTitle, editedExcerpt, editedMeta];
    const lastSource = lastAutosaveSourceRef.current;
    if (lastSource && source.every((value, i) => value === lastSource[i])) {
      return;
    }

    const timer = window.setTimeout(() => {
      lastAutosaveSourceRef.current = source;
      void autosave(source);
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
    recoveryBlocked,
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
   * Duplicate and restore start from the saved template. They refuse while
   * core-data reports unsaved canonical edits (a recovery autosave does not
   * clear those), while a save is running, or while another of them runs.
   * State is read from core-data at call time so no caller can bypass it.
   */
  const canonicalOperationBlocker = useCallback(():
    'busy' | 'unsaved' | null => {
    const core = select(coreStore) as any;

    if (
      recoveryBlocked ||
      operationRef.current ||
      savingRef.current ||
      core.isSavingEntityRecord('postType', postType, postId)
    ) {
      return 'busy';
    }

    return core.hasEditsForEntityRecord('postType', postType, postId)
      ? 'unsaved'
      : null;
  }, [postId, postType, recoveryBlocked]);

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

    if (recoveryBlocked || savingRef.current || operationRef.current) {
      return false;
    }

    savingRef.current = true;
    try {
      await save();
      setAutosavedSource(null);
      dispatch(coreStore).invalidateResolution('getAutosaves', [
        postType,
        postId,
      ]);
      onSuccess?.(editorMessages.saved());
      return true;
    } catch {
      // core-data keeps the edits, so the operator can retry.
      onError?.(editorMessages.saveFailed(), { id: EDITOR_NOTICE_IDS.save });
      return false;
    } finally {
      savingRef.current = false;
    }
  }, [hasEdits, onError, onSuccess, save, recoveryBlocked, postType, postId]);

  const publish = useCallback(async () => {
    if (
      recoveryBlocked ||
      (isSaving && !isAutosaving) ||
      savingRef.current ||
      operationRef.current
    ) {
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

    savingRef.current = true;
    try {
      core.editEntityRecord('postType', postType, postId, {
        status: 'publish',
      });
      await save();
      setAutosavedSource(null);
      dispatch(coreStore).invalidateResolution('getAutosaves', [
        postType,
        postId,
      ]);
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
    } finally {
      savingRef.current = false;
    }
  }, [
    isAutosaving,
    isSaving,
    onError,
    onSuccess,
    postId,
    postType,
    save,
    recoveryBlocked,
  ]);

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
        // The copy exists, but the operator has already moved elsewhere.
        if (!mountedRef.current) {
          return { success: true };
        }
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

      // Like autosave recovery, restoring a revision loads its content into
      // the editor as unsaved edits; the canonical template stays unchanged
      // until an explicit save. Data comes from WordPress's native
      // single-revision route.
      return runCanonicalOperation('restore', async () => {
        try {
          // context=edit is required for WordPress to include the raw
          // title/content fields; a view-context payload has no .raw and must
          // be rejected as incomplete.
          const revision = await apiFetch<RecoveryRecord>({
            path: `/wp/v2/${postType}/${postId}/revisions/${revisionId}?context=edit`,
          });

          // A partial payload must be rejected before any editor state is
          // touched. getRecoveryEdits throws on incomplete data.
          const edits = getRecoveryEdits(
            revision,
            (select(coreStore) as any).getRawEntityRecord(
              'postType',
              postType,
              postId
            )?.meta,
            revisionedMetaKeys
          );

          // Refuse if edits arrived during the revision fetch.
          if (
            (select(coreStore) as any).hasEditsForEntityRecord(
              'postType',
              postType,
              postId
            )
          ) {
            return { success: false, error: editorMessages.restoreUnsaved() };
          }

          // A clean editor may still hold transient parsed blocks that would
          // otherwise win over the new content; clear them so
          // useEntityBlockEditor parses the revision. The template was clean
          // when this started and the history modal blocks editing meanwhile.
          const core = dispatch(coreStore) as any;
          core.clearEntityRecordEdits('postType', postType, postId);
          core.editEntityRecord('postType', postType, postId, edits);
          return { success: true };
        } catch {
          // A failed fetch or incomplete payload changed nothing canonical.
          return { success: false, error: editorMessages.restoreFailed() };
        }
      });
    },
    [
      canonicalOperationBlocker,
      postType,
      postId,
      revisionedMetaKeys,
      runCanonicalOperation,
    ]
  );

  const saveStatus: SaveStatus = saveError
    ? 'error'
    : isSaving && !isAutosaving
      ? 'saving'
      : hasEdits
        ? 'dirty'
        : 'saved';

  return {
    blocks: (rawBlocks ?? []) as Block[],
    duplicate,
    hasEdits,
    isAutosaving,
    hasAutosaved:
      autosavedSource !== null &&
      !saveError &&
      (!hasEdits ||
        [rawBlocks, editedTitle, editedExcerpt, editedMeta].every(
          (value, index) => value === autosavedSource[index]
        )),
    isPersisting: isSaving,
    recovery,
    isOperationPending: pendingOperation !== null || recoveryBlocked,
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
