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
import { getRecoveryEdits } from '../../src/scripts/editor/utils/autosaveRecovery';

jest.mock('../../src/scripts/editor/hooks/useAutosaveRecovery', () => ({
  useAutosaveRecovery: () => ({ blocksPersistence: false, state: 'dismissed' }),
}));

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
  autosaveFailed: 'Autosave failed. Your changes are still in the editor.',
  duplicateFailed: 'This template could not be duplicated.',
  duplicateUnsaved: 'Save your changes before duplicating this template.',
  restoreFailed: 'This revision could not be restored. Please try again.',
  restoreUnsaved: 'Save your changes before restoring a revision.',
};

// Native single-revision REST payload (title/content as { raw, rendered }).
const REVISION_7 = {
  id: 7,
  title: { raw: 'My Template', rendered: 'My Template' },
  content: {
    raw: '<!-- wp:paragraph --><p>Revision seven</p><!-- /wp:paragraph -->',
    rendered: '<p>Revision seven</p>',
  },
  meta: { campaignbridge_subject: 'Revised subject' },
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
    isAutosaving: false,
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
    revisionedMetaKeys: [
      'campaignbridge_subject',
      'campaignbridge_utm_enabled',
    ],
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

  it('keeps canonical Save dirty during a native background autosave', () => {
    setupEntityRecord({ hasEdits: true });
    setupUseSelect({ isSaving: true, isAutosaving: true });
    render();
    expect(current.saveStatus).toBe('dirty');
    expect(current.isAutosaving).toBe(true);
    expect(current.isPersisting).toBe(true);
    expect(mockOnSuccess).not.toHaveBeenCalled();
  });

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
    it('loads the native single revision as unsaved edits without saving', async () => {
      jest
        .mocked(apiFetch)
        .mockResolvedValueOnce(REVISION_7)
        .mockResolvedValueOnce(mockRawRecord);

      let result: RestoreResult | null = null;
      await act(async () => {
        result = await current.restoreRevision(7);
      });

      expect(result).toEqual({ success: true });
      // Restore reads the native revision and the current canonical record
      // before applying unsaved edits.
      expect(apiFetch).toHaveBeenCalledTimes(2);
      expect(apiFetch.mock.calls[0][0]).toEqual({
        path: '/wp/v2/cb_templates/42/revisions/7?context=edit',
      });
      expect(apiFetch.mock.calls[1][0]).toEqual({
        path: '/wp/v2/cb_templates/42?context=edit',
      });

      // The payload passes through the recovery validator and lands in
      // core-data as unsaved edits: clear transient edits, then write.
      const expectedEdits = getRecoveryEdits(REVISION_7, mockRawRecord.meta, [
        'campaignbridge_subject',
        'campaignbridge_utm_enabled',
      ]);
      expect(coreDispatch.clearEntityRecordEdits).toHaveBeenCalledTimes(1);
      expect(coreDispatch.editEntityRecord).toHaveBeenCalledTimes(1);
      expect(coreDispatch.editEntityRecord).toHaveBeenCalledWith(
        'postType',
        'cb_templates',
        42,
        expectedEdits
      );
      expect(
        coreDispatch.clearEntityRecordEdits.mock.invocationCallOrder[0]
      ).toBeLessThan(coreDispatch.editEntityRecord.mock.invocationCallOrder[0]);

      // Restore never saves; canonical content changes only on explicit save.
      expect(mockSave).not.toHaveBeenCalled();
      expect(current.isOperationPending).toBe(false);
    });

    it('keeps newer edits when they arrive during a revision fetch', async () => {
      const request = deferred<unknown>();
      jest.mocked(apiFetch).mockReturnValue(request.promise as any);
      let pending: Promise<RestoreResult>;
      await act(async () => {
        pending = current.restoreRevision(7);
      });
      mockCoreState.hasEdits = true;
      await act(async () => {
        request.resolve(REVISION_7);
      });
      expect(await pending!).toEqual({
        success: false,
        error: MESSAGES.restoreUnsaved,
      });
      expect(coreDispatch.clearEntityRecordEdits).not.toHaveBeenCalled();
      expect(coreDispatch.editEntityRecord).not.toHaveBeenCalled();
      expect(mockSave).not.toHaveBeenCalled();
    });

    it('persists the restored content only after an explicit save', async () => {
      jest
        .mocked(apiFetch)
        .mockResolvedValueOnce(REVISION_7)
        .mockResolvedValueOnce(mockRawRecord);

      let result: RestoreResult | null = null;
      await act(async () => {
        result = await current.restoreRevision(7);
      });
      expect(result).toEqual({ success: true });
      expect(mockSave).not.toHaveBeenCalled();

      // The editor now holds the restored content as unsaved edits.
      mockCoreState.hasEdits = true;
      mockSave = setupEntityRecord({
        hasEdits: true,
        edits: { content: 'Revision seven' },
      });
      render();

      let saved: boolean | null = null;
      await act(async () => {
        saved = await current.saveNow();
      });

      expect(saved).toBe(true);
      expect(mockSave).toHaveBeenCalledTimes(1);
      expect(mockOnSuccess).toHaveBeenCalledWith(MESSAGES.saved);
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

    it('returns a safe error and leaves core-data untouched when the revision request fails', async () => {
      jest.mocked(apiFetch).mockRejectedValue({
        code: 'rest_invalid_param',
        message: RAW_SERVER_ERROR,
      });

      let result: RestoreResult | null = null;
      await act(async () => {
        result = await current.restoreRevision(7);
      });

      expect(result).toEqual({ success: false, error: MESSAGES.restoreFailed });
      expect(JSON.stringify(result)).not.toContain('SQLSTATE');
      expect(shownMessages().join(' ')).not.toContain(RAW_SERVER_ERROR);
      expect(coreDispatch.clearEntityRecordEdits).not.toHaveBeenCalled();
      expect(coreDispatch.editEntityRecord).not.toHaveBeenCalled();
      expect(mockSave).not.toHaveBeenCalled();
      expect(current.isOperationPending).toBe(false);
    });

    it('refuses an incomplete revision payload before touching editor state', async () => {
      jest.mocked(apiFetch).mockResolvedValue({ id: 7, title: 'My Template' });

      let result: RestoreResult | null = null;
      await act(async () => {
        result = await current.restoreRevision(7);
      });

      // getRecoveryEdits rejects partial payloads before any edit is written.
      expect(result).toEqual({ success: false, error: MESSAGES.restoreFailed });
      expect(coreDispatch.clearEntityRecordEdits).not.toHaveBeenCalled();
      expect(coreDispatch.editEntityRecord).not.toHaveBeenCalled();
    });

    it('sends one revision request when called twice at once', async () => {
      const request = deferred<typeof REVISION_7>();
      jest
        .mocked(apiFetch)
        .mockReturnValueOnce(request.promise as any)
        .mockResolvedValueOnce(mockRawRecord);

      let first: Promise<RestoreResult> = Promise.resolve({ success: false });
      let second: RestoreResult = { success: true };
      await act(async () => {
        first = current.restoreRevision(7);
        second = await current.restoreRevision(7);
        request.resolve(REVISION_7);
      });

      // The second call is ignored while the first restore owns the operation.
      expect(second).toEqual({ success: false });
      expect(await first).toEqual({ success: true });
      expect(apiFetch).toHaveBeenCalledTimes(2);
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
      // Duplicate returns a new record; restore returns the native revision payload.
      jest
        .mocked(apiFetch)
        .mockResolvedValueOnce({ id: 99 } as any)
        .mockResolvedValue(REVISION_7);

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
