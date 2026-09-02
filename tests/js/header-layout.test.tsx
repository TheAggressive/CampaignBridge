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
});
