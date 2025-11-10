import { __ } from '@wordpress/i18n';
import { drawerRight } from '@wordpress/icons';
import { SidebarToggle, SidebarToggleProps } from './SidebarToggle';

// Configuration for primary sidebar
const PRIMARY_SIDEBAR_CONFIG = {
  shortcut: 'ctrl+shift+,',
  className: 'cb-editor__toggle--primary',
  icon: drawerRight,
  label: __('Sidebar Ctrl+Shift+,', 'campaignbridge'),
} as const;

/**
 * Primary Sidebar Toggle Button Component
 *
 * A toggle button component for the primary sidebar (block inspector).
 * Uses the generic SidebarToggle component for consistent behavior.
 *
 * Features:
 * - Receives state from parent component for consistency
 * - Built-in keyboard shortcut (Ctrl+Shift+,)
 * - Accessible with ARIA attributes and tooltips
 * - Consistent styling with active state indicators
 *
 * State Management:
 * - State managed centrally in parent Header component
 * - Props-based state ensures all buttons stay in sync
 * - Centralized toggle logic prevents race conditions
 *
 * @param props - Component props
 * @param props.isOpen - Whether the primary sidebar is currently open
 * @param props.onToggle - Function to toggle the primary sidebar
 * @return The primary sidebar toggle button
 *
 * @example
 * ```jsx
 * <PrimarySidebarToggle isOpen={true} onToggle={handleToggle} />
 * ```
 */
export function PrimarySidebarToggle(props: SidebarToggleProps) {
  return <SidebarToggle {...PRIMARY_SIDEBAR_CONFIG} {...props} />;
}
