/**
 * Mailchimp Service - Handles Mailchimp API interactions
 * Follows Single Responsibility Principle - only handles Mailchimp operations
 *
 * @package CampaignBridge
 */

export class MailchimpService {
  constructor(apiClient) {
    this.api = apiClient;
    this.cache = new Map();
    this.cacheTimeout = 15 * 60 * 1000; // 15 minutes
  }

  /**
   * Get Mailchimp audiences (lists)
   * @param {boolean} refresh - Force refresh cache
   * @returns {Promise<Array>} Array of audiences
   */
  async getAudiences(refresh = false) {
    const cacheKey = 'audiences';

    if (!refresh && this.isCacheValid(cacheKey)) {
      return this.cache.get(cacheKey).data;
    }

    try {
      const response = await this.api.get('/mailchimp/audiences?refresh=1');
      const audiences = this.extractAudiences(response);

      this.setCache(cacheKey, audiences);
      return audiences;
    } catch (error) {
      throw new Error(`Failed to fetch Mailchimp audiences: ${error.message}`);
    }
  }

  /**
   * Get Mailchimp templates
   * @param {boolean} refresh - Force refresh cache
   * @returns {Promise<Array>} Array of templates
   */
  async getTemplates(refresh = false) {
    const cacheKey = 'templates';

    if (!refresh && this.isCacheValid(cacheKey)) {
      return this.cache.get(cacheKey).data;
    }

    try {
      const response = await this.api.get('/mailchimp/templates?refresh=1');
      const templates = this.extractTemplates(response);

      this.setCache(cacheKey, templates);
      return templates;
    } catch (error) {
      throw new Error(`Failed to fetch Mailchimp templates: ${error.message}`);
    }
  }

  /**
   * Get template section keys
   * @param {number} templateId - Template ID
   * @param {boolean} refresh - Force refresh cache
   * @returns {Promise<Array>} Array of section keys
   */
  async getSectionKeys(templateId, refresh = false) {
    const cacheKey = `sections_${templateId}`;

    if (!refresh && this.isCacheValid(cacheKey)) {
      return this.cache.get(cacheKey).data;
    }

    try {
      const response = await this.api.get(`/mailchimp/sections?refresh=1`);
      const sections = this.extractSections(response);

      this.setCache(cacheKey, sections);
      return sections;
    } catch (error) {
      throw new Error(`Failed to fetch template sections: ${error.message}`);
    }
  }

  /**
   * Verify Mailchimp API key
   * @param {string} apiKey - API key to verify
   * @returns {Promise<boolean>} True if valid
   */
  async verifyApiKey(apiKey = null) {
    try {
      const body = apiKey ? { api_key: apiKey } : {};
      const response = await this.api.post('/mailchimp/verify', body);
      return response?.ok === true;
    } catch (error) {
      return false;
    }
  }

  /**
   * Create campaign and update content
   * @param {Object} campaignData - Campaign data
   * @returns {Promise<Object>} Campaign result
   */
  async createCampaign(campaignData) {
    try {
      const response = await this.api.post(
        '/mailchimp/campaigns',
        campaignData
      );
      return this.extractCampaignResult(response);
    } catch (error) {
      throw new Error(`Failed to create campaign: ${error.message}`);
    }
  }

  /**
   * Extract audiences from API response
   * @param {Object} response - API response
   * @returns {Array} Array of audiences
   */
  extractAudiences(response) {
    const items = response?.items ?? response?.data?.items ?? [];
    return items.map((item) => ({
      id: String(item.id),
      name: String(item.name || item.label || ''),
    }));
  }

  /**
   * Extract templates from API response
   * @param {Object} response - API response
   * @returns {Array} Array of templates
   */
  extractTemplates(response) {
    const items = response?.items ?? response?.data?.items ?? [];
    return items.map((item) => ({
      id: Number(item.id),
      name: String(item.name || item.label || ''),
    }));
  }

  /**
   * Extract sections from API response
   * @param {Object} response - API response
   * @returns {Array} Array of section keys
   */
  extractSections(response) {
    return response?.sections ?? response?.data?.sections ?? [];
  }

  /**
   * Extract campaign result from API response
   * @param {Object} response - API response
   * @returns {Object} Campaign result
   */
  extractCampaignResult(response) {
    return {
      success: response?.ok === true,
      campaignId: response?.campaign_id,
      message: response?.message || 'Campaign created successfully',
    };
  }

  /**
   * Check if cache is valid
   * @param {string} key - Cache key
   * @returns {boolean} True if cache is valid
   */
  isCacheValid(key) {
    if (!this.cache.has(key)) return false;

    const cached = this.cache.get(key);
    const now = Date.now();

    return now - cached.timestamp < this.cacheTimeout;
  }

  /**
   * Set cache data
   * @param {string} key - Cache key
   * @param {any} data - Data to cache
   */
  setCache(key, data) {
    this.cache.set(key, {
      data,
      timestamp: Date.now(),
    });
  }

  /**
   * Clear cache
   * @param {string} key - Optional specific key to clear
   */
  clearCache(key = null) {
    if (key) {
      this.cache.delete(key);
    } else {
      this.cache.clear();
    }
  }

  /**
   * Get cache statistics
   * @returns {Object} Cache statistics
   */
  getCacheStats() {
    const now = Date.now();
    const stats = {
      totalEntries: this.cache.size,
      validEntries: 0,
      expiredEntries: 0,
      totalSize: 0,
    };

    this.cache.forEach((value, key) => {
      if (this.isCacheValid(key)) {
        stats.validEntries++;
      } else {
        stats.expiredEntries++;
      }
      stats.totalSize += JSON.stringify(value.data).length;
    });

    return stats;
  }
}
