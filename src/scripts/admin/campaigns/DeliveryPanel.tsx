import {
  Button,
  CheckboxControl,
  Modal,
  Notice,
  SelectControl,
  TextareaControl,
  TextControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { apiFailure, lookupAudiences, newIdempotencyKey } from './api';
import {
  createProviderDraft,
  deliveryOutcome,
  earliestSchedule,
  ensureMergeFields,
  getRemote,
  reconcileCampaign,
  scheduleCampaign,
  scheduleTime,
  sendCampaign,
  sendTest,
  unscheduleCampaign,
  type DeliveryResult,
  type RemoteReference,
} from './delivery';
import type { AudienceOption, Campaign, CampaignsConfig } from './types';

type Dialog = 'test' | 'schedule' | 'unschedule' | 'send' | null;

interface Props {
  campaign: Campaign;
  config: CampaignsConfig;
  onChange: (campaign: Campaign) => void;
}

interface Message {
  status: 'success' | 'warning' | 'error';
  text: string;
}

/** Provider handoff, tests, and guarded delivery for one campaign. */
export function DeliveryPanel({
  campaign,
  config,
  onChange,
}: Props): JSX.Element | null {
  // undefined while loading; null when the provider campaign does not exist yet.
  const [remote, setRemote] = useState<RemoteReference | null | undefined>(
    undefined
  );
  const [audience, setAudience] = useState<AudienceOption | null>(null);
  const [dialog, setDialog] = useState<Dialog>(null);
  const [busy, setBusy] = useState<string | null>(null);
  const [message, setMessage] = useState<Message | null>(null);
  const [draftKey, setDraftKey] = useState(() => newIdempotencyKey('draft'));

  const provider = config.providers.find(
    option => option.slug === campaign.provider
  );

  useEffect(() => {
    getRemote(campaign)
      .then(result => setRemote(result.remote))
      .catch(() => setRemote(null));
  }, [campaign.id, campaign.version]); // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => {
    if (!campaign.provider || !campaign.audience_reference) return;
    lookupAudiences(campaign.provider, false)
      .then(result =>
        setAudience(
          result.items.find(item => item.id === campaign.audience_reference) ??
            null
        )
      )
      .catch(() => setAudience(null));
  }, [campaign.provider, campaign.audience_reference]);

  if (!campaign.provider || !campaign.audience_reference) {
    return null;
  }

  const providerName = provider?.label ?? campaign.provider;
  const can = (action: Campaign['actions'][number]) =>
    campaign.actions.includes(action);
  const audienceName = audience
    ? audience.member_count === null
      ? audience.name
      : sprintf(
          /* translators: 1: audience name, 2: contact count. */
          _n(
            '%1$s (%2$d contact)',
            '%1$s (%2$d contacts)',
            audience.member_count,
            'campaignbridge'
          ),
          audience.name,
          audience.member_count
        )
    : campaign.audience_reference;

  const applied = (result: DeliveryResult, text: string) => {
    onChange(result.campaign);
    setRemote(result.remote);
    setMessage({ status: 'success', text });
  };

  const handoff = () => {
    setBusy('draft');
    setMessage(null);
    ensureMergeFields(campaign)
      .then(() => createProviderDraft(campaign, draftKey))
      .then(result =>
        applied(
          result,
          sprintf(
            /* translators: %s: provider name. */
            __('The %s draft is ready.', 'campaignbridge'),
            providerName
          )
        )
      )
      .catch(caught => {
        const failure = apiFailure(
          caught,
          __('The draft could not be created.', 'campaignbridge')
        );
        const outcome = deliveryOutcome(failure, 'draft');
        setMessage({ status: outcome.status, text: outcome.message });
        if (outcome.retry) setDraftKey(newIdempotencyKey('draft'));
        if (failure.currentVersion !== null) refresh();
      })
      .finally(() => setBusy(null));
  };

  const refresh = () => {
    getRemote(campaign)
      .then(result => {
        onChange(result.campaign);
        setRemote(result.remote);
      })
      .catch(() => undefined);
  };

  const reconcile = () => {
    setBusy('reconcile');
    setMessage(null);
    reconcileCampaign(campaign)
      .then(result =>
        applied(
          result,
          __('Reconciled with what the provider reports.', 'campaignbridge')
        )
      )
      .catch(caught =>
        setMessage({
          status: 'warning',
          text: apiFailure(
            caught,
            __('The campaign could not be reconciled.', 'campaignbridge')
          ).message,
        })
      )
      .finally(() => setBusy(null));
  };

  return (
    <section className='cb-admin-card campaignbridge-campaigns__delivery'>
      <div className='campaignbridge-campaigns__section-header'>
        <h3>{__('Delivery', 'campaignbridge')}</h3>
        <div className='campaignbridge-campaigns__actions'>
          {can('create_provider_draft') && (
            <Button
              variant='primary'
              onClick={handoff}
              isBusy={busy === 'draft'}
              disabled={busy !== null}
              accessibleWhenDisabled
            >
              {sprintf(
                /* translators: %s: provider name. */
                __('Create %s draft', 'campaignbridge'),
                providerName
              )}
            </Button>
          )}
          {can('test_send') && (
            <Button variant='secondary' onClick={() => setDialog('test')}>
              {__('Send a test', 'campaignbridge')}
            </Button>
          )}
          {can('schedule') && (
            <Button variant='secondary' onClick={() => setDialog('schedule')}>
              {__('Schedule', 'campaignbridge')}
            </Button>
          )}
          {can('unschedule') && (
            <Button variant='secondary' onClick={() => setDialog('unschedule')}>
              {__('Unschedule', 'campaignbridge')}
            </Button>
          )}
          {can('send') && (
            <Button
              variant='primary'
              isDestructive
              onClick={() => setDialog('send')}
            >
              {__('Send now', 'campaignbridge')}
            </Button>
          )}
          {can('reconcile') && (
            <Button
              variant='tertiary'
              onClick={reconcile}
              isBusy={busy === 'reconcile'}
              disabled={busy !== null}
              accessibleWhenDisabled
            >
              {__('Reconcile', 'campaignbridge')}
            </Button>
          )}
        </div>
      </div>

      <div aria-live='polite'>
        {message && (
          <Notice status={message.status} onRemove={() => setMessage(null)}>
            {message.text}
          </Notice>
        )}
      </div>

      <dl className='campaignbridge-campaigns__envelope'>
        <dt>{__('Audience', 'campaignbridge')}</dt>
        <dd>{audienceName}</dd>
        <dt>{__('Provider campaign', 'campaignbridge')}</dt>
        <dd>
          {remote === undefined
            ? __('Loading…', 'campaignbridge')
            : remote
              ? sprintf(
                  /* translators: 1: provider campaign ID, 2: observed state. */
                  __('%1$s (%2$s)', 'campaignbridge'),
                  remote.remote_id,
                  remote.observed_state
                )
              : __('Not created yet', 'campaignbridge')}
        </dd>
        {campaign.scheduled_for && (
          <>
            <dt>{__('Scheduled for', 'campaignbridge')}</dt>
            <dd>
              <time dateTime={campaign.scheduled_for}>
                {new Date(campaign.scheduled_for).toLocaleString()}
              </time>
            </dd>
          </>
        )}
      </dl>
      {campaign.state === 'unknown' && (
        <Notice status='warning' isDismissible={false}>
          {__(
            'The last delivery request was not confirmed, so the campaign may or may not have been scheduled or sent. Every delivery action stays blocked until you reconcile.',
            'campaignbridge'
          )}
        </Notice>
      )}
      {campaign.state === 'provider_draft' &&
        config.separateDelivery &&
        !can('send') &&
        can('test_send') && (
          <p className='campaignbridge-campaigns__guidance'>
            {__(
              'This site requires separation of duties, so someone other than the approver must schedule or send this campaign.',
              'campaignbridge'
            )}
          </p>
        )}

      {dialog === 'test' && (
        <TestDialog
          campaign={campaign}
          onClose={() => setDialog(null)}
          onDone={(result, text) => {
            setDialog(null);
            applied(result, text);
          }}
          onWarning={text => setMessage({ status: 'warning', text })}
        />
      )}
      {dialog === 'schedule' && (
        <DeliveryDialog
          kind='schedule'
          campaign={campaign}
          audienceName={audienceName}
          onClose={() => setDialog(null)}
          onDone={result => {
            setDialog(null);
            applied(result, __('The campaign is scheduled.', 'campaignbridge'));
          }}
          onUncertain={(text, result) => {
            setDialog(null);
            setMessage({ status: 'warning', text });
            if (result) onChange(result);
            refresh();
          }}
        />
      )}
      {dialog === 'send' && (
        <DeliveryDialog
          kind='send'
          campaign={campaign}
          audienceName={audienceName}
          onClose={() => setDialog(null)}
          onDone={result => {
            setDialog(null);
            applied(
              result,
              __(
                'Mailchimp accepted the campaign and is sending it. Reconcile later to record when it has been sent.',
                'campaignbridge'
              )
            );
          }}
          onUncertain={(text, result) => {
            setDialog(null);
            setMessage({ status: 'warning', text });
            if (result) onChange(result);
            refresh();
          }}
        />
      )}
      {dialog === 'unschedule' && (
        <DeliveryDialog
          kind='unschedule'
          campaign={campaign}
          audienceName={audienceName}
          onClose={() => setDialog(null)}
          onDone={result => {
            setDialog(null);
            applied(
              result,
              __('The campaign is no longer scheduled.', 'campaignbridge')
            );
          }}
          onUncertain={(text, result) => {
            setDialog(null);
            setMessage({ status: 'warning', text });
            if (result) onChange(result);
            refresh();
          }}
        />
      )}
    </section>
  );
}

