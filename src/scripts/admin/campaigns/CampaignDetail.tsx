import {
  Button,
  ExternalLink,
  Modal,
  Notice,
  Spinner,
} from '@wordpress/components';
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
  apiFailure,
  checkCampaign,
  getCampaign,
  getReviewedSnapshot,
  listTemplates,
  takeSnapshot,
  transitionCampaign,
  type ReviewTransition,
} from './api';
import { DeliveryPanel } from './DeliveryPanel';
import { Diagnostics } from './Diagnostics';
import { providerLabel, stateLabels } from './labels';
import { ReviewPreview } from './ReviewPreview';
import type {
  ApiFailure,
  Campaign,
  CampaignsConfig,
  ReviewedSnapshot,
  Validation,
} from './types';

const CONFLICT = 'campaignbridge_campaign_conflict';

/** One campaign's review page: what will be sent, its checks, and review actions. */
export function CampaignDetail({
  id,
  config,
}: {
  id: string;
  config: CampaignsConfig;
}): JSX.Element {
  const [campaign, setCampaign] = useState<Campaign | null>(null);
  const [reviewed, setReviewed] = useState<ReviewedSnapshot | null>(null);
  const [loadFailure, setLoadFailure] = useState<ApiFailure | null>(null);
  const [failure, setFailure] = useState<ApiFailure | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [busy, setBusy] = useState<string | null>(null);
  const [check, setCheck] = useState<Validation | null>(null);
  const [templateTitle, setTemplateTitle] = useState<string | null>(null);
  const [confirmRefresh, setConfirmRefresh] = useState(false);

  const labels = useMemo(stateLabels, []);
  const providerNames = useMemo(
    () =>
      Object.fromEntries(
        config.providers.map(option => [option.slug, option.label])
      ),
    [config.providers]
  );

  const loadReviewed = useCallback((current: Campaign) => {
    if (!current.active_snapshot_id) {
      setReviewed(null);
      return;
    }
    getReviewedSnapshot(current.id)
      .then(setReviewed)
      .catch(caught => {
        setReviewed(null);
        setFailure(
          apiFailure(
            caught,
            __('The reviewed email could not be loaded.', 'campaignbridge')
          )
        );
      });
  }, []);

  const load = useCallback(() => {
    setLoadFailure(null);
    setFailure(null);
    getCampaign(id)
      .then(current => {
        setCampaign(current);
        loadReviewed(current);
      })
      .catch(caught =>
        setLoadFailure(
          apiFailure(
            caught,
            __('The campaign could not be loaded.', 'campaignbridge')
          )
        )
      );
  }, [id, loadReviewed]);

  useEffect(load, [load]);

  useEffect(() => {
    if (!campaign) return;
    listTemplates(config.templatesRestBase)
      .then(templates =>
        setTemplateTitle(
          templates.find(template => template.id === campaign.template_id)
            ?.title ?? null
        )
      )
      .catch(() => setTemplateTitle(null));
  }, [campaign?.template_id, config.templatesRestBase]); // eslint-disable-line react-hooks/exhaustive-deps

  if (loadFailure) {
    return (
      <div className='campaignbridge-campaigns__detail'>
        <BackLink config={config} />
        <Notice status='error' isDismissible={false}>
          {loadFailure.message}
        </Notice>
      </div>
    );
  }

  if (!campaign) {
    return (
      <p className='campaignbridge-campaigns__inline-status'>
        <Spinner /> {__('Loading campaign…', 'campaignbridge')}
      </p>
    );
  }

  const can = (action: Campaign['actions'][number]) =>
    campaign.actions.includes(action);
  const title =
    templateTitle ??
    sprintf(
      /* translators: %d: template ID. */
      __('Template #%d', 'campaignbridge'),
      campaign.template_id
    );

  const run = (
    key: string,
    operation: () => Promise<Campaign>,
    done: string
  ) => {
    setBusy(key);
    setFailure(null);
    setNotice(null);
    operation()
      .then(updated => {
        setCampaign(updated);
        setNotice(done);
        loadReviewed(updated);
      })
      .catch(caught =>
        setFailure(
          apiFailure(
            caught,
            __('The campaign could not be updated.', 'campaignbridge')
          )
        )
      )
      .finally(() => setBusy(null));
  };

  const snapshot = () => {
    setConfirmRefresh(false);
    setCheck(null);
    run(
      'snapshot',
      () =>
        takeSnapshot(campaign).then(result => {
          setCheck(result.validation);
          return result.campaign;
        }),
      __('The email is ready for review.', 'campaignbridge')
    );
  };

  const transition = (to: ReviewTransition, done: string) =>
    run(to, () => transitionCampaign(campaign, to), done);

  const runCheck = () => {
    setBusy('check');
    setFailure(null);
    checkCampaign(campaign)
      .then(setCheck)
      .catch(caught => {
        const result = apiFailure(
          caught,
          __('The template could not be checked.', 'campaignbridge')
        );
        if (result.diagnostics.length) {
          setCheck({
            valid: false,
            diagnostics: result.diagnostics,
            fingerprint: null,
          });
        } else {
          setFailure(result);
        }
      })
      .finally(() => setBusy(null));
  };

  const envelope = reviewed?.snapshot.envelope ?? null;

  return (
    <div className='campaignbridge-campaigns__detail'>
      <BackLink config={config} />
      <header className='campaignbridge-campaigns__detail-header'>
        <h2>{title}</h2>
        <span
          className={`campaignbridge-campaigns__state campaignbridge-campaigns__state--${campaign.state}`}
        >
          {labels[campaign.state]}
        </span>
      </header>

      <div aria-live='polite'>
        {notice && (
          <Notice status='success' onRemove={() => setNotice(null)}>
            {notice}
          </Notice>
        )}
      </div>
      {failure && (
        <Notice status='error' isDismissible={false}>
          {failure.code === CONFLICT
            ? __(
                'This campaign changed since you opened it. Reload to see the latest version before trying again.',
                'campaignbridge'
              )
            : failure.message}{' '}
          {failure.code === CONFLICT && (
            <Button variant='link' onClick={load}>
              {__('Reload', 'campaignbridge')}
            </Button>
          )}
          <Diagnostics diagnostics={failure.diagnostics} />
        </Notice>
      )}

      <section className='cb-admin-card campaignbridge-campaigns__summary'>
        <dl>
          <dt>{__('Template', 'campaignbridge')}</dt>
          <dd>
            <ExternalLink
              href={`${config.editTemplateUrl}${campaign.template_id}`}
            >
              {title}
            </ExternalLink>
          </dd>
          <dt>{__('Delivery', 'campaignbridge')}</dt>
          <dd>{providerLabel(campaign.provider, providerNames)}</dd>
          <dt>{__('Audience', 'campaignbridge')}</dt>
          <dd>{campaign.audience_reference ?? '—'}</dd>
          <dt>{__('Updated', 'campaignbridge')}</dt>
          <dd>
            <time dateTime={campaign.updated_at}>
              {new Date(campaign.updated_at).toLocaleString()}
            </time>
          </dd>
        </dl>
      </section>

      <section className='cb-admin-card campaignbridge-campaigns__review'>
        <div className='campaignbridge-campaigns__section-header'>
          <h3>{__('Review', 'campaignbridge')}</h3>
          <div className='campaignbridge-campaigns__actions'>
            <Button
              variant='secondary'
              onClick={runCheck}
              isBusy={busy === 'check'}
              disabled={busy !== null}
              accessibleWhenDisabled
            >
              {__('Check current template', 'campaignbridge')}
            </Button>
            {can('snapshot') && (
              <Button
                variant={campaign.active_snapshot_id ? 'secondary' : 'primary'}
                onClick={() =>
                  campaign.active_snapshot_id && campaign.state !== 'draft'
                    ? setConfirmRefresh(true)
                    : snapshot()
                }
                isBusy={busy === 'snapshot'}
                disabled={busy !== null}
                accessibleWhenDisabled
              >
                {campaign.active_snapshot_id
                  ? __('Refresh from template', 'campaignbridge')
                  : __('Prepare for review', 'campaignbridge')}
              </Button>
            )}
            {can('submit') && (
              <Button
                variant='primary'
                onClick={() =>
                  transition(
                    'submit',
                    __('Submitted for review.', 'campaignbridge')
                  )
                }
                isBusy={busy === 'submit'}
                disabled={busy !== null}
                accessibleWhenDisabled
              >
                {__('Submit for review', 'campaignbridge')}
              </Button>
            )}
            {can('approve') && (
              <Button
                variant='primary'
                onClick={() =>
                  transition(
                    'approve',
                    __('Campaign approved.', 'campaignbridge')
                  )
                }
                isBusy={busy === 'approve'}
                disabled={busy !== null}
                accessibleWhenDisabled
              >
                {__('Approve', 'campaignbridge')}
              </Button>
            )}
            {can('revoke_approval') && (
              <Button
                variant='secondary'
                isDestructive
                onClick={() =>
                  transition(
                    'revoke-approval',
                    __(
                      'Approval revoked. The campaign is back in review.',
                      'campaignbridge'
                    )
                  )
                }
                isBusy={busy === 'revoke-approval'}
                disabled={busy !== null}
                accessibleWhenDisabled
              >
                {__('Revoke approval', 'campaignbridge')}
              </Button>
            )}
          </div>
        </div>

        <ReviewGuidance campaign={campaign} config={config} />

        {check && (
          <div className='campaignbridge-campaigns__check' role='status'>
            {check.valid && !check.diagnostics.length ? (
              <Notice status='success' isDismissible={false}>
                {__('The template passes every check.', 'campaignbridge')}
              </Notice>
            ) : (
              <Notice
                status={check.valid ? 'warning' : 'error'}
                isDismissible={false}
              >
                {check.valid
                  ? __(
                      'The template can be sent, with these warnings:',
                      'campaignbridge'
                    )
                  : __(
                      'Fix these problems in the template before it can be reviewed:',
                      'campaignbridge'
                    )}
                <Diagnostics diagnostics={check.diagnostics} />
              </Notice>
            )}
          </div>
        )}

        {envelope && (
          <dl className='campaignbridge-campaigns__envelope'>
            <dt>{__('Subject', 'campaignbridge')}</dt>
            <dd>{envelope.subject || '—'}</dd>
            <dt>{__('Preview text', 'campaignbridge')}</dt>
            <dd>{envelope.preview_text || '—'}</dd>
            <dt>{__('From', 'campaignbridge')}</dt>
            <dd>
              {envelope.from_name || envelope.from_email
                ? `${envelope.from_name} <${envelope.from_email}>`
                : '—'}
            </dd>
          </dl>
        )}
        {envelope && !envelope.complete && campaign.provider && (
          <Notice status='warning' isDismissible={false}>
            {__(
              'The provider will refuse this email until the template’s subject and sender are complete. Fix them in the template settings, then refresh from the template.',
              'campaignbridge'
            )}
          </Notice>
        )}

        {reviewed && <ReviewPreview artifact={reviewed.artifact} />}
        {campaign.active_snapshot_id && !reviewed && !failure && (
          <p className='campaignbridge-campaigns__inline-status'>
            <Spinner /> {__('Loading the reviewed email…', 'campaignbridge')}
          </p>
        )}
      </section>

      <DeliveryPanel
        campaign={campaign}
        config={config}
        onChange={updated => setCampaign(updated)}
      />

      {confirmRefresh && (
        <Modal
          title={__('Refresh from template?', 'campaignbridge')}
          onRequestClose={() => setConfirmRefresh(false)}
        >
          <p>
            {campaign.state === 'approved'
              ? __(
                  'The campaign takes the template’s current content and loses its approval. It goes back to review and must be approved again.',
                  'campaignbridge'
                )
              : __(
                  'The content waiting for review is replaced with the template’s current content.',
                  'campaignbridge'
                )}
          </p>
          <div className='campaignbridge-campaigns__form-actions'>
            <Button variant='tertiary' onClick={() => setConfirmRefresh(false)}>
              {__('Cancel', 'campaignbridge')}
            </Button>
            <Button variant='primary' onClick={snapshot}>
              {__('Refresh', 'campaignbridge')}
            </Button>
          </div>
        </Modal>
      )}
    </div>
  );
}

