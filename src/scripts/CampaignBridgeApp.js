import { PostManager } from './managers/PostManager.js';
import { PreviewManager } from './managers/PreviewManager.js';
import { TemplateManager } from './managers/TemplateManager.js';
import { EmailExportService } from './services/EmailExportService.js';
import { MailchimpIntegration } from './services/MailchimpIntegration.js';
import { ErrorHandler } from './utils/ErrorHandler.js';
import {
  getCampaignBridgePage,
  hasSettingsFunctionality,
  hasTemplateFunctionality,
} from './utils/helpers.js';

// Main application class
export class CampaignBridgeApp {
  constructor() {
    try {
      const currentPage = getCampaignBridgePage();

      if (currentPage) {
        console.log(`CampaignBridge: Initializing on ${currentPage} page`);

        // Initialize managers based on current page
        if (hasTemplateFunctionality()) {
          this.templateManager = new TemplateManager();
          this.postManager = new PostManager();
          this.previewManager = new PreviewManager();
          this.emailExportService = new EmailExportService();
        }

        if (hasSettingsFunctionality()) {
          this.mailchimpIntegration = new MailchimpIntegration();
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
}
