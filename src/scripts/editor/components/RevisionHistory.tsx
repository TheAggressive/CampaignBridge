import apiFetch from '@wordpress/api-fetch';
import { Button, Modal, Notice, Spinner } from '@wordpress/components';
import { time } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';

interface Revision {
  id: number;
  date: string;
  date_gmt: string;
  author: number;
  parent: number;
  slug: string;
}

/**
 * Core's revisions endpoint also returns autosaves. They are unsaved recovery
 * state, not template history, and the restore route refuses them. This
 * mirrors wp_is_post_autosave().
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

function formatRevisionDate(dateStr: string): string {
  const date = new Date(dateStr);
  if (Number.isNaN(date.getTime())) {
    return dateStr;
  }

  return date.toLocaleString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
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
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [restoreError, setRestoreError] = useState<string | null>(null);
  const [restoringId, setRestoringId] = useState<number | null>(null);
  const [confirmId, setConfirmId] = useState<number | null>(null);
  // Blocks a second confirm in the same tick, before the busy state renders.
  const restoringRef = useRef(false);

  const fetchRevisions = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const data = await apiFetch<Revision[]>({
        path: `/wp/v2/${postType}/${postId}/revisions?per_page=20&order=desc`,
      });
      setRevisions(data.filter(revision => !isAutosave(revision)));
    } catch {
      setError(__('Failed to load revision history.', 'campaignbridge'));
    } finally {
      setIsLoading(false);
    }
  }, [postType, postId]);

  useEffect(() => {
    if (isOpen) {
      void fetchRevisions();
      setConfirmId(null);
      setRestoringId(null);
      setRestoreError(null);
    }
  }, [isOpen, fetchRevisions]);

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
          onClick={() => void fetchRevisions()}
          disabled={isLoading}
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
          <div className='cb-editor__revision-loading'>
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

        {!isLoading && !error && revisions !== null && revisions.length > 0 && (
          <ul className='cb-editor__revision-items'>
            {revisions.map(revision => (
              <li key={revision.id} className='cb-editor__revision-item'>
                <span className='cb-editor__revision-date'>
                  {formatRevisionDate(revision.date)}
                </span>
                <span className='cb-editor__revision-actions'>
                  {confirmId === revision.id ? (
                    <>
                      <span className='cb-editor__revision-confirm'>
                        {__('Restore this version?', 'campaignbridge')}
                      </span>
                      <Button
                        variant='primary'
                        onClick={() => void handleRestore(revision.id)}
                        isBusy={restoringId === revision.id}
                        disabled={restoringId !== null}
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
                    >
                      {__('Restore', 'campaignbridge')}
                    </Button>
                  )}
                </span>
              </li>
            ))}
          </ul>
        )}
      </div>
    </Modal>
  );
}
