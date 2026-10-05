/** Campaign states, mirroring Campaign_State::all(). */
export type CampaignState =
  | 'draft'
  | 'ready_for_review'
  | 'approved'
  | 'provider_draft'
  | 'scheduled'
  | 'sending'
  | 'sent'
  | 'failed'
  | 'cancelled'
  | 'unknown'
  | 'archived';

/** Actions the server says the current user may take; see Campaign_Actions. */
export type CampaignAction = 'archive' | 'duplicate';

/** A campaign as published by Campaign_Rest_Resource. */
export interface Campaign {
  id: string;
  state: CampaignState;
  version: number;
  owner_user_id: number;
  template_id: number;
  provider: string | null;
  audience_reference: string | null;
  active_snapshot_id: string | null;
  created_at: string;
  updated_at: string;
  scheduled_for: string | null;
  approved_by_user_id: number | null;
  actions: CampaignAction[];
}

export interface Pagination {
  page: number;
  per_page: number;
  total: number;
  total_pages: number;
}

export interface CampaignCollection {
  items: Campaign[];
  pagination: Pagination;
}

/** Server-side collection query for GET /campaigns. */
export interface CampaignQuery {
  page: number;
  perPage: number;
  states: CampaignState[];
  provider: string | null;
  ownerUserId: number | null;
}

export interface TemplateOption {
  id: number;
  title: string;
}

export interface AudienceOption {
  id: string;
  name: string;
  member_count: number | null;
}

/** Normalized provider discovery lookup for audiences. */
export interface AudienceLookup {
  items: AudienceOption[];
  stale: boolean;
  fetchedAt: string | null;
  error: string | null;
}

export interface ProviderOption {
  slug: string;
  label: string;
  connected: boolean;
  audience: string;
}

/** Server-validated configuration localized by the Campaigns screen. */
export interface CampaignsConfig {
  currentUserId: number;
  canManageAll: boolean;
  templatesRestBase: string;
  newTemplateUrl: string;
  providersUrl: string;
  providers: ProviderOption[];
}

declare global {
  // Emitted only on the Campaigns screen.
  var campaignbridgeCampaigns: CampaignsConfig | undefined;
}
