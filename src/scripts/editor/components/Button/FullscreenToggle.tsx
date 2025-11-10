import { KeyboardShortcuts } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { fullscreen } from '@wordpress/icons';
import { SIDEBAR_CONSTANTS } from '../../hooks/useSidebarState';
import { ToggleButton } from './ToggleButton';

// Fullscreen mode preference constants (consistent with EditorChrome)
const FULLSCREEN_SCOPE = SIDEBAR_CONSTANTS.PREFERENCES.FULLSCREEN_MODE;
const FULLSCREEN_KEY = 'isFullscreen';

/**
 * Fullscreen Toggle Button Component
 *
 * A completely self-contained component for toggling the editor fullscreen mode.
 * Handles all state management, keyboard shortcuts, and functionality internally.
 *
 * Features:
 * - Self-contained state management using WordPress preferences
 * - Built-in keyboard shortcut (Ctrl+Shift+Alt+F)
 * - Accessible with ARIA attributes and tooltips
 * - Dynamic label based on current state
 * - Consistent styling with active state indicators
 * - No external dependencies - completely independent
 *
 * State Management:
 * - Uses WordPress preferences store with proper scope/key pattern
 * - Consistent with EditorChrome fullscreen state management
 * - Persists state across page reloads
 *
 * @return The fullscreen toggle button
 *
 * @example
 * ```tsx
 * <FullscreenToggle />
 * ```
 */
export function FullscreenToggle(): JSX.Element {
  // Get fullscreen state from WordPress preferences (same pattern as EditorChrome)
  const isActive = useSelect(
    select =>
      (
        select('core/preferences') as {
          // eslint-disable-next-line no-unused-vars -- Parameter names in type definition are for documentation.
          get: (scope: string, key: string) => unknown;
        }
      ).get(FULLSCREEN_SCOPE, FULLSCREEN_KEY) as boolean,
    [FULLSCREEN_SCOPE, FULLSCREEN_KEY]
  );

  // Get preference toggle dispatcher
  const { toggle: togglePreference } = useDispatch('core/preferences');

  // Handle toggle action
  const handleToggle = (): void => {
    togglePreference(FULLSCREEN_SCOPE, FULLSCREEN_KEY);
  };

  // Dynamic label based on current state
  const label = isActive
    ? __('Exit Fullscreen Ctrl+Shift+Alt+F', 'campaignbridge')
    : __('Enter Fullscreen Ctrl+Shift+Alt+F', 'campaignbridge');

  return (
    <>
      <KeyboardShortcuts
        bindGlobal
        shortcuts={{ 'ctrl+shift+alt+f': handleToggle }}
      />
      <ToggleButton
        className='cb-fullscreen-toggle'
        isActive={isActive}
        onClick={handleToggle}
        shortcut='ctrl+shift+alt+f'
        label={label}
        icon={fullscreen}
      />
    </>
  );
}
