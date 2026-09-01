/** @jest-environment jsdom */

import { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import EditorEffects from '../../src/scripts/editor/components/EditorEffects';

const mockMarkLastChangeAsPersistent = jest.fn();
let mockSelectedClientId: string | null = null;

jest.mock('@wordpress/block-editor', () => ({
  store: 'core/block-editor',
}));

jest.mock('@wordpress/data', () => ({
  useDispatch: () => ({
    __unstableMarkLastChangeAsPersistent: mockMarkLastChangeAsPersistent,
  }),
  useSelect: callback =>
    callback(() => ({
      getSelectedBlockClientId: () => mockSelectedClientId,
    })),
}));

describe('EditorEffects', () => {
  let container: HTMLDivElement;
  let root: Root;

  beforeEach(() => {
    container = document.createElement('div');
    document.body.appendChild(container);
    root = createRoot(container);
    mockSelectedClientId = null;
    mockMarkLastChangeAsPersistent.mockClear();
    (globalThis as any).IS_REACT_ACT_ENVIRONMENT = true;
  });

  afterEach(() => {
    act(() => root.unmount());
    container.remove();
  });

  it('creates a persistent edit boundary after a successful save', () => {
    const onBlockSelected = jest.fn();

    act(() => {
      root.render(
        <EditorEffects saveStatus='saving' onBlockSelected={onBlockSelected} />
      );
    });
    act(() => {
      root.render(
        <EditorEffects saveStatus='saved' onBlockSelected={onBlockSelected} />
      );
    });

    expect(mockMarkLastChangeAsPersistent).toHaveBeenCalledTimes(1);
  });

  it('reports block selection from the scoped editor registry', () => {
    const onBlockSelected = jest.fn();

    act(() => {
      root.render(
        <EditorEffects saveStatus='saved' onBlockSelected={onBlockSelected} />
      );
    });
    mockSelectedClientId = 'selected-block';
    act(() => {
      root.render(
        <EditorEffects saveStatus='saved' onBlockSelected={onBlockSelected} />
      );
    });

    expect(onBlockSelected).toHaveBeenCalledTimes(1);
  });
});
