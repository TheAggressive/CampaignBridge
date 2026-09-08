/** @jest-environment jsdom */

import { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import type { EmailPreview } from '../../src/scripts/editor/hooks/useEmailPreview';
import EmailPreviewModal from '../../src/scripts/editor/components/EmailPreviewModal';

const makePreview = (overrides: Partial<EmailPreview> = {}): EmailPreview => ({
  status: 'success',
  html: '<div>hello</div>',
  diagnostics: { errors: [], warnings: [] },
  error: null,
  ...overrides,
});

// Minimal stand-ins for the heavy @wordpress/components so we can assert on
// our own markup without pulling in the real component tree.
jest.mock('@wordpress/components', () => ({
  Button: ({
    children,
    className,
    disabled,
    icon,
    isBusy,
    label,
    onClick,
    text,
    variant,
  }) => (
    <button
      className={className}
      disabled={disabled}
      aria-busy={isBusy || undefined}
      aria-label={label || undefined}
      data-icon={icon ? 'icon' : undefined}
      data-variant={variant || undefined}
      onClick={onClick}
      type='button'
    >
      {text || children || label || ''}
    </button>
  ),
  Modal: ({ children, className, headerActions, title }) => (
    <div className={className || 'wp-block-editor-modal'} data-testid='modal'>
      <div className='cb-editor__preview-modal-header' aria-label={title}>
        {headerActions}
      </div>
      {children}
    </div>
  ),
  Spinner: () => <span data-testid='spinner' className='cb-editor__spinner' />,
}));

jest.mock('@wordpress/icons', () => ({
  check: 'check-icon',
  code: 'code-icon',
  download: 'download-icon',
  desktop: 'desktop-icon',
  mobile: 'mobile-icon',
  error: 'error-icon',
  update: 'update-icon',
}));

jest.mock('@wordpress/i18n', () => ({
  __: (text: string) => text,
  _n: (single: string, plural: string, count: number) =>
    count === 1 ? single : plural,
  sprintf: (fmt: string, ...args: Array<string | number>) =>
    args.reduce<string>(
      (acc, arg, i) => acc.replace('%' + (i === 0 ? 'd' : 'd'), String(arg)),
      fmt
    ),
}));

describe('EmailPreviewModal', () => {
  let container: HTMLDivElement;
  let root: Root;

  const render = (
    props: Partial<Parameters<typeof EmailPreviewModal>[0]> = {}
  ): void => {
    act(() => {
      root.render(
        <EmailPreviewModal
          isOpen
          onRequestClose={jest.fn()}
          preview={makePreview()}
          onRefresh={jest.fn()}
          title='My Template'
          subject='Your subject line'
          hasEdits={false}
          {...props}
        />
      );
    });
  };

  beforeEach(() => {
    container = document.createElement('div');
    document.body.appendChild(container);
    root = createRoot(container);
    (globalThis as any).IS_REACT_ACT_ENVIRONMENT = true;
  });

  afterEach(() => {
    act(() => root.unmount());
    container.remove();
  });

  it('renders nothing when isOpen is false', () => {
    render({ isOpen: false });
    expect(container.querySelector('.cb-editor__preview-modal')).toBeNull();
  });

  describe('secondary row', () => {
    it('renders the subject in the secondary row when provided', () => {
      render({ subject: 'Your subject line' });
      const subject = container.querySelector(
        '.cb-editor__preview-subject'
      ) as HTMLParagraphElement;
      expect(subject).not.toBeNull();
      expect(subject.textContent).toContain('Subject:');
      expect(subject.textContent).toContain('Your subject line');
    });

    it('shows an empty subject state when subject is not provided', () => {
      render({ subject: undefined });
      expect(
        container.querySelector('.cb-editor__preview-subject')?.textContent
      ).toContain('No subject set');
    });

    it('renders a labelled Desktop / Mobile toggle group', () => {
      render();
      const toggle = container.querySelector('.cb-editor__preview-toggle');
      expect(toggle).not.toBeNull();
      expect(toggle?.getAttribute('aria-label')).toBe('Preview viewport');
      const options = container.querySelectorAll(
        '.cb-editor__preview-toggle-option'
      );
      expect(options).toHaveLength(2);
      const labels = Array.from(options).map(el =>
        (el as HTMLElement).getAttribute('aria-label')
      );
      expect(labels).toContain('Desktop');
      expect(labels).toContain('Mobile');
    });

    it('switches the preview iframe width when the mobile option is selected', () => {
      render();
      const initial = container.querySelector(
        '.cb-editor__preview-iframe'
      ) as HTMLElement;
      expect(initial).not.toBeNull();
      expect(initial.style.width).toBe('600px');
      const canvas = container.querySelector(
        '.cb-editor__preview-canvas'
      ) as HTMLElement;
      canvas.scrollTop = 800;
      canvas.scrollLeft = 8;

      act(() => {
        const mobileOption = Array.from(
          container.querySelectorAll('.cb-editor__preview-toggle-option')
        ).find(el =>
          (el as HTMLElement).getAttribute('aria-label')?.includes('Mobile')
        ) as HTMLElement;
        mobileOption?.click();
      });

      const after = container.querySelector(
        '.cb-editor__preview-iframe'
      ) as HTMLElement;
      expect(after.style.width).toBe('390px');
      expect(canvas.scrollTop).toBe(0);
      expect(canvas.scrollLeft).toBe(0);
    });
  });

  describe('status indicator', () => {
    it('reports up-to-date when successful with no diagnostics', () => {
      render({
        preview: makePreview(),
        hasEdits: false,
      });
      const status = container.querySelector('.cb-editor__preview-status');
      expect(status).not.toBeNull();
      expect(status.className).toContain('cb-editor__preview-status--ok');
      expect(status.textContent).toContain('Preview up to date');
    });

    it('reports stale when the editor has newer edits', () => {
      render({
        preview: makePreview(),
        hasEdits: true,
      });
      const status = container.querySelector('.cb-editor__preview-status');
      expect(status.className).toContain('cb-editor__preview-status--stale');
      expect(status.textContent).toContain('Preview out of date');
    });

    it('reports compiling while loading', () => {
      render({
        preview: makePreview({ status: 'loading' }),
      });
      const status = container.querySelector('.cb-editor__preview-status');
      expect(status.className).toContain('cb-editor__preview-status--loading');
      expect(status.textContent).toContain('Compiling…');
    });

    it('reports compilation failed on error status', () => {
      render({
        preview: makePreview({
          status: 'error',
          error: 'Something went wrong.',
          diagnostics: {
            errors: [{ code: 'E001', message: 'Unknown block' }],
            warnings: [],
          },
        }),
      });
      const status = container.querySelector('.cb-editor__preview-status');
      expect(status.className).toContain('cb-editor__preview-status--error');
      expect(status.textContent).toContain('Compilation failed');
    });
  });

  describe('diagnostics', () => {
    it('shows a warning count toggle in the secondary row when warnings exist', () => {
      render({
        preview: makePreview({
          diagnostics: {
            errors: [],
            warnings: [
              { code: 'W100', message: 'Deprecated block' },
              { code: 'W200', message: 'Unknown attribute' },
            ],
          },
        }),
      });
      const toggle = container.querySelector(
        '.cb-editor__preview-diagnostics-toggle'
      ) as HTMLButtonElement;
      expect(toggle).not.toBeNull();
      expect(toggle.textContent).toBe('2');
    });

    it('renders the diagnostics list inside the error state', () => {
      render({
        preview: makePreview({
          status: 'error',
          error: 'Failed to compile',
          diagnostics: {
            errors: [
              { code: 'E001', message: 'Unknown block' },
              { code: 'E002', message: 'Invalid markup' },
            ],
            warnings: [],
          },
        }),
      });
      const alert = container.querySelector(
        '.cb-editor__preview-state[role="alert"]'
      );
      expect(alert).not.toBeNull();
      const list = alert?.querySelector('.cb-editor__preview-diagnostics-list');
      expect(list).not.toBeNull();
      expect(list?.children).toHaveLength(2);
    });

    it('does not render the inline warnings banner by default (collapsed)', () => {
      render({
        preview: makePreview({
          diagnostics: {
            errors: [],
            warnings: [{ code: 'W100', message: 'Deprecated block' }],
          },
        }),
      });
      expect(
        container.querySelector('.cb-editor__preview-diagnostics--inline')
      ).toBeNull();
    });
  });

  describe('source pane', () => {
    it('opens the HTML source pane when the Source action is clicked', () => {
      render();
      expect(
        container.querySelector('.cb-editor__preview-source-pane')
      ).toBeNull();

      const sourceButton = Array.from(
        container.querySelectorAll('button.cb-editor__preview-header-action')
      ).find(el =>
        (el as HTMLElement).textContent?.includes('Source')
      ) as HTMLButtonElement;
      expect(sourceButton).not.toBeNull();

      act(() => sourceButton.click());

      expect(
        container.querySelector('.cb-editor__preview-source-pane')
      ).not.toBeNull();
    });
  });

  it('does not report an API failure or idle preview as up to date', () => {
    render({
      preview: makePreview({ status: 'error', error: 'Network failed' }),
    });
    expect(
      container.querySelector('.cb-editor__preview-status')?.textContent
    ).toContain('Compilation failed');
    render({ preview: makePreview({ status: 'idle' }) });
    expect(
      container.querySelector('.cb-editor__preview-status')?.textContent
    ).toContain('Preview not compiled');
  });

  it('refreshes on click and disables refresh during compilation', () => {
    const refresh = jest.fn();
    render({ onRefresh: refresh });
    act(() =>
      (
        container.querySelector(
          '[aria-label="Refresh email preview"]'
        ) as HTMLButtonElement
      ).click()
    );
    expect(refresh).toHaveBeenCalledTimes(1);
    render({ onRefresh: refresh, preview: makePreview({ status: 'loading' }) });
    expect(
      (
        container.querySelector(
          '[aria-label="Refresh email preview"]'
        ) as HTMLButtonElement
      ).disabled
    ).toBe(true);
  });

  it('preserves the complete compiled document without nesting HTML documents', () => {
    const html =
      '<!doctype html><html><head><style>body{color:red}</style></head><body>Hello</body></html>';
    render({ preview: makePreview({ html }) });
    expect(container.querySelector('iframe')?.getAttribute('srcdoc')).toBe(
      html
    );
  });

  it('reports clipboard failure without closing the source pane', async () => {
    Object.defineProperty(navigator, 'clipboard', {
      configurable: true,
      value: { writeText: jest.fn().mockRejectedValue(new Error('Denied')) },
    });
    render();
    act(() =>
      (
        container.querySelector(
          '[aria-label="View HTML source"]'
        ) as HTMLButtonElement
      ).click()
    );
    await act(async () =>
      (
        container.querySelector('[aria-label="Copy HTML"]') as HTMLButtonElement
      ).click()
    );
    expect(container.querySelector('[role="alert"]')?.textContent).toContain(
      'Could not copy'
    );
  });

  it('shows formatted source but copies the original compiled HTML', async () => {
    const html = '<table><tr><td>Hello</td></tr></table>';
    const writeText = jest.fn().mockResolvedValue(undefined);
    Object.defineProperty(navigator, 'clipboard', {
      configurable: true,
      value: { writeText },
    });
    render({ preview: makePreview({ html }) });
    act(() =>
      (
        container.querySelector(
          '[aria-label="View HTML source"]'
        ) as HTMLButtonElement
      ).click()
    );
    expect(
      container.querySelectorAll('.cb-editor__preview-source-line')
    ).toHaveLength(7);
    expect(
      container.querySelector('.cb-editor__preview-source-code')?.textContent
    ).toContain('    <td>');
    await act(async () =>
      (
        container.querySelector('[aria-label="Copy HTML"]') as HTMLButtonElement
      ).click()
    );
    expect(writeText).toHaveBeenCalledWith(html);
  });

  describe('iframe', () => {
    it('renders a sandboxed iframe with allow-same-origin', () => {
      render();
      const iframe = container.querySelector(
        '.cb-editor__preview-iframe iframe'
      ) as HTMLIFrameElement;
      expect(iframe).not.toBeNull();
      expect(iframe.getAttribute('sandbox')).toBe('allow-same-origin');
      expect(iframe.getAttribute('scrolling')).toBe('no');
      expect(iframe.getAttribute('title')).toBe('Email preview');
    });

    it('renders the compiled HTML in the srcDoc', () => {
      render({
        preview: makePreview({ html: '<h1>Hello world</h1>' }),
      });
      const iframe = container.querySelector(
        '.cb-editor__preview-iframe iframe'
      ) as HTMLIFrameElement;
      expect(iframe.getAttribute('srcdoc')).toBe('<h1>Hello world</h1>');
    });
  });
});
