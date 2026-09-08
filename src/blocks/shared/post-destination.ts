import { useSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';

import { fetchPostTypes, type PostTypeItem } from './post-types';

export type Destination =
  'article' | 'postParent' | 'postTypeArchive' | 'custom';

interface PostRecord {
  link?: string;
  parent?: number;
}

interface CoreSelectors {
  getEntityRecord: (
    kind: string,
    name: string,
    id: number
  ) => PostRecord | null;
}

const DESTINATIONS: readonly Destination[] = [
  'article',
  'postParent',
  'postTypeArchive',
  'custom',
];

export function normalizeDestination(value: unknown): Destination {
  return typeof value === 'string' &&
    DESTINATIONS.includes(value as Destination)
    ? (value as Destination)
    : 'article';
}

export function isHttpsUrl(value: string): boolean {
  try {
    const protocol = new URL(value).protocol;
    return protocol === 'https:' || protocol === 'http:';
  } catch {
    return false;
  }
}
export interface DestinationPreview {
  previewUrl: string;
  destinationHelp: string;
}

/**
 * Resolve the preview URL and help message for the editor canvas.
 *
 * Pure and dependency-free so it can be unit-tested directly. Callers own
 * the i18n help strings: pass `helpMessages` to override the canonical
 * English strings with localized text.
 *
 * @param args destination, candidate URLs, and optional help overrides.
 */
export function resolveDestinationPreview(args: {
  destination: Destination;
  customUrl: string;
  articleUrl: string;
  postParentUrl: string;
  postTypeArchiveUrl: string;
  helpMessages?: {
    customUrlRequired?: string;
    noAbsoluteUrlYet?: string;
  };
}): DestinationPreview {
  const {
    destination,
    customUrl,
    articleUrl,
    postParentUrl,
    postTypeArchiveUrl,
    helpMessages,
  } = args;

  let previewUrl = '';
  if (destination === 'custom') {
    previewUrl = customUrl;
  } else if (destination === 'postParent' && isHttpsUrl(postParentUrl)) {
    previewUrl = postParentUrl;
  } else if (
    destination === 'postTypeArchive' &&
    isHttpsUrl(postTypeArchiveUrl)
  ) {
    previewUrl = postTypeArchiveUrl;
  } else if (isHttpsUrl(articleUrl)) {
    previewUrl = articleUrl;
  }

  if (previewUrl) {
    return { previewUrl, destinationHelp: '' };
  }

  const destinationHelp =
    destination === 'custom'
      ? (helpMessages?.customUrlRequired ??
        'Enter a custom absolute URL to preview it.')
      : (helpMessages?.noAbsoluteUrlYet ??
        'This post snapshot has no absolute URL yet; the link renders from the post data at send time.');

  return { previewUrl, destinationHelp };
}

/**
 * Shared post-destination preview data for the post button block inside a post card.
 *
 * Reads the immutable post snapshot from the core entity store (so a preview
 * URL is available without a server round-trip) and fetches the configured
 * post types once per editor session. Both `post-button` and `post-link` use
 * this hook so the lookup logic lives in exactly one place.
 */
export function usePostDestination(
  postId: number,
  postType: string
): {
  articleUrl: string;
  postParentUrl: string;
  postTypeArchiveUrl: string | null;
} {
  const [postTypes, setPostTypes] = useState<PostTypeItem[]>([]);
  useEffect(() => {
    let active = true;
    fetchPostTypes()
      .then(items => {
        if (active) setPostTypes(items);
      })
      .catch(() => {
        if (active) setPostTypes([]);
      });

    return () => {
      active = false;
    };
  }, []);

  const post = useSelect(
    select =>
      postId
        ? (select('core') as unknown as CoreSelectors).getEntityRecord(
            'postType',
            postType,
            postId
          )
        : null,
    [postType, postId]
  );
  const postParentId = Number(post?.parent) || 0;
  const postParent = useSelect(
    select =>
      postParentId
        ? (select('core') as unknown as CoreSelectors).getEntityRecord(
            'postType',
            postType,
            postParentId
          )
        : null,
    [postType, postParentId]
  );

  return {
    articleUrl: post?.link ?? '',
    postParentUrl: postParent?.link ?? '',
    postTypeArchiveUrl:
      postTypes.find(item => item.id === postType)?.archive_url ?? null,
  };
}
