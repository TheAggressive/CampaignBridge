import { Button } from '@wordpress/components';

// Editor states-specific constants (exported for reuse if needed)
export const EDITOR_STATES_CONSTANTS = {
  CSS_CLASSES: {
    EDITOR_LOADING: 'cb-editor-loading',
    EDITOR_ERROR: 'cb-editor-error',
    EDITOR_ERROR_ACTIONS: 'cb-editor-error__actions',
  },
};

interface EditorStateProps {
  message: string;
}

export interface EditorStateAction {
  label: string;
  variant: 'primary' | 'secondary';
  onClick?: () => void;
  href?: string;
}

interface ErrorStateProps extends EditorStateProps {
  /** Recovery actions, such as retrying or leaving the failed template. */
  actions?: EditorStateAction[];
}

/**
 * Loading State Component
 *
 * Displays loading state while editor is initializing or loading data.
 *
 * @param {string} message - Loading message to display
 * @returns {JSX.Element} Loading state UI
 */
export function LoadingState({ message }: EditorStateProps): JSX.Element {
  return (
    <div className={EDITOR_STATES_CONSTANTS.CSS_CLASSES.EDITOR_LOADING}>
      <p>{message}</p>
    </div>
  );
}

/**
 * Error State Component
 *
 * Replaces the editor when it cannot safely be used, announces the failure,
 * and offers recovery actions.
 */
export function ErrorState({
  message,
  actions = [],
}: ErrorStateProps): JSX.Element {
  return (
    <div
      className={EDITOR_STATES_CONSTANTS.CSS_CLASSES.EDITOR_ERROR}
      role='alert'
    >
      <p>{message}</p>
      {actions.length > 0 && (
        <div
          className={EDITOR_STATES_CONSTANTS.CSS_CLASSES.EDITOR_ERROR_ACTIONS}
        >
          {actions.map(action => (
            <Button
              key={action.label}
              variant={action.variant}
              onClick={action.onClick}
              href={action.href}
            >
              {action.label}
            </Button>
          ))}
        </div>
      )}
    </div>
  );
}
