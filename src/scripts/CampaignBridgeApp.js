import { serviceContainer } from './core/ServiceContainer.js';
import { ErrorHandler } from './utils/ErrorHandler.js';
import {
  getCampaignBridgePage,
  hasSettingsFunctionality,
  hasTemplateFunctionality,
} from './utils/helpers.js';

// Main application class - follows SOLID principles with dependency injection
export class CampaignBridgeApp {
  constructor() {
    this.managers = new Map();
    this.services = new Map();
  }

  async initialize() {
    try {
      const currentPage = getCampaignBridgePage();

      if (currentPage) {
        console.log(`CampaignBridge: Initializing on ${currentPage} page`);

        // Initialize service container first
        await serviceContainer.initialize();

        // Initialize managers based on current page
        if (hasTemplateFunctionality()) {
          await this.initializeTemplateManagers();
        }

        if (hasSettingsFunctionality()) {
          await this.initializeSettingsManagers();
        }

        console.log('🚀 CampaignBridge initialized successfully!');
      } else {
        console.log(
          'CampaignBridge: Not on a relevant page, skipping initialization'
        );
      }
    } catch (error) {
      ErrorHandler.handleError(
        error,
        'App Initialization',
        'Failed to initialize CampaignBridge'
      );
    }
  }

  async initializeTemplateManagers() {
    const managers = [
      'templateManager',
      'postManager',
      'previewManager',
      'exportManager',
    ];

    for (const managerName of managers) {
      if (serviceContainer.has(managerName)) {
        const manager = serviceContainer.get(managerName);
        this.managers.set(managerName, manager);
      }
    }
  }

  async initializeSettingsManagers() {
    if (serviceContainer.has('mailchimpService')) {
      const mailchimpService = serviceContainer.get('mailchimpService');
      this.services.set('mailchimp', mailchimpService);
    }
  }

  // Cleanup method for proper resource management
  cleanup() {
    this.managers.forEach((manager) => {
      if (typeof manager.cleanup === 'function') {
        manager.cleanup();
      }
    });
    this.managers.clear();
    this.services.clear();
  }
}
