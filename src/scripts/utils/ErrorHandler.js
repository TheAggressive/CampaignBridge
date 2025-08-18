// Centralized error handling service
export class ErrorHandler {
  static errorTypes = {
    NETWORK: 'network',
    VALIDATION: 'validation',
    PERMISSION: 'permission',
    NOT_FOUND: 'not_found',
    UNKNOWN: 'unknown',
  };

  static handleError(
    error,
    context = '',
    fallbackMessage = 'An error occurred'
  ) {
    const errorInfo = this.analyzeError(error);

    // Log error for debugging
    console.error(`[${context}] Error:`, errorInfo);

    // Show user-friendly message
    this.showErrorMessage(
      errorInfo.userMessage || fallbackMessage,
      errorInfo.type
    );

    // Track error if analytics are available
    this.trackError(errorInfo, context);

    return errorInfo;
  }

  static analyzeError(error) {
    if (error.name === 'TypeError' && error.message.includes('fetch')) {
      return {
        type: this.errorTypes.NETWORK,
        userMessage:
          'Network connection failed. Please check your internet connection.',
        technicalMessage: error.message,
        recoverable: true,
      };
    }

    if (error.status === 403 || error.status === 401) {
      return {
        type: this.errorTypes.PERMISSION,
        userMessage: "You don't have permission to perform this action.",
        technicalMessage: `HTTP ${error.status}: ${error.statusText}`,
        recoverable: false,
      };
    }

    if (error.status === 404) {
      return {
        type: this.errorTypes.NOT_FOUND,
        userMessage: 'The requested resource was not found.',
        technicalMessage: `HTTP ${error.status}: ${error.statusText}`,
        recoverable: true,
      };
    }

    if (error.name === 'ValidationError') {
      return {
        type: this.errorTypes.VALIDATION,
        userMessage: 'Please check your input and try again.',
        technicalMessage: error.message,
        recoverable: true,
      };
    }

    return {
      type: this.errorTypes.UNKNOWN,
      userMessage: 'An unexpected error occurred. Please try again.',
      technicalMessage: error.message || error.toString(),
      recoverable: true,
    };
  }

  static showErrorMessage(message, type = 'error') {
    // Create error notification
    const notification = document.createElement('div');
    notification.className = `cb-notification cb-notification-${type}`;
    notification.style.cssText = `
      position: fixed;
      top: 20px;
      right: 20px;
      padding: 12px 20px;
      border-radius: 4px;
      color: white;
      font-weight: 500;
      z-index: 9999;
      background: ${type === 'error' ? '#ef4444' : '#f59e0b'};
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
      max-width: 400px;
      word-wrap: break-word;
    `;
    notification.textContent = message;

    document.body.appendChild(notification);

    // Auto-remove after 5 seconds for errors
    setTimeout(() => {
      if (notification.parentNode) {
        notification.parentNode.removeChild(notification);
      }
    }, 5000);
  }

  static trackError(errorInfo, context) {
    // Send error to analytics if available
    if (window.gtag) {
      window.gtag('event', 'exception', {
        description: `${context}: ${errorInfo.technicalMessage}`,
        fatal: !errorInfo.recoverable,
      });
    }

    // Send to WordPress error log if available
    if (window.console && window.console.error) {
      window.console.error(`CampaignBridge Error [${context}]:`, errorInfo);
    }
  }

  static async withErrorHandling(
    operation,
    context = '',
    fallbackMessage = ''
  ) {
    try {
      return await operation();
    } catch (error) {
      this.handleError(error, context, fallbackMessage);
      throw error; // Re-throw for caller to handle if needed
    }
  }

  static withErrorHandlingSync(operation, context = '', fallbackMessage = '') {
    try {
      return operation();
    } catch (error) {
      this.handleError(error, context, fallbackMessage);
      throw error;
    }
  }
}