interface TestDialogProps {
  campaign: Campaign;
  onClose: () => void;
  onDone: (result: DeliveryResult, text: string) => void;
  onWarning: (text: string) => void;
}

/** Send one test to up to five named addresses. */
function TestDialog({
  campaign,
  onClose,
  onDone,
  onWarning,
}: TestDialogProps): JSX.Element {
  const [key, setKey] = useState(() => newIdempotencyKey('test'));
  const [recipients, setRecipients] = useState('');
  const [format, setFormat] = useState<'html' | 'text'>('html');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const addresses = recipients
    .split(/[\s,;]+/u)
    .map(address => address.trim())
    .filter(Boolean);
  const valid =
    addresses.length > 0 &&
    addresses.length <= 5 &&
    addresses.every(address => /^[^\s@]+@[^\s@]+\.[^\s@]+$/u.test(address));

  const submit = () => {
    setBusy(true);
    setError(null);
    sendTest(campaign, addresses, format, key)
      .then(result =>
        onDone(
          result,
          sprintf(
            /* translators: %d: number of test recipients. */
            __('Test sent to %d address(es).', 'campaignbridge'),
            addresses.length
          )
        )
      )
      .catch(caught => {
        const outcome = deliveryOutcome(
          apiFailure(
            caught,
            __('The test could not be sent.', 'campaignbridge')
          ),
          'test'
        );
        if (outcome.status === 'warning') {
          onWarning(outcome.message);
          onClose();
          return;
        }
        setError(outcome.message);
        setKey(newIdempotencyKey('test'));
        setBusy(false);
      });
  };

  return (
    <Modal title={__('Send a test', 'campaignbridge')} onRequestClose={onClose}>
      <form
        className='campaignbridge-campaigns__form'
        onSubmit={event => {
          event.preventDefault();
          if (valid && !busy) submit();
        }}
      >
        {error && (
          <Notice status='error' isDismissible={false}>
            {error}
          </Notice>
        )}
        <TextareaControl
          label={__('Test recipients', 'campaignbridge')}
          help={__(
            'Up to five addresses, separated by commas or new lines. Only these addresses receive the test; the audience does not.',
            'campaignbridge'
          )}
          value={recipients}
          onChange={setRecipients}
          rows={3}
          __nextHasNoMarginBottom
        />
        <SelectControl
          id='campaignbridge-test-format'
          label={__('Format', 'campaignbridge')}
          value={format}
          onChange={value => setFormat(value === 'text' ? 'text' : 'html')}
          options={[
            { value: 'html', label: __('HTML', 'campaignbridge') },
            { value: 'text', label: __('Plain text', 'campaignbridge') },
          ]}
          __nextHasNoMarginBottom
          __next40pxDefaultSize
        />
        <div className='campaignbridge-campaigns__form-actions'>
          <Button variant='tertiary' onClick={onClose} disabled={busy}>
            {__('Cancel', 'campaignbridge')}
          </Button>
          <Button
            variant='primary'
            type='submit'
            isBusy={busy}
            disabled={!valid || busy}
            accessibleWhenDisabled
          >
            {__('Send test', 'campaignbridge')}
          </Button>
        </div>
      </form>
    </Modal>
  );
}

