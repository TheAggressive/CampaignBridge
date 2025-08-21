/**
 * Service Container for dependency injection and service management
 * Follows SOLID principles and provides centralized service access
 *
 * @package CampaignBridge
 */

export class ServiceContainer {
  constructor() {
    this.services = new Map();
    this.singletons = new Map();
    this.initialized = false;
  }

  /**
   * Register a service with optional factory function
   * @param {string} name - Service name
   * @param {Function|Object} implementation - Service implementation or factory
   * @param {boolean} singleton - Whether to cache the instance
   */
  register(name, implementation, singleton = true) {
    this.services.set(name, { implementation, singleton });
  }

  /**
   * Get a service instance, creating it if necessary
   * @param {string} name - Service name
   * @returns {Object} Service instance
   */
  get(name) {
    if (!this.services.has(name)) {
      throw new Error(`Service '${name}' not registered`);
    }

    const { implementation, singleton } = this.services.get(name);

    if (singleton && this.singletons.has(name)) {
      return this.singletons.get(name);
    }

    const instance =
      typeof implementation === 'function'
        ? implementation(this)
        : implementation;

    if (singleton) {
      this.singletons.set(name, instance);
    }

    return instance;
  }

  /**
   * Check if a service is registered
   * @param {string} name - Service name
   * @returns {boolean}
   */
  has(name) {
    return this.services.has(name);
  }

  /**
   * Initialize all services
   */
  async initialize() {
    if (this.initialized) return;

    // Register core services
    this.registerServices();

    // Initialize services that need it
    await this.initializeServices();

    this.initialized = true;
  }

  /**
   * Register all services
   */
  registerServices() {
    // Core services
    this.register(
      'apiClient',
      async (container) =>
        new (await import('../services/ApiClient.js')).ApiClient()
    );
    this.register(
      'domManager',
      async (container) =>
        new (await import('../utils/DOMManager.js')).DOMManager()
    );
    this.register(
      'errorHandler',
      async (container) =>
        new (await import('../utils/ErrorHandler.js')).ErrorHandler()
    );

    // Business services
    this.register(
      'emailGenerator',
      async (container) =>
        new (await import('../services/EmailGenerator.js')).EmailGenerator()
    );
    this.register(
      'mailchimpService',
      async (container) =>
        new (await import('../services/MailchimpService.js')).MailchimpService(
          container.get('apiClient')
        )
    );

    // Managers
    this.register(
      'postManager',
      async (container) =>
        new (await import('../managers/PostManager.js')).PostManager(container)
    );
  }

  /**
   * Initialize services that need async setup
   */
  async initializeServices() {
    // Initialize services that need async operations
    const services = ['postManager'];

    for (const serviceName of services) {
      if (this.has(serviceName)) {
        const service = this.get(serviceName);
        if (typeof service.initialize === 'function') {
          await service.initialize();
        }
      }
    }
  }
}

// Export singleton instance
export const serviceContainer = new ServiceContainer();
