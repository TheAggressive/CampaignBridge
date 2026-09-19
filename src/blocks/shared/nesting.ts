import contract from '../../../includes/Email_Blocks/email-blocks.json';

/**
 * The email block nesting grammar, derived from the authoritative contract.
 *
 * `includes/Email_Blocks/email-blocks.json` is the single source for the
 * supported authoring blocks (WordPress Core and CampaignBridge), their email
 * semantics, and their permitted children. The PHP renderers read the same
 * file through `Email_Block_Contract`, so the editor presents exactly the tree
 * the server-side compiler enforces.
 */
export interface EmailBlockContractEntry {
  source: 'core' | 'campaignbridge';
  semantics: string;
  children: string[];
}

export const EMAIL_BLOCK_CONTRACT = contract.blocks as Record<
  string,
  EmailBlockContractEntry
>;

/** Every supported authoring block name. */
export const EMAIL_BLOCK_NAMES = Object.keys(EMAIL_BLOCK_CONTRACT);

/** Supported WordPress Core authoring block names. */
export const CORE_EMAIL_BLOCK_NAMES = EMAIL_BLOCK_NAMES.filter(
  name => EMAIL_BLOCK_CONTRACT[name]?.source === 'core'
);

function children(name: string): readonly string[] {
  return EMAIL_BLOCK_CONTRACT[name]?.children ?? [];
}

export const EMAIL_BLOCK_NESTING = {
  container: children('campaignbridge/container'),
  section: children('campaignbridge/section'),
  'post-card': children('campaignbridge/post-card'),
  columns: children('campaignbridge/columns'),
  column: children('campaignbridge/column'),
  buttons: children('core/buttons'),
  list: children('core/list'),
} as const;
