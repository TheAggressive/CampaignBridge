import { KeyboardShortcuts } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { listView } from '@wordpress/icons';
import {
  SIDEBAR_CONSTANTS,
  useSidebarState,
} from '../../hooks/useSidebarState';
import { ToggleButton } from './ToggleButton';

/**
 * Secondary Sidebar Toggle Button Component (List View)
 *
 * A toggle button component for the secondary sidebar (list view).
 * Uses the centralized useSidebarState hook for consistent state management.
 *
 * Features:
 * - Uses centralized sidebar state management
 * - Built-in keyboard shortcut (Shift+Alt+O)
 * - Accessible with ARIA attributes and tooltips
 * - Consistent styling with active state indicators
 * - Integrates with WordPress interface store for close button support
 *
 * State Management:
 * - Uses the useSidebarState hook for consistent behavior
 * - Integrates with ComplementaryArea close button interactions
 * - Provides instant UI feedback and state restoration
 * - Reuses proven state management logic
 *
 * @return {JSX.Element} The secondary sidebar toggle button
 *
 * @example
 * ```jsx
 * <SecondarySidebarToggle />
 * ```
 */
export function SecondarySidebarToggle() {
  const { isSecondaryOpen, toggleSecondary } = useSidebarState(
    SIDEBAR_CONSTANTS.SCOPES.PRIMARY,
    SIDEBAR_CONSTANTS.SCOPES.SECONDARY
  );

  return (
    <>
      <KeyboardShortcuts
        bindGlobal
        shortcuts={{ 'shift+alt+o': toggleSecondary }}
      />
      <ToggleButton
        className='cb-editor__toggle--secondary'
        isActive={isSecondaryOpen}
        onClick={toggleSecondary}
        shortcut='shift+alt+o'
        label={__('List View Shift+Alt+O', 'campaignbridge')}
        icon={listView}
      />
    </>
  );
}
