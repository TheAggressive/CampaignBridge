/**
 * Client-side conditional field engine for CampaignBridge forms.
 *
 * Uses API-driven approach - sends form data to server for evaluation
 * instead of client-side logic duplication. Includes enhanced UX with
 * loading states, error handling, and accessibility features.
 */

import { ConditionalAccessibility } from './accessibility';
import { ConditionalApiService } from './api-service';
import { conditionalCache } from './cache';
import { configManager } from './config';
import { performanceMonitor } from './performance-monitor';
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
  private performanceInterval: number | null = null;

  // Focused responsibility managers
  private stateManager: ConditionalStateManager;
  private apiService: ConditionalApiService;
  private uiManager: ConditionalUIManager;
  private accessibility: ConditionalAccessibility;

  private get debugEnabled(): boolean {
    return this.config.enableDebugLogging ?? false;
  }

  constructor(formId: string) {
    this.form = document.getElementById(formId) as HTMLFormElement;

    if (!this.form) {
      throw new Error(`Form with ID "${formId}" not found`);
    }

    const globalConfig = configManager.getConfig();

    this.config = {
      formId,
      apiEndpoint: (window as any).ajaxurl || '/wp-admin/admin-ajax.php',
      ajaxAction:
        this.form.getAttribute('data-conditional-action') ||
        'campaignbridge_evaluate_conditions',
      debounceDelay: globalConfig.debounceDelay,
      requestTimeout: globalConfig.requestTimeout,
      cacheSize: globalConfig.cacheSize,
      maxRetries: globalConfig.maxRetries,
      enableDebugLogging: globalConfig.enableDebugLogging,
      enablePerformanceMonitoring: globalConfig.enablePerformanceMonitoring,
      validationRules: undefined, // Will be set below
    };

    // Now that config is set, extract validation rules
    this.config.validationRules = this.getValidationRulesFromForm();

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

    // Initialize UI manager (accessibility initializes automatically)
    this.uiManager.initialize();

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

    // Set up periodic performance monitoring
    this.setupPerformanceMonitoring();
  }

  /**
   * Extract validation rules from form data attributes
   */
  private getValidationRulesFromForm():
    | Record<string, import('./validation').ValidationRule>
    | undefined {
    const rules: Record<string, import('./validation').ValidationRule> = {};

    // Look for data-validation attributes on form fields
    const inputs = this.form.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
      const fieldName = input.getAttribute('name');
      if (!fieldName) return;

      const fieldId = fieldName.replace(
        new RegExp(`^${this.config.formId}\\[(.+)\\]$`),
        '$1'
      );
      if (!fieldId || fieldId === fieldName) return;

      const validationAttr = input.getAttribute('data-validation');
      if (validationAttr) {
        try {
          const validationRule = JSON.parse(validationAttr);
          rules[fieldId] = validationRule;
        } catch (error) {
          // Invalid JSON, skip this field
          console.warn(
            `Invalid validation rule for field ${fieldId}:`,
            validationAttr
          );
        }
      }
    });

    return Object.keys(rules).length > 0 ? rules : undefined;
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
      this.config.formId,
      this.config.validationRules
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
   * Set up periodic performance monitoring
   */
  private setupPerformanceMonitoring(): void {
    if (!this.config.enablePerformanceMonitoring) {
      return;
    }

    // Record memory usage every 30 seconds
    this.performanceInterval = window.setInterval(() => {
      performanceMonitor.recordMemoryUsage();
    }, 30000);
  }

  /**
   * Get performance statistics for debugging
   */
  public getPerformanceStats(): ReturnType<typeof performanceMonitor.getStats> {
    return performanceMonitor.getStats();
  }

  /**
   * Generate performance report
   */
  public getPerformanceReport(
    hours: number = 1
  ): ReturnType<typeof performanceMonitor.generateReport> {
    return performanceMonitor.generateReport(hours);
  }

  /**
   * Get cache performance statistics
   */
  public getCacheStats(): ReturnType<typeof conditionalCache.getStats> {
    return this.stateManager.getCacheStats();
  }

  /**
   * Cleanup resources when the engine is destroyed
   */
  public destroy(): void {
    // Clear performance monitoring interval
    if (this.performanceInterval) {
      clearInterval(this.performanceInterval);
      this.performanceInterval = null;
    }

    this.uiManager.destroy();
    this.accessibility.destroy();
    this.apiService.cancelEvaluation();
  }
}
