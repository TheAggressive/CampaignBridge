import apiFetch from '@wordpress/api-fetch';
import type { BrandKitPayload, BrandSlot, GoogleFontResult } from './types';

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

export async function saveBrandFonts(
  restUrl: string,
  fonts: Record<string, string>
): Promise<BrandKitPayload> {
  return apiFetch({
    url: restUrl,
    method: 'PUT',
    data: {
      fonts,
    },
  });
}

export async function searchGoogleFonts(
  restUrl: string,
  search: string
): Promise<GoogleFontResult[]> {
  const response = await apiFetch<{ fonts: GoogleFontResult[] }>({
    url: `${restUrl}/fonts?search=${encodeURIComponent(search)}`,
  });
  return response.fonts;
}

export async function addGoogleFont(
  restUrl: string,
  family: string,
  fonts: Record<string, string>
): Promise<BrandKitPayload> {
  return apiFetch({
    url: restUrl,
    method: 'PUT',
    data: { fonts, customFontFamily: family },
  });
}
