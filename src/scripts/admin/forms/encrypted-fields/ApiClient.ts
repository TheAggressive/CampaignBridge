/**
 * API Client
 *
 * Handles secure API communications for encrypted fields
 *
 * @package CampaignBridge
 */

import apiFetch from '@wordpress/api-fetch';
import { dispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import type { AccessibilityManager } from './AccessibilityManager';
import type { StateManager } from './StateManager';
import type {
  ApiDecryptResponse,
  ApiEncryptResponse,
  WordPressApiResponse,
} from './types';
import type { ValidationManager } from './ValidationManager';

/**
 * Handles secure API communications
 */
export class ApiClient {
  constructor(
    // eslint-disable-next-line no-unused-vars -- Parameter property automatically creates class property used via this.stateManager.
    private stateManager: StateManager,
    // eslint-disable-next-line no-unused-vars -- Parameter property automatically creates class property used via this.validationManager.
    private validationManager: ValidationManager,
    // eslint-disable-next-line no-unused-vars -- Parameter property automatically creates class property used via this.accessibility.
    private accessibility: AccessibilityManager
  ) {}

  /**
   * Make secure request to decrypt field
   */
  async decryptField(
    fieldId: string,
    encryptedValue: string
  ): Promise<ApiDecryptResponse> {
    return this.makeSecureRequest('campaignbridge_decrypt-field', {
      field_id: fieldId,
      encrypted_value: encryptedValue,
    }) as Promise<ApiDecryptResponse>;
  }

  /**
   * Make secure request to encrypt field
   */
  async encryptField(
    fieldId: string,
    newValue: string
  ): Promise<ApiEncryptResponse> {
    return this.makeSecureRequest('campaignbridge_encrypt-field', {
      field_id: fieldId,
      new_value: newValue,
    }) as Promise<ApiEncryptResponse>;
  }

  /**
   * Make secure AJAX request with enhanced security
   */
  private async makeSecureRequest(
    action: string,
    data: Record<string, any>,
    retryCount: number = 0
  ): Promise<WordPressApiResponse> {
    const requestId = `${action}_${Date.now()}_${Math.random()}`;

    // Prevent concurrent requests for the same action
    if (this.stateManager.isRequestInQueue(action)) {
      throw new Error('Request already in progress');
    }

    // Validate request data will happen after nonce is added

    // Check retry limits
    const maxRetries = campaignbridgeAdmin.security?.maxRetries || 1;
    if (retryCount > maxRetries) {
      throw new Error('Maximum retry attempts exceeded');
    }

    this.stateManager.addRequestToQueue(action, requestId);

    try {
      // Enhanced security: Add nonce and validate data
      const nonce = campaignbridgeAdmin?.nonce || '';
      if (!nonce) {
        this.logError('Security token not available', { action: 'decrypt' });
      }
      const secureData: Record<string, any> = {
        ...data,
        _wpnonce: nonce,
      };

      // Validate request data (now includes nonce)
      const validation = this.validationManager.validateRequestData({
        action,
        ...secureData,
      });
      if (!validation.isValid) {
        throw new Error(validation.error || 'Request validation failed');
      }

      // Create AbortController for timeout handling
      const controller = new AbortController();
      const timeoutMs = campaignbridgeAdmin.security?.requestTimeout || 30000;
      const timeoutId = setTimeout(() => {
        controller.abort();
      }, timeoutMs);

      // Use WordPress apiFetch with enhanced security
      const result = await apiFetch<WordPressApiResponse>({
        path: '/campaignbridge/v1/' + action.replace('campaignbridge_', ''),
        method: 'POST',
        data: secureData,
        signal: controller.signal, // Add abort signal
      });

      clearTimeout(timeoutId);

      // Enhanced security validation of response
      if (!this.isValidApiResponse(result)) {
        throw new Error('Invalid or tampered response from server');
      }

      return result;
    } catch (error) {
      this.stateManager.removeRequestFromQueue(action);

      // Handle specific error types
      if ((error as Error).name === 'AbortError') {
        throw new Error('Request timeout - please try again');
      }

      const wpError = error as { code?: string; message?: string };
      if (
        wpError.code === 'rest_cookie_invalid_nonce' ||
        wpError.code === 'rest_not_logged_in'
      ) {
        throw new Error('Security validation failed - please refresh the page');
      }

      // Retry logic for network errors only
      if (this.isRetryableError(error as Error) && retryCount < maxRetries) {
        this.accessibility.announceToScreenReader(
          `Retrying request (${retryCount + 1}/${maxRetries}): ${(error as Error).message}`
        );
        await this.delay(1000 * (retryCount + 1)); // Exponential backoff
        return this.makeSecureRequest(action, data, retryCount + 1);
      }

      // Re-throw with sanitized error message
      throw new Error(
        this.sanitizeErrorMessage((error as Error).message || 'Request failed')
      );
    } finally {
      this.stateManager.removeRequestFromQueue(action);
    }
  }

  /**
   * Validate API response for tampering
   */
  private isValidApiResponse(response: any): response is WordPressApiResponse {
    if (!response || typeof response !== 'object') {
      return false;
    }

    // Basic structure validation
    if (
      response.success !== undefined &&
      typeof response.success !== 'boolean'
    ) {
      return false;
    }

    // For encrypted field responses, check expected structure
    if (response.data && typeof response.data !== 'object') {
      return false;
    }

    return true;
  }

  /**
   * Check if error is retryable
   */
  private isRetryableError(error: Error): boolean {
    const retryableCodes = ['fetch_error', 'network_error', 'timeout'];
    const wpError = error as { code?: string };
    return (
      error.name === 'TypeError' ||
      wpError.code === 'failed_to_fetch' ||
      retryableCodes.includes(wpError.code || '')
    );
  }

  /**
   * Log error with context
   */
  private logError(message: string, context: any = {}): void {
    const errorData = {
      message,
      context,
      timestamp: new Date().toISOString(),
      component: 'ApiClient',
    };

    // Use console.error for client-side logging
    console.error('[EncryptedFields:ApiClient]', errorData);
  }

  /**
   * Sanitize error messages to prevent information leakage
   */
  private sanitizeErrorMessage(message: string): string {
    // Remove potentially sensitive information
    const sensitive = [/nonce/i, /token/i, /key/i, /password/i, /secret/i];

    let sanitized = message;
    sensitive.forEach(pattern => {
      sanitized = sanitized.replace(pattern, '[REDACTED]');
    });

    return sanitized;
  }

  /**
   * Delay utility for retry backoff
   */
  private delay(ms: number): Promise<void> {
    return new Promise(resolve => setTimeout(resolve, ms));
  }

  /**
   * Show error to user
   */
  showError(message: string): void {
    // Use WordPress notices system for consistent admin experience
    dispatch(noticesStore).createNotice('error', message, {
      type: 'snackbar',
      isDismissible: true,
      explicitDismiss: true,
    });

    // Also announce to screen readers
    this.accessibility.announceToScreenReader(`Error: ${message}`);
  }
}
