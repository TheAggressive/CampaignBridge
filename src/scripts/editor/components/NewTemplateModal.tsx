import { Button, Modal, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

interface NewTemplateModalProps {
  open: boolean;
  title: string;
  onChangeTitle: (title: string) => void;
  onCancel: () => void;
  onConfirm: () => void | Promise<unknown>;
  busy?: boolean;
}

/**
 * New Template Modal
 *
 * Presentational modal that prompts for a template name.
 * Parent controls open state, title value, and create/cancel actions.
 */
export default function NewTemplateModal({
  open,
  title,
  onChangeTitle,
  onCancel,
  onConfirm,
  busy = false,
}: NewTemplateModalProps): JSX.Element | null {
  if (!open) return null;

  return (
    <Modal
      title={__('Name your template', 'campaignbridge')}
      onRequestClose={() => (!busy ? onCancel() : null)}
      shouldCloseOnClickOutside={!busy}
      isDismissible={!busy}
    >
      <div className='cb-editor__new-template-modal'>
        <TextControl
          label={__('Template name', 'campaignbridge')}
          placeholder={__('e.g., Weekly Newsletter', 'campaignbridge')}
          value={title}
          onChange={onChangeTitle}
          __next40pxDefaultSize
          __nextHasNoMarginBottom
          autoFocus
        />
        <div className='cb-editor__new-template-actions'>
          <Button variant='tertiary' onClick={onCancel} disabled={busy}>
            {__('Cancel', 'campaignbridge')}
          </Button>
          <Button variant='primary' onClick={onConfirm} isBusy={busy}>
            {busy
              ? __('Creating…', 'campaignbridge')
              : __('Create', 'campaignbridge')}
          </Button>
        </div>
      </div>
    </Modal>
  );
}
