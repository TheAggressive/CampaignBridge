import { Button, Modal, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

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
}) {
  if (!open) return null;

  return (
    <Modal
      title={__('Name your template', 'campaignbridge')}
      onRequestClose={() => (!busy ? onCancel() : null)}
      shouldCloseOnClickOutside={!busy}
      isDismissible={!busy}
    >
      <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
        <TextControl
          label={__('Template name', 'campaignbridge')}
          placeholder={__('e.g., Weekly Newsletter', 'campaignbridge')}
          value={title}
          onChange={onChangeTitle}
          __next40pxDefaultSize
          __nextHasNoMarginBottom
          autoFocus
        />
        <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
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
