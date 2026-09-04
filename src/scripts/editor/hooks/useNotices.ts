import { useDispatch } from '@wordpress/data';
import { useCallback } from '@wordpress/element';
import { store as noticesStore } from '@wordpress/notices';

/**
 * useNotices
 *
 * Thin wrapper around the WordPress notices store that exposes convenient
 * helpers for creating native snackbars.
 *
 * @returns {{success:Function, error:Function, info:Function, warning:Function, removeNotice:Function}}
 */
export function useNotices() {
  const {
    createSuccessNotice,
    createErrorNotice,
    createInfoNotice,
    createWarningNotice,
    removeNotice,
  } = useDispatch(noticesStore);

  const success = useCallback(
    (message: string, options: Record<string, unknown> = {}) => {
      createSuccessNotice(message, {
        type: 'snackbar',
        isDismissible: true,
        ...options,
      });
    },
    [createSuccessNotice]
  );

  const error = useCallback(
    (message: string, options: Record<string, unknown> = {}) => {
      createErrorNotice(message, {
        type: 'snackbar',
        isDismissible: true,
        ...options,
      });
    },
    [createErrorNotice]
  );

  const info = useCallback(
    (message: string, options: Record<string, unknown> = {}) => {
      createInfoNotice(message, {
        type: 'snackbar',
        isDismissible: true,
        ...options,
      });
    },
    [createInfoNotice]
  );

  const warning = useCallback(
    (message: string, options: Record<string, unknown> = {}) => {
      createWarningNotice(message, {
        type: 'snackbar',
        isDismissible: true,
        ...options,
      });
    },
    [createWarningNotice]
  );

  return { success, error, info, warning, removeNotice };
}
