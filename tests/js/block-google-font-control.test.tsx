/** @jest-environment jsdom */

import apiFetch from '@wordpress/api-fetch';
import { useEntityProp } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { addFilter } from '@wordpress/hooks';
import { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import {
  GOOGLE_FONT_BLOCKS,
  supportsGoogleFontControl,
  withBlockGoogleFontControl,
} from '../../src/scripts/editor/block-google-font-control';
import { editorDesignConfig } from '../../src/scripts/editor/editor-design-config';
import { DESIGN_FONT_META_KEY } from '../../src/scripts/editor/design-fonts';

const setMeta = jest.fn();
let rawMeta: Record<string, string> = {};

jest.mock('@wordpress/api-fetch', () => ({
  __esModule: true,
  default: jest.fn(),
}));
jest.mock('@wordpress/block-editor', () => ({
  InspectorControls: ({ children }: { children: React.ReactNode }) => (
    <aside>{children}</aside>
  ),
}));
jest.mock('@wordpress/components', () => ({
  Button: ({
    children,
    disabled,
    isBusy,
    label,
    onClick,
    type = 'button',
  }: {
    children?: React.ReactNode;
    disabled?: boolean;
    isBusy?: boolean;
    label?: string;
    onClick?: () => void;
    type?: 'button' | 'submit';
  }) => (
    <button
      type={type}
      aria-busy={isBusy || undefined}
      aria-label={label}
      disabled={disabled}
      onClick={onClick}
    >
      {children}
    </button>
  ),
  Notice: ({
    children,
    status,
  }: {
    children: React.ReactNode;
    status: string;
  }) => <div data-status={status}>{children}</div>,
  PanelBody: ({
    children,
    title,
  }: {
    children: React.ReactNode;
    title: string;
  }) => <section aria-label={title}>{children}</section>,
  Spinner: () => <span>Loading</span>,
  TextControl: ({
    label,
    maxLength,
    onChange,
    value,
  }: {
    label: string;
    maxLength?: number;
    onChange: (value: string) => void;
    value: string;
  }) => (
    <label>
      {label}
      <input
        maxLength={maxLength}
        value={value}
        onChange={event => onChange(event.currentTarget.value)}
      />
    </label>
  ),
}));
jest.mock('@wordpress/core-data', () => ({ useEntityProp: jest.fn() }));
jest.mock('@wordpress/data', () => ({
  useRegistry: () => ({ batch: (callback: () => void) => callback() }),
  useSelect: jest.fn(),
}));
jest.mock('@wordpress/editor', () => ({ store: 'core/editor' }));
jest.mock('@wordpress/hooks', () => ({ addFilter: jest.fn() }));
jest.mock('@wordpress/i18n', () => ({
  __: (text: string) => text,
  sprintf: (format: string, ...values: Array<string | number>) => {
    let sequential = 0;
    return format.replace(/%(?:(\d+)\$)?[ds]/g, (_match, position) => {
      const index = position ? Number(position) - 1 : sequential++;
      return String(values[index]);
    });
  },
}));

const resolvedFont = {
  slug: 'custom-a1b2c3d4e5f6',
  name: 'Agu Display',
  family: 'Agu Display,Arial,Helvetica,sans-serif',
  weights: [400],
  url: 'https://fonts.googleapis.com/css2?family=Agu+Display:wght@400&display=swap',
};

describe('per-block Google Font control', () => {
  let container: HTMLDivElement;
  let root: Root;
  const setAttributes = jest.fn();

  beforeEach(() => {
    (
      globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }
    ).IS_REACT_ACT_ENVIRONMENT = true;
    rawMeta = {};
    setMeta.mockReset();
    setAttributes.mockReset();
    jest.mocked(apiFetch).mockReset();
    jest
      .mocked(useEntityProp)
      .mockImplementation(() => [rawMeta, setMeta, null]);
    jest.mocked(useSelect).mockImplementation(callback =>
      callback(() => ({
        getCurrentPostId: () => 42,
        getCurrentPostType: () => 'cb_templates',
      }))
    );
    editorDesignConfig.features = {
      typography: {
        fontFamilies: {
          theme: [
            {
              slug: 'anton',
              name: 'Anton',
              fontFamily: 'Anton,Arial,Helvetica,sans-serif',
            },
          ],
        },
      },
    };
    editorDesignConfig.designFontSlugs = [];
    container = document.createElement('div');
    document.body.appendChild(container);
    root = createRoot(container);
  });

  afterEach(() => {
    act(() => root.unmount());
    container.remove();
  });

  function render(blockName = 'core/heading') {
    const Wrapped = withBlockGoogleFontControl(() => <div>Block editor</div>);
    act(() => {
      root.render(
        <Wrapped
          name={blockName}
          attributes={{}}
          setAttributes={setAttributes}
        />
      );
    });
  }

  async function search(family: string) {
    const input = container.querySelector('input') as HTMLInputElement;
    await act(async () => {
      Object.getOwnPropertyDescriptor(
        HTMLInputElement.prototype,
        'value'
      )?.set?.call(input, family);
      input.dispatchEvent(new Event('input', { bubbles: true }));
    });
    const form = container.querySelector('form') as HTMLFormElement;
    await act(async () => {
      form.dispatchEvent(
        new Event('submit', { bubbles: true, cancelable: true })
      );
      await Promise.resolve();
    });
  }

  it('registers for exactly the email blocks with per-block typography', () => {
    expect(GOOGLE_FONT_BLOCKS).toEqual([
      'core/heading',
      'core/paragraph',
      'core/button',
    ]);
    expect(supportsGoogleFontControl('core/heading')).toBe(true);
    expect(supportsGoogleFontControl('core/image')).toBe(false);
    expect(addFilter).toHaveBeenCalledWith(
      'editor.BlockEdit',
      'campaignbridge/block-google-font-control',
      withBlockGoogleFontControl
    );
  });

  it('registers and applies a searched font in one block action', async () => {
    jest
      .mocked(apiFetch)
      .mockResolvedValueOnce({
        fonts: [
          {
            family: 'Agu Display',
            category: 'sans-serif',
            variants: ['regular'],
          },
        ],
      })
      .mockResolvedValueOnce({ font: resolvedFont });
    render();

    await search('Agu Display');
    const apply = container.querySelector(
      'button[aria-label="Add and use Agu Display"]'
    ) as HTMLButtonElement;
    await act(async () => {
      apply.click();
      await Promise.resolve();
    });

    expect(apiFetch).toHaveBeenNthCalledWith(1, {
      path: '/campaignbridge/v1/design-fonts?search=Agu%20Display',
    });
    expect(apiFetch).toHaveBeenNthCalledWith(2, {
      path: '/campaignbridge/v1/design-fonts',
      method: 'POST',
      data: { family: 'Agu Display' },
    });
    expect(setAttributes).toHaveBeenCalledWith({
      fontFamily: resolvedFont.slug,
    });
    const saved = setMeta.mock.calls[0][0][DESIGN_FONT_META_KEY];
    expect(JSON.parse(saved)).toEqual({
      version: 1,
      fonts: [resolvedFont],
      slots: {},
    });
  });

  it('applies an existing preset without duplicating template metadata', async () => {
    jest.mocked(apiFetch).mockResolvedValueOnce({
      fonts: [
        { family: 'Anton', category: 'sans-serif', variants: ['regular'] },
      ],
    });
    render('core/paragraph');

    await search('Anton');
    const apply = container.querySelector(
      'button[aria-label="Use Anton"]'
    ) as HTMLButtonElement;
    await act(async () => apply.click());

    expect(apiFetch).toHaveBeenCalledTimes(1);
    expect(setMeta).not.toHaveBeenCalled();
    expect(setAttributes).toHaveBeenCalledWith({ fontFamily: 'anton' });
  });

  it('does not add font controls to unsupported blocks', () => {
    render('core/image');

    expect(container.textContent).toBe('Block editor');
    expect(container.querySelector('aside')).toBeNull();
  });
});
