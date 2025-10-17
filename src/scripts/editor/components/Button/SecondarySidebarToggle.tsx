import { __ } from '@wordpress/i18n';
import { listView } from '@wordpress/icons';
import { SidebarToggle, SidebarToggleProps } from './SidebarToggle';

// Configuration for secondary sidebar
const SECONDARY_SIDEBAR_CONFIG = {
  shortcut: 'shift+alt+o',
  className: 'cb-editor__toggle--secondary',
  icon: listView,
  label: __('List View Shift+Alt+O', 'campaignbridge'),
} as const;

/**
 * Secondary Sidebar Toggle Button Component (List View)
 *
 * A toggle button component for the secondary sidebar (list view).
 * Uses the generic SidebarToggle component for consistent behavior.
 *
 * Features:
 * - Receives state from parent component for consistency
 * - Built-in keyboard shortcut (Shift+Alt+O)
 * - Accessible with ARIA attributes and tooltips
 * - Consistent styling with active state indicators
 *
 * State Management:
 * - State managed centrally in parent Header component
 * - Props-based state ensures all buttons stay in sync
 * - Centralized toggle logic prevents race conditions
 *
 * @param props - Component props
 * @param props.isOpen - Whether the secondary sidebar is currently open
 * @param props.onToggle - Function to toggle the secondary sidebar
 * @return The secondary sidebar toggle button
 *
 * @example
 * ```jsx
 * <SecondarySidebarToggle isOpen={true} onToggle={handleToggle} />
 * ```
 */
export function SecondarySidebarToggle(props: SidebarToggleProps) {
  return <SidebarToggle {...SECONDARY_SIDEBAR_CONFIG} {...props} />;
}
