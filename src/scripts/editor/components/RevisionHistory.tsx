import apiFetch from '@wordpress/api-fetch';
import { Button, Modal, Spinner } from '@wordpress/components';
import { time } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';
import { useCallback, useEffect, useState } from '@wordpress/element';

interface Revision {
  id: number;
  date: string;
  date_gmt: string;
  author: number;
}

interface RevisionHistoryProps {
  postId: number;
  postType: string;
  isOpen: boolean;
  onRequestClose: () => void;
  onRestore: (
    revisionId: number
  ) => Promise<{ success: boolean; error?: string }>;
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
}: RevisionHistoryProps): JSX.Element | null {
  const [revisions, setRevisions] = useState<Revision[] | null>(null);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [restoreError, setRestoreError] = useState<string | null>(null);
  const [restoringId, setRestoringId] = useState<number | null>(null);
  const [confirmId, setConfirmId] = useState<number | null>(null);

  const fetchRevisions = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const data = await apiFetch<Revision[]>({
        path: `/wp/v2/${postType}/${postId}/revisions?per_page=20&order=desc`,
      });
      setRevisions(data);
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
      setRestoringId(revisionId);
      setRestoreError(null);
      const result = await onRestore(revisionId);
      setRestoringId(null);
      setConfirmId(null);
      if (result.success) {
        onRequestClose();
      } else {
        setRestoreError(
          result.error || __('Failed to restore revision.', 'campaignbridge')
        );
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
