import { useEffect, useState } from "@wordpress/element";
import { __ } from "@wordpress/i18n";

/**
 * Simple save notification component.
 *
 * Shows temporary save status messages without relying on complex notices system.
 *
 * @param {Object} props - Component props
 * @param {string} props.status - Current save status ('saved', 'saving', 'autosaving', 'error')
 * @returns {JSX.Element|null} The notification element or null
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
      className={`cb-save-notification cb-save-notification--${messageType}`}
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
