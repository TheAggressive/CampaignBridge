/**
 * Handles all API communication for conditional field evaluation
 */

import { ConditionalApiClient } from './api-client';
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
    this.requestTimeout = config.requestTimeout ?? 30000;
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

      this.clearTimeout();

      if (result.success && result.fields) {
        return {
          success: true,
          fields: result.fields,
        };
      } else {
        return {
          success: false,
          error: result.message ?? 'Server returned an invalid response',
        };
      }
    } catch (error: any) {
      this.clearTimeout();

      const { xhr, textStatus, errorThrown } = error || {};

      if (xhr) {
        return this.handleAjaxError(xhr, textStatus, errorThrown);
      }

      if (error === 'timeout') {
        return {
          success: false,
          error:
            'Request timed out. Please check your connection and try again.',
        };
      }

      return {
        success: false,
        error: error?.message ?? 'An unexpected error occurred',
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
  ): EvaluationResult {
    console.error(
      '[ConditionalApiService] AJAX Error:',
      xhr.status,
      textStatus,
      errorThrown
    );

    let errorMessage = 'Failed to update form. Please try again.';
    const status = xhr?.status ?? 0;

    if (status === 400) {
      errorMessage =
        'Invalid form data. Please check your input and try again.';
    } else if (status === 403) {
      errorMessage = 'You do not have permission to update this form.';
    } else if (status === 429) {
      errorMessage =
        'Too many requests. Please wait a moment before trying again.';
    } else if (status >= 500) {
      errorMessage = 'Server error occurred. Please try again later.';
    } else if (textStatus === 'timeout') {
      errorMessage =
        'Request timed out. Please check your connection and try again.';
    }

    return {
      success: false,
      error: errorMessage,
    };
  }
}
