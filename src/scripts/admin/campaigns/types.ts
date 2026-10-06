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
export type CampaignAction =
  | 'edit'
  | 'snapshot'
  | 'submit'
  | 'approve'
  | 'revoke_approval'
  | 'archive'
  | 'duplicate';

/** A compiler diagnostic with the block path it refers to. */
export interface Diagnostic {
  severity: 'error' | 'warning';
  code: string;
  path: string;
  message: string;
}

/** Subject, preview text, and sender frozen with a snapshot. */
export interface Envelope {
  subject: string;
  preview_text: string;
  from_name: string;
  from_email: string;
  complete: boolean;
  problems: string[];
}

export interface Snapshot {
  id: string;
  revision: number;
  fingerprint: string;
  created_at: string;
  envelope: Envelope | null;
}

/** The stored artifact of the active snapshot, as reviewed. */
export interface ReviewedArtifact {
  html: string;
  text: string;
  fingerprint: string;
  compiler_version: string;
  profile_version: string;
  sample: { html: string; text: string } | null;
}

export interface ReviewedSnapshot {
  campaign: Campaign;
  snapshot: Snapshot;
  artifact: ReviewedArtifact;
}

export interface Validation {
  valid: boolean;
  diagnostics: Diagnostic[];
  fingerprint: string | null;
}

/** The parts of a REST error envelope the screen acts on. */
export interface ApiFailure {
  code: string;
  message: string;
  diagnostics: Diagnostic[];
  currentVersion: number | null;
}

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
  editTemplateUrl: string;
  screenUrl: string;
  separateDelivery: boolean;
  providersUrl: string;
  providers: ProviderOption[];
}

declare global {
  // Emitted only on the Campaigns screen.
  var campaignbridgeCampaigns: CampaignsConfig | undefined;
}
