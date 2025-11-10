import { KeyboardShortcuts } from '@wordpress/components';
import { ToggleButton } from './ToggleButton';

// TypeScript interfaces for better type safety
export interface SidebarToggleProps {
  isOpen: boolean;
  onToggle: () => void;
}

export interface SidebarToggleConfig {
  shortcut: string;
  className: string;
  label: string;
  icon: any; // WordPress icon definition
}

/**
 * Generic Sidebar Toggle Button Component
 *
 * A reusable toggle button component for sidebars that accepts configuration.
 * This eliminates code duplication between different sidebar toggle buttons.
 *
 * Features:
 * - Configurable keyboard shortcut, icon, label, and styling
 * - Built-in accessibility features
 * - Consistent behavior across all sidebar toggles
 *
 * State Management:
 * - Receives state and handlers as props for centralized management
 * - Props-based approach ensures all buttons stay synchronized
 *
 * @param props - Component props
 * @param props.isOpen - Whether the sidebar is currently open
 * @param props.onToggle - Function to toggle the sidebar
 * @param props.shortcut - Keyboard shortcut string
 * @param props.className - CSS class name for styling
 * @param props.label - Accessible label for the button
 * @param props.icon - Icon component to display
 * @return The sidebar toggle button
 *
 * @example
 * ```tsx
 * const config = {
 *   shortcut: 'ctrl+shift+,',
 *   className: 'cb-editor__toggle--primary',
 *   label: 'Toggle Primary Sidebar',
 *   icon: drawerRight
 * };
 *
 * <SidebarToggle
 *   {...config}
 *   isOpen={isPrimaryOpen}
 *   onToggle={togglePrimary}
 * />
 * ```
 */
export function SidebarToggle({
  isOpen,
  onToggle,
  shortcut,
  className,
  label,
  icon,
}: SidebarToggleProps & SidebarToggleConfig) {
  return (
    <>
      <KeyboardShortcuts bindGlobal shortcuts={{ [shortcut]: onToggle }} />
      <ToggleButton
        className={className}
        isActive={isOpen}
        onClick={onToggle}
        shortcut={shortcut}
        label={label}
        icon={icon}
      />
    </>
  );
}
