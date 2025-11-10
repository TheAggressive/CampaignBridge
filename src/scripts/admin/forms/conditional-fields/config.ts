/**
 * Configuration management for conditional fields
 */

export interface ConditionalFieldsConfig {
  debounceDelay: number;
  requestTimeout: number;
  cacheSize: number;
  maxRetries: number;
  enableDebugLogging: boolean;
  enablePerformanceMonitoring: boolean;
}

export class ConditionalFieldsConfigManager {
  private static instance: ConditionalFieldsConfigManager;
  private config: ConditionalFieldsConfig;

  private constructor() {
    this.config = this.loadConfig();
  }

  public static getInstance(): ConditionalFieldsConfigManager {
    if (!ConditionalFieldsConfigManager.instance) {
      ConditionalFieldsConfigManager.instance =
        new ConditionalFieldsConfigManager();
    }
    return ConditionalFieldsConfigManager.instance;
  }

  /**
   * Get the current configuration
   */
  public getConfig(): ConditionalFieldsConfig {
    return { ...this.config };
  }

  /**
   * Update configuration values
   */
  public updateConfig(updates: Partial<ConditionalFieldsConfig>): void {
    this.config = { ...this.config, ...updates };
  }

  /**
   * Get a specific configuration value
   */
  public get<K extends keyof ConditionalFieldsConfig>(
    key: K
  ): ConditionalFieldsConfig[K] {
    return this.config[key];
  }

  /**
   * Reset configuration to defaults
   */
  public resetToDefaults(): void {
    this.config = this.getDefaultConfig();
  }

  /**
   * Load configuration from various sources
   */
  private loadConfig(): ConditionalFieldsConfig {
    const defaults = this.getDefaultConfig();

    // Try to load from global window object (WordPress)
    const windowConfig = (window as any).CAMPAIGNBRIDGE_CONDITIONAL_CONFIG;
    if (windowConfig && typeof windowConfig === 'object') {
      return { ...defaults, ...windowConfig };
    }

    // Try to load from localStorage (for development)
    try {
      const stored = localStorage.getItem('campaignbridge_conditional_config');
      if (stored) {
        const parsed = JSON.parse(stored);
        return { ...defaults, ...parsed };
      }
    } catch {
      // Ignore localStorage errors
    }

    return defaults;
  }

  /**
   * Get default configuration values
   */
  private getDefaultConfig(): ConditionalFieldsConfig {
    return {
      debounceDelay: 100,
      requestTimeout: 30000,
      cacheSize: 10,
      maxRetries: 3,
      enableDebugLogging: (window as any).CAMPAIGNBRIDGE_DEBUG === true,
      enablePerformanceMonitoring: false,
    };
  }

  /**
   * Save configuration to localStorage (for development)
   */
  public saveToLocalStorage(): void {
    try {
      localStorage.setItem(
        'campaignbridge_conditional_config',
        JSON.stringify(this.config)
      );
    } catch {
      // Ignore localStorage errors.
    }
  }
}

// Export singleton instance
export const configManager = ConditionalFieldsConfigManager.getInstance();
