// Editor states-specific constants (exported for reuse if needed)
export const EDITOR_STATES_CONSTANTS = {
  CSS_CLASSES: {
    EDITOR_LOADING: 'cb-editor-loading',
    EDITOR_ERROR: 'cb-editor-error',
  },
};

/**
 * Loading State Component
 *
 * Displays loading state while editor is initializing or loading data.
 *
 * @param {string} message - Loading message to display
 * @returns {JSX.Element} Loading state UI
 */
export function LoadingState({ message }) {
  return (
    <div className={EDITOR_STATES_CONSTANTS.CSS_CLASSES.EDITOR_LOADING}>
      <p>{message}</p>
    </div>
  );
}

/**
 * Error State Component
 *
 * Displays error state when editor fails to load or encounters an error.
 *
 * @param {string} message - Error message to display
 * @returns {JSX.Element} Error state UI
 */
export function ErrorState({ message }) {
  return (
    <div className={EDITOR_STATES_CONSTANTS.CSS_CLASSES.EDITOR_ERROR}>
      <p>{message}</p>
    </div>
  );
}