interface DeliveryDialogProps {
  kind: 'schedule' | 'unschedule' | 'send';
  campaign: Campaign;
  audienceName: string;
  onClose: () => void;
  onDone: (result: DeliveryResult) => void;
  onUncertain: (text: string, campaign: Campaign | null) => void;
}

/**
 * Confirmation for an action that reaches, or stops reaching, the audience.
 * The idempotency key is fixed when the dialog opens, so a double click or a
 * network retry is the same request and cannot deliver twice.
 */
function DeliveryDialog({
  kind,
  campaign,
  audienceName,
  onClose,
  onDone,
  onUncertain,
}: DeliveryDialogProps): JSX.Element {
  const [key, setKey] = useState(() => newIdempotencyKey(kind));
  const [confirmed, setConfirmed] = useState(false);
  const [when, setWhen] = useState(() => earliestSchedule(new Date()));
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const time = kind === 'schedule' ? scheduleTime(when, new Date()) : null;
  const timeError = time && 'error' in time ? time.error : null;
  const needsConfirmation = kind !== 'unschedule';
  const ready = !busy && (!needsConfirmation || confirmed) && !timeError;
  const audience = campaign.audience_reference ?? '';

  const titles = {
    schedule: __('Schedule campaign', 'campaignbridge'),
    unschedule: __('Unschedule campaign', 'campaignbridge'),
    send: __('Send campaign now', 'campaignbridge'),
  };

  const submit = () => {
    setBusy(true);
    setError(null);
    const request =
      kind === 'schedule' && time && 'utc' in time
        ? scheduleCampaign(campaign, time.utc, audience, key)
        : kind === 'send'
          ? sendCampaign(campaign, audience, key)
          : unscheduleCampaign(campaign, key);
    request.then(onDone).catch(caught => {
      const failure = apiFailure(
        caught,
        __('The request could not be completed.', 'campaignbridge')
      );
      const outcome = deliveryOutcome(failure, kind);
      // Unconfirmed, or the request consumed a version: close and reload,
      // so the next attempt starts from the campaign as it is now.
      if (!outcome.retry || failure.currentVersion !== null) {
        onUncertain(outcome.message, null);
        return;
      }
      // A definite refusal before any provider call may be retried, but only
      // as a new request.
      setError(outcome.message);
      setKey(newIdempotencyKey(kind));
      setBusy(false);
    });
  };

  return (
    <Modal title={titles[kind]} onRequestClose={onClose}>
      <form
        className='campaignbridge-campaigns__form'
        onSubmit={event => {
          event.preventDefault();
          if (ready) submit();
        }}
      >
        {error && (
          <Notice status='error' isDismissible={false}>
            {error}
          </Notice>
        )}
        {kind === 'schedule' && (
          <TextControl
            type='datetime-local'
            id='campaignbridge-schedule-at'
            label={__('Send at (your local time)', 'campaignbridge')}
            help={
              timeError ??
              (time && 'utc' in time
                ? sprintf(
                    /* translators: %s: UTC time. */
                    __('Mailchimp receives %s UTC.', 'campaignbridge'),
                    time.utc.replace('T', ' ').replace('Z', '')
                  )
                : undefined)
            }
            step={900}
            value={when}
            onChange={setWhen}
            __nextHasNoMarginBottom
            __next40pxDefaultSize
          />
        )}
        {kind === 'unschedule' ? (
          <p>
            {__(
              'The campaign returns to a Mailchimp draft and will not send until it is scheduled or sent again.',
              'campaignbridge'
            )}
          </p>
        ) : (
          <CheckboxControl
            id={`campaignbridge-confirm-${kind}`}
            label={sprintf(
              kind === 'send'
                ? /* translators: %s: audience name. */
                  __(
                    'Send this campaign to %s now. This cannot be undone.',
                    'campaignbridge'
                  )
                : /* translators: %s: audience name. */
                  __('Schedule this campaign for %s.', 'campaignbridge'),
              audienceName
            )}
            checked={confirmed}
            onChange={setConfirmed}
            __nextHasNoMarginBottom
          />
        )}
        <div className='campaignbridge-campaigns__form-actions'>
          <Button variant='tertiary' onClick={onClose} disabled={busy}>
            {__('Cancel', 'campaignbridge')}
          </Button>
          <Button
            variant='primary'
            isDestructive={kind === 'send'}
            type='submit'
            isBusy={busy}
            disabled={!ready}
            accessibleWhenDisabled
          >
            {titles[kind]}
          </Button>
        </div>
      </form>
    </Modal>
  );
}
