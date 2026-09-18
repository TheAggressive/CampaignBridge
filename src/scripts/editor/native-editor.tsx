import { useSelect } from '@wordpress/data';
import {
  PluginDocumentSettingPanel,
  PluginPreviewMenuItem,
  store as editorStore,
} from '@wordpress/editor';
import { useCallback, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { search } from '@wordpress/icons';
import { registerPlugin } from '@wordpress/plugins';
import EmailPreviewModal from './components/EmailPreviewModal';
import {
  TemplateBasicSettings,
  TemplateComplianceSettings,
  TemplateEmailSettings,
} from './components/Sidebars/TemplateSettings';
import { useEmailPreview } from './hooks/useEmailPreview';

const TEMPLATE_POST_TYPE = 'cb_templates';

interface NativeEditorState {
  postId: number;
  postType: string;
  title: string;
  subject: string;
}

function stringValue(value: unknown): string {
  if (typeof value === 'string') {
    return value;
  }

  if (
    value &&
    typeof value === 'object' &&
    'raw' in value &&
    typeof value.raw === 'string'
  ) {
    return value.raw;
  }

  return '';
}

/** CampaignBridge-owned extensions rendered inside Core's post editor. */
export function NativeEditorExtension(): JSX.Element | null {
  const { postId, postType, title, subject } = useSelect(select => {
    const editor = select(editorStore);
    const rawId = editor.getCurrentPostId();
    const meta = editor.getEditedPostAttribute('meta') as
      Record<string, unknown> | undefined;

    return {
      postId: typeof rawId === 'number' ? rawId : Number(rawId) || 0,
      postType: editor.getCurrentPostType(),
      title: stringValue(editor.getEditedPostAttribute('title')),
      subject: stringValue(meta?.campaignbridge_subject),
    } satisfies NativeEditorState;
  }, []);
  const [previewOpen, setPreviewOpen] = useState(false);
  const { preview, requestPreview, resetPreview, isStale } = useEmailPreview(
    postId,
    title
  );
  const openPreview = useCallback(() => {
    setPreviewOpen(true);
    void requestPreview();
  }, [requestPreview]);
  const closePreview = useCallback(() => {
    setPreviewOpen(false);
    resetPreview();
  }, [resetPreview]);

  if (postType !== TEMPLATE_POST_TYPE || postId < 1) {
    return null;
  }

  const settingsProps = { postType, postId };

  return (
    <>
      <PluginDocumentSettingPanel
        name='template-settings'
        title={__('Template Settings', 'campaignbridge')}
      >
        <TemplateBasicSettings {...settingsProps} />
      </PluginDocumentSettingPanel>
      <PluginDocumentSettingPanel
        name='email-settings'
        title={__('Email Settings', 'campaignbridge')}
      >
        <TemplateEmailSettings {...settingsProps} />
      </PluginDocumentSettingPanel>
      <PluginDocumentSettingPanel
        name='compliance-settings'
        title={__('Footer & Compliance', 'campaignbridge')}
      >
        <TemplateComplianceSettings {...settingsProps} />
      </PluginDocumentSettingPanel>
      <PluginPreviewMenuItem icon={search} onClick={openPreview}>
        {__('Email Preview', 'campaignbridge')}
      </PluginPreviewMenuItem>
      <EmailPreviewModal
        isOpen={previewOpen}
        onRequestClose={closePreview}
        preview={preview}
        onRefresh={() => void requestPreview()}
        title={title}
        subject={subject}
        hasEdits={isStale}
      />
    </>
  );
}

registerPlugin('campaignbridge-native-editor', {
  icon: 'email',
  render: NativeEditorExtension,
});
