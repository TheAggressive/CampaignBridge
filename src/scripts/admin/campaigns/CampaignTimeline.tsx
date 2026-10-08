import { Button, Notice, Spinner } from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { apiFailure } from './api';
import {
  actionLabel,
  attemptStatusLabel,
  eventDetails,
  getAttempts,
  getHistory,
  operationLabel,
  resultLabel,
  type DeliveryAttempt,
  type HistoryEvent,
} from './timeline';
import type { Campaign } from './types';

/**
 * Everything recorded about a campaign: the audit history, newest first, and
 * the delivery attempts behind each provider request. Reloads when the
 * campaign's version changes.
 */
export function CampaignTimeline({
  campaign,
}: {
  campaign: Campaign;
}): JSX.Element {
  const [events, setEvents] = useState<HistoryEvent[] | null>(null);
  const [attempts, setAttempts] = useState<DeliveryAttempt[] | null>(null);
  const [page, setPage] = useState(1);
  const [pages, setPages] = useState(1);
  const [loadingMore, setLoadingMore] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    setError(null);
    Promise.all([getHistory(campaign.id, 1), getAttempts(campaign.id)])
      .then(([history, delivery]) => {
        setEvents(history.items);
        setPage(1);
        setPages(history.pagination.total_pages);
        setAttempts(delivery.items);
      })
      .catch(caught =>
        setError(
          apiFailure(
            caught,
            __('The campaign history could not be loaded.', 'campaignbridge')
          ).message
        )
      );
  }, [campaign.id]);

  useEffect(load, [load, campaign.version]);

  const more = () => {
    setLoadingMore(true);
    getHistory(campaign.id, page + 1)
      .then(next => {
        setEvents(current => [...(current ?? []), ...next.items]);
        setPage(page + 1);
        setPages(next.pagination.total_pages);
      })
      .catch(() => undefined)
      .finally(() => setLoadingMore(false));
  };

  return (
    <section className='cb-admin-card campaignbridge-campaigns__timeline'>
      <h3>{__('History', 'campaignbridge')}</h3>
      {error && (
        <Notice status='error' isDismissible={false}>
          {error}{' '}
          <Button variant='link' onClick={load}>
            {__('Try again', 'campaignbridge')}
          </Button>
        </Notice>
      )}
      {!events && !error && (
        <p className='campaignbridge-campaigns__inline-status'>
          <Spinner /> {__('Loading history…', 'campaignbridge')}
        </p>
      )}
      {events && (
        <ol className='campaignbridge-campaigns__events'>
          {events.map(event => {
            const details = eventDetails(event);
            return (
              <li
                key={event.id}
                className={`campaignbridge-campaigns__event campaignbridge-campaigns__event--${event.result}`}
              >
                <div className='campaignbridge-campaigns__event-head'>
                  <strong>{actionLabel(event.action)}</strong>
                  {event.result !== 'success' && (
                    <span className='campaignbridge-campaigns__event-result'>
                      {resultLabel(event.result)}
                    </span>
                  )}
                </div>
                <div className='campaignbridge-campaigns__event-meta'>
                  <time dateTime={event.created_at}>
                    {new Date(event.created_at).toLocaleString()}
                  </time>
                  {event.actor.name && <span> · {event.actor.name}</span>}
                </div>
                {details.length > 0 && (
                  <div className='campaignbridge-campaigns__event-details'>
                    {details.join(' · ')}
                  </div>
                )}
              </li>
            );
          })}
        </ol>
      )}
      {events && page < pages && (
        <Button variant='secondary' onClick={more} isBusy={loadingMore}>
          {__('Show older', 'campaignbridge')}
        </Button>
      )}

      {attempts && attempts.length > 0 && (
        <>
          <h4>{__('Delivery attempts', 'campaignbridge')}</h4>
          <table className='widefat striped campaignbridge-campaigns__attempts'>
            <thead>
              <tr>
                <th scope='col'>{__('Request', 'campaignbridge')}</th>
                <th scope='col'>{__('Outcome', 'campaignbridge')}</th>
                <th scope='col'>{__('Started', 'campaignbridge')}</th>
                <th scope='col'>{__('Last update', 'campaignbridge')}</th>
                <th scope='col'>{__('Provider ID', 'campaignbridge')}</th>
              </tr>
            </thead>
            <tbody>
              {attempts.map(attempt => (
                <tr
                  key={attempt.id}
                  className={`campaignbridge-campaigns__attempt--${attempt.status}`}
                >
                  <td>{operationLabel(attempt.operation)}</td>
                  <td>{attemptStatusLabel(attempt.status)}</td>
                  <td>
                    <time dateTime={attempt.created_at}>
                      {new Date(attempt.created_at).toLocaleString()}
                    </time>
                  </td>
                  <td>
                    <time dateTime={attempt.updated_at}>
                      {new Date(attempt.updated_at).toLocaleString()}
                    </time>
                  </td>
                  <td>{attempt.remote_correlation ?? '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </>
      )}
    </section>
  );
}
