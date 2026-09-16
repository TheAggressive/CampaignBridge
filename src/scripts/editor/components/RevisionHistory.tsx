import apiFetch from '@wordpress/api-fetch';
import { Button, Modal, Notice, Spinner } from '@wordpress/components';
import { time } from '@wordpress/icons';
import { __, sprintf } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';

/** Revisions requested per page of core's revisions collection. */
export const REVISIONS_PER_PAGE = 20;

interface Revision {
  id: number;
  date: string;
  date_gmt: string;
  author: number;
  parent: number;
  slug: string;
}

interface RevisionPage {
  revisions: Revision[];
  total: number;
  totalPages: number;
}

interface Pagination {
  page: number;
  total: number;
  totalPages: number;
}

const NO_PAGES: Pagination = { page: 0, total: 0, totalPages: 0 };

/**
 * Autosaves are excluded from the revisions request itself. This mirrors
 * wp_is_post_autosave() only to guard against an autosave created between
 * the two requests; the restore route refuses autosaves regardless.
 */
function isAutosave(revision: Revision): boolean {
  return revision.slug.includes(`${revision.parent}-autosave`);
}

interface RevisionHistoryProps {
  postId: number;
  postType: string;
  isOpen: boolean;
  onRequestClose: () => void;
  onRestore: (
    revisionId: number
  ) => Promise<{ success: boolean; error?: string }>;
  /** The template has unsaved canonical edits, so restore cannot proceed. */
  hasEdits?: boolean;
}

