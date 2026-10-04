import {
  Button,
  ExternalLink,
  Modal,
  Notice,
  SelectControl,
  Spinner,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { requestErrorMessage } from '../brand-kit/errors';
import { createCampaign, listTemplates, lookupAudiences } from './api';
import type {
  AudienceLookup,
  Campaign,
  CampaignsConfig,
  TemplateOption,
} from './types';

const HTML_EXPORT = '';

interface Props {
  config: CampaignsConfig;
  onClose: () => void;
  onCreated: (campaign: Campaign) => void;
}

/** Choose a template, a provider, and an audience, then create the campaign. */
export function CreateCampaignModal({
  config,
  onClose,
  onCreated,
}: Props): JSX.Element {
  const [templates, setTemplates] = useState<TemplateOption[] | null>(null);
  const [templateError, setTemplateError] = useState<string | null>(null);
  const [templateId, setTemplateId] = useState('');
  const [provider, setProvider] = useState(HTML_EXPORT);
  const [audiences, setAudiences] = useState<AudienceLookup | null>(null);
  const [audienceBusy, setAudienceBusy] = useState(false);
  const [audience, setAudience] = useState('');
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const connected = config.providers.filter(option => option.connected);
  const selectedProvider = connected.find(option => option.slug === provider);

  useEffect(() => {
    listTemplates(config.templatesRestBase)
      .then(setTemplates)
      .catch(caught =>
        setTemplateError(
          requestErrorMessage(
            caught,
            __('Email templates could not be loaded.', 'campaignbridge')
          )
        )
      );
  }, [config.templatesRestBase]);

  const loadAudiences = (slug: string, refresh: boolean) => {
    setAudienceBusy(true);
    lookupAudiences(slug, refresh)
      .then(result => {
        setAudiences(result);
        setAudience(current => {
          if (result.items.some(item => item.id === current)) return current;
          const preferred = connected.find(
            option => option.slug === slug
          )?.audience;
          return result.items.some(item => item.id === preferred)
            ? (preferred ?? '')
            : (result.items[0]?.id ?? '');
        });
      })
      .catch(caught =>
        setAudiences({
          items: [],
          stale: false,
          fetchedAt: null,
          error: requestErrorMessage(
            caught,
            __('Audiences could not be loaded.', 'campaignbridge')
          ),
        })
      )
      .finally(() => setAudienceBusy(false));
  };

  useEffect(() => {
    setAudiences(null);
    setAudience('');
    if (provider !== HTML_EXPORT) {
      loadAudiences(provider, false);
    }
    // loadAudiences reads only stable configuration.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [provider]);

  const needsAudience = provider !== HTML_EXPORT;
  const canSubmit =
    !saving && templateId !== '' && (!needsAudience || audience !== '');

  const submit = () => {
    if (!canSubmit) return;
    setSaving(true);
    setError(null);
    createCampaign({
      templateId: Number(templateId),
      provider: needsAudience ? provider : null,
      audienceReference: needsAudience ? audience : null,
    })
      .then(onCreated)
      .catch(caught => {
        setError(
          requestErrorMessage(
            caught,
            __('The campaign could not be created.', 'campaignbridge')
          )
        );
        setSaving(false);
      });
  };

  return (
    <Modal
      title={__('New campaign', 'campaignbridge')}
      onRequestClose={onClose}
      className='campaignbridge-campaigns__modal'
    >
      <form
        className='campaignbridge-campaigns__form'
        onSubmit={event => {
          event.preventDefault();
          submit();
        }}
      >
        {error && (
          <Notice status='error' isDismissible={false}>
            {error}
          </Notice>
        )}

        {templateError && (
          <Notice status='error' isDismissible={false}>
            {templateError}
          </Notice>
        )}
        {!templates && !templateError && (
          <p className='campaignbridge-campaigns__inline-status'>
            <Spinner /> {__('Loading email templates…', 'campaignbridge')}
          </p>
        )}
        {templates && templates.length === 0 && (
          <Notice status='warning' isDismissible={false}>
            {__(
              'There are no published email templates yet.',
              'campaignbridge'
            )}{' '}
            <ExternalLink href={config.newTemplateUrl}>
              {__('Create an email template', 'campaignbridge')}
            </ExternalLink>
          </Notice>
        )}
        {templates && templates.length > 0 && (
          <SelectControl
            label={__('Email template', 'campaignbridge')}
            help={__(
              'The campaign uses this template’s current content when it is reviewed.',
              'campaignbridge'
            )}
            value={templateId}
            onChange={setTemplateId}
            options={[
              { value: '', label: __('Select a template', 'campaignbridge') },
              ...templates.map(template => ({
                value: String(template.id),
                label:
                  template.title ||
                  sprintf(
                    /* translators: %d: template ID. */
                    __('Untitled template #%d', 'campaignbridge'),
                    template.id
                  ),
              })),
            ]}
            __nextHasNoMarginBottom
            __next40pxDefaultSize
          />
        )}

        <SelectControl
          label={__('Delivery', 'campaignbridge')}
          value={provider}
          onChange={setProvider}
          options={[
            {
              value: HTML_EXPORT,
              label: __('HTML export (no provider)', 'campaignbridge'),
            },
            ...connected.map(option => ({
              value: option.slug,
              label: option.label,
            })),
          ]}
          help={
            connected.length === 0
              ? __(
                  'Connect an email provider in Settings to send campaigns.',
                  'campaignbridge'
                )
              : undefined
          }
          __nextHasNoMarginBottom
          __next40pxDefaultSize
        />

        {needsAudience && (
          <div className='campaignbridge-campaigns__audience'>
            {audienceBusy && (
              <p className='campaignbridge-campaigns__inline-status'>
                <Spinner /> {__('Loading audiences…', 'campaignbridge')}
              </p>
            )}
            {audiences?.error && (
              <Notice status='error' isDismissible={false}>
                {audiences.error}
              </Notice>
            )}
            {audiences && !audienceBusy && audiences.items.length === 0 && (
              <Notice status='warning' isDismissible={false}>
                {__(
                  'No audiences are cached for this provider. Refresh to load them.',
                  'campaignbridge'
                )}
              </Notice>
            )}
            {audiences && audiences.items.length > 0 && (
              <SelectControl
                label={__('Audience', 'campaignbridge')}
                value={audience}
                onChange={setAudience}
                options={audiences.items.map(item => ({
                  value: item.id,
                  label:
                    item.member_count === null
                      ? item.name
                      : sprintf(
                          /* translators: 1: audience name, 2: contact count. */
                          __('%1$s (%2$d contacts)', 'campaignbridge'),
                          item.name,
                          item.member_count
                        ),
                }))}
                help={
                  audiences.stale
                    ? __(
                        'This list may be out of date. Refresh it to read the provider again.',
                        'campaignbridge'
                      )
                    : undefined
                }
                __nextHasNoMarginBottom
                __next40pxDefaultSize
              />
            )}
            <Button
              variant='link'
              onClick={() => loadAudiences(provider, true)}
              disabled={audienceBusy}
              aria-label={sprintf(
                /* translators: %s: provider name. */
                __('Refresh audiences from %s', 'campaignbridge'),
                selectedProvider?.label ?? provider
              )}
            >
              {__('Refresh audiences', 'campaignbridge')}
            </Button>
          </div>
        )}

        <div className='campaignbridge-campaigns__form-actions'>
          <Button variant='tertiary' onClick={onClose} disabled={saving}>
            {__('Cancel', 'campaignbridge')}
          </Button>
          <Button
            variant='primary'
            type='submit'
            isBusy={saving}
            disabled={!canSubmit}
            accessibleWhenDisabled
          >
            {__('Create campaign', 'campaignbridge')}
          </Button>
        </div>
      </form>
    </Modal>
  );
}
