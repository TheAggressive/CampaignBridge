/** @jest-environment jsdom */
import { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import apiFetch from '@wordpress/api-fetch';
import {
  useEmailPreview,
  type UseEmailPreview,
} from '../../src/scripts/editor/hooks/useEmailPreview';

let mockContent = 'first';
let mockTitle = 'First title';
jest.mock('@wordpress/api-fetch', () => ({
  __esModule: true,
  default: jest.fn(),
}));
jest.mock('@wordpress/blocks', () => ({ serialize: (value: string) => value }));
jest.mock('@wordpress/block-editor', () => ({ store: 'editor' }));
jest.mock('@wordpress/data', () => ({ useSelect: () => mockContent }));

let current: UseEmailPreview;
function Harness() {
  current = useEmailPreview(42, mockTitle);
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
    mockTitle = 'First title';
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
        data: {
          template_id: 42,
          content: 'edited',
          metadata: { title: 'First title' },
        },
      })
    );
  });

  it('includes the unsaved title and treats title changes as stale', async () => {
    jest.mocked(apiFetch).mockResolvedValue({
      html: '<title>First title</title>',
      diagnostics: [],
    });
    await act(async () => current.requestPreview());
    expect(current.isStale).toBe(false);

    mockTitle = 'Unsaved title';
    act(() => root.render(<Harness />));
    expect(current.isStale).toBe(true);

    await act(async () => current.requestPreview());
    expect(apiFetch).toHaveBeenLastCalledWith(
      expect.objectContaining({
        data: expect.objectContaining({
          metadata: { title: 'Unsaved title' },
        }),
      })
    );
    expect(current.isStale).toBe(false);
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

  it('keeps the canonical artifact separate from the sample view', async () => {
    jest.mocked(apiFetch).mockResolvedValue({
      html: '<p>Hi {{cb:subscriber.first_name}}</p>',
      sample: { html: '<p>Hi Alex</p>', text: 'Hi Alex' },
      diagnostics: [],
    });
    await act(async () => current.requestPreview());
    expect(current.preview.html).toBe('<p>Hi {{cb:subscriber.first_name}}</p>');
    expect(current.preview.sampleHtml).toBe('<p>Hi Alex</p>');

    jest.mocked(apiFetch).mockResolvedValue({
      html: '<p>Hi</p>',
      sample: null,
      diagnostics: [],
    });
    await act(async () => current.requestPreview());
    expect(current.preview.sampleHtml).toBeNull();
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
      .mockRejectedValue({ message: 'SQLSTATE[HY000] raw preview failure' });
    await act(async () => current.requestPreview());
    // Operators see fixed copy, never the server's message.
    expect(current.preview.error).toBe(
      'The email preview could not be generated. Please try again.'
    );
  });
});