function BackLink({ config }: { config: CampaignsConfig }): JSX.Element {
  return (
    <a className='campaignbridge-campaigns__back' href={config.screenUrl}>
      ← {__('All campaigns', 'campaignbridge')}
    </a>
  );
}

/** Plain-language next step for the campaign's state. */
function ReviewGuidance({
  campaign,
  config,
}: {
  campaign: Campaign;
  config: CampaignsConfig;
}): JSX.Element | null {
  let text: string | null = null;
  if (!campaign.active_snapshot_id) {
    text = __(
      'Preparing for review freezes the template’s current content. What you approve is exactly what will be sent.',
      'campaignbridge'
    );
  } else if (campaign.state === 'draft') {
    text = __(
      'Review the email below, then submit it for approval.',
      'campaignbridge'
    );
  } else if (campaign.state === 'ready_for_review') {
    text = campaign.actions.includes('approve')
      ? __(
          'Approve the campaign if the email below is ready to send.',
          'campaignbridge'
        )
      : __('Waiting for someone who can approve campaigns.', 'campaignbridge');
  } else if (campaign.state === 'approved') {
    text = campaign.provider
      ? __(
          'Approved. Create the provider draft below to test and deliver it.',
          'campaignbridge'
        )
      : __(
          'Approved. Export the reviewed HTML from the preview to send it yourself.',
          'campaignbridge'
        );
  }

  if (!text) {
    return null;
  }

  return (
    <p className='campaignbridge-campaigns__guidance'>
      {text}
      {config.separateDelivery &&
        (campaign.state === 'ready_for_review' ||
          campaign.state === 'approved') && (
          <>
            {' '}
            {__(
              'This site requires separation of duties: the person who approves cannot schedule or send the campaign.',
              'campaignbridge'
            )}
          </>
        )}
    </p>
  );
}
