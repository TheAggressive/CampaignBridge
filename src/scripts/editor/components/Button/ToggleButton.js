import { Button, Icon } from "@wordpress/components";

/**
 * Base Toggle Button Component
 *
 * A reusable toggle button component that provides consistent styling
 * and accessibility features. This component receives its state and
 * toggle handler as props.
 *
 * @param {Object} props - Component props
 * @param {string} props.className - Additional CSS classes
 * @param {boolean} props.isActive - Whether the toggle is currently active
 * @param {Function} props.onClick - Click handler function
 * @param {string} props.shortcut - Keyboard shortcut string
 * @param {string} props.label - Accessible label for the button
 * @param {React.ReactNode} props.icon - Icon component to display
 * @param {string} [props.variant="tertiary"] - Button variant
 * @returns {JSX.Element} The toggle button component
 */
export function ToggleButton({
  className = "",
  isActive,
  onClick,
  shortcut,
  label,
  icon,
  variant = "tertiary",
}) {
  // ========== STYLING ==========

  const CLASSES = {
    TOGGLE: "cb-editor__toggle",
    TOGGLE_ACTIVE: "is-active",
  };

  const baseClass = `${CLASSES.TOGGLE} ${className} ${
    isActive ? CLASSES.TOGGLE_ACTIVE : ""
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
