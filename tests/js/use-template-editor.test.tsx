/** @jest-environment jsdom */
import { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import apiFetch from '@wordpress/api-fetch';
import { dispatch } from '@wordpress/data';
import {
  EDITOR_NOTICE_IDS,
  useTemplateEditor,
  type DuplicateResult,
  type RestoreResult,
} from '../../src/scripts/editor/hooks/useTemplateEditor';

jest.mock('@wordpress/api-fetch', () => ({
  __esModule: true,
  default: jest.fn(),
}));

// core-data returns a stable blocks reference until the blocks change.
const mockBlockState = {
  blocks: [{ name: 'core/paragraph', attrs: {}, innerBlocks: [] }] as unknown[],
};

// The core-data state the hook reads at operation time.
const mockCoreState = {
  hasEdits: false,
  isSaving: false,
  rawRecord: undefined as Record<string, unknown> | undefined,
};

const mockRefetch = jest.fn();

jest.mock('@wordpress/core-data', () => ({
  store: 'core/store',
  useEntityBlockEditor: () => [mockBlockState.blocks, jest.fn(), jest.fn()],
  useEntityRecord: jest.fn(),
}));

jest.mock('@wordpress/data', () => ({
  dispatch: jest.fn(),
  resolveSelect: () => ({ getEntityRecord: mockRefetch }),
  select: () => ({
    getRawEntityRecord: () => mockCoreState.rawRecord,
    hasEditsForEntityRecord: () => mockCoreState.hasEdits,
    isSavingEntityRecord: () => mockCoreState.isSaving,
  }),
  useSelect: jest.fn(),
}));

jest.mock('../../src/scripts/editor/hooks/useTemplates', () => ({
  TEMPLATE_LIST_QUERY: { per_page: 100, status: 'draft,publish' },
}));

const mockRecord = {
  id: 42,
  title: { raw: 'My Template', rendered: 'My Template' },
  status: 'draft',
  content: '<!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->',
};

// Raw values core-data holds for the saved template.
const mockRawRecord = {
  id: 42,
  title: 'My Template',
  status: 'draft',
  content: '<!-- wp:paragraph --><p>Saved</p><!-- /wp:paragraph -->',
  meta: {
    campaignbridge_subject: 'Saved subject',
    campaignbridge_utm_enabled: false,
    campaignbridge_audience_tags: 'saved-list',
    campaignbridge_template_category: 'newsletter',
    campaignbridge_provider_campaign_id: 'remote-campaign-1',
  },
};

// The server-provided duplication policy the editor receives.
const DUPLICABLE_META_KEYS = [
  'campaignbridge_subject',
  'campaignbridge_utm_enabled',
];
const mockPolicy = {
  duplicableMetaKeys: DUPLICABLE_META_KEYS as readonly string[] | null,
};

const RAW_SERVER_ERROR = 'SQLSTATE[HY000]: raw database failure in /var/www';
const MESSAGES = {
  saved: 'Template saved.',
  published: 'Template published.',
  saveFailed: 'Template changes could not be saved. Please try again.',
  publishFailed: 'Template could not be published. Please try again.',
  autosaveFailed:
    'Your recovery copy could not be saved. Your changes are still in the editor.',
  duplicateFailed: 'This template could not be duplicated.',
  duplicateUnsaved: 'Save your changes before duplicating this template.',
  restoreFailed: 'This revision could not be restored. Please try again.',
  restoreUnsaved: 'Save your changes before restoring a revision.',
};

const mockOnSuccess = jest.fn();
const mockOnError = jest.fn();

function setupEntityRecord(overrides: Record<string, unknown> = {}) {
  const { useEntityRecord } = require('@wordpress/core-data');
  const save = jest.fn().mockResolvedValue(true);
  useEntityRecord.mockReturnValue({
    edits: {},
    hasEdits: false,
    hasStarted: true,
    isResolving: false,
    record: mockRecord,
    save,
    ...overrides,
  });
  return save;
}

function setupUseSelect(overrides: Record<string, unknown> = {}) {
  const { useSelect } = require('@wordpress/data');
  useSelect.mockReturnValue({
    isSaving: false,
    loadError: null,
    saveError: null,
    ...overrides,
  });
}

function deferred<T>() {
  let resolve!: (value: T) => void;
  const promise = new Promise<T>(settle => {
    resolve = settle;
  });
  return { promise, resolve };
}

async function flushPromises() {
  await act(async () => {
    await Promise.resolve();
    await Promise.resolve();
  });
}

function shownMessages(): string[] {
  return [
    ...mockOnSuccess.mock.calls.map(call => call[0]),
    ...mockOnError.mock.calls.map(call => call[0]),
  ];
}

let current: ReturnType<typeof useTemplateEditor>;
function Harness() {
  current = useTemplateEditor({
    postId: 42,
    postType: 'cb_templates',
    duplicableMetaKeys: mockPolicy.duplicableMetaKeys,
    onSuccess: mockOnSuccess,
    onError: mockOnError,
  });
  return null;
}

describe('useTemplateEditor', () => {
  let root: Root;
  let container: HTMLDivElement;
  let mockSave: jest.Mock;
  let coreDispatch: {
    clearEntityRecordEdits: jest.Mock;
    editEntityRecord: jest.Mock;
    invalidateResolution: jest.Mock;
  };

  function render() {
    act(() => root.render(<Harness />));
  }

  beforeEach(() => {
    (
      globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }
    ).IS_REACT_ACT_ENVIRONMENT = true;
    jest.mocked(apiFetch).mockReset();
    mockRefetch.mockReset().mockResolvedValue(mockRawRecord);
    mockOnSuccess.mockReset();
    mockOnError.mockReset();
    mockBlockState.blocks = [
      { name: 'core/paragraph', attrs: {}, innerBlocks: [] },
    ];
    mockCoreState.hasEdits = false;
    mockCoreState.isSaving = false;
    mockCoreState.rawRecord = mockRawRecord;
    mockPolicy.duplicableMetaKeys = DUPLICABLE_META_KEYS;
    mockSave = setupEntityRecord();
    setupUseSelect();
    coreDispatch = {
      clearEntityRecordEdits: jest.fn(),
      editEntityRecord: jest.fn(),
      invalidateResolution: jest.fn(),
    };
    jest.mocked(dispatch).mockReturnValue(coreDispatch as any);
    container = document.createElement('div');
    root = createRoot(container);
    render();
  });

  afterEach(() => act(() => root.unmount()));

  describe('manual Save', () => {
    beforeEach(() => {
      mockSave = setupEntityRecord({ hasEdits: true, edits: { content: 'x' } });
      render();
    });

    it('reports success only after the canonical save resolves', async () => {
      await act(async () => {
        expect(await current.saveNow()).toBe(true);
      });

      expect(mockSave).toHaveBeenCalledWith();
      expect(mockOnSuccess).toHaveBeenCalledWith(MESSAGES.saved);
      expect(mockOnError).not.toHaveBeenCalled();
    });

    it('keeps edits, shows safe copy, and stays retryable when the save fails', async () => {
      mockSave.mockRejectedValueOnce(new Error(RAW_SERVER_ERROR));

      await act(async () => {
        expect(await current.saveNow()).toBe(false);
      });

      expect(mockOnError).toHaveBeenCalledWith(MESSAGES.saveFailed, {
        id: EDITOR_NOTICE_IDS.save,
      });
      expect(mockOnSuccess).not.toHaveBeenCalled();
      expect(shownMessages().join(' ')).not.toContain('SQLSTATE');
      // Nothing clears or rewrites the operator's edits.
      expect(coreDispatch.editEntityRecord).not.toHaveBeenCalled();
      expect(coreDispatch.clearEntityRecordEdits).not.toHaveBeenCalled();

      await act(async () => {
        expect(await current.saveNow()).toBe(true);
      });
      expect(mockSave).toHaveBeenCalledTimes(2);
      expect(mockOnSuccess).toHaveBeenCalledWith(MESSAGES.saved);
    });

    it('sends one canonical save when Save is triggered twice in the same tick', async () => {
      const pending = deferred<boolean>();
      mockSave.mockReturnValueOnce(pending.promise);

      let first: Promise<boolean> = Promise.resolve(false);
      let second = true;
      await act(async () => {
        first = current.saveNow();
        second = await current.saveNow();
      });
      await act(async () => pending.resolve(true));

      expect(second).toBe(false);
      expect(await first).toBe(true);
      expect(mockSave).toHaveBeenCalledTimes(1);
      expect(mockOnSuccess).toHaveBeenCalledTimes(1);
    });

    it('refuses Publish, Duplicate, and Restore while a Save started in the same tick is running', async () => {
      const pending = deferred<boolean>();
      mockSave.mockReturnValueOnce(pending.promise);
      mockCoreState.hasEdits = false;

      let published = true;
      let duplicated: DuplicateResult = { success: true };
      let restored: RestoreResult = { success: true };
      let saving: Promise<boolean> = Promise.resolve(false);
      await act(async () => {
        saving = current.saveNow();
        published = await current.publish();
        duplicated = await current.duplicate();
        restored = await current.restoreRevision(7);
      });
      await act(async () => pending.resolve(true));

      expect(published).toBe(false);
      expect(duplicated).toEqual({ success: false });
      expect(restored).toEqual({ success: false });
      expect(coreDispatch.editEntityRecord).not.toHaveBeenCalled();
      expect(apiFetch).not.toHaveBeenCalled();
      expect(mockSave).toHaveBeenCalledTimes(1);
      expect(await saving).toBe(true);
    });
  });

  describe('publish', () => {
    it('edits the status, saves, and reports publish success', async () => {
      await act(async () => {
        expect(await current.publish()).toBe(true);
      });

      expect(coreDispatch.editEntityRecord).toHaveBeenCalledWith(
        'postType',
        'cb_templates',
        42,
        { status: 'publish' }
      );
      expect(mockSave).toHaveBeenCalledWith();
      expect(mockOnSuccess).toHaveBeenCalledWith(MESSAGES.published);
    });

    it('restores the saved status and reports safe copy when publishing fails', async () => {
      mockSave.mockRejectedValueOnce(new Error(RAW_SERVER_ERROR));

      await act(async () => {
        expect(await current.publish()).toBe(false);
      });

      // The unsaved publish edit is replaced with the canonical status, so
      // neither the editor nor a later Save treats the template as published.
      expect(coreDispatch.editEntityRecord).toHaveBeenLastCalledWith(
        'postType',
        'cb_templates',
        42,
        { status: 'draft' }
      );
      expect(mockOnError).toHaveBeenCalledWith(MESSAGES.publishFailed, {
        id: EDITOR_NOTICE_IDS.publish,
      });
      expect(mockOnSuccess).not.toHaveBeenCalled();
      expect(shownMessages().join(' ')).not.toContain('SQLSTATE');

      await act(async () => {
        expect(await current.publish()).toBe(true);
      });
      expect(mockOnSuccess).toHaveBeenCalledWith(MESSAGES.published);
    });

    it('returns false when already saving', async () => {
      setupUseSelect({ isSaving: true });
      render();

      await act(async () => {
        expect(await current.publish()).toBe(false);
      });
      expect(mockSave).not.toHaveBeenCalled();
    });

    it('sends one publish save when Publish is triggered twice in the same tick', async () => {
      const pending = deferred<boolean>();
      mockSave.mockReturnValueOnce(pending.promise);

      let first: Promise<boolean> = Promise.resolve(false);
      let second = true;
      await act(async () => {
        first = current.publish();
        second = await current.publish();
      });
      await act(async () => pending.resolve(true));

      expect(second).toBe(false);
      expect(await first).toBe(true);
      expect(mockSave).toHaveBeenCalledTimes(1);
      expect(coreDispatch.editEntityRecord).toHaveBeenCalledTimes(1);
    });
  });

  describe('autosave', () => {
    beforeEach(() => {
      jest.useFakeTimers();
    });

    afterEach(() => {
      jest.useRealTimers();
    });

    function renderDirty() {
      mockSave = setupEntityRecord({ edits: { content: 'x' }, hasEdits: true });
      render();
    }

    it('calls save with isAutosave: true after the debounce delay', async () => {
      renderDirty();

      act(() => {
        jest.advanceTimersByTime(2000);
      });
      await flushPromises();

      expect(mockSave).toHaveBeenCalledWith({ isAutosave: true });
    });

    it('never reports a successful autosave as a manual save', async () => {
      renderDirty();

      act(() => {
        jest.advanceTimersByTime(2000);
      });
      await flushPromises();

      expect(mockSave).toHaveBeenCalledWith({ isAutosave: true });
      expect(mockOnSuccess).not.toHaveBeenCalled();
    });

    it('reports a failed autosave as a recovery-copy failure, not a Save failure', async () => {
      renderDirty();
      mockSave.mockRejectedValueOnce(new Error(RAW_SERVER_ERROR));

      act(() => {
        jest.advanceTimersByTime(2000);
      });
      await flushPromises();

      expect(mockOnError).toHaveBeenCalledTimes(1);
      expect(mockOnError).toHaveBeenCalledWith(MESSAGES.autosaveFailed, {
        id: EDITOR_NOTICE_IDS.autosave,
      });
      expect(mockOnSuccess).not.toHaveBeenCalled();
      expect(shownMessages().join(' ')).not.toContain('SQLSTATE');
      expect(coreDispatch.clearEntityRecordEdits).not.toHaveBeenCalled();
    });

    it('does not repeat an autosave failure notice without a new edit', async () => {
      renderDirty();
      mockSave.mockRejectedValue(new Error(RAW_SERVER_ERROR));

      act(() => {
        jest.advanceTimersByTime(2000);
      });
      await flushPromises();
      expect(mockOnError).toHaveBeenCalledTimes(1);

      // A failed save attempt finishing does not re-arm the timer.
      setupUseSelect({ isSaving: true });
      render();
      setupUseSelect();
      render();
      act(() => {
        jest.advanceTimersByTime(10000);
      });
      await flushPromises();
      expect(mockOnError).toHaveBeenCalledTimes(1);

      // A new edit that fails again replaces the same notice.
      mockBlockState.blocks = [
        { name: 'core/paragraph', attrs: { content: 'y' }, innerBlocks: [] },
      ];
      render();
      act(() => {
        jest.advanceTimersByTime(2000);
      });
      await flushPromises();
      expect(mockOnError).toHaveBeenCalledTimes(2);
      expect(mockOnError.mock.calls.map(call => call[1])).toEqual([
        { id: EDITOR_NOTICE_IDS.autosave },
        { id: EDITOR_NOTICE_IDS.autosave },
      ]);
    });

    it('does not autosave when there are no edits', () => {
      render();

      act(() => {
        jest.advanceTimersByTime(5000);
      });

      expect(mockSave).not.toHaveBeenCalled();
    });

    it('does not autosave while a save is already in progress', () => {
      setupUseSelect({ isSaving: true });
      renderDirty();

      act(() => {
        jest.advanceTimersByTime(5000);
      });

      expect(mockSave).not.toHaveBeenCalled();
    });
  });

  describe('duplicate', () => {
    async function duplicateNow(): Promise<DuplicateResult> {
      let result: DuplicateResult = { success: false };
      await act(async () => {
        result = await current.duplicate();
      });
      return result;
    }

    function createPayload(): Record<string, unknown> {
      return (jest.mocked(apiFetch).mock.calls[0][0] as any).data;
    }

    it('creates one draft of the saved template with only allowlisted meta', async () => {
      jest.mocked(apiFetch).mockResolvedValue({ id: 99 } as any);

      expect(await duplicateNow()).toEqual({ success: true, id: 99 });
      expect(apiFetch).toHaveBeenCalledTimes(1);
      expect(apiFetch).toHaveBeenCalledWith({
        path: '/wp/v2/cb_templates',
        method: 'POST',
        data: {
          status: 'draft',
          content: mockRawRecord.content,
          title: 'My Template (Copy)',
          meta: {
            campaignbridge_subject: 'Saved subject',
            campaignbridge_utm_enabled: false,
          },
        },
      });
    });

    it('omits denied and unrecognized meta from the create payload', async () => {
      jest.mocked(apiFetch).mockResolvedValue({ id: 99 } as any);

      await duplicateNow();

      const meta = createPayload().meta as Record<string, unknown>;
      expect(meta).not.toHaveProperty('campaignbridge_audience_tags');
      expect(meta).not.toHaveProperty('campaignbridge_template_category');
      expect(meta).not.toHaveProperty('campaignbridge_provider_campaign_id');
      expect(meta).not.toBe(mockRawRecord.meta);
    });

    it('creates a draft without source identity, status, or dates from a published template', async () => {
      mockCoreState.rawRecord = {
        ...mockRawRecord,
        status: 'publish',
        date: '2026-09-13T10:00:00',
        date_gmt: '2026-09-13T10:00:00',
        author: 3,
        slug: 'my-template',
        parent: 0,
      };
      jest.mocked(apiFetch).mockResolvedValue({ id: 99 } as any);

      await duplicateNow();

      const payload = createPayload();
      expect(Object.keys(payload).sort()).toEqual([
        'content',
        'meta',
        'status',
        'title',
      ]);
      expect(payload.status).toBe('draft');
    });

    it('copies the saved content, not unsaved editor state', async () => {
      setupEntityRecord({
        edits: { content: 'Unsaved editor content' },
        record: { ...mockRecord, content: 'Unsaved editor content' },
      });
      render();
      jest.mocked(apiFetch).mockResolvedValue({ id: 99 } as any);

      await duplicateNow();

      expect(createPayload().content).toBe(mockRawRecord.content);
    });

    it('gives no navigation target when the editor unmounts before the copy is created', async () => {
      const request = deferred<{ id: number }>();
      jest.mocked(apiFetch).mockReturnValue(request.promise as any);
      const otherContainer = document.createElement('div');
      const otherRoot = createRoot(otherContainer);
      act(() => otherRoot.render(<Harness />));

      let settled: Promise<DuplicateResult> = Promise.resolve({
        success: false,
      });
      await act(async () => {
        settled = current.duplicate();
      });
      act(() => otherRoot.unmount());
      await act(async () => request.resolve({ id: 99 }));

      // The copy exists, but the operator is not moved to it.
      expect(await settled).toEqual({ success: true });
      expect(apiFetch).toHaveBeenCalledTimes(1);
    });

    it('refuses without a request when the duplication policy is missing', async () => {
      mockPolicy.duplicableMetaKeys = null;
      render();

      expect(await duplicateNow()).toEqual({
        success: false,
        error: MESSAGES.duplicateFailed,
      });
      expect(apiFetch).not.toHaveBeenCalled();
    });

    it('refuses without a request when the saved template is unavailable', async () => {
      mockCoreState.rawRecord = undefined;

      expect(await duplicateNow()).toEqual({
        success: false,
        error: MESSAGES.duplicateFailed,
      });
      expect(apiFetch).not.toHaveBeenCalled();
    });

    it('returns an ID to navigate to only after the create resolves', async () => {
      const request = deferred<{ id: number }>();
      jest.mocked(apiFetch).mockReturnValue(request.promise as any);

      let settled: DuplicateResult | null = null;
      await act(async () => {
        void current.duplicate().then(result => {
          settled = result;
        });
      });
      expect(settled).toBeNull();
      expect(current.isOperationPending).toBe(true);

      await act(async () => request.resolve({ id: 99 }));
      expect(settled).toEqual({ success: true, id: 99 });
      expect(current.isOperationPending).toBe(false);
    });

    it('refuses without a request while core-data reports unsaved edits', async () => {
      mockCoreState.hasEdits = true;

      let result: DuplicateResult = { success: true };
      await act(async () => {
        result = await current.duplicate();
      });

      expect(result).toEqual({
        success: false,
        error: MESSAGES.duplicateUnsaved,
      });
      expect(apiFetch).not.toHaveBeenCalled();
    });

    it('returns safe copy and no new template when creation fails', async () => {
      jest.mocked(apiFetch).mockRejectedValue(new Error(RAW_SERVER_ERROR));

      let result: DuplicateResult = { success: true };
      await act(async () => {
        result = await current.duplicate();
      });

      expect(result).toEqual({
        success: false,
        error: MESSAGES.duplicateFailed,
      });
      expect(result.id).toBeUndefined();
      expect(coreDispatch.invalidateResolution).not.toHaveBeenCalled();
      expect(current.isOperationPending).toBe(false);
    });

    it('sends one create request when called twice at once', async () => {
      const request = deferred<{ id: number }>();
      jest.mocked(apiFetch).mockReturnValue(request.promise as any);

      let first: Promise<DuplicateResult> = Promise.resolve({ success: false });
      let second: DuplicateResult = { success: true };
      await act(async () => {
        first = current.duplicate();
        second = await current.duplicate();
        request.resolve({ id: 99 });
      });

      expect(second).toEqual({ success: false });
      expect(await first).toEqual({ success: true, id: 99 });
      expect(apiFetch).toHaveBeenCalledTimes(1);
    });
  });

  describe('restoreRevision', () => {
    it('restores, waits for the refetch, then drops stale editor edits', async () => {
      jest.mocked(apiFetch).mockResolvedValue({ success: true } as any);

      let result: RestoreResult = { success: false };
      await act(async () => {
        result = await current.restoreRevision(7);
      });

      expect(result).toEqual({ success: true });
      expect(apiFetch).toHaveBeenCalledWith({
        path: '/campaignbridge/v1/templates/42/revisions/7/restore',
        method: 'POST',
      });
      expect(mockRefetch).toHaveBeenCalledWith('postType', 'cb_templates', 42);
      expect(
        coreDispatch.clearEntityRecordEdits.mock.invocationCallOrder[0]
      ).toBeGreaterThan(mockRefetch.mock.invocationCallOrder[0]);
      expect(current.needsReload).toBe(false);
    });

    it('refuses without a request or invalidation while core-data reports unsaved edits', async () => {
      mockCoreState.hasEdits = true;

      let result: RestoreResult = { success: true };
      await act(async () => {
        result = await current.restoreRevision(7);
      });

      expect(result).toEqual({
        success: false,
        error: MESSAGES.restoreUnsaved,
      });
      expect(apiFetch).not.toHaveBeenCalled();
      expect(coreDispatch.invalidateResolution).not.toHaveBeenCalled();
    });

    it('returns safe copy and leaves core-data untouched when the restore fails', async () => {
      jest.mocked(apiFetch).mockRejectedValue({
        code: 'restore_failed',
        message: RAW_SERVER_ERROR,
      });

      let result: RestoreResult = { success: true };
      await act(async () => {
        result = await current.restoreRevision(7);
      });

      expect(result).toEqual({ success: false, error: MESSAGES.restoreFailed });
      expect(JSON.stringify(result)).not.toContain('SQLSTATE');
      expect(coreDispatch.invalidateResolution).not.toHaveBeenCalled();
      expect(coreDispatch.clearEntityRecordEdits).not.toHaveBeenCalled();
      expect(current.isOperationPending).toBe(false);
      expect(current.needsReload).toBe(false);
    });

    it.each([
      ['rejects', () => mockRefetch.mockRejectedValue(new Error('500'))],
      ['returns nothing', () => mockRefetch.mockResolvedValue(undefined)],
    ])(
      'reports a successful restore truthfully and locks the stale editor when the refetch %s',
      async (_label, failRefetch) => {
        jest.mocked(apiFetch).mockResolvedValue({ success: true } as any);
        failRefetch();

        let result: RestoreResult = { success: false };
        await act(async () => {
          result = await current.restoreRevision(7);
        });

        // The server restored the revision, so it is not reported as failed.
        expect(result).toEqual({ success: true, needsReload: true });
        expect(current.needsReload).toBe(true);
        expect(coreDispatch.clearEntityRecordEdits).not.toHaveBeenCalled();

        // Nothing may write the stale editor content back.
        mockSave = setupEntityRecord({
          hasEdits: true,
          edits: { content: 'stale' },
        });
        render();
        await act(async () => {
          expect(await current.saveNow()).toBe(false);
          expect(await current.publish()).toBe(false);
          expect(await current.duplicate()).toEqual({ success: false });
        });
        expect(mockSave).not.toHaveBeenCalled();
      }
    );

    it('does not autosave stale content after a failed refetch', async () => {
      jest.mocked(apiFetch).mockResolvedValue({ success: true } as any);
      mockRefetch.mockRejectedValue(new Error('500'));
      await act(async () => {
        await current.restoreRevision(7);
      });

      jest.useFakeTimers();
      try {
        mockSave = setupEntityRecord({
          hasEdits: true,
          edits: { content: 'stale' },
        });
        render();
        act(() => {
          jest.advanceTimersByTime(5000);
        });
        expect(mockSave).not.toHaveBeenCalled();
      } finally {
        jest.useRealTimers();
      }
    });

    it('sends one restore request when called twice at once', async () => {
      const request = deferred<unknown>();
      jest.mocked(apiFetch).mockReturnValue(request.promise as any);

      let first: Promise<RestoreResult> = Promise.resolve({ success: false });
      let second: RestoreResult = { success: true };
      await act(async () => {
        first = current.restoreRevision(7);
        second = await current.restoreRevision(7);
        request.resolve({ success: true });
      });

      expect(second).toEqual({ success: false });
      expect(await first).toEqual({ success: true });
      expect(apiFetch).toHaveBeenCalledTimes(1);
    });
  });

  describe('canonical operations and saving', () => {
    it('keeps duplicate and restore blocked when a save fails', async () => {
      mockCoreState.hasEdits = true;
      mockSave = setupEntityRecord({ hasEdits: true, edits: { content: 'x' } });
      mockSave.mockRejectedValue(new Error('Save failed'));
      render();

      await act(async () => {
        expect(await current.saveNow()).toBe(false);
      });

      let duplicated: DuplicateResult = { success: true };
      let restored: RestoreResult = { success: true };
      await act(async () => {
        duplicated = await current.duplicate();
        restored = await current.restoreRevision(7);
      });

      expect(duplicated.success).toBe(false);
      expect(restored.success).toBe(false);
      expect(apiFetch).not.toHaveBeenCalled();
    });

    it('allows duplicate and restore once a save leaves core-data clean', async () => {
      mockCoreState.hasEdits = true;
      mockSave = setupEntityRecord({ hasEdits: true, edits: { content: 'x' } });
      mockSave.mockImplementation(async () => {
        // core-data clears the edits the server persisted.
        mockCoreState.hasEdits = false;
      });
      render();
      jest.mocked(apiFetch).mockResolvedValue({ id: 99 } as any);

      await act(async () => {
        expect(await current.saveNow()).toBe(true);
      });

      let duplicated: DuplicateResult = { success: false };
      let restored: RestoreResult = { success: false };
      await act(async () => {
        duplicated = await current.duplicate();
        restored = await current.restoreRevision(7);
      });

      expect(duplicated).toEqual({ success: true, id: 99 });
      expect(restored).toEqual({ success: true });
    });

    it('refuses Save and Publish while a duplicate is running', async () => {
      const request = deferred<{ id: number }>();
      jest.mocked(apiFetch).mockReturnValue(request.promise as any);
      mockSave = setupEntityRecord({ hasEdits: true, edits: { content: 'x' } });
      render();

      let pending: Promise<DuplicateResult> = Promise.resolve({
        success: false,
      });
      await act(async () => {
        pending = current.duplicate();
      });
      expect(current.isOperationPending).toBe(true);

      await act(async () => {
        expect(await current.saveNow()).toBe(false);
        expect(await current.publish()).toBe(false);
      });
      expect(mockSave).not.toHaveBeenCalled();

      await act(async () => {
        request.resolve({ id: 99 });
        await pending;
      });
      expect(current.isOperationPending).toBe(false);
    });
  });
});
