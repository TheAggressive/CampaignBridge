import apiFetch from '@wordpress/api-fetch';
import type { BrandKitPayload, BrandSlot } from './types';

export async function saveBrandSlot(
  restUrl: string,
  slot: BrandSlot
): Promise<BrandKitPayload> {
  return apiFetch({
    url: restUrl,
    method: 'PUT',
    data: {
      id: slot.id,
      color: slot.color,
    },
  });
}
