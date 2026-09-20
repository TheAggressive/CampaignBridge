import { registerBlockBindingsSource } from '@wordpress/blocks';
import { decodeEntities } from '@wordpress/html-entities';
import { __ } from '@wordpress/i18n';

import {
  EXCERPT_MAX_WORDS,
  POST_BINDING_SOURCE,
  truncateWords,
} from '../../blocks/shared/post-bindings';

/**
 * The one CampaignBridge post binding source.
 *
 * Registered read-only on purpose: it exposes no `setValues` and no
 * `canUserEditValue`, so WordPress disables editing of every bound attribute
 * and silently discards writes to it. Authoring an email template can
 * therefore never modify the article, page, or custom post it displays.
 *
 * The source resolves live post data for the editor canvas only. The email
 * compiler never runs it: it resolves the same semantics from the Post Card's
 * immutable snapshot, so a compiled campaign cannot vary with a later edit to
 * the source post.
 */

interface PostRecord {
  link?: string;
  parent?: number;
  title?: { rendered?: string };
  excerpt?: { rendered?: string };
  content?: { rendered?: string; raw?: string };
}

interface CoreSelectors {
  getEntityRecord: (
    kind: string,
    name: string,
    id: number
  ) => PostRecord | null | undefined;
}

interface BindingArgs {
  field?: string;
  maxWords?: number;
}

interface GetValuesArgs {
  select: (store: unknown) => unknown;
  context: Record<string, unknown>;
  bindings: Record<string, { args?: BindingArgs }>;
}

/** Post snapshot fields this source can resolve, in contract order. */
export const FIELD_LABELS: Record<string, string> = {
  title: __('Title', 'campaignbridge'),
  titleLink: __('Title, linked to the post', 'campaignbridge'),
  excerpt: __('Excerpt', 'campaignbridge'),
  content: __('Content', 'campaignbridge'),
  url: __('Post URL', 'campaignbridge'),
  postParentUrl: __('Parent post URL', 'campaignbridge'),
  postTypeArchiveUrl: __('Post type archive URL', 'campaignbridge'),
};

function selection(context: Record<string, unknown>): {
  postId: number;
  postType: string;
} {
  return {
    postId: Number(context['campaignbridge:postId']) || 0,
    postType:
      typeof context['campaignbridge:postType'] === 'string'
        ? context['campaignbridge:postType']
        : 'post',
  };
}

function archiveUrl(select: GetValuesArgs['select'], postType: string): string {
  // Addressed by store name so this module never pulls the whole block editor
  // into the bundle it registers from.
  const settings = (
    select('core/block-editor') as {
      getSettings: () => Record<string, unknown>;
    }
  ).getSettings();
  const archives = settings.campaignbridgePostTypeArchives;

  return archives && typeof archives === 'object'
    ? String((archives as Record<string, string>)[postType] ?? '')
    : '';
}

/** Resolve one snapshot field from live post data for the editor canvas. */
export function resolveField(
  select: GetValuesArgs['select'],
  context: Record<string, unknown>,
  args: BindingArgs
): string | undefined {
  const { postId, postType } = selection(context);
  if (!postId) {
    return undefined;
  }

  const core = select('core') as CoreSelectors;
  const post = core.getEntityRecord('postType', postType, postId);
  if (!post) {
    return undefined;
  }

  switch (args.field) {
    // The canvas previews live post data; the compiled preview remains the
    // source of truth for exactly what a campaign will send.
    case 'title':
    case 'titleLink':
      return decodeEntities(post.title?.rendered ?? '');
    case 'excerpt':
      return truncateWords(
        post.excerpt?.rendered ?? '',
        Number(args.maxWords) || EXCERPT_MAX_WORDS
      );
    case 'content':
      return truncateWords(
        post.content?.raw ?? post.content?.rendered ?? '',
        Number(args.maxWords) || EXCERPT_MAX_WORDS
      );
    case 'url':
      return post.link;
    case 'postParentUrl': {
      const parentId = Number(post.parent) || 0;
      return parentId
        ? (core.getEntityRecord('postType', postType, parentId)?.link ??
            undefined)
        : undefined;
    }
    case 'postTypeArchiveUrl':
      return archiveUrl(select, postType) || undefined;
    default:
      return undefined;
  }
}

/**
 * The source definition, exported so its read-only shape can be asserted.
 *
 * Neither `setValues` nor `canUserEditValue` is defined: that is what makes
 * WordPress disable editing of every bound attribute.
 */
export const POST_DATA_SOURCE = {
  name: POST_BINDING_SOURCE,
  label: __('Post Card content', 'campaignbridge'),
  usesContext: ['campaignbridge:postId', 'campaignbridge:postType'],
  getValues({ select, context, bindings }: GetValuesArgs) {
    const values: Record<string, string | undefined> = {};
    for (const [attribute, binding] of Object.entries(bindings)) {
      values[attribute] = resolveField(select, context, binding.args ?? {});
    }

    return values;
  },
  getFieldsList({ context }: { context: Record<string, unknown> }) {
    const { postId } = selection(context);

    return postId
      ? Object.entries(FIELD_LABELS).map(([field, label]) => ({
          label,
          args: { field },
        }))
      : [];
  },
};

registerBlockBindingsSource(POST_DATA_SOURCE);
