/**
 * Handles all API communication for conditional field evaluation
 */

import { ConditionalApiClient } from './api-client';
import { configManager } from './config';
import {
  ApiTimeoutError,
  ApiValidationError,
  ConditionalFieldError,
  NetworkError,
  RateLimitError,
} from './errors';
import { performanceMonitor } from './performance-monitor';
import type {
  ConditionalApiRequest,
  ConditionalEngineConfig,
  EvaluationResult,
} from './types';

export class ConditionalApiService {
  private apiClient = new ConditionalApiClient();
  private readonly requestTimeout: number;
  private evaluationTimeout: number | null = null;
  private evaluationInProgress = false;

  constructor(config: ConditionalEngineConfig) {
    const globalConfig = configManager.getConfig();
    this.requestTimeout = config.requestTimeout ?? globalConfig.requestTimeout;
  }

  /**
   * Evaluate conditional fields via API
   */
  public async evaluateConditions(
    apiEndpoint: string,
    requestPayload: ConditionalApiRequest
  ): Promise<EvaluationResult> {
    if (this.evaluationInProgress) {
      return { success: false, error: 'Evaluation already in progress' };
    }

    this.evaluationInProgress = true;
    const startTime = performance.now();

    try {
      // Set timeout for the request
      const timeoutPromise = this.createTimeoutPromise();

      // Make the API request
      const evaluationPromise = this.apiClient.evaluate(
        apiEndpoint,
        requestPayload
      );

      // Race between timeout and evaluation
      const result = await Promise.race([evaluationPromise, timeoutPromise]);
      const duration = performance.now() - startTime;

      this.clearTimeout();

      // Record performance metrics
      performanceMonitor.recordApiCall(
        apiEndpoint,
        duration,
        result.success ?? false
      );

      if (result.success && result.fields) {
        return {
          success: true,
          fields: result.fields,
        };
      } else {
        const validationError = new ApiValidationError(
          result.message ?? 'Server returned an invalid response',
          { result }
        );
        return {
          success: false,
          error: validationError.message,
        };
      }
    } catch (error: any) {
      this.clearTimeout();

      const { xhr, textStatus, errorThrown } = error || {};

      if (xhr) {
        const ajaxError = this.handleAjaxError(xhr, textStatus, errorThrown);
        return {
          success: false,
          error: ajaxError.message,
        };
      }

      if (error === 'timeout') {
        const timeoutError = new ApiTimeoutError(this.requestTimeout);
        return {
          success: false,
          error: timeoutError.message,
        };
      }

      const networkError = new NetworkError(
        0,
        error instanceof Error ? error.message : 'An unexpected error occurred'
      );
      return {
        success: false,
        error: networkError.message,
      };
    } finally {
      this.evaluationInProgress = false;
    }
  }

  /**
   * Check if evaluation is currently in progress
   */
  public isEvaluationInProgress(): boolean {
    return this.evaluationInProgress;
  }

  /**
   * Cancel any ongoing evaluation
   */
  public cancelEvaluation(): void {
    this.clearTimeout();
    this.evaluationInProgress = false;
  }

  /**
   * Create a timeout promise that rejects after the specified time
   */
  private createTimeoutPromise(): Promise<never> {
    return new Promise((_, reject) => {
      this.evaluationTimeout = window.setTimeout(() => {
        reject('timeout');
      }, this.requestTimeout);
    });
  }

  /**
   * Clear the evaluation timeout
   */
  private clearTimeout(): void {
    if (this.evaluationTimeout) {
      clearTimeout(this.evaluationTimeout);
      this.evaluationTimeout = null;
    }
  }

  /**
   * Handle AJAX errors with appropriate error messages
   */
  private handleAjaxError(
    xhr: any,
    textStatus: string,
    errorThrown: string
  ): ConditionalFieldError {
    this.logAjaxError(xhr, textStatus, errorThrown);

    const status = this.getErrorStatus(xhr);

    return this.createErrorForStatus(status, textStatus, errorThrown);
  }

  /**
   * Log AJAX error details for debugging
   */
  private logAjaxError(
    xhr: any,
    textStatus: string,
    errorThrown: string
  ): void {
    console.error(
      '[ConditionalApiService] AJAX Error:',
      xhr?.status,
      textStatus,
      errorThrown
    );
  }

  /**
   * Extract status code from error response
   */
  private getErrorStatus(xhr: any): number {
    return xhr?.status ?? 0;
  }

  /**
   * Create appropriate error based on HTTP status
   */
  private createErrorForStatus(
    status: number,
    textStatus: string,
    errorThrown: string
  ): ConditionalFieldError {
    switch (status) {
      case 400:
        return new ApiValidationError(
          'Invalid form data. Please check your input and try again.',
          { status, textStatus, errorThrown }
        );

      case 403:
        return new NetworkError(
          status,
          'You do not have permission to update this form.'
        );

      case 429:
        return new RateLimitError();

      default:
        return this.createErrorForStatusRange(status, textStatus, errorThrown);
    }
  }

  /**
   * Handle status ranges and fallback cases
   */
  private createErrorForStatusRange(
    status: number,
    textStatus: string,
    // eslint-disable-next-line no-unused-vars -- Parameter part of jQuery error callback signature, reserved for future use.
    errorThrown: string
  ): ConditionalFieldError {
    if (status >= 500) {
      return new NetworkError(
        status,
        'Server error occurred. Please try again later.'
      );
    }

    if (textStatus === 'timeout') {
      return new ApiTimeoutError(this.requestTimeout);
    }

    return new NetworkError(status, 'Failed to update form. Please try again.');
  }
}
