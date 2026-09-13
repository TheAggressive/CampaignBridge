/** @jest-environment jsdom */
import { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import apiFetch from '@wordpress/api-fetch';
import { store as coreStore } from '@wordpress/core-data';
import { dispatch } from '@wordpress/data';
import {
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
  status: 'publish',
  content: '<!-- wp:paragraph --><p>Saved</p><!-- /wp:paragraph -->',
  meta: { campaignbridge_subject: 'Saved subject' },
};

const UNSAVED_DUPLICATE = 'Save your changes before duplicating this template.';
const UNSAVED_RESTORE = 'Save your changes before restoring a revision.';

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
    isAutosaving: false,
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

let current: ReturnType<typeof useTemplateEditor>;
function Harness() {
  current = useTemplateEditor({ postId: 42, postType: 'cb_templates' });
  return null;
}

function ErrorHarness({ onError }: { onError: (msg: string) => void }) {
  current = useTemplateEditor({
    postId: 42,
    postType: 'cb_templates',
    onError,
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

  beforeEach(() => {
    (
      globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }
    ).IS_REACT_ACT_ENVIRONMENT = true;
    jest.mocked(apiFetch).mockReset();
    mockRefetch.mockReset().mockResolvedValue(mockRawRecord);
    mockBlockState.blocks = [
      { name: 'core/paragraph', attrs: {}, innerBlocks: [] },
    ];
    mockCoreState.hasEdits = false;
    mockCoreState.isSaving = false;
    mockCoreState.rawRecord = mockRawRecord;
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
    act(() => root.render(<Harness />));
  });

  afterEach(() => act(() => root.unmount()));

  describe('publish', () => {
    it('dispatches editEntityRecord with publish status then saves', async () => {
      await act(async () => {
        expect(await current.publish()).toBe(true);
      });

      expect(coreDispatch.editEntityRecord).toHaveBeenCalledWith(
        'postType',
        'cb_templates',
        42,
        { status: 'publish' }
      );
      expect(mockSave).toHaveBeenCalled();
    });

    it('returns false when save fails', async () => {
      mockSave.mockRejectedValue(new Error('Save failed'));

      await act(async () => {
        expect(await current.publish()).toBe(false);
      });
    });

    it('returns false when already saving', async () => {
      setupUseSelect({ isSaving: true });
      act(() => root.render(<Harness />));

      await act(async () => {
        expect(await current.publish()).toBe(false);
      });
      expect(mockSave).not.toHaveBeenCalled();
    });
  });

  describe('duplicate', () => {
    it('copies the saved template when core-data reports it clean', async () => {
      jest.mocked(apiFetch).mockResolvedValue({ id: 99 } as any);

      let result: DuplicateResult = { success: false };
      await act(async () => {
        result = await current.duplicate();
      });

      expect(result).toEqual({ success: true, id: 99 });
      expect(apiFetch).toHaveBeenCalledTimes(1);
      expect(apiFetch).toHaveBeenCalledWith({
        path: '/wp/v2/cb_templates',
        method: 'POST',
        data: {
          status: 'draft',
          content: mockRawRecord.content,
          title: 'My Template (Copy)',
          meta: mockRawRecord.meta,
        },
      });
      expect(coreDispatch.invalidateResolution).toHaveBeenCalledWith(
        'getEntityRecords',
        ['postType', 'cb_templates', expect.objectContaining({ per_page: 100 })]
      );
    });

    it('refuses without a request while core-data reports unsaved edits', async () => {
      mockCoreState.hasEdits = true;

      let result: DuplicateResult = { success: true };
      await act(async () => {
        result = await current.duplicate();
      });

      expect(result).toEqual({ success: false, error: UNSAVED_DUPLICATE });
      expect(apiFetch).not.toHaveBeenCalled();
      expect(coreDispatch.invalidateResolution).not.toHaveBeenCalled();
    });

    it('ignores a request while a save is running', async () => {
      mockCoreState.isSaving = true;

      let result: DuplicateResult = { success: true };
      await act(async () => {
        result = await current.duplicate();
      });

      expect(result).toEqual({ success: false });
      expect(apiFetch).not.toHaveBeenCalled();
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

    it('reports a safe failure and invalidates nothing when creation fails', async () => {
      jest
        .mocked(apiFetch)
        .mockRejectedValue(new Error('SQLSTATE[HY000] raw database error'));

      let result: DuplicateResult = { success: true };
      await act(async () => {
        result = await current.duplicate();
      });

      expect(result).toEqual({
        success: false,
        error: 'This template could not be duplicated.',
      });
      expect(coreDispatch.invalidateResolution).not.toHaveBeenCalled();
    });

    it('fails without a request when no saved record is loaded', async () => {
      mockCoreState.rawRecord = undefined;

      let result: DuplicateResult = { success: true };
      await act(async () => {
        result = await current.duplicate();
      });

      expect(result.success).toBe(false);
      expect(apiFetch).not.toHaveBeenCalled();
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
      expect(apiFetch).toHaveBeenCalledTimes(1);
      expect(apiFetch).toHaveBeenCalledWith({
        path: '/campaignbridge/v1/templates/42/revisions/7/restore',
        method: 'POST',
      });
      expect(coreDispatch.invalidateResolution).toHaveBeenCalledWith(
        'getEntityRecord',
        ['postType', 'cb_templates', 42]
      );
      expect(mockRefetch).toHaveBeenCalledWith('postType', 'cb_templates', 42);
      expect(coreDispatch.clearEntityRecordEdits).toHaveBeenCalledWith(
        'postType',
        'cb_templates',
        42
      );
      expect(
        coreDispatch.clearEntityRecordEdits.mock.invocationCallOrder[0]
      ).toBeGreaterThan(mockRefetch.mock.invocationCallOrder[0]);
    });

    it('refuses without a request or invalidation while core-data reports unsaved edits', async () => {
      mockCoreState.hasEdits = true;

      let result: RestoreResult = { success: true };
      await act(async () => {
        result = await current.restoreRevision(7);
      });

      expect(result).toEqual({ success: false, error: UNSAVED_RESTORE });
      expect(apiFetch).not.toHaveBeenCalled();
      expect(coreDispatch.invalidateResolution).not.toHaveBeenCalled();
      expect(coreDispatch.clearEntityRecordEdits).not.toHaveBeenCalled();
    });

    it('ignores a request while a save is running', async () => {
      mockCoreState.isSaving = true;

      let result: RestoreResult = { success: true };
      await act(async () => {
        result = await current.restoreRevision(7);
      });

      expect(result).toEqual({ success: false });
      expect(apiFetch).not.toHaveBeenCalled();
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

    it('returns the failure and leaves core-data untouched when the request fails', async () => {
      jest.mocked(apiFetch).mockRejectedValue(new Error('Not found'));

      let result: RestoreResult = { success: true };
      await act(async () => {
        result = await current.restoreRevision(7);
      });

      expect(result).toMatchObject({ success: false, error: 'Not found' });
      expect(coreDispatch.invalidateResolution).not.toHaveBeenCalled();
      expect(coreDispatch.clearEntityRecordEdits).not.toHaveBeenCalled();
    });

    it('keeps edits that exist once the restored record has loaded', async () => {
      jest.mocked(apiFetch).mockResolvedValue({ success: true } as any);
      mockRefetch.mockImplementation(async () => {
        mockCoreState.hasEdits = true;
        return mockRawRecord;
      });

      await act(async () => {
        await current.restoreRevision(7);
      });

      expect(coreDispatch.clearEntityRecordEdits).not.toHaveBeenCalled();
    });
  });

  describe('canonical operations and saving', () => {
    it('keeps duplicate and restore blocked when a save fails', async () => {
      mockCoreState.hasEdits = true;
      mockSave = setupEntityRecord({ hasEdits: true, edits: { content: 'x' } });
      mockSave.mockRejectedValue(new Error('Save failed'));
      act(() => root.render(<Harness />));

      await act(async () => {
        expect(await current.saveNow()).toBe(false);
      });

      let duplicated: DuplicateResult = { success: true };
      let restored: RestoreResult = { success: true };
      await act(async () => {
        duplicated = await current.duplicate();
        restored = await current.restoreRevision(7);
      });

      expect(duplicated).toEqual({ success: false, error: UNSAVED_DUPLICATE });
      expect(restored).toEqual({ success: false, error: UNSAVED_RESTORE });
      expect(apiFetch).not.toHaveBeenCalled();
    });

    it('allows duplicate and restore once a save leaves core-data clean', async () => {
      mockCoreState.hasEdits = true;
      mockSave = setupEntityRecord({ hasEdits: true, edits: { content: 'x' } });
      mockSave.mockImplementation(async () => {
        // core-data clears the edits the server persisted.
        mockCoreState.hasEdits = false;
      });
      act(() => root.render(<Harness />));
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

    it('does not treat an autosave as a canonical save', async () => {
      jest.useFakeTimers();
      try {
        mockCoreState.hasEdits = true;
        mockSave = setupEntityRecord({
          hasEdits: true,
          edits: { content: 'x' },
        });
        act(() => root.render(<Harness />));

        act(() => {
          jest.advanceTimersByTime(2000);
        });
        await act(async () => {
          await Promise.resolve();
        });
        expect(mockSave).toHaveBeenCalledWith({ isAutosave: true });

        // A published template's autosave leaves core-data dirty.
        let duplicated: DuplicateResult = { success: true };
        let restored: RestoreResult = { success: true };
        await act(async () => {
          duplicated = await current.duplicate();
          restored = await current.restoreRevision(7);
        });

        expect(duplicated).toEqual({
          success: false,
          error: UNSAVED_DUPLICATE,
        });
        expect(restored).toEqual({ success: false, error: UNSAVED_RESTORE });
        expect(apiFetch).not.toHaveBeenCalled();
      } finally {
        jest.useRealTimers();
      }
    });

    it('refuses Save and Publish while a duplicate is running', async () => {
      const request = deferred<{ id: number }>();
      jest.mocked(apiFetch).mockReturnValue(request.promise as any);
      mockSave = setupEntityRecord({ hasEdits: true, edits: { content: 'x' } });
      act(() => root.render(<Harness />));

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

  describe('autosave', () => {
    beforeEach(() => {
      jest.useFakeTimers();
    });

    afterEach(() => {
      jest.useRealTimers();
    });

    it('calls save with isAutosave: true after the debounce delay', async () => {
      mockSave = setupEntityRecord({
        edits: {
          content: '<!-- wp:paragraph --><p>Edited</p><!-- /wp:paragraph -->',
        },
        hasEdits: true,
      });
      act(() => root.render(<Harness />));

      act(() => {
        jest.advanceTimersByTime(2000);
      });
      await act(async () => {
        await Promise.resolve();
      });

      expect(mockSave).toHaveBeenCalledWith({ isAutosave: true });
    });

    it('does not autosave when there are no edits', async () => {
      act(() => root.render(<Harness />));

      act(() => {
        jest.advanceTimersByTime(5000);
      });

      expect(mockSave).not.toHaveBeenCalled();
    });

    it('does not autosave while a save is already in progress', async () => {
      mockSave = setupEntityRecord({
        edits: { content: 'edited' },
        hasEdits: true,
      });
      setupUseSelect({ isSaving: true });
      act(() => root.render(<Harness />));

      act(() => {
        jest.advanceTimersByTime(5000);
      });

      expect(mockSave).not.toHaveBeenCalled();
    });

    it('manual saveNow calls save without isAutosave', async () => {
      jest.useRealTimers();
      mockSave = setupEntityRecord({
        edits: { content: 'edited' },
        hasEdits: true,
      });
      act(() => root.render(<Harness />));

      await act(async () => {
        await current.saveNow();
      });

      expect(mockSave).toHaveBeenCalledTimes(1);
      expect(mockSave).toHaveBeenCalledWith();
    });

    it('publish calls save without isAutosave', async () => {
      jest.useRealTimers();
      mockSave = setupEntityRecord({
        edits: { content: 'edited' },
        hasEdits: true,
      });
      act(() => root.render(<Harness />));

      await act(async () => {
        await current.publish();
      });

      // Publish calls save() without isAutosave — a canonical save.
      const saveCall = mockSave.mock.calls[0];
      expect(saveCall[0] === undefined || saveCall[0] === null).toBe(true);
    });

    it('does not re-arm autosave when a save attempt finishes with unchanged edits', async () => {
      mockSave = setupEntityRecord({
        edits: { content: 'edited' },
        hasEdits: true,
      });
      act(() => root.render(<Harness />));
      act(() => {
        jest.advanceTimersByTime(2000);
      });
      expect(mockSave).toHaveBeenCalledTimes(1);

      // Published autosaves and failed autosaves both leave the entity dirty.
      setupUseSelect({ isAutosaving: true, isSaving: true });
      act(() => root.render(<Harness />));
      setupUseSelect();
      act(() => root.render(<Harness />));
      act(() => {
        jest.advanceTimersByTime(10000);
      });
      expect(mockSave).toHaveBeenCalledTimes(1);

      // A new block change schedules the next autosave.
      mockBlockState.blocks = [
        { name: 'core/paragraph', attrs: { content: 'x' }, innerBlocks: [] },
      ];
      act(() => root.render(<Harness />));
      act(() => {
        jest.advanceTimersByTime(2000);
      });
      expect(mockSave).toHaveBeenCalledTimes(2);
      expect(mockSave).toHaveBeenLastCalledWith({ isAutosave: true });
    });

    it('does not report manual-save success when an autosave completes', () => {
      const onSave = jest.fn();
      function SaveHarness() {
        current = useTemplateEditor({
          postId: 42,
          postType: 'cb_templates',
          onSave,
        });
        return null;
      }

      setupUseSelect({ isAutosaving: true, isSaving: true });
      act(() => root.render(<SaveHarness />));
      setupUseSelect();
      act(() => root.render(<SaveHarness />));
      expect(onSave).not.toHaveBeenCalled();

      setupUseSelect({ isSaving: true });
      act(() => root.render(<SaveHarness />));
      setupUseSelect();
      act(() => root.render(<SaveHarness />));
      expect(onSave).toHaveBeenCalledTimes(1);
    });

    it('reports a safe operator-facing message on autosave failure', async () => {
      mockSave = setupEntityRecord({
        edits: { content: 'edited' },
        hasEdits: true,
      });
      mockSave.mockRejectedValue(
        new Error('Internal Server Error: DB connection lost')
      );

      const onError = jest.fn();
      act(() => {
        root.render(<ErrorHarness onError={onError} />);
      });

      act(() => {
        jest.advanceTimersByTime(2000);
      });

      await act(async () => {
        await Promise.resolve();
      });

      // The error should use a safe message, not the raw server error text.
      const errorCalls = onError.mock.calls.map(call => call[0]);
      expect(errorCalls.some(msg => msg.includes('DB connection lost'))).toBe(
        false
      );
    });
  });
});

// Keep the coreStore import referenced for the mocked module contract.
void coreStore;
