/**
 * Base Manager Class - Provides common functionality for all managers
 * Follows DRY principle and provides consistent patterns
 *
 * @package CampaignBridge
 */

export class BaseManager {
  constructor(serviceContainer) {
    this.container = serviceContainer;
    this.initialized = false;
    this.eventListeners = new Map();
  }

  /**
   * Get a service from the container
   * @param {string} serviceName - Name of the service
   * @returns {Object} Service instance
   */
  getService(serviceName) {
    return this.container.get(serviceName);
  }

  /**
   * Initialize the manager (to be overridden by subclasses)
   */
  async initialize() {
    if (this.initialized) return;

    try {
      await this.doInitialize();
      this.initialized = true;
    } catch (error) {
      this.getService('errorHandler').handleError(
        error,
        this.constructor.name,
        'Failed to initialize manager'
      );
    }
  }

  /**
   * Subclass initialization logic (to be implemented by subclasses)
   */
  async doInitialize() {
    // Override in subclasses
  }

  /**
   * Check if current page supports this manager's functionality
   * @param {string} pageType - Expected page type
   * @returns {boolean}
   */
  isPageSupported(pageType) {
    const currentPage = this.getService('domManager').getCampaignBridgePage();
    return currentPage === pageType;
  }

  /**
   * Add event listener with automatic cleanup
   * @param {string} eventName - Event name
   * @param {string} selector - CSS selector for delegation
   * @param {Function} handler - Event handler
   */
  addEventListener(eventName, selector, handler) {
    const wrappedHandler = (event) => {
      const target = event.target?.closest(selector);
      if (target) {
        handler(event, target);
      }
    };

    document.addEventListener(eventName, wrappedHandler);

    // Store for cleanup
    const key = `${eventName}:${selector}`;
    this.eventListeners.set(key, { eventName, handler: wrappedHandler });
  }

  /**
   * Remove all event listeners added by this manager
   */
  cleanup() {
    this.eventListeners.forEach(({ eventName, handler }) => {
      document.removeEventListener(eventName, handler);
    });
    this.eventListeners.clear();
  }

  /**
   * Safe DOM element retrieval with error handling
   * @param {string} elementKey - DOM element key
   * @returns {Element|null} DOM element or null if not found
   */
  getElement(elementKey) {
    try {
      return this.getService('domManager').getElement(elementKey);
    } catch (error) {
      this.getService('errorHandler').handleError(
        error,
        `${this.constructor.name}.getElement`,
        `Failed to get element: ${elementKey}`
      );
      return null;
    }
  }

  /**
   * Safe DOM elements retrieval with error handling
   * @param {string} elementKey - DOM elements key
   * @returns {Element[]} Array of DOM elements
   */
  getElements(elementKey) {
    try {
      return this.getService('domManager').getElements(elementKey);
    } catch (error) {
      this.getService('errorHandler').handleError(
        error,
        `${this.constructor.name}.getElements`,
        `Failed to get elements: ${elementKey}`
      );
      return [];
    }
  }

  /**
   * Execute operation with error handling
   * @param {Function} operation - Operation to execute
   * @param {string} context - Context for error handling
   * @returns {Promise<any>} Operation result
   */
  async executeSafely(operation, context = '') {
    try {
      return await operation();
    } catch (error) {
      this.getService('errorHandler').handleError(
        error,
        `${this.constructor.name}.${context}`,
        'Operation failed'
      );
      throw error;
    }
  }

  /**
   * Execute synchronous operation with error handling
   * @param {Function} operation - Operation to execute
   * @param {string} context - Context for error handling
   * @returns {any} Operation result
   */
  executeSafelySync(operation, context = '') {
    try {
      return operation();
    } catch (error) {
      this.getService('errorHandler').handleError(
        error,
        `${this.constructor.name}.${context}`,
        'Operation failed'
      );
      throw error;
    }
  }
}
