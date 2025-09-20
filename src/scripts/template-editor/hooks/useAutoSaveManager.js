import { useCallback, useRef, useState } from "@wordpress/element";
import { EDITOR_CONSTANTS } from "../constants/editor";
import { savePostContent } from "../services/api";
import { serializeSafe } from "../utils/blocks";
import { useAutoSave } from "./useAutoSave";

/**
 * Custom hook for managing auto-save functionality with status tracking
 *
 * @param {number} postId - The ID of the post being edited
 * @param {Function} onBlocksChange - Callback fired when blocks change
 * @param {Function} onSuccess - Success notification callback
 * @param {Function} onError - Error notification callback
 * @returns {Object} Save status and save function
 */
export function useAutoSaveManager(postId, onBlocksChange, onSuccess, onError) {
  const [saveStatus, setSaveStatus] = useState(
    EDITOR_CONSTANTS.SAVE_STATUS.SAVED,
  );
  const lastNoticeAtRef = useRef(0);

  const performSave = useCallback(
    async (blocksToSave, { signal } = {}) => {
      try {
        setSaveStatus(EDITOR_CONSTANTS.SAVE_STATUS.SAVING);

        const result = await savePostContent(
          postId,
          { content: serializeSafe(blocksToSave) },
          signal,
        );

        setSaveStatus(EDITOR_CONSTANTS.SAVE_STATUS.SAVED);

        // Throttled success notification
        try {
          const now = Date.now();
          if (
            now - lastNoticeAtRef.current >
            EDITOR_CONSTANTS.NOTIFICATION_THROTTLE
          ) {
            onSuccess && onSuccess("Template saved");
            lastNoticeAtRef.current = now;
          }
        } catch (notificationError) {
          // Silently handle notification errors
        }

        onBlocksChange && onBlocksChange(blocksToSave);
        return result;
      } catch (error) {
        console.error("Save failed:", error);
        setSaveStatus(EDITOR_CONSTANTS.SAVE_STATUS.ERROR);

        try {
          onError && onError("Failed to save changes");
        } catch (notificationError) {
          // Silently handle notification errors
        }

        throw error;
      }
    },
    [postId, onBlocksChange, onSuccess, onError],
  );

  const save = useAutoSave(
    performSave,
    EDITOR_CONSTANTS.AUTOSAVE.DEFAULT_DEBOUNCE_MS,
  );

  return {
    save,
    saveStatus,
    setSaveStatus,
  };
}
