import apiFetch from '@wordpress/api-fetch';
import type {
  AudienceLookup,
  AudienceOption,
  Campaign,
  CampaignCollection,
  CampaignQuery,
  TemplateOption,
} from './types';

const NAMESPACE = '/campaignbridge/v1';

/**
 * Append query arguments; arrays become repeated `key[]` pairs, which the
 * WordPress REST API parses into arrays.
 */
export function withQuery(path: string, args: Record<string, unknown>): string {
  const pairs: string[] = [];
  for (const [key, value] of Object.entries(args)) {
    if (value === null || value === undefined) continue;
    const values = Array.isArray(value) ? value : [value];
    const name = Array.isArray(value) ? `${key}[]` : key;
    for (const item of values) {
      pairs.push(
        `${encodeURIComponent(name)}=${encodeURIComponent(String(item))}`
      );
    }
  }

  return pairs.length ? `${path}?${pairs.join('&')}` : path;
}

/** Query arguments for GET /campaigns; empty filters are omitted. */
export function campaignQueryArgs(
  query: CampaignQuery
): Record<string, unknown> {
  const args: Record<string, unknown> = {
    page: query.page,
    per_page: query.perPage,
  };
  if (query.states.length) {
    args.state = query.states;
  }
  if (query.provider) {
    args.provider = query.provider;
  }
  if (query.ownerUserId) {
    args.owner_user_id = query.ownerUserId;
  }

  return args;
}

export function listCampaigns(
  query: CampaignQuery
): Promise<CampaignCollection> {
  return apiFetch<CampaignCollection>({
    path: withQuery(`${NAMESPACE}/campaigns`, campaignQueryArgs(query)),
  });
}

export async function createCampaign(input: {
  templateId: number;
  provider: string | null;
  audienceReference: string | null;
}): Promise<Campaign> {
  const response = await apiFetch<{ campaign: Campaign }>({
    path: `${NAMESPACE}/campaigns`,
    method: 'POST',
    data: {
      template_id: input.templateId,
      provider: input.provider,
      audience_reference: input.audienceReference,
    },
  });

  return response.campaign;
}

export async function archiveCampaign(campaign: Campaign): Promise<Campaign> {
  const response = await apiFetch<{ campaign: Campaign }>({
    path: `${NAMESPACE}/campaigns/${encodeURIComponent(campaign.id)}/archive`,
    method: 'POST',
    data: { expected_version: campaign.version },
  });

  return response.campaign;
}

/**
 * Duplicate a campaign. The caller supplies one idempotency key per operator
 * intent, so a retried request returns the same copy instead of a second one.
 */
export async function duplicateCampaign(
  campaign: Campaign,
  idempotencyKey: string
): Promise<Campaign> {
  const response = await apiFetch<{ campaign: Campaign }>({
    path: `${NAMESPACE}/campaigns/${encodeURIComponent(campaign.id)}/duplicate`,
    method: 'POST',
    data: { idempotency_key: idempotencyKey },
  });

  return response.campaign;
}

/** Published email templates the current user can read, by title. */
export async function listTemplates(
  restBase: string
): Promise<TemplateOption[]> {
  const templates = await apiFetch<
    Array<{ id: number; title: { rendered: string } }>
  >({
    path: withQuery(`/wp/v2/${restBase}`, {
      status: 'publish',
      per_page: 100,
      orderby: 'title',
      order: 'asc',
      _fields: 'id,title',
    }),
  });

  return templates.map(template => ({
    id: template.id,
    title: decodeTitle(template.title.rendered),
  }));
}

/** Cached audiences, or a fresh provider read when `refresh` is set. */
export async function lookupAudiences(
  provider: string,
  refresh: boolean
): Promise<AudienceLookup> {
  const path = `${NAMESPACE}/providers/${encodeURIComponent(provider)}/discovery/audiences`;
  const response = await apiFetch<{
    items: AudienceOption[];
    stale: boolean;
    fetched_at: string | null;
    error: { message: string } | null;
  }>(refresh ? { path: `${path}/refresh`, method: 'POST' } : { path });

  return {
    items: response.items,
    stale: response.stale,
    fetchedAt: response.fetched_at,
    error: response.error?.message ?? null,
  };
}

/** A per-intent idempotency key for operations that must not repeat. */
export function newIdempotencyKey(prefix: string): string {
  const random =
    typeof globalThis.crypto?.randomUUID === 'function'
      ? globalThis.crypto.randomUUID()
      : `${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}`;

  return `${prefix}-${random}`.slice(0, 64);
}

/** Template titles arrive HTML-encoded from the REST API. */
export function decodeTitle(rendered: string): string {
  const element = document.createElement('textarea');
  element.innerHTML = rendered;

  return element.value.trim();
}
