/** @jest-environment jsdom */

import { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import apiFetch from '@wordpress/api-fetch';
import RevisionHistory from '../../src/scripts/editor/components/RevisionHistory';

jest.mock('@wordpress/api-fetch', () => ({
  __esModule: true,
  default: jest.fn(),
}));

jest.mock('@wordpress/i18n', () => ({
  __: (text: string) => text,
}));

jest.mock('@wordpress/icons', () => ({
  time: 'time-icon',
}));

jest.mock('@wordpress/components', () => ({
  Button: ({ children, className, disabled, onClick, text }: any) => (
    <button className={className} disabled={disabled} onClick={onClick}>
      {children ?? text}
    </button>
  ),
  Modal: ({ children, headerActions }: any) => (
    <div data-testid='modal'>
      {headerActions}
      {children}
    </div>
  ),
  Notice: ({ children, className }: any) => (
    <div className={className}>{children}</div>
  ),
  Spinner: () => <span />,
}));

const REVISIONS = [
  {
    id: 12,
    date: '2026-09-13T11:00:00',
    date_gmt: '2026-09-13T11:00:00',
    author: 1,
    parent: 5,
    slug: '5-autosave-v1',
  },
  {
    id: 11,
    date: '2026-09-13T10:00:00',
    date_gmt: '2026-09-13T10:00:00',
    author: 1,
    parent: 5,
    slug: '5-revision-v1',
  },
];

const UNSAVED_NOTICE =
  'You have unsaved changes. Save them before restoring a revision.';

function deferred<T>() {
  let resolve!: (value: T) => void;
  const promise = new Promise<T>(settle => {
    resolve = settle;
  });
  return { promise, resolve };
}

describe('RevisionHistory', () => {
  let container: HTMLDivElement;
  let root: Root;

  beforeEach(() => {
    (globalThis as any).IS_REACT_ACT_ENVIRONMENT = true;
    jest
      .mocked(apiFetch)
      .mockReset()
      .mockResolvedValue(REVISIONS as any);
    container = document.createElement('div');
    document.body.appendChild(container);
    root = createRoot(container);
  });

  afterEach(() => {
    act(() => root.unmount());
    container.remove();
  });

  async function render(props: {
    onRestore: jest.Mock;
    onRequestClose?: jest.Mock;
    hasEdits?: boolean;
  }) {
    await act(async () => {
      root.render(
        <RevisionHistory
          postId={5}
          postType='cb_templates'
          isOpen
          onRequestClose={props.onRequestClose ?? jest.fn()}
          onRestore={props.onRestore}
          hasEdits={props.hasEdits}
        />
      );
    });
  }

  async function confirmRestore() {
    const restore = container.querySelector(
      '.cb-editor__revision-item button'
    ) as HTMLButtonElement;
    await act(async () => restore.click());
    return container.querySelector(
      '.cb-editor__revision-restore-confirm'
    ) as HTMLButtonElement;
  }

  it('lists normal revisions while dirty and explains why restore is unavailable', async () => {
    await render({ onRestore: jest.fn(), hasEdits: true });

    expect(container.textContent).toContain(UNSAVED_NOTICE);
    // The autosave is hidden; the saved revision is still inspectable.
    expect(
      container.querySelectorAll('.cb-editor__revision-item')
    ).toHaveLength(1);
  });

  it('does not show the unsaved notice for a clean template', async () => {
    await render({ onRestore: jest.fn() });

    expect(container.textContent).not.toContain(UNSAVED_NOTICE);
  });

  it('shows a refused restore as an alert and stays open', async () => {
    const onRestore = jest.fn().mockResolvedValue({
      success: false,
      error: 'Save your changes before restoring a revision.',
    });
    const onRequestClose = jest.fn();
    await render({ onRestore, onRequestClose, hasEdits: true });

    const confirm = await confirmRestore();
    await act(async () => confirm.click());

    expect(onRestore).toHaveBeenCalledWith(11);
    expect(container.querySelector('[role="alert"]')?.textContent).toBe(
      'Save your changes before restoring a revision.'
    );
    expect(onRequestClose).not.toHaveBeenCalled();
  });

  it('closes after a successful restore', async () => {
    const onRestore = jest.fn().mockResolvedValue({ success: true });
    const onRequestClose = jest.fn();
    await render({ onRestore, onRequestClose });

    const confirm = await confirmRestore();
    await act(async () => confirm.click());

    expect(onRequestClose).toHaveBeenCalledTimes(1);
    expect(container.querySelector('[role="alert"]')).toBeNull();
  });

  it('shows nothing for an ignored request instead of a false failure', async () => {
    const onRestore = jest.fn().mockResolvedValue({ success: false });
    const onRequestClose = jest.fn();
    await render({ onRestore, onRequestClose });

    const confirm = await confirmRestore();
    await act(async () => confirm.click());

    expect(container.querySelector('[role="alert"]')).toBeNull();
    expect(onRequestClose).not.toHaveBeenCalled();
  });

  it('starts one restore when confirm is clicked twice', async () => {
    const pending = deferred<{ success: boolean }>();
    const onRestore = jest.fn().mockReturnValue(pending.promise);
    await render({ onRestore });

    const confirm = await confirmRestore();
    await act(async () => {
      confirm.click();
      confirm.click();
    });
    await act(async () => pending.resolve({ success: true }));

    expect(onRestore).toHaveBeenCalledTimes(1);
  });

  it('shows a safe load failure and recovers when Refresh succeeds', async () => {
    jest
      .mocked(apiFetch)
      .mockReset()
      .mockRejectedValueOnce({ message: 'SQLSTATE[HY000] raw history failure' })
      .mockResolvedValueOnce(REVISIONS as any);
    await render({ onRestore: jest.fn() });

    const alert = container.querySelector('[role="alert"]');
    expect(alert?.textContent).toBe(
      'Revision history could not be loaded. Please try again.'
    );
    expect(container.textContent).not.toContain('SQLSTATE');
    expect(
      container.querySelectorAll('.cb-editor__revision-item')
    ).toHaveLength(0);

    const refresh = container.querySelector(
      '.cb-editor__revision-header-action'
    ) as HTMLButtonElement;
    expect(refresh.disabled).toBe(false);
    await act(async () => refresh.click());

    expect(container.querySelector('[role="alert"]')).toBeNull();
    expect(
      container.querySelectorAll('.cb-editor__revision-item')
    ).toHaveLength(1);
  });

  it('shows safe copy and clears the busy state when a restore rejects', async () => {
    const onRestore = jest
      .fn()
      .mockRejectedValue(new Error('SQLSTATE[HY000] raw restore failure'));
    const onRequestClose = jest.fn();
    await render({ onRestore, onRequestClose });

    const confirm = await confirmRestore();
    await act(async () => confirm.click());

    expect(container.querySelector('[role="alert"]')?.textContent).toBe(
      'This revision could not be restored. Please try again.'
    );
    expect(container.textContent).not.toContain('SQLSTATE');
    expect(onRequestClose).not.toHaveBeenCalled();
    // The list is usable again for a retry.
    const restore = container.querySelector(
      '.cb-editor__revision-item button'
    ) as HTMLButtonElement;
    expect(restore.disabled).toBe(false);
  });
});
