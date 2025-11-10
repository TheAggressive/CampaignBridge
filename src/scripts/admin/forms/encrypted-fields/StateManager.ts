/**
 * State Manager
 *
 * Manages application state for encrypted fields (timeouts, values, etc.)
 *
 * @package CampaignBridge
 */

import type { HandlerState } from './types';

/**
 * Manages state for encrypted fields functionality
 */
export class StateManager implements HandlerState {
  public revealTimeouts: Map<string, number> = new Map();
  public requestQueue: Map<string, string> = new Map();
  public originalValues: Map<string, string> = new Map();
  public revealedValues: Map<string, string> = new Map();

  /**
   * Initialize state from existing fields
   */
  initializeFromFields(displayInputs: NodeListOf<HTMLInputElement>): void {
    displayInputs.forEach(input => {
      const field = input.closest(
        '.campaignbridge-encrypted-field'
      ) as HTMLElement;
      if (field) {
        const fieldId = field.dataset.fieldId;
        if (fieldId && input.value) {
          // Store the initial masked value (as rendered by PHP)
          this.originalValues.set(fieldId, input.value);
        }
      }
    });
  }

  /**
   * Store a revealed value for a field
   */
  storeRevealedValue(fieldId: string, value: string): void {
    this.revealedValues.set(fieldId, value);
  }

  /**
   * Get stored revealed value for a field
   */
  getRevealedValue(fieldId: string): string | undefined {
    return this.revealedValues.get(fieldId);
  }

  /**
   * Clear revealed value for a field
   */
  clearRevealedValue(fieldId: string): void {
    this.revealedValues.delete(fieldId);
  }

  /**
   * Check if field has revealed value
   */
  hasRevealedValue(fieldId: string): boolean {
    return this.revealedValues.has(fieldId);
  }

  /**
   * Get original masked value for a field
   */
  getOriginalValue(fieldId: string): string | undefined {
    return this.originalValues.get(fieldId);
  }

  /**
   * Store a reveal timeout for a field
   */
  storeTimeout(fieldId: string, timeoutId: number): void {
    this.revealTimeouts.set(fieldId, timeoutId);
  }

  /**
   * Get stored timeout for a field
   */
  getTimeout(fieldId: string): number | undefined {
    return this.revealTimeouts.get(fieldId);
  }

  /**
   * Clear timeout for a field
   */
  clearTimeout(fieldId: string): void {
    const timeoutId = this.revealTimeouts.get(fieldId);
    if (timeoutId) {
      clearTimeout(timeoutId);
      this.revealTimeouts.delete(fieldId);
    }
  }

  /**
   * Check if request is already in queue
   */
  isRequestInQueue(action: string): boolean {
    return this.requestQueue.has(action);
  }

  /**
   * Add request to queue
   */
  addRequestToQueue(action: string, requestId: string): void {
    this.requestQueue.set(action, requestId);
  }

  /**
   * Remove request from queue
   */
  removeRequestFromQueue(action: string): void {
    this.requestQueue.delete(action);
  }

  /**
   * Clear all timeouts
   */
  clearAllTimeouts(): void {
    for (const timeoutId of this.revealTimeouts.values()) {
      clearTimeout(timeoutId);
    }
    this.revealTimeouts.clear();
  }

  /**
   * Clear all state (for cleanup)
   */
  clearAll(): void {
    this.clearAllTimeouts();
    this.originalValues.clear();
    this.revealedValues.clear();
    this.requestQueue.clear();
  }

  /**
   * Get state summary for debugging
   */
  getStateSummary(): {
    timeouts: number;
    originalValues: number;
    revealedValues: number;
    activeRequests: number;
  } {
    return {
      timeouts: this.revealTimeouts.size,
      originalValues: this.originalValues.size,
      revealedValues: this.revealedValues.size,
      activeRequests: this.requestQueue.size,
    };
  }
}
