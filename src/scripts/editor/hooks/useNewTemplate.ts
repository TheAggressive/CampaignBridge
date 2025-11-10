import { useCallback, useState } from '@wordpress/element';
import { createDraft } from '../services/api';
import { setParamAndReload } from '../utils/url';

/**
 * useNewTemplate
 *
 * Encapsulates the state and actions for creating a new template with a name.
 * UI (modal) stays presentational; this hook handles open/close, title, busy,
 * and the create + navigate flow.
 *
 */
export function useNewTemplate(
  options: {
    // eslint-disable-next-line no-unused-vars -- Parameter name in type definition is for documentation.
    onError?: (message: string) => void;
  } = {}
) {
  const { onError } = options;

  const [open, setOpen] = useState(false);
  const [title, setTitle] = useState('');
  const [busy, setBusy] = useState(false);

  const openModal = useCallback(() => {
    setTitle('');
    setOpen(true);
  }, []);

  const closeModal = useCallback(() => {
    setOpen(false);
  }, []);

  const confirmCreate = useCallback(async () => {
    try {
      setBusy(true);
      const created = await createDraft(title);
      setBusy(false);
      setOpen(false);
      setParamAndReload(
        'post_id',
        ((created as { id?: number | string })?.id || created) as
          | string
          | number
      );
    } catch (e) {
      setBusy(false);
      setOpen(false);
      if (typeof onError === 'function') {
        onError(e?.message || 'Failed to create template.');
      }
    }
  }, [title, onError]);

  return {
    open,
    title,
    busy,
    openModal,
    closeModal,
    setTitle,
    confirmCreate,
  };
}