/** Revision dates are stored in GMT; show them in the viewer's time zone. */
function formatRevisionDate(revision: Revision): string {
  const date = new Date(
    revision.date_gmt ? `${revision.date_gmt}Z` : revision.date
  );
  if (Number.isNaN(date.getTime())) {
    return revision.date;
  }

  return date.toLocaleString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

/**
 * Read one page of normal revisions with core's authoritative pagination
 * headers. Core-data's revisions resolver is not used because it discards
 * request errors, which would present a failed load as an empty history.
 */
async function fetchRevisionPage(
  postType: string,
  postId: number,
  page: number,
  autosaveIds: readonly number[]
): Promise<RevisionPage> {
  const response = await apiFetch<Response, false>({
    path: addQueryArgs(`/wp/v2/${postType}/${postId}/revisions`, {
      per_page: REVISIONS_PER_PAGE,
      page,
      order: 'desc',
      orderby: 'date',
      ...(autosaveIds.length > 0 ? { exclude: autosaveIds.join(',') } : {}),
    }),
    parse: false,
  });
  const revisions = (await response.json()) as Revision[];

  return {
    revisions: revisions.filter(revision => !isAutosave(revision)),
    total: Number(response.headers.get('X-WP-Total') ?? 0),
    totalPages: Number(response.headers.get('X-WP-TotalPages') ?? 0),
  };
}

export default function RevisionHistory({
  postId,
  postType,
  isOpen,
  onRequestClose,
  onRestore,
  hasEdits = false,
}: RevisionHistoryProps): JSX.Element | null {
  const [revisions, setRevisions] = useState<Revision[] | null>(null);
  const [pagination, setPagination] = useState<Pagination>(NO_PAGES);
  const [isLoading, setIsLoading] = useState(false);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [restoreError, setRestoreError] = useState<string | null>(null);
  const [restoringId, setRestoringId] = useState<number | null>(null);
  const [confirmId, setConfirmId] = useState<number | null>(null);
  // Blocks a second confirm in the same tick, before the busy state renders.
  const restoringRef = useRef(false);
  // Blocks a second page request in the same tick.
  const loadingMoreRef = useRef(false);
  // Only the newest history request may update the list.
  const generationRef = useRef(0);
  const autosaveIdsRef = useRef<number[]>([]);
  const listRef = useRef<HTMLUListElement>(null);
  const focusIndexRef = useRef<number | null>(null);

  /** Load page 1 from canonical server state, replacing earlier pages. */
  const loadHistory = useCallback(async () => {
    const generation = ++generationRef.current;
    loadingMoreRef.current = false;
    setIsLoadingMore(false);
    setIsLoading(true);
    setError(null);
    try {
      const autosaves = await apiFetch<{ id: number }[]>({
        path: addQueryArgs(`/wp/v2/${postType}/${postId}/autosaves`, {
          _fields: 'id',
        }),
      });
      const autosaveIds = autosaves.map(autosave => autosave.id);
      const firstPage = await fetchRevisionPage(
        postType,
        postId,
        1,
        autosaveIds
      );
      if (generation !== generationRef.current) {
        return;
      }
      autosaveIdsRef.current = autosaveIds;
      setRevisions(firstPage.revisions);
      setPagination({
        page: 1,
        total: firstPage.total,
        totalPages: firstPage.totalPages,
      });
    } catch {
      // Earlier history stays visible; only the failure is reported.
      if (generation === generationRef.current) {
        setError(
          __(
            'Revision history could not be loaded. Please try again.',
            'campaignbridge'
          )
        );
      }
    } finally {
      if (generation === generationRef.current) {
        setIsLoading(false);
      }
    }
  }, [postType, postId]);

  /** Append the next page, keeping every loaded revision visible. */
  const loadMore = useCallback(async () => {
    const nextPage = pagination.page + 1;
    if (
      loadingMoreRef.current ||
      revisions === null ||
      nextPage > pagination.totalPages
    ) {
      return;
    }

    const generation = generationRef.current;
    loadingMoreRef.current = true;
    setIsLoadingMore(true);
    setError(null);
    try {
      const page = await fetchRevisionPage(
        postType,
        postId,
        nextPage,
        autosaveIdsRef.current
      );
      if (generation !== generationRef.current) {
        return;
      }
      // A revision saved meanwhile shifts later pages; never list one twice.
      const loadedIds = new Set(revisions.map(revision => revision.id));
      const added = page.revisions.filter(
        revision => !loadedIds.has(revision.id)
      );
      focusIndexRef.current = added.length > 0 ? revisions.length : null;
      setRevisions([...revisions, ...added]);
      setPagination({
        page: nextPage,
        total: page.total,
        totalPages: page.totalPages,
      });
    } catch {
      if (generation === generationRef.current) {
        setError(
          __(
            'More revisions could not be loaded. Please try again.',
            'campaignbridge'
          )
        );
      }
    } finally {
      if (generation === generationRef.current) {
        loadingMoreRef.current = false;
        setIsLoadingMore(false);
      }
    }
  }, [pagination, postId, postType, revisions]);

  useEffect(() => {
    if (!isOpen) {
      // Discard any request still running when the modal closes.
      generationRef.current++;
      return;
    }

    setRevisions(null);
    setPagination(NO_PAGES);
    setConfirmId(null);
    setRestoringId(null);
    setRestoreError(null);
    void loadHistory();
  }, [isOpen, loadHistory]);

  // Move focus to the first revision a Load more request added.
  useEffect(() => {
    if (focusIndexRef.current === null) {
      return;
    }
    const item = listRef.current?.children[focusIndexRef.current];
    focusIndexRef.current = null;
    item?.querySelector<HTMLButtonElement>('button')?.focus();
  }, [revisions]);

  const handleRestore = useCallback(
    async (revisionId: number) => {
      if (restoringRef.current) {
        return;
      }

      restoringRef.current = true;
      setRestoringId(revisionId);
      setRestoreError(null);
      try {
        const result = await onRestore(revisionId);
        if (result.success) {
          onRequestClose();
        } else if (result.error) {
          setRestoreError(result.error);
        }
      } catch {
        setRestoreError(
          __(
            'This revision could not be restored. Please try again.',
            'campaignbridge'
          )
        );
      } finally {
        restoringRef.current = false;
        setRestoringId(null);
        setConfirmId(null);
      }
    },
    [onRestore, onRequestClose]
  );

  if (!isOpen) {
    return null;
  }

  const hasRevisions = revisions !== null && revisions.length > 0;
  const hasMore = pagination.page < pagination.totalPages;
  let status = '';
  if (isLoading && hasRevisions) {
    status = __('Refreshing revisions…', 'campaignbridge');
  } else if (isLoadingMore) {
    status = __('Loading more revisions…', 'campaignbridge');
  } else if (hasRevisions) {
    status = sprintf(
      /* translators: 1: number of revisions shown, 2: total number of revisions. */
      __('Showing %1$d of %2$d revisions.', 'campaignbridge'),
      revisions.length,
      Math.max(pagination.total, revisions.length)
    );
  }

  return (
    <Modal
      title={__('Revision History', 'campaignbridge')}
      onRequestClose={onRequestClose}
      isDismissible
      shouldCloseOnClickOutside
      className='cb-editor__revision-modal-frame'
      headerActions={
        <Button
          variant='tertiary'
          icon={time}
          label={__('Refresh revision list', 'campaignbridge')}
          text={__('Refresh', 'campaignbridge')}
          onClick={() => void loadHistory()}
          isBusy={isLoading}
          disabled={isLoading || restoringId !== null}
          accessibleWhenDisabled
          className='cb-editor__revision-header-action'
        />
      }
    >
      <div className='cb-editor__revision-list'>
        {hasEdits && (
          <Notice
            status='warning'
            isDismissible={false}
            className='cb-editor__revision-unsaved'
          >
            {__(
              'You have unsaved changes. Save them before restoring a revision.',
              'campaignbridge'
            )}
          </Notice>
        )}

        {isLoading && revisions === null && (
          <div className='cb-editor__revision-loading' role='status'>
            <Spinner />
            <span>{__('Loading revisions…', 'campaignbridge')}</span>
          </div>
        )}

        {error && (
          <p className='cb-editor__revision-error' role='alert'>
            {error}
          </p>
        )}

        {!isLoading &&
          !error &&
          revisions !== null &&
          revisions.length === 0 && (
            <p className='cb-editor__revision-empty'>
              {__(
                'No revisions yet. Revisions are created automatically when you save changes.',
                'campaignbridge'
              )}
            </p>
          )}

        {restoreError && (
          <p className='cb-editor__revision-error' role='alert'>
            {restoreError}
          </p>
        )}

        {hasRevisions && (
          <ul className='cb-editor__revision-items' ref={listRef}>
            {revisions.map(revision => {
              const date = formatRevisionDate(revision);
              return (
                <li
                  key={revision.id}
                  className='cb-editor__revision-item'
                  data-revision-id={revision.id}
                >
                  <span className='cb-editor__revision-date'>{date}</span>
                  <span className='cb-editor__revision-actions'>
                    {confirmId === revision.id ? (
                      <>
                        <span className='cb-editor__revision-confirm'>
                          {__(
                            'Restoring loads this revision as unsaved changes. The saved template stays unchanged until you save.',
                            'campaignbridge'
                          )}
                        </span>
                        <Button
                          variant='primary'
                          onClick={() => void handleRestore(revision.id)}
                          isBusy={restoringId === revision.id}
                          disabled={restoringId !== null}
                          accessibleWhenDisabled
                          aria-label={sprintf(
                            /* translators: %s: revision date and time. */
                            __('Restore revision from %s', 'campaignbridge'),
                            date
                          )}
                          className='cb-editor__revision-restore-confirm'
                        >
                          {__('Restore', 'campaignbridge')}
                        </Button>
                        <Button
                          variant='tertiary'
                          onClick={() => setConfirmId(null)}
                          disabled={restoringId !== null}
                        >
                          {__('Cancel', 'campaignbridge')}
                        </Button>
                      </>
                    ) : (
                      <Button
                        variant='secondary'
                        onClick={() => setConfirmId(revision.id)}
                        disabled={restoringId !== null}
                        aria-label={sprintf(
                          /* translators: %s: revision date and time. */
                          __('Restore revision from %s', 'campaignbridge'),
                          date
                        )}
                      >
                        {__('Restore', 'campaignbridge')}
                      </Button>
                    )}
                  </span>
                </li>
              );
            })}
          </ul>
        )}

        {revisions !== null && (
          <div className='cb-editor__revision-more'>
            <p className='cb-editor__revision-status' role='status'>
              {status}
            </p>
            {hasMore && (
              <Button
                variant='secondary'
                onClick={() => void loadMore()}
                isBusy={isLoadingMore}
                disabled={isLoadingMore || isLoading || restoringId !== null}
                accessibleWhenDisabled
                className='cb-editor__revision-load-more'
              >
                {__('Load more', 'campaignbridge')}
              </Button>
            )}
          </div>
        )}
      </div>
    </Modal>
  );
}
