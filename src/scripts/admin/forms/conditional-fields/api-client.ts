/**
 * Thin API client wrapping jQuery.ajax to preserve current behavior.
 */

import type { ConditionalApiRequest, ConditionalApiResponse } from './types';

export class ConditionalApiClient {
  evaluate(
    endpoint: string,
    payload: ConditionalApiRequest
  ): Promise<ConditionalApiResponse> {
    return new Promise((resolve, reject) => {
      (window as any).jQuery.ajax({
        url: endpoint,
        method: 'POST',
        timeout: 25000,
        data: payload,
        success: (result: ConditionalApiResponse) => resolve(result),
        error: (xhr: any, textStatus: string, errorThrown: string) =>
          reject({ xhr, textStatus, errorThrown }),
      });
    });
  }
}
