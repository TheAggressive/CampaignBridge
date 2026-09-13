/** @jest-environment jsdom */
import { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import apiFetch from '@wordpress/api-fetch';
import { store as coreStore } from '@wordpress/core-data';
import { dispatch } from '@wordpress/data';
import {
  useTemplateEditor,
  type UseTemplateEditor,
} from '../../src/scripts/editor/hooks/useTemplateEditor';

jest.mock('@wordpress/api-fetch', () => ({
  __esModule: true,
  default: jest.fn(),
}));

// core-data returns a stable blocks reference until the blocks change.
const mockBlockState = {
  blocks: [{ name: 'core/paragraph', attrs: {}, innerBlocks: [] }] as unknown[],
};

jest.mock('@wordpress/core-data', () => ({
  store: 'core/store',
  useEntityBlockEditor: () => [mockBlockState.blocks, jest.fn(), jest.fn()],
  useEntityRecord: jest.fn(),
}));

jest.mock('@wordpress/data', () => ({
  dispatch: jest.fn(),
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

function setupEntityRecord() {
  const { useEntityRecord } = require('@wordpress/core-data');
  const mockSave = jest.fn().mockResolvedValue(true);
  useEntityRecord.mockReturnValue({
    edits: {},
    hasEdits: false,
    hasStarted: true,
    isResolving: false,
    record: mockRecord,
    save: mockSave,
  });
  return mockSave;
}

function setupUseSelect() {
  const { useSelect } = require('@wordpress/data');
  useSelect.mockReturnValue({
    isSaving: false,
    loadError: null,
    saveError: null,
  });
}

let current: UseTemplateEditor;
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

  beforeEach(() => {
    (
      globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }
    ).IS_REACT_ACT_ENVIRONMENT = true;
    jest.mocked(apiFetch).mockReset();
    mockBlockState.blocks = [
      { name: 'core/paragraph', attrs: {}, innerBlocks: [] },
    ];
    mockSave = setupEntityRecord();
    setupUseSelect();
    jest.mocked(dispatch).mockReturnValue({
      editEntityRecord: jest.fn(),
      invalidateResolution: jest.fn(),
    } as any);
    container = document.createElement('div');
    root = createRoot(container);
    act(() => root.render(<Harness />));
  });

  afterEach(() => act(() => root.unmount()));

  describe('publish', () => {
    it('dispatches editEntityRecord with publish status then saves', async () => {
      const dispatchMock = jest.mocked(dispatch);
      const dispatchReturn = dispatchMock(coreStore as any) as any;

      await act(async () => {
        const result = await current.publish();
        expect(result).toBe(true);
      });

      expect(dispatchReturn.editEntityRecord).toHaveBeenCalledWith(
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
        const result = await current.publish();
        expect(result).toBe(false);
      });
    });

    it('returns false when already saving', async () => {
      const { useSelect } = require('@wordpress/data');
      useSelect.mockReturnValue({
        isSaving: true,
        loadError: null,
        saveError: null,
      });

      act(() => root.render(<Harness />));

      await act(async () => {
        const result = await current.publish();
        expect(result).toBe(false);
      });
      expect(mockSave).not.toHaveBeenCalled();
    });
  });

  describe('duplicate', () => {
    it('creates a copy via apiFetch and invalidates the template list resolver', async () => {
      jest.mocked(apiFetch).mockResolvedValue({ id: 99 } as any);

      const dispatchMock = jest.mocked(dispatch);
      const dispatchReturn = dispatchMock(coreStore as any) as any;

      let newId: number | null = null;
      await act(async () => {
        newId = await current.duplicate();
      });

      expect(newId).toBe(99);
      expect(apiFetch).toHaveBeenCalledWith({
        path: '/wp/v2/cb_templates',
        method: 'POST',
        data: {
          status: 'draft',
          content: mockRecord.content,
          title: 'My Template (Copy)',
        },
      });
      expect(dispatchReturn.invalidateResolution).toHaveBeenCalledWith(
        'getEntityRecords',
        ['postType', 'cb_templates', expect.objectContaining({ per_page: 100 })]
      );
    });

    it('returns null when apiFetch fails', async () => {
      jest.mocked(apiFetch).mockRejectedValue(new Error('Network error'));

      const dispatchMock = jest.mocked(dispatch);
      const dispatchReturn = dispatchMock(coreStore as any) as any;

      let newId: number | null = null;
      await act(async () => {
        newId = await current.duplicate();
      });

      expect(newId).toBeNull();
      expect(dispatchReturn.invalidateResolution).not.toHaveBeenCalled();
    });

    it('returns null when no record is loaded', async () => {
      const { useEntityRecord } = require('@wordpress/core-data');
      useEntityRecord.mockReturnValue({
        edits: {},
        hasEdits: false,
        hasStarted: true,
        isResolving: false,
        record: null,
        save: jest.fn(),
      });

      act(() => root.render(<Harness />));

      let newId: number | null = null;
      await act(async () => {
        newId = await current.duplicate();
      });

      expect(newId).toBeNull();
      expect(apiFetch).not.toHaveBeenCalled();
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
      const { useEntityRecord } = require('@wordpress/core-data');
      const mockSave = jest.fn().mockResolvedValue(undefined);
      useEntityRecord.mockReturnValue({
        edits: {
          content: '<!-- wp:paragraph --><p>Edited</p><!-- /wp:paragraph -->',
        },
        hasEdits: true,
        hasStarted: true,
        isResolving: false,
        record: mockRecord,
        save: mockSave,
      });

      act(() => root.render(<Harness />));

      // Advance past the 2000ms autosave debounce.
      act(() => {
        jest.advanceTimersByTime(2000);
      });

      // Allow the async autosave to resolve.
      await act(async () => {
        await Promise.resolve();
      });

      expect(mockSave).toHaveBeenCalledWith({ isAutosave: true });
    });

    it('does not autosave when there are no edits', async () => {
      const { useEntityRecord } = require('@wordpress/core-data');
      const mockSave = jest.fn().mockResolvedValue(undefined);
      useEntityRecord.mockReturnValue({
        edits: {},
        hasEdits: false,
        hasStarted: true,
        isResolving: false,
        record: mockRecord,
        save: mockSave,
      });

      act(() => root.render(<Harness />));

      act(() => {
        jest.advanceTimersByTime(5000);
      });

      expect(mockSave).not.toHaveBeenCalled();
    });

    it('does not autosave while a save is already in progress', async () => {
      const { useEntityRecord } = require('@wordpress/core-data');
      const mockSave = jest.fn().mockResolvedValue(undefined);
      useEntityRecord.mockReturnValue({
        edits: { content: 'edited' },
        hasEdits: true,
        hasStarted: true,
        isResolving: false,
        record: mockRecord,
        save: mockSave,
      });

      const { useSelect } = require('@wordpress/data');
      useSelect.mockReturnValue({
        isSaving: true,
        loadError: null,
        saveError: null,
      });

      act(() => root.render(<Harness />));

      act(() => {
        jest.advanceTimersByTime(5000);
      });

      expect(mockSave).not.toHaveBeenCalled();
    });

    it('manual saveNow calls save without isAutosave', async () => {
      jest.useRealTimers();
      const { useEntityRecord } = require('@wordpress/core-data');
      const mockSave = jest.fn().mockResolvedValue(undefined);
      useEntityRecord.mockReturnValue({
        edits: { content: 'edited' },
        hasEdits: true,
        hasStarted: true,
        isResolving: false,
        record: mockRecord,
        save: mockSave,
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
      const { useEntityRecord } = require('@wordpress/core-data');
      const mockSave = jest.fn().mockResolvedValue(undefined);
      useEntityRecord.mockReturnValue({
        edits: { content: 'edited' },
        hasEdits: true,
        hasStarted: true,
        isResolving: false,
        record: mockRecord,
        save: mockSave,
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
      const { useEntityRecord } = require('@wordpress/core-data');
      const { useSelect } = require('@wordpress/data');
      const mockSave = jest.fn().mockResolvedValue(undefined);
      useEntityRecord.mockReturnValue({
        edits: { content: 'edited' },
        hasEdits: true,
        hasStarted: true,
        isResolving: false,
        record: mockRecord,
        save: mockSave,
      });

      act(() => root.render(<Harness />));
      act(() => {
        jest.advanceTimersByTime(2000);
      });
      expect(mockSave).toHaveBeenCalledTimes(1);

      // Published autosaves and failed autosaves both leave the entity dirty.
      useSelect.mockReturnValue({
        isAutosaving: true,
        isSaving: true,
        loadError: null,
        saveError: null,
      });
      act(() => root.render(<Harness />));
      useSelect.mockReturnValue({
        isAutosaving: false,
        isSaving: false,
        loadError: null,
        saveError: null,
      });
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
      const { useSelect } = require('@wordpress/data');
      const onSave = jest.fn();
      function SaveHarness() {
        current = useTemplateEditor({
          postId: 42,
          postType: 'cb_templates',
          onSave,
        });
        return null;
      }

      useSelect.mockReturnValue({
        isAutosaving: true,
        isSaving: true,
        loadError: null,
        saveError: null,
      });
      act(() => root.render(<SaveHarness />));
      useSelect.mockReturnValue({
        isAutosaving: false,
        isSaving: false,
        loadError: null,
        saveError: null,
      });
      act(() => root.render(<SaveHarness />));
      expect(onSave).not.toHaveBeenCalled();

      useSelect.mockReturnValue({
        isAutosaving: false,
        isSaving: true,
        loadError: null,
        saveError: null,
      });
      act(() => root.render(<SaveHarness />));
      useSelect.mockReturnValue({
        isAutosaving: false,
        isSaving: false,
        loadError: null,
        saveError: null,
      });
      act(() => root.render(<SaveHarness />));
      expect(onSave).toHaveBeenCalledTimes(1);
    });

    it('reports a safe operator-facing message on autosave failure', async () => {
      const { useEntityRecord } = require('@wordpress/core-data');
      const mockSave = jest
        .fn()
        .mockRejectedValue(
          new Error('Internal Server Error: DB connection lost')
        );
      useEntityRecord.mockReturnValue({
        edits: { content: 'edited' },
        hasEdits: true,
        hasStarted: true,
        isResolving: false,
        record: mockRecord,
        save: mockSave,
      });

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

  describe('restoreRevision', () => {
    it('calls the restore endpoint and invalidates the entity record', async () => {
      jest.mocked(apiFetch).mockResolvedValue({} as any);

      const dispatchMock = jest.mocked(dispatch);
      const dispatchReturn = dispatchMock(coreStore as any) as any;

      let result: { success: boolean; error?: string } = { success: false };
      await act(async () => {
        result = await current.restoreRevision(7);
      });

      expect(result).toEqual({ success: true });
      expect(apiFetch).toHaveBeenCalledWith({
        path: '/campaignbridge/v1/templates/42/revisions/7/restore',
        method: 'POST',
      });
      expect(dispatchReturn.invalidateResolution).toHaveBeenCalledWith(
        'getEntityRecord',
        ['postType', 'cb_templates', 42]
      );
    });

    it('returns failure with error message when the restore request fails', async () => {
      jest.mocked(apiFetch).mockRejectedValue(new Error('Not found'));

      const dispatchMock = jest.mocked(dispatch);
      const dispatchReturn = dispatchMock(coreStore as any) as any;

      let result: { success: boolean; error?: string } = { success: true };
      await act(async () => {
        result = await current.restoreRevision(7);
      });

      expect(result).toMatchObject({ success: false, error: 'Not found' });
      expect(dispatchReturn.invalidateResolution).not.toHaveBeenCalled();
    });
  });
});
