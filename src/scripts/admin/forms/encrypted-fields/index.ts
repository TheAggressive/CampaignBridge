/**
 * Encrypted Fields - Main Entry Point
 *
 * Initializes encrypted fields functionality and exports the modular architecture.
 *
 * @package CampaignBridge
 */

// Initialize on DOM ready
import { EncryptedFieldsHandler } from './EncryptedFieldsHandler';

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    new EncryptedFieldsHandler();
  });
} else {
  new EncryptedFieldsHandler();
}
