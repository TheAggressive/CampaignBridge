/** @jest-environment jsdom */
import { act } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { useSelect } from '@wordpress/data';
import { registerPlugin } from '@wordpress/plugins';
import { NativeEditorExtension } from '../../src/scripts/editor/native-editor';
import { useEmailPreview } from '../../src/scripts/editor/hooks/useEmailPreview';

const requestPreview = jest.fn(async () => {});
const resetPreview = jest.fn();
let postType = 'cb_templates';

jest.mock('@wordpress/data', () => ({ useSelect: jest.fn() }));
jest.mock('@wordpress/editor', () => ({
  store: 'core/editor',
  PluginDocumentSettingPanel: ({
    title,
    children,
  }: {
    title: string;
    children: React.ReactNode;
  }) => <section aria-label={title}>{children}</section>,
  PluginPreviewMenuItem: ({
    children,
    onClick,
  }: {
    children: React.ReactNode;
    onClick: () => void;
  }) => <button onClick={onClick}>{children}</button>,
}));
jest.mock('@wordpress/plugins', () => ({ registerPlugin: jest.fn() }));
jest.mock('@wordpress/blocks', () => ({
  registerBlockBindingsSource: jest.fn(),
  registerBlockVariation: jest.fn(),
}));
// Editor-only side-effect modules; each is covered by its own suite.
jest.mock('../../src/scripts/editor/post-binding-controls', () => ({}));
jest.mock(
  '../../src/scripts/editor/components/Sidebars/TemplateSettings',
  () => ({
    TemplateBasicSettings: () => <div>basic settings</div>,
    TemplateEmailSettings: () => <div>email settings</div>,
    TemplateComplianceSettings: () => <div>compliance settings</div>,
  })
);
jest.mock('../../src/scripts/editor/components/EmailPreviewModal', () => ({
  __esModule: true,
  default: ({ isOpen }: { isOpen: boolean }) =>
    isOpen ? <div role='dialog'>compiled preview</div> : null,
}));
jest.mock('../../src/scripts/editor/hooks/useEmailPreview', () => ({
  useEmailPreview: jest.fn(),
}));

describe('native editor extension', () => {
  let root: Root;
  let container: HTMLDivElement;

  beforeEach(() => {
    (
      globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }
    ).IS_REACT_ACT_ENVIRONMENT = true;
    postType = 'cb_templates';
    requestPreview.mockClear();
    resetPreview.mockClear();
    jest.mocked(useSelect).mockImplementation(callback =>
      callback(() => ({
        getCurrentPostId: () => 42,
        getCurrentPostType: () => postType,
        getEditedPostAttribute: (attribute: string) =>
          attribute === 'meta'
            ? { campaignbridge_subject: 'Unsaved subject' }
            : 'Template title',
        isEditedPostDirty: () => true,
      }))
    );
    jest.mocked(useEmailPreview).mockReturnValue({
      preview: {
        status: 'idle',
        html: '',
        diagnostics: { errors: [], warnings: [] },
        error: null,
      },
      requestPreview,
      resetPreview,
      isStale: false,
    });
    container = document.createElement('div');
    root = createRoot(container);
  });

  afterEach(() => act(() => root.unmount()));

  it('registers a public editor plugin entry point', () => {
    expect(registerPlugin).toHaveBeenCalledWith(
      'campaignbridge-native-editor',
      expect.objectContaining({ render: NativeEditorExtension })
    );
  });

  it('adds template settings and compiles preview from the current editor state', async () => {
    act(() => root.render(<NativeEditorExtension />));
    expect(container.textContent).toContain('basic settings');
    expect(container.textContent).toContain('email settings');
    expect(container.textContent).toContain('compliance settings');
    expect(useEmailPreview).toHaveBeenCalledWith(42, 'Template title');

    const previewButton = Array.from(container.querySelectorAll('button')).find(
      button => button.textContent === 'Email Preview'
    );
    await act(async () => previewButton?.click());

    expect(requestPreview).toHaveBeenCalledTimes(1);
    expect(container.querySelector('[role="dialog"]')).not.toBeNull();
  });

  it('renders nothing outside the template post type', () => {
    postType = 'post';
    act(() => root.render(<NativeEditorExtension />));
    expect(container.innerHTML).toBe('');
  });
});
