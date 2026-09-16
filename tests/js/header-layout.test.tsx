/** @jest-environment jsdom */

import { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import Header from '../../src/scripts/editor/components/Header';

jest.mock('@wordpress/components', () => ({
  Button: ({ children, className, disabled, onClick }) => (
    <button className={className} disabled={disabled} onClick={onClick}>
      {children}
    </button>
  ),
}));

jest.mock('@wordpress/i18n', () => ({
  __: text => text,
}));

jest.mock('@wordpress/icons', () => ({
  time: 'time-icon',
}));

jest.mock('../../src/scripts/editor/components/TemplateToolbar', () => () => (
  <div data-testid='template-toolbar' />
));

jest.mock(
  '../../src/scripts/editor/components/Button/SecondarySidebarToggle',
  () => ({
    SecondarySidebarToggle: () => <button data-testid='secondary-toggle' />,
  })
);

jest.mock(
  '../../src/scripts/editor/components/Button/PrimarySidebarToggle',
  () => ({
    PrimarySidebarToggle: () => <button data-testid='primary-toggle' />,
  })
);

jest.mock(
  '../../src/scripts/editor/components/Button/FullscreenToggle',
  () => ({
    FullscreenToggle: () => <button data-testid='fullscreen-toggle' />,
  })
);

jest.mock('../../src/scripts/editor/components/PreviewButton', () => ({
  __esModule: true,
  default: ({ onClick }) => (
    <button data-testid='preview-button' onClick={onClick} />
  ),
}));

describe('Header layout', () => {
  let container: HTMLDivElement;
  let root: Root;

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

  it.each([
    ['draft', true, 'dirty', false, 'Save draft', false],
    ['draft', false, 'saved', false, 'Saved', true],
    ['draft', true, 'saving', false, 'Saving…', true],
    ['publish', false, 'saved', false, 'Updated', true],
    ['publish', true, 'dirty', false, 'Update', false],
    ['publish', true, 'saving', false, 'Updating…', true],
    ['publish', true, 'dirty', true, 'Update', true],
  ])(
    'renders %s canonical state with label %s',
    (status, hasEdits, saveStatus, isAutosaving, label, disabled) => {
      act(() =>
        root.render(
          <Header
            list={[]}
            currentId={1}
            loading={false}
            onSelect={jest.fn()}
            onNew={jest.fn()}
            isPrimaryOpen={false}
            isSecondaryOpen={false}
            togglePrimary={jest.fn()}
            toggleSecondary={jest.fn()}
            status={status as string}
            hasEdits={hasEdits as boolean}
            saveStatus={saveStatus as any}
            isAutosaving={isAutosaving as boolean}
          />
        )
      );
      const button = container.querySelector(
        '.cb-editor__save-button'
      ) as HTMLButtonElement;
      expect(button.textContent).toBe(label);
      expect(button.disabled).toBe(disabled);
      if (isAutosaving) {
        expect(
          container.querySelector(
            '.cb-editor__header-center .cb-editor__autosave-status'
          )?.textContent
        ).toBe('Autosaving…');
      }
    }
  );

  it('keeps template controls in a dedicated center group', () => {
    act(() => {
      root.render(
        <Header
          list={[]}
          currentId={null}
          loading={false}
          onSelect={jest.fn()}
          onNew={jest.fn()}
          isPrimaryOpen={false}
          isSecondaryOpen={false}
          togglePrimary={jest.fn()}
          toggleSecondary={jest.fn()}
        />
      );
    });

    const left = container.querySelector('.cb-editor__header-left');
    const center = container.querySelector('.cb-editor__header-center');
    const actions = container.querySelector('.cb-editor__header-actions');
    const toolbar = container.querySelector('[data-testid="template-toolbar"]');

    expect(center?.contains(toolbar)).toBe(true);
    expect(
      left?.contains(
        container.querySelector('[data-testid="secondary-toggle"]')
      )
    ).toBe(true);
    expect(left?.contains(toolbar)).toBe(false);
    expect(
      actions?.contains(
        container.querySelector('[data-testid="primary-toggle"]')
      )
    ).toBe(true);
  });

  describe('publish and duplicate buttons', () => {
    it('shows the Publish button when status is draft', () => {
      act(() => {
        root.render(
          <Header
            list={[]}
            currentId={1}
            loading={false}
            onSelect={jest.fn()}
            onNew={jest.fn()}
            isPrimaryOpen={false}
            isSecondaryOpen={false}
            togglePrimary={jest.fn()}
            toggleSecondary={jest.fn()}
            status='draft'
          />
        );
      });

      const publishButton = container.querySelector(
        '.cb-editor__publish-button'
      );
      expect(publishButton).not.toBeNull();
      expect(publishButton?.textContent).toBe('Publish');
    });

    it('does not show the Publish button when status is publish', () => {
      act(() => {
        root.render(
          <Header
            list={[]}
            currentId={1}
            loading={false}
            onSelect={jest.fn()}
            onNew={jest.fn()}
            isPrimaryOpen={false}
            isSecondaryOpen={false}
            togglePrimary={jest.fn()}
            toggleSecondary={jest.fn()}
            status='publish'
          />
        );
      });

      const publishButton = container.querySelector(
        '.cb-editor__publish-button'
      );
      expect(publishButton).toBeNull();
    });

    it('shows the Duplicate button regardless of status', () => {
      act(() => {
        root.render(
          <Header
            list={[]}
            currentId={1}
            loading={false}
            onSelect={jest.fn()}
            onNew={jest.fn()}
            isPrimaryOpen={false}
            isSecondaryOpen={false}
            togglePrimary={jest.fn()}
            toggleSecondary={jest.fn()}
            status='draft'
          />
        );
      });

      const duplicateButton = container.querySelector(
        '.cb-editor__duplicate-button'
      );
      expect(duplicateButton).not.toBeNull();
      expect(duplicateButton?.textContent).toBe('Duplicate');
    });

    it('calls onPublish when the Publish button is clicked', () => {
      const onPublish = jest.fn();
      act(() => {
        root.render(
          <Header
            list={[]}
            currentId={1}
            loading={false}
            onSelect={jest.fn()}
            onNew={jest.fn()}
            isPrimaryOpen={false}
            isSecondaryOpen={false}
            togglePrimary={jest.fn()}
            toggleSecondary={jest.fn()}
            status='draft'
            onPublish={onPublish}
          />
        );
      });

      const publishButton = container.querySelector(
        '.cb-editor__publish-button'
      );
      act(() => {
        publishButton?.click();
      });

      expect(onPublish).toHaveBeenCalledTimes(1);
    });

    it('calls onDuplicate when the Duplicate button is clicked', () => {
      const onDuplicate = jest.fn();
      act(() => {
        root.render(
          <Header
            list={[]}
            currentId={1}
            loading={false}
            onSelect={jest.fn()}
            onNew={jest.fn()}
            isPrimaryOpen={false}
            isSecondaryOpen={false}
            togglePrimary={jest.fn()}
            toggleSecondary={jest.fn()}
            status='draft'
            onDuplicate={onDuplicate}
          />
        );
      });

      const duplicateButton = container.querySelector(
        '.cb-editor__duplicate-button'
      );
      act(() => {
        duplicateButton?.click();
      });

      expect(onDuplicate).toHaveBeenCalledTimes(1);
    });

    it('disables Publish and Duplicate buttons while saving', () => {
      act(() => {
        root.render(
          <Header
            list={[]}
            currentId={1}
            loading={false}
            onSelect={jest.fn()}
            onNew={jest.fn()}
            isPrimaryOpen={false}
            isSecondaryOpen={false}
            togglePrimary={jest.fn()}
            toggleSecondary={jest.fn()}
            status='draft'
            saveStatus='saving'
          />
        );
      });

      const publishButton = container.querySelector(
        '.cb-editor__publish-button'
      );
      const duplicateButton = container.querySelector(
        '.cb-editor__duplicate-button'
      );
      expect(publishButton?.hasAttribute('disabled')).toBe(true);
      expect(duplicateButton?.hasAttribute('disabled')).toBe(true);
    });

    it('disables competing template actions while a duplicate or restore runs', () => {
      act(() => {
        root.render(
          <Header
            list={[]}
            currentId={1}
            loading={false}
            onSelect={jest.fn()}
            onNew={jest.fn()}
            isPrimaryOpen={false}
            isSecondaryOpen={false}
            togglePrimary={jest.fn()}
            toggleSecondary={jest.fn()}
            status='draft'
            hasEdits={true}
            saveStatus='dirty'
            isOperationPending={true}
          />
        );
      });

      for (const selector of [
        '.cb-editor__publish-button',
        '.cb-editor__duplicate-button',
        '.cb-editor__history-button',
        '.cb-editor__save-button',
      ]) {
        expect(
          container.querySelector(selector)?.hasAttribute('disabled')
        ).toBe(true);
      }
    });
  });

  describe('status badge', () => {
    it('shows a Draft badge when status is draft', () => {
      act(() => {
        root.render(
          <Header
            list={[]}
            currentId={1}
            loading={false}
            onSelect={jest.fn()}
            onNew={jest.fn()}
            isPrimaryOpen={false}
            isSecondaryOpen={false}
            togglePrimary={jest.fn()}
            toggleSecondary={jest.fn()}
            status='draft'
          />
        );
      });

      const badge = container.querySelector('.cb-editor__status-badge');
      expect(badge).not.toBeNull();
      expect(badge?.textContent).toBe('Draft');
      expect(badge?.classList.contains('cb-editor__status-badge--draft')).toBe(
        true
      );
    });

    it('shows a Published badge when status is publish', () => {
      act(() => {
        root.render(
          <Header
            list={[]}
            currentId={1}
            loading={false}
            onSelect={jest.fn()}
            onNew={jest.fn()}
            isPrimaryOpen={false}
            isSecondaryOpen={false}
            togglePrimary={jest.fn()}
            toggleSecondary={jest.fn()}
            status='publish'
          />
        );
      });

      const badge = container.querySelector('.cb-editor__status-badge');
      expect(badge).not.toBeNull();
      expect(badge?.textContent).toBe('Published');
      expect(
        badge?.classList.contains('cb-editor__status-badge--published')
      ).toBe(true);
    });

    it('hides the badge when status is undefined', () => {
      act(() => {
        root.render(
          <Header
            list={[]}
            currentId={1}
            loading={false}
            onSelect={jest.fn()}
            onNew={jest.fn()}
            isPrimaryOpen={false}
            isSecondaryOpen={false}
            togglePrimary={jest.fn()}
            toggleSecondary={jest.fn()}
          />
        );
      });

      const badge = container.querySelector('.cb-editor__status-badge');
      expect(badge).toBeNull();
    });
  });

  describe('history button', () => {
    it('renders the History button', () => {
      act(() => {
        root.render(
          <Header
            list={[]}
            currentId={1}
            loading={false}
            onSelect={jest.fn()}
            onNew={jest.fn()}
            isPrimaryOpen={false}
            isSecondaryOpen={false}
            togglePrimary={jest.fn()}
            toggleSecondary={jest.fn()}
          />
        );
      });

      const historyButton = container.querySelector(
        '.cb-editor__history-button'
      );
      expect(historyButton).not.toBeNull();
    });

    it('calls onOpenHistory when the History button is clicked', () => {
      const onOpenHistory = jest.fn();
      act(() => {
        root.render(
          <Header
            list={[]}
            currentId={1}
            loading={false}
            onSelect={jest.fn()}
            onNew={jest.fn()}
            isPrimaryOpen={false}
            isSecondaryOpen={false}
            togglePrimary={jest.fn()}
            toggleSecondary={jest.fn()}
            onOpenHistory={onOpenHistory}
          />
        );
      });

      const historyButton = container.querySelector(
        '.cb-editor__history-button'
      );
      act(() => {
        historyButton?.click();
      });

      expect(onOpenHistory).toHaveBeenCalledTimes(1);
    });

    it('disables the History button while saving', () => {
      act(() => {
        root.render(
          <Header
            list={[]}
            currentId={1}
            loading={false}
            onSelect={jest.fn()}
            onNew={jest.fn()}
            isPrimaryOpen={false}
            isSecondaryOpen={false}
            togglePrimary={jest.fn()}
            toggleSecondary={jest.fn()}
            saveStatus='saving'
          />
        );
      });

      const historyButton = container.querySelector(
        '.cb-editor__history-button'
      );
      expect(historyButton?.hasAttribute('disabled')).toBe(true);
    });
  });
});
