import { Button, ColorPicker } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { saveBrandSlot } from './api';
import { toPortableHex } from './color';
import type { BrandKitConfig, BrandKitPayload, BrandSlot } from './types';

interface EditColorModalProps {
  item: BrandSlot;
  config: BrandKitConfig;
  closeModal?: () => void;
  onActionPerformed?: (items: BrandSlot[]) => void;
  onSaved: (kit: BrandKitPayload) => void;
  onFailed: () => void;
}

export function EditColorModal({
  item,
  config,
  closeModal,
  onActionPerformed,
  onSaved,
  onFailed,
}: EditColorModalProps) {
  const [draft, setDraft] = useState(item.color);
  const [saving, setSaving] = useState(false);
  const hex = toPortableHex(draft);

  return (
    <form
      onSubmit={async event => {
        event.preventDefault();
        if (!hex) {
          return;
        }

        setSaving(true);
        try {
          const next = await saveBrandSlot(config.restUrl, {
            ...item,
            color: hex,
          });
          onSaved(next);
          onActionPerformed?.([item]);
          closeModal?.();
        } catch {
          onFailed();
        } finally {
          setSaving(false);
        }
      }}
    >
      <ColorPicker
        color={draft}
        enableAlpha={false}
        onChange={value => setDraft(value)}
      />
      <p>
        <code>{hex ?? draft}</code>
      </p>
      <div className='campaignbridge-brand-kit__actions'>
        <Button variant='tertiary' onClick={closeModal}>
          {config.i18n.cancel}
        </Button>
        <Button
          variant='primary'
          type='submit'
          isBusy={saving}
          disabled={!hex || saving}
        >
          {config.i18n.save}
        </Button>
      </div>
    </form>
  );
}
