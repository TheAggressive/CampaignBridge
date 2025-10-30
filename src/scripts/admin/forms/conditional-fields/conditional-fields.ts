/**
 * Client-side conditional field engine for CampaignBridge forms.
 *
 * Uses API-driven approach - sends form data to server for evaluation
 * instead of client-side logic duplication. Includes enhanced UX with
 * loading states, error handling, and accessibility features.
 */

import { ConditionalAccessibility } from './accessibility';
import { ConditionalApiService } from './api-service';
import { ConditionalStateManager } from './state-manager';
import type {
  ConditionalApiRequest,
  ConditionalEngineConfig,
  EvaluationResult,
} from './types';
import { ConditionalUIManager } from './ui-manager';

export class ConditionalEngine {
  private form: HTMLFormElement;
  private config: ConditionalEngineConfig;
  private initialized: boolean = false;
  private debouncedEvaluate: () => void;

  // Focused responsibility managers
  private stateManager: ConditionalStateManager;
  private apiService: ConditionalApiService;
  private uiManager: ConditionalUIManager;
  private accessibility: ConditionalAccessibility;

  private debugEnabled = (window as any).CAMPAIGNBRIDGE_DEBUG === true;

  constructor(formId: string) {
    this.form = document.getElementById(formId) as HTMLFormElement;

    if (!this.form) {
      throw new Error(`Form with ID "${formId}" not found`);
    }

    this.config = {
      formId,
      apiEndpoint: (window as any).ajaxurl || '/wp-admin/admin-ajax.php',
      ajaxAction:
        this.form.getAttribute('data-conditional-action') ||
        'campaignbridge_evaluate_conditions',
      debounceDelay: 100,
      requestTimeout: 30000,
      cacheSize: 10,
    };

    // Initialize focused responsibility managers
    this.stateManager = new ConditionalStateManager(this.config);
    this.apiService = new ConditionalApiService(this.config);
    this.uiManager = new ConditionalUIManager(this.form, this.config);
    this.accessibility = new ConditionalAccessibility(formId);

    // Initialize debounced evaluator
    this.debouncedEvaluate = this.debounce(() => {
      this.evaluateConditions();
    }, this.config.debounceDelay);

    this.init();
  }

  private init(): void {
    // Prevent multiple initializations
    if (this.initialized) {
      return;
    }
    this.initialized = true;

    // Initialize UI and accessibility managers
    this.uiManager.initialize();
    this.accessibility.initialize();

    // Wait for form to be fully rendered
    this.waitForFormReady(() => {
      // Initially hide all conditional fields to prevent FOUC
      this.uiManager.hideAllConditionalFields();

      // Then evaluate conditions to show the ones that should be visible
      this.evaluateConditions();
    });

    // Bind events for future changes
    this.bindEvents();

    // Set up retry functionality
    this.uiManager.setRetryCallback(() => {
      this.evaluateConditions();
    });
  }

  private waitForFormReady(callback: () => void): void {
    let callbackCalled = false;

    const checkReady = () => {
      // Check immediately if form is ready
      if (this.form && this.form.querySelector('input, select, textarea')) {
        if (!callbackCalled) {
          callbackCalled = true;
          callback();
        }
        return;
      }

      // If not ready, check again in 50ms (faster polling)
      setTimeout(checkReady, 50);
    };

    checkReady();
  }

  private bindEvents(): void {
    // Simple change event binding - evaluate on any form change
    this.form.addEventListener('change', () => {
      this.debouncedEvaluate();
    });
  }

  // debouncedEvaluate is assigned in the constructor to ensure availability before init binds events.

  /**
   * Evaluate conditions with intelligent caching and enhanced UX
   */
  private async evaluateConditions(): Promise<void> {
    if (this.apiService.isEvaluationInProgress()) {
      return; // Prevent concurrent evaluations
    }

    const formData = this.stateManager.collectFormData(
      this.form,
      this.config.formId
    );

    // Check client-side cache first
    const cachedResult = this.stateManager.getCachedResult(formData);
    if (cachedResult) {
      this.uiManager.updateFields(cachedResult.fields!, this.accessibility);
      return;
    }

    // Show loading indicator and hide previous errors
    this.uiManager.showLoading();
    this.uiManager.hideError();

    // Prepare API request
    const requestPayload: ConditionalApiRequest = {
      action: this.config.ajaxAction!,
      form_id: this.config.formId,
      data: formData,
      nonce: this.getNonce(),
    };

    try {
      const result: EvaluationResult = await this.apiService.evaluateConditions(
        this.config.apiEndpoint!,
        requestPayload
      );

      this.uiManager.hideLoading();

      if (result.success && result.fields) {
        // Cache successful result
        this.stateManager.cacheResult(formData, {
          success: true,
          fields: result.fields,
        });
        this.stateManager.updateLastFormData(formData);

        // Update UI
        this.uiManager.updateFields(result.fields, this.accessibility);
      } else {
        this.handleEvaluationError(
          result.error || 'Server returned an invalid response'
        );
      }
    } catch (error) {
      this.uiManager.hideLoading();
      this.handleEvaluationError(
        error instanceof Error ? error.message : 'An unexpected error occurred'
      );
    }
  }

  private getNonce(): string {
    const nonceInput = this.form.querySelector(
      `input[name="${this.config.formId}_wpnonce"]`
    ) as HTMLInputElement;
    return nonceInput ? nonceInput.value : '';
  }

  /**
   * Handle evaluation errors
   */
  private handleEvaluationError(error: string): void {
    this.uiManager.showError(error);
    this.log('CFE_ERROR', 'Evaluation failed', {
      error,
      formId: this.config.formId,
    });
  }

  /**
   * Clear evaluation cache (useful for debugging or when form structure changes)
   */
  public clearCache(): void {
    this.stateManager.clearCache();
  }

  /**
   * Structured debug logger (no-op unless CAMPAIGNBRIDGE_DEBUG === true).
   */
  private log(
    code: string,
    message: string,
    context: Record<string, any> = {}
  ): void {
    if (!this.debugEnabled) {
      return;
    }

    try {
      // eslint-disable-next-line no-console
      console.warn(`[ConditionalEngine:${code}] ${message}`, context);
    } catch (_) {
      // Ignore logging failures.
    }
  }

  /**
   * Utility method to debounce function calls
   */
  private debounce<T extends (...args: any[]) => any>(
    func: T,
    wait: number
  ): (...args: Parameters<T>) => void {
    let timeout: number;
    return (...args: Parameters<T>) => {
      clearTimeout(timeout);
      timeout = window.setTimeout(() => func(...args), wait);
    };
  }

  /**
   * Cleanup resources when the engine is destroyed
   */
  public destroy(): void {
    this.uiManager.destroy();
    this.accessibility.destroy();
    this.apiService.cancelEvaluation();
  }
}
