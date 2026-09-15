/** @jest-environment jsdom */
import { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { useAutosaveRecovery } from '../../src/scripts/editor/hooks/useAutosaveRecovery';
import { isRecoverableAutosave } from '../../src/scripts/editor/utils/autosaveRecovery';

const canonical = {
  id: 42,
  status: 'publish',
  title: 'Saved title',
  content: 'Saved content',
  excerpt: '',
  modified_gmt: '2026-09-15T10:00:00',
  meta: { campaignbridge_subject: 'Saved subject', unrelated: 'keep' },
};
const autosave = {
  id: 43,
  parent: 42,
  author: 7,
  title: { raw: 'Recovered title' },
  content: { raw: 'Recovered content' },
  excerpt: { raw: '' },
  modified_gmt: '2026-09-15T10:00:03',
  meta: { campaignbridge_subject: 'Recovered subject' },
};
const mockCore = {
  getCurrentUser: jest.fn(),
  getAutosaves: jest.fn(),
  getAutosave: jest.fn(),
  getRawEntityRecord: jest.fn(),
  getResolutionError: jest.fn(),
  hasFetchedAutosaves: jest.fn(),
};
const mockActions = {
  clearEntityRecordEdits: jest.fn(),
  editEntityRecord: jest.fn(),
};
jest.mock('@wordpress/core-data', () => ({ store: 'core' }));
jest.mock('@wordpress/data', () => ({
  select: () => mockCore,
  resolveSelect: () => mockCore,
  dispatch: () => mockActions,
}));

let current: ReturnType<typeof useAutosaveRecovery>;
const mockCanRestore = jest.fn();
function Harness() {
  current = useAutosaveRecovery('cb_templates', 42, true, mockCanRestore);
  return null;
}

describe('native autosave recovery', () => {
  let root: Root;
  beforeEach(() => {
    (globalThis as any).IS_REACT_ACT_ENVIRONMENT = true;
    jest.clearAllMocks();
    mockCore.getCurrentUser.mockResolvedValue({ id: 7 });
    mockCore.getAutosaves.mockResolvedValue([autosave]);
    mockCore.getAutosave.mockReturnValue(autosave);
    mockCore.getRawEntityRecord.mockReturnValue(canonical);
    mockCore.getResolutionError.mockReturnValue(undefined);
    mockCore.hasFetchedAutosaves.mockReturnValue(true);
    mockCanRestore.mockReturnValue(true);
    root = createRoot(document.createElement('div'));
  });
  afterEach(() => act(() => root.unmount()));
  async function mount() {
    await act(async () => root.render(<Harness />));
  }

  it('resolves the current user and restores only editable fields as unsaved edits', async () => {
    await mount();
    expect(current.state).toBe('available');
    expect(mockCore.getAutosaves).toHaveBeenCalledWith('cb_templates', 42);
    expect(mockCore.getAutosave).toHaveBeenCalledWith('cb_templates', 42, 7);
    mockCore.getCurrentUser.mockReturnValue({ id: 7 });
    act(() => current.restore());
    expect(mockActions.clearEntityRecordEdits).toHaveBeenCalledWith(
      'postType',
      'cb_templates',
      42
    );
    expect(mockActions.editEntityRecord).toHaveBeenCalledWith(
      'postType',
      'cb_templates',
      42,
      {
        title: 'Recovered title',
        content: 'Recovered content',
        excerpt: '',
        meta: autosave.meta,
      }
    );
    expect(current.state).toBe('dismissed');
  });

  it('ignores recovery for this session without writing or clearing canonical edits', async () => {
    await mount();
    act(() => current.ignore());
    expect(current.blocksPersistence).toBe(false);
    expect(mockActions.editEntityRecord).not.toHaveBeenCalled();
    expect(mockActions.clearEntityRecordEdits).not.toHaveBeenCalled();
  });

  it('refuses recovery if an operation or newer editor edit prevents restoring', async () => {
    await mount();
    mockCanRestore.mockReturnValue(false);
    act(() => current.restore());
    expect(current.state).toBe('available');
    expect(mockActions.editEntityRecord).not.toHaveBeenCalled();
  });

  it.each(['user', 'autosaves', 'partial'])(
    'fails closed when %s data cannot be loaded',
    async failure => {
      if (failure === 'user')
        mockCore.getCurrentUser.mockResolvedValue(undefined);
      if (failure === 'autosaves')
        mockCore.getResolutionError.mockReturnValue(
          new Error('Private server error')
        );
      if (failure === 'partial')
        mockCore.getAutosave.mockReturnValue({
          ...autosave,
          content: { rendered: 'Unsafe' },
        });
      await mount();
      expect(current.state).toBe('error');
      expect(current.blocksPersistence).toBe(true);
      expect(mockActions.editEntityRecord).not.toHaveBeenCalled();
    }
  );

  it('ignores obsolete or identical recovery data', () => {
    expect(
      isRecoverableAutosave(
        canonical,
        { ...autosave, modified_gmt: canonical.modified_gmt },
        7
      )
    ).toBe(false);
    expect(
      isRecoverableAutosave(
        canonical,
        {
          ...autosave,
          title: canonical.title,
          content: canonical.content,
          meta: canonical.meta,
        },
        7
      )
    ).toBe(false);
  });

  it('does not recover another user’s autosave or an in-place draft autosave', () => {
    expect(isRecoverableAutosave(canonical, autosave, 8)).toBe(false);
    expect(
      isRecoverableAutosave(
        { ...canonical, status: 'draft' },
        { ...autosave, id: 42 },
        7
      )
    ).toBe(false);
  });

  it('recovers changes to revisioned meta alone', () => {
    expect(
      isRecoverableAutosave(
        canonical,
        { ...autosave, title: canonical.title, content: canonical.content },
        7
      )
    ).toBe(true);
  });
});
