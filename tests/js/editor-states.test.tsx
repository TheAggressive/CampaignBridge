/** @jest-environment jsdom */

import { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { ErrorState } from '../../src/scripts/editor/components/EditorStates';

jest.mock('@wordpress/components', () => ({
  Button: ({ children, href, onClick, variant }: any) =>
    href ? (
      <a href={href} data-variant={variant}>
        {children}
      </a>
    ) : (
      <button type='button' onClick={onClick} data-variant={variant}>
        {children}
      </button>
    ),
}));

describe('ErrorState', () => {
  let container: HTMLDivElement;
  let root: Root;

  beforeEach(() => {
    (globalThis as any).IS_REACT_ACT_ENVIRONMENT = true;
    container = document.createElement('div');
    document.body.appendChild(container);
    root = createRoot(container);
  });

  afterEach(() => {
    act(() => root.unmount());
    container.remove();
  });

  it('announces the failure and offers keyboard-reachable recovery actions', () => {
    const retry = jest.fn();

    act(() => {
      root.render(
        <ErrorState
          message='Template could not be loaded. Please try again.'
          actions={[
            { label: 'Try again', variant: 'primary', onClick: retry },
            {
              label: 'Back to templates',
              variant: 'secondary',
              href: '/wp-admin/admin.php?page=campaignbridge-editor',
            },
          ]}
        />
      );
    });

    const region = container.querySelector('[role="alert"]');
    expect(region?.textContent).toContain(
      'Template could not be loaded. Please try again.'
    );

    const button = container.querySelector('button');
    expect(button?.textContent).toBe('Try again');
    act(() => button?.click());
    expect(retry).toHaveBeenCalledTimes(1);

    expect(container.querySelector('a')?.getAttribute('href')).toBe(
      '/wp-admin/admin.php?page=campaignbridge-editor'
    );
  });

  it('renders without actions', () => {
    act(() => {
      root.render(<ErrorState message='Something failed.' />);
    });

    expect(container.querySelector('[role="alert"]')?.textContent).toBe(
      'Something failed.'
    );
    expect(container.querySelector('button')).toBeNull();
  });
});
