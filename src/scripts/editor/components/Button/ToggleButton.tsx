import { Button, Icon } from '@wordpress/components';

// TypeScript interface for component props
export interface ToggleButtonProps {
  className?: string;
  isActive: boolean;
  onClick: () => void;
  shortcut: string;
  label: string;
  icon: any; // WordPress icon definition
  variant?: 'primary' | 'secondary' | 'tertiary' | 'link';
}

/**
 * Base Toggle Button Component
 *
 * A reusable toggle button component that provides consistent styling
 * and accessibility features. This component receives its state and
 * toggle handler as props.
 *
 * Features:
 * - Consistent styling with active state indicators
 * - Built-in accessibility (ARIA attributes, tooltips)
 * - Keyboard shortcut support
 * - TypeScript support for better type safety
 *
 * @param props - Component props
 * @param props.className - Additional CSS classes
 * @param props.isActive - Whether the toggle is currently active
 * @param props.onClick - Click handler function
 * @param props.shortcut - Keyboard shortcut string
 * @param props.label - Accessible label for the button
 * @param props.icon - WordPress icon definition
 * @param props.variant - Button variant (defaults to 'tertiary')
 * @return The toggle button component
 *
 * @example
 * ```tsx
 * <ToggleButton
 *   className="my-toggle"
 *   isActive={isToggled}
 *   onClick={handleToggle}
 *   shortcut="ctrl+shift+t"
 *   label="Toggle feature"
 *   icon={myIcon}
 *   variant="primary"
 * />
 * ```
 */
export function ToggleButton({
  className = '',
  isActive,
  onClick,
  shortcut,
  label,
  icon,
  variant = 'tertiary',
}: ToggleButtonProps): JSX.Element {
  // ========== STYLING ==========

  const CLASSES = {
    TOGGLE: 'cb-editor__toggle',
    TOGGLE_ACTIVE: 'is-active',
  } as const;

  const baseClass = `${CLASSES.TOGGLE} ${className} ${
    isActive ? CLASSES.TOGGLE_ACTIVE : ''
  }`;

  // ========== RENDER ==========

  return (
    <Button
      className={baseClass}
      onClick={onClick}
      aria-pressed={isActive}
      aria-keyshortcuts={shortcut}
      label={label}
      showTooltip
      variant={variant}
    >
      <Icon icon={icon} size={24} />
    </Button>
  );
}
