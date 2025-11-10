import { BlockInstance } from '@wordpress/blocks';
import { useCallback, useRef, useState } from '@wordpress/element';
import { savePostContent } from '../services/api';
import { serializeSafe } from '../utils/blocks';
import { AUTOSAVE_CONSTANTS, useAutoSave } from './useAutoSave';

/**
 * useAutoSaveManager - Custom hook for managing auto-save functionality with status tracking.
 *
 * Provides a debounced auto-save system that saves block content changes while tracking
 * save status and providing user feedback. Integrates with the useAutoSave hook for
 * advanced debouncing and request management.
 *
 * @returns {Object} Auto-save management interface
 * @returns {Function} returns.save - Auto-save function with debouncing and request management
 * @returns {string} returns.saveStatus - Current save status ('saved' | 'saving' | 'error')
 * @returns {Function} returns.setSaveStatus - Function to manually set save status
 *
 * @example
 * ```tsx
 * const { save, saveStatus, setSaveStatus } = useAutoSaveManager(
 *   postId,
 *   handleBlocksChange,
 *   showSuccessMessage,
 *   showErrorMessage
 * );
 *
 * // Auto-save will trigger when blocks change
 * useEffect(() => {
 *   save.schedule(blocks);
 * }, [blocks]);
 * ```
 */
export function useAutoSaveManager(
  postId: number,
  // eslint-disable-next-line no-unused-vars -- Parameter name in type definition is for documentation.
  onBlocksChange: (blocks: BlockInstance[]) => void,
  // eslint-disable-next-line no-unused-vars -- Parameter name in type definition is for documentation.
  onSuccess: (message: string) => void,
  // eslint-disable-next-line no-unused-vars -- Parameter name in type definition is for documentation.
  onError: (message: string) => void
): {
  save: any;
  saveStatus: string;
  // eslint-disable-next-line no-unused-vars -- Parameter name in type definition is for documentation.
  setSaveStatus: (status: string) => void;
} {
  const [saveStatus, setSaveStatus] = useState(
    AUTOSAVE_CONSTANTS.SAVE_STATUS.SAVED
  );
  const lastNoticeAtRef = useRef(0);

  const performSave = useCallback(
    async (
      blocksToSave: BlockInstance[],
      { signal }: { signal?: AbortSignal } = {}
    ) => {
      try {
        setSaveStatus(AUTOSAVE_CONSTANTS.SAVE_STATUS.SAVING);

        const result = await savePostContent(
          postId,
          {
            content: serializeSafe(blocksToSave),
            status: 'publish',
          },
          signal
        );

        setSaveStatus(AUTOSAVE_CONSTANTS.SAVE_STATUS.SAVED);

        // Throttled success notification
        try {
          const now = Date.now();
          if (
            now - lastNoticeAtRef.current >
            AUTOSAVE_CONSTANTS.NOTIFICATION_THROTTLE
          ) {
            onSuccess && onSuccess('Template saved');
            lastNoticeAtRef.current = now;
          }
        } catch {
          // Silently handle notification errors
        }

        onBlocksChange && onBlocksChange(blocksToSave);
        return result;
      } catch (error) {
        console.error('Save failed:', error);
        setSaveStatus(AUTOSAVE_CONSTANTS.SAVE_STATUS.ERROR);

        try {
          onError && onError('Failed to save changes');
        } catch {
          // Silently handle notification errors
        }

        throw error;
      }
    },
    [postId, onBlocksChange, onSuccess, onError]
  );

  const save = useAutoSave(performSave, AUTOSAVE_CONSTANTS.DEFAULT_DEBOUNCE_MS);

  return {
    save,
    saveStatus,
    setSaveStatus,
  };
}
