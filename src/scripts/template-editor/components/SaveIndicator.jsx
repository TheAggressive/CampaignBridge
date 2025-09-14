import { useEffect, useState } from "@wordpress/element";
import { __ } from "@wordpress/i18n";

/**
 * Save Indicator Component
 *
 * Displays temporary save status notifications with automatic hide functionality.
 * Shows different colored notifications for saving states, success, and errors.
 * Success messages auto-hide after 3 seconds, error messages after 5 seconds.
 * Renders as a fixed-position overlay without relying on WordPress notices system.
 *
 * @param {Object} props - Component props
 * @param {('saved'|'saving'|'autosaving'|'error')} props.status - Save status values:
 *   - 'saved': Changes have been saved successfully (green, auto-hide after 3s)
 *   - 'saving': Currently saving changes (blue, persistent)
 *   - 'autosaving': Auto-saving in progress (blue, persistent)
 *   - 'error': Save operation failed (red, auto-hide after 5s)
 * @returns {JSX.Element|null} The notification element with appropriate styling, or null if no notification to show
 *
 * @example
 * ```jsx
 * <SaveIndicator status="saving" />
 * <SaveIndicator status="saved" />
 * <SaveIndicator status="error" />
 * ```
 */
export default function SaveIndicator({ status }) {
  const [showNotification, setShowNotification] = useState(false);
  const [message, setMessage] = useState("");
  const [messageType, setMessageType] = useState("");

  useEffect(() => {
    let timeoutId;

    if (status === "saving") {
      setMessage(__("Saving...", "campaignbridge"));
      setMessageType("saving");
      setShowNotification(true);
    } else if (status === "autosaving") {
      setMessage(__("Auto-saving...", "campaignbridge"));
      setMessageType("saving");
      setShowNotification(true);
    } else if (status === "saved") {
      setMessage(__("Changes saved", "campaignbridge"));
      setMessageType("success");
      setShowNotification(true);

      // Auto-hide success message after 3 seconds
      timeoutId = setTimeout(() => {
        setShowNotification(false);
      }, 3000);
    } else if (status === "error") {
      setMessage(__("Failed to save changes", "campaignbridge"));
      setMessageType("error");
      setShowNotification(true);

      // Auto-hide error message after 5 seconds
      timeoutId = setTimeout(() => {
        setShowNotification(false);
      }, 5000);
    } else {
      setShowNotification(false);
    }

    return () => {
      if (timeoutId) {
        clearTimeout(timeoutId);
      }
    };
  }, [status]);

  if (!showNotification) {
    return null;
  }

  return (
    <div
      className={`cb-editor__save cb-editor__save--${messageType}`}
      style={{
        position: "fixed",
        bottom: "20px",
        right: "20px",
        background:
          messageType === "success"
            ? "#00a32a"
            : messageType === "error"
              ? "#d63638"
              : "#007cba",
        color: "white",
        padding: "12px 16px",
        borderRadius: "4px",
        boxShadow: "0 2px 8px rgba(0,0,0,0.15)",
        zIndex: 1000,
        fontSize: "14px",
        maxWidth: "300px",
        wordWrap: "break-word",
        opacity: 0.95,
        transition: "opacity 0.3s ease",
      }}
    >
      {message}
    </div>
  );
}
