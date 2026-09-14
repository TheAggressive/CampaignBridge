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
  sprintf: (format: string, ...args: unknown[]) => {
    let index = 0;
    return format.replace(/%(\d\$)?[sd]/g, (_match, position?: string) =>
      String(position ? args[Number(position[0]) - 1] : args[index++])
    );
  },
}));

jest.mock('@wordpress/icons', () => ({
  time: 'time-icon',
}));

jest.mock('@wordpress/components', () => ({
  Button: ({
    children,
    className,
    disabled,
    onClick,
    text,
    'aria-label': ariaLabel,
  }: any) => (
    <button
      aria-label={ariaLabel}
      className={className}
      disabled={disabled}
      onClick={onClick}
    >
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

interface TestRevision {
  id: number;
  date: string;
  date_gmt: string;
  author: number;
  parent: number;
  slug: string;
}

function revision(id: number, parent = 5): TestRevision {
  return {
    id,
    date: '2026-09-13T10:00:00',
    date_gmt: '2026-09-13T10:00:00',
    author: 1,
    parent,
    slug: `${parent}-revision-v1`,
  };
}

/** Newest-first revision IDs, split into pages of `perPage`. */
function pagesOf(ids: number[], perPage = 20): TestRevision[][] {
  const pages: TestRevision[][] = [];
  for (let start = 0; start < ids.length; start += perPage) {
    pages.push(ids.slice(start, start + perPage).map(id => revision(id)));
  }
  return pages;
}

function range(from: number, to: number): number[] {
  const ids: number[] = [];
  for (let id = from; id >= to; id--) {
    ids.push(id);
  }
  return ids;
}

function pageResponse(items: unknown[], total: number, totalPages: number) {
  const headers: Record<string, string> = {
    'X-WP-Total': String(total),
    'X-WP-TotalPages': String(totalPages),
  };
  return {
    json: async () => items,
    headers: { get: (name: string) => headers[name] ?? null },
  };
}

function requestUrl(options: any): URL {
  return new URL(options.path, 'https://example.test');
}

interface HistoryServer {
  autosaves?: number[];
  pages: TestRevision[][];
  total?: number;
}

/** Answer autosave and revision page requests like core's REST routes. */
function serve(history: HistoryServer) {
  jest.mocked(apiFetch).mockImplementation((options: any) => {
    const url = requestUrl(options);
    if (url.pathname.endsWith('/autosaves')) {
      return Promise.resolve(
        (history.autosaves ?? []).map(id => ({ id }))
      ) as any;
    }
    const page = Number(url.searchParams.get('page'));
    const total =
      history.total ??
      history.pages.reduce((sum, items) => sum + items.length, 0);
    return Promise.resolve(
      pageResponse(history.pages[page - 1] ?? [], total, history.pages.length)
    ) as any;
  });
}

function revisionRequests(): URL[] {
  return jest
    .mocked(apiFetch)
    .mock.calls.map(([options]) => requestUrl(options))
    .filter(url => url.pathname.endsWith('/revisions'));
}

function deferred<T>() {
  let resolve!: (value: T) => void;
  let reject!: (reason?: unknown) => void;
  const promise = new Promise<T>((settle, fail) => {
    resolve = settle;
    reject = fail;
  });
  return { promise, resolve, reject };
}

const UNSAVED_NOTICE =
  'You have unsaved changes. Save them before restoring a revision.';
const LOAD_FAILED = 'Revision history could not be loaded. Please try again.';
const LOAD_MORE_FAILED =
  'More revisions could not be loaded. Please try again.';

describe('RevisionHistory', () => {
  let container: HTMLDivElement;
  let root: Root;

  beforeEach(() => {
    (globalThis as any).IS_REACT_ACT_ENVIRONMENT = true;
    jest.mocked(apiFetch).mockReset();
    serve({ pages: pagesOf([11, 10]) });
    container = document.createElement('div');
    document.body.appendChild(container);
    root = createRoot(container);
  });

  afterEach(() => {
    act(() => root.unmount());
    container.remove();
  });

  async function render(
    props: {
      onRestore?: jest.Mock;
      onRequestClose?: jest.Mock;
      hasEdits?: boolean;
      isOpen?: boolean;
    } = {}
  ) {
    await act(async () => {
      root.render(
        <RevisionHistory
          postId={5}
          postType='cb_templates'
          isOpen={props.isOpen ?? true}
          onRequestClose={props.onRequestClose ?? jest.fn()}
          onRestore={props.onRestore ?? jest.fn()}
          hasEdits={props.hasEdits}
        />
      );
    });
  }

  function listedIds(): number[] {
    return Array.from(
      container.querySelectorAll<HTMLElement>('.cb-editor__revision-item')
    ).map(item => Number(item.dataset.revisionId));
  }

  function loadMoreButton(): HTMLButtonElement | null {
    return container.querySelector('.cb-editor__revision-load-more');
  }

  function refreshButton(): HTMLButtonElement {
    return container.querySelector(
      '.cb-editor__revision-header-action'
    ) as HTMLButtonElement;
  }

  function statusText(): string | undefined {
    return container.querySelector('.cb-editor__revision-status')?.textContent;
  }

  function alertText(): string | undefined {
    return container.querySelector('[role="alert"]')?.textContent ?? undefined;
  }

  async function click(button: HTMLButtonElement | null) {
    await act(async () => button?.click());
  }

  async function confirmRestore(index = 0) {
    const restore = container.querySelectorAll<HTMLButtonElement>(
      '.cb-editor__revision-item button'
    )[index];
    await click(restore);
    return container.querySelector(
      '.cb-editor__revision-restore-confirm'
    ) as HTMLButtonElement;
  }

  describe('pagination', () => {
    it('loads the first page newest first without Load more when it is the only page', async () => {
      await render();

      expect(listedIds()).toEqual([11, 10]);
      expect(loadMoreButton()).toBeNull();
      expect(statusText()).toBe('Showing 2 of 2 revisions.');

      const [request] = revisionRequests();
      expect(request.searchParams.get('page')).toBe('1');
      expect(request.searchParams.get('per_page')).toBe('20');
      expect(request.searchParams.get('order')).toBe('desc');
      expect(request.searchParams.get('orderby')).toBe('date');
    });

    it('offers Load more only while the server reports more pages', async () => {
      serve({ pages: pagesOf(range(45, 1)) });
      await render();

      expect(listedIds()).toEqual(range(45, 26));
      expect(statusText()).toBe('Showing 20 of 45 revisions.');
      expect(loadMoreButton()).not.toBeNull();

      await click(loadMoreButton());
      expect(listedIds()).toEqual(range(45, 6));
      expect(revisionRequests().at(-1)?.searchParams.get('page')).toBe('2');
      expect(loadMoreButton()).not.toBeNull();

      await click(loadMoreButton());
      expect(listedIds()).toEqual(range(45, 1));
      expect(revisionRequests().at(-1)?.searchParams.get('page')).toBe('3');
      expect(statusText()).toBe('Showing 45 of 45 revisions.');
      expect(loadMoreButton()).toBeNull();
    });

    it('trusts the pagination headers rather than the page length', async () => {
      // A full page, but the server reports it is the only one.
      serve({ pages: pagesOf(range(20, 1)) });
      await render();

      expect(listedIds()).toHaveLength(20);
      expect(loadMoreButton()).toBeNull();
    });

    it('never lists a revision twice when a new save shifts later pages', async () => {
      const pageOne = pagesOf(range(30, 11))[0];
      // A revision saved after page 1 loaded pushes 11 onto page 2.
      const shiftedPageTwo = [11, 10, 9, 8].map(id => revision(id));
      serve({ pages: [pageOne, shiftedPageTwo], total: 24 });
      await render();

      await click(loadMoreButton());

      expect(listedIds()).toEqual([...range(30, 11), 10, 9, 8]);
      expect(new Set(listedIds()).size).toBe(listedIds().length);
    });

    it('sends one page request when Load more is clicked twice', async () => {
      serve({ pages: pagesOf(range(25, 1)) });
      await render();
      const pageTwo = deferred<unknown>();
      jest.mocked(apiFetch).mockReturnValueOnce(pageTwo.promise as any);

      await act(async () => {
        loadMoreButton()?.click();
        loadMoreButton()?.click();
      });
      expect(loadMoreButton()?.disabled).toBe(true);
      expect(statusText()).toBe('Loading more revisions…');
      // The loaded page stays visible while the next one loads.
      expect(listedIds()).toEqual(range(25, 6));

      await act(async () =>
        pageTwo.resolve(pageResponse(pagesOf(range(25, 1))[1], 25, 2))
      );

      expect(
        revisionRequests().filter(url => url.searchParams.get('page') === '2')
      ).toHaveLength(1);
      expect(listedIds()).toEqual(range(25, 1));
    });

    it('moves focus to the first revision a Load more request added', async () => {
      serve({ pages: pagesOf(range(25, 1)) });
      await render();

      await click(loadMoreButton());

      const firstAdded = container.querySelectorAll(
        '.cb-editor__revision-item'
      )[20];
      expect(document.activeElement).toBe(firstAdded.querySelector('button'));
    });

    it('keeps loaded revisions and Load more when the next page fails', async () => {
      serve({ pages: pagesOf(range(25, 1)) });
      await render();
      jest
        .mocked(apiFetch)
        .mockRejectedValueOnce({ message: 'SQLSTATE[HY000] raw page failure' });

      await click(loadMoreButton());

      expect(alertText()).toBe(LOAD_MORE_FAILED);
      expect(container.textContent).not.toContain('SQLSTATE');
      expect(listedIds()).toEqual(range(25, 6));
      expect(loadMoreButton()?.disabled).toBe(false);

      await click(loadMoreButton());
      expect(alertText()).toBeUndefined();
      expect(listedIds()).toEqual(range(25, 1));
    });
  });

  describe('refresh', () => {
    it('reloads page 1 from the server and resets pagination', async () => {
      serve({ pages: pagesOf(range(25, 1)) });
      await render();
      await click(loadMoreButton());
      expect(listedIds()).toHaveLength(25);

      // A new canonical save and a removed revision since the last load.
      serve({ pages: pagesOf([26, ...range(25, 2)]) });
      await click(refreshButton());

      expect(listedIds()).toEqual([26, ...range(25, 7)]);
      expect(revisionRequests().at(-1)?.searchParams.get('page')).toBe('1');
      expect(statusText()).toBe('Showing 20 of 25 revisions.');
      expect(loadMoreButton()).not.toBeNull();

      await click(loadMoreButton());
      expect(listedIds()).toEqual([26, ...range(25, 2)]);
      expect(loadMoreButton()).toBeNull();
    });

    it('keeps loaded history and stays retryable when refresh fails', async () => {
      serve({ pages: pagesOf(range(25, 1)) });
      await render();
      await click(loadMoreButton());
      jest.mocked(apiFetch).mockRejectedValueOnce({
        message: 'SQLSTATE[HY000] raw history failure',
      });

      await click(refreshButton());

      expect(alertText()).toBe(LOAD_FAILED);
      expect(container.textContent).not.toContain('SQLSTATE');
      expect(listedIds()).toEqual(range(25, 1));
      expect(refreshButton().disabled).toBe(false);

      await click(refreshButton());
      expect(alertText()).toBeUndefined();
      expect(listedIds()).toEqual(range(25, 6));
    });

    it('shows a safe load failure when nothing has loaded and recovers on Refresh', async () => {
      jest.mocked(apiFetch).mockReset().mockRejectedValueOnce({
        message: 'SQLSTATE[HY000] raw history failure',
      });
      await render();

      expect(alertText()).toBe(LOAD_FAILED);
      expect(container.textContent).not.toContain('SQLSTATE');
      expect(listedIds()).toEqual([]);

      serve({ pages: pagesOf([11, 10]) });
      await click(refreshButton());

      expect(alertText()).toBeUndefined();
      expect(listedIds()).toEqual([11, 10]);
    });

    it('discards a page request that finishes after Refresh', async () => {
      serve({ pages: pagesOf(range(25, 1)) });
      await render();
      const stalePage = deferred<unknown>();
      jest.mocked(apiFetch).mockReturnValueOnce(stalePage.promise as any);
      await click(loadMoreButton());

      serve({ pages: pagesOf([26, ...range(25, 1)]) });
      await click(refreshButton());
      await act(async () =>
        stalePage.resolve(pageResponse(pagesOf(range(25, 1))[1], 25, 2))
      );

      expect(listedIds()).toEqual([26, ...range(25, 7)]);
    });

    it('starts from page 1 again when the modal is reopened', async () => {
      serve({ pages: pagesOf(range(25, 1)) });
      await render();
      await click(loadMoreButton());

      await render({ isOpen: false });
      serve({ pages: pagesOf([26, ...range(25, 1)]) });
      await render({ isOpen: true });

      expect(listedIds()).toEqual([26, ...range(25, 7)]);
    });
  });

  describe('autosaves', () => {
    it('excludes autosaves in the revisions request so pagination counts only revisions', async () => {
      serve({ autosaves: [99, 98], pages: pagesOf([11, 10]) });
      await render();

      const [request] = revisionRequests();
      expect(request.searchParams.get('exclude')).toBe('99,98');
      expect(listedIds()).toEqual([11, 10]);
    });

    it('never lists an autosave the request did not exclude', async () => {
      serve({
        pages: [
          [
            { ...revision(12), slug: '5-autosave-v1' },
            revision(11),
            revision(10),
          ],
        ],
      });
      await render();

      expect(listedIds()).toEqual([11, 10]);
    });
  });

  describe('restore', () => {
    it('lists revisions while dirty and explains why restore is unavailable', async () => {
      await render({ hasEdits: true });

      expect(container.textContent).toContain(UNSAVED_NOTICE);
      expect(listedIds()).toEqual([11, 10]);
    });

    it('does not show the unsaved notice for a clean template', async () => {
      await render();

      expect(container.textContent).not.toContain(UNSAVED_NOTICE);
    });

    it('names each restore action by its revision date', async () => {
      await render();

      const restore = container.querySelector(
        '.cb-editor__revision-item button'
      ) as HTMLButtonElement;
      expect(restore.getAttribute('aria-label')).toMatch(
        /^Restore revision from .+/
      );
      expect(restore.textContent).toBe('Restore');
    });

    it('shows a refused restore as an alert and stays open', async () => {
      const onRestore = jest.fn().mockResolvedValue({
        success: false,
        error: 'Save your changes before restoring a revision.',
      });
      const onRequestClose = jest.fn();
      await render({ onRestore, onRequestClose, hasEdits: true });

      await click(await confirmRestore());

      expect(onRestore).toHaveBeenCalledWith(11);
      expect(alertText()).toBe(
        'Save your changes before restoring a revision.'
      );
      expect(onRequestClose).not.toHaveBeenCalled();
    });

    it('restores a revision from a later page', async () => {
      serve({ pages: pagesOf(range(25, 1)) });
      const onRestore = jest.fn().mockResolvedValue({ success: true });
      const onRequestClose = jest.fn();
      await render({ onRestore, onRequestClose });
      await click(loadMoreButton());

      await click(await confirmRestore(24));

      expect(onRestore).toHaveBeenCalledWith(1);
      expect(onRequestClose).toHaveBeenCalledTimes(1);
    });

    it('shows nothing for an ignored request instead of a false failure', async () => {
      const onRestore = jest.fn().mockResolvedValue({ success: false });
      const onRequestClose = jest.fn();
      await render({ onRestore, onRequestClose });

      await click(await confirmRestore());

      expect(alertText()).toBeUndefined();
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

    it('shows safe copy and clears the busy state when a restore rejects', async () => {
      const onRestore = jest
        .fn()
        .mockRejectedValue(new Error('SQLSTATE[HY000] raw restore failure'));
      const onRequestClose = jest.fn();
      await render({ onRestore, onRequestClose });

      await click(await confirmRestore());

      expect(alertText()).toBe(
        'This revision could not be restored. Please try again.'
      );
      expect(container.textContent).not.toContain('SQLSTATE');
      expect(onRequestClose).not.toHaveBeenCalled();
      const restore = container.querySelector(
        '.cb-editor__revision-item button'
      ) as HTMLButtonElement;
      expect(restore.disabled).toBe(false);
    });
  });
});
