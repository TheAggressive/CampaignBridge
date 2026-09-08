/** @jest-environment jsdom */
import { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import apiFetch from '@wordpress/api-fetch';
import {
  useEmailPreview,
  type UseEmailPreview,
} from '../../src/scripts/editor/hooks/useEmailPreview';

let mockContent = 'first';
jest.mock('@wordpress/api-fetch', () => ({
  __esModule: true,
  default: jest.fn(),
}));
jest.mock('@wordpress/blocks', () => ({ serialize: (value: string) => value }));
jest.mock('@wordpress/block-editor', () => ({ store: 'editor' }));
jest.mock('@wordpress/data', () => ({ useSelect: () => mockContent }));

let current: UseEmailPreview;
function Harness() {
  current = useEmailPreview(42);
  return null;
}

describe('useEmailPreview', () => {
  let root: Root;
  let container: HTMLDivElement;
  beforeEach(() => {
    (
      globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }
    ).IS_REACT_ACT_ENVIRONMENT = true;
    mockContent = 'first';
    jest.mocked(apiFetch).mockReset();
    container = document.createElement('div');
    root = createRoot(container);
    act(() => root.render(<Harness />));
  });
  afterEach(() => act(() => root.unmount()));

  it('tracks the compiled content independently of save state', async () => {
    jest
      .mocked(apiFetch)
      .mockResolvedValue({ html: '<p>Hello</p>', diagnostics: [] });
    await act(async () => current.requestPreview());
    expect(current.isStale).toBe(false);
    expect(current.preview.compiledAt).toEqual(expect.any(Number));
    mockContent = 'edited';
    act(() => root.render(<Harness />));
    expect(current.isStale).toBe(true);
    await act(async () => current.requestPreview());
    expect(current.isStale).toBe(false);
    expect(apiFetch).toHaveBeenLastCalledWith(
      expect.objectContaining({
        data: { template_id: 42, content: 'edited', metadata: {} },
      })
    );
  });

  it('ignores an in-flight response after reset', async () => {
    let resolve!: (value: unknown) => void;
    jest.mocked(apiFetch).mockImplementation(
      () =>
        new Promise(done => {
          resolve = done;
        })
    );
    let request!: Promise<void>;
    act(() => {
      request = current.requestPreview();
    });
    act(() => current.resetPreview());
    await act(async () => {
      resolve({ html: 'old', diagnostics: [] });
      await request;
    });
    expect(current.preview.status).toBe('idle');
  });

  it('treats compiler diagnostics and REST errors as failures', async () => {
    jest.mocked(apiFetch).mockResolvedValue({
      html: '',
      diagnostics: [
        { severity: 'error', code: 'invalid', message: 'Invalid block' },
      ],
    });
    await act(async () => current.requestPreview());
    expect(current.preview.status).toBe('error');
    expect(current.preview.diagnostics.errors).toHaveLength(1);
    jest
      .mocked(apiFetch)
      .mockRejectedValue({ message: 'Cannot preview this template' });
    await act(async () => current.requestPreview());
    expect(current.preview.error).toBe('Cannot preview this template');
  });
});
