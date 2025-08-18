// REST API client
export class ApiClient {
  constructor() {
    this.baseUrl = window.wpApiSettings?.root || '/wp-json/';
    this.nonce = window.wpApiSettings?.nonce;
  }

  async request(path, { method = 'GET', body } = {}) {
    const url = `${this.baseUrl}campaignbridge/v1${path}`;
    const headers = { 'Content-Type': 'application/json' };

    if (this.nonce) {
      headers['X-WP-Nonce'] = this.nonce;
    }

    const response = await fetch(url, {
      method,
      headers,
      credentials: 'same-origin',
      body: body ? JSON.stringify(body) : undefined,
    });

    return response.json();
  }

  async get(path) {
    return this.request(path, { method: 'GET' });
  }

  async post(path, body) {
    return this.request(path, { method: 'POST', body });
  }
}
