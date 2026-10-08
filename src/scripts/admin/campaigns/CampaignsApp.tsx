import { Button, Notice } from '@wordpress/components';
import {
  DataViews,
  type Action,
  type Field,
  type View,
} from '@wordpress/dataviews/wp';
import apiFetch from '@wordpress/api-fetch';
import {
  useCallback,
  useEffect,
  useMemo,
  useRef,
  useState,
} from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { requestErrorMessage } from '../brand-kit/errors';
import {
  archiveCampaign,
  duplicateCampaign,
  listCampaigns,
  listTemplates,
  newIdempotencyKey,
  withQuery,
} from './api';
import { CreateCampaignModal } from './CreateCampaignModal';
import { OnboardingChecklist } from './OnboardingChecklist';
import { providerLabel, stateLabels } from './labels';
import { campaignUrl, viewToQuery } from './view';
import type {
  Campaign,
  CampaignCollection,
  CampaignsConfig,
  CampaignState,
} from './types';

const DEFAULT_VIEW: View = {
  type: 'table',
  page: 1,
  perPage: 20,
  fields: ['state', 'provider', 'audience', 'updated'],
  titleField: 'template',
  filters: [],
  layout: {},
};

const DEFAULT_LAYOUTS = {
  table: { fields: ['state', 'provider', 'audience', 'updated'] },
};

interface Owner {
  id: number;
  name: string;
}

/** Campaign list with server-side filters, creation, and permitted row actions. */
export function CampaignsApp({
  config,
}: {
  config: CampaignsConfig;
}): JSX.Element {
  const [view, setView] = useState<View>(DEFAULT_VIEW);
  const [collection, setCollection] = useState<CampaignCollection | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [creating, setCreating] = useState(false);
  const [templateTitles, setTemplateTitles] = useState<Map<number, string>>(
    new Map()
  );
  const [owners, setOwners] = useState<Owner[]>([]);
  const request = useRef(0);

  const labels = useMemo(stateLabels, []);
  const providerNames = useMemo(
    () =>
      Object.fromEntries(
        config.providers.map(option => [option.slug, option.label])
      ),
    [config.providers]
  );

  const load = useCallback(() => {
    const current = ++request.current;
    setLoading(true);
    setError(null);
    listCampaigns(viewToQuery(view))
      .then(result => {
        if (current === request.current) setCollection(result);
      })
      .catch(caught => {
        if (current !== request.current) return;
        setError(
          requestErrorMessage(
            caught,
            __('Campaigns could not be loaded.', 'campaignbridge')
          )
        );
      })
      .finally(() => {
        if (current === request.current) setLoading(false);
      });
  }, [view]);

  useEffect(load, [load]);

  useEffect(() => {
    listTemplates(config.templatesRestBase)
      .then(templates =>
        setTemplateTitles(
          new Map(templates.map(template => [template.id, template.title]))
        )
      )
      .catch(() => setTemplateTitles(new Map()));
  }, [config.templatesRestBase]);

  useEffect(() => {
    if (!config.canManageAll) return;
    apiFetch<Owner[]>({
      path: withQuery('/wp/v2/users', {
        per_page: 100,
        orderby: 'name',
        _fields: 'id,name',
      }),
    })
      .then(setOwners)
      .catch(() => setOwners([]));
  }, [config.canManageAll]);

  const fields = useMemo<Field<Campaign>[]>(() => {
    const list: Field<Campaign>[] = [
      {
        id: 'template',
        label: __('Campaign', 'campaignbridge'),
        enableHiding: false,
        enableSorting: false,
        getValue: ({ item }) =>
          templateTitles.get(item.template_id) ??
          sprintf(
            /* translators: %d: template ID. */
            __('Template #%d', 'campaignbridge'),
            item.template_id
          ),
        render: ({ item }) => (
          <a href={campaignUrl(config.screenUrl, item.id)}>
            {templateTitles.get(item.template_id) ??
              sprintf(
                /* translators: %d: template ID. */
                __('Template #%d', 'campaignbridge'),
                item.template_id
              )}
          </a>
        ),
      },
      {
        id: 'state',
        label: __('Status', 'campaignbridge'),
        enableSorting: false,
        elements: (Object.keys(labels) as CampaignState[]).map(state => ({
          value: state,
          label: labels[state],
        })),
        filterBy: { operators: ['isAny'] },
        getValue: ({ item }) => item.state,
        render: ({ item }) => (
          <span
            className={`campaignbridge-campaigns__state campaignbridge-campaigns__state--${item.state}`}
          >
            {labels[item.state]}
          </span>
        ),
      },
      {
        id: 'provider',
        label: __('Delivery', 'campaignbridge'),
        enableSorting: false,
        elements: [
          { value: 'none', label: providerLabel(null, providerNames) },
          ...config.providers.map(option => ({
            value: option.slug,
            label: option.label,
          })),
        ],
        filterBy: { operators: ['is'] },
        getValue: ({ item }) => item.provider ?? 'none',
        render: ({ item }) => providerLabel(item.provider, providerNames),
      },
      {
        id: 'audience',
        label: __('Audience', 'campaignbridge'),
        enableSorting: false,
        getValue: ({ item }) => item.audience_reference ?? '',
        render: ({ item }) => item.audience_reference ?? '—',
      },
      {
        id: 'updated',
        label: __('Updated', 'campaignbridge'),
        enableSorting: false,
        getValue: ({ item }) => item.updated_at,
        render: ({ item }) => (
          <time dateTime={item.updated_at}>
            {new Date(item.updated_at).toLocaleString()}
          </time>
        ),
      },
    ];
    if (config.canManageAll) {
      list.push({
        id: 'owner',
        label: __('Owner', 'campaignbridge'),
        enableSorting: false,
        elements: owners.map(owner => ({ value: owner.id, label: owner.name })),
        filterBy: { operators: ['is'] },
        getValue: ({ item }) => item.owner_user_id,
        render: ({ item }) =>
          owners.find(owner => owner.id === item.owner_user_id)?.name ??
          String(item.owner_user_id),
      });
    }

    return list;
  }, [
    config.canManageAll,
    config.providers,
    config.screenUrl,
    labels,
    owners,
    providerNames,
    templateTitles,
  ]);

  const actions = useMemo<Action<Campaign>[]>(
    () => [
      {
        id: 'duplicate',
        label: __('Duplicate', 'campaignbridge'),
        isEligible: item => item.actions.includes('duplicate'),
        modalHeader: __('Duplicate campaign', 'campaignbridge'),
        RenderModal: ({ items, closeModal }) => (
          <ConfirmAction
            message={__(
              'Create a new draft campaign with the same template, provider, and audience. Approvals and delivery history are not copied.',
              'campaignbridge'
            )}
            confirmLabel={__('Duplicate', 'campaignbridge')}
            onCancel={() => closeModal?.()}
            perform={key => duplicateCampaign(items[0], key)}
            onDone={() => {
              closeModal?.();
              setNotice(__('Campaign duplicated.', 'campaignbridge'));
              load();
            }}
            failure={__(
              'The campaign could not be duplicated.',
              'campaignbridge'
            )}
            keyPrefix='duplicate'
          />
        ),
      },
      {
        id: 'archive',
        label: __('Archive', 'campaignbridge'),
        isDestructive: true,
        isEligible: item => item.actions.includes('archive'),
        modalHeader: __('Archive campaign', 'campaignbridge'),
        RenderModal: ({ items, closeModal }) => (
          <ConfirmAction
            message={__(
              'Archived campaigns stay in the list for their history but cannot be changed or sent.',
              'campaignbridge'
            )}
            confirmLabel={__('Archive', 'campaignbridge')}
            destructive
            onCancel={() => closeModal?.()}
            perform={() => archiveCampaign(items[0])}
            onDone={() => {
              closeModal?.();
              setNotice(__('Campaign archived.', 'campaignbridge'));
              load();
            }}
            failure={__(
              'The campaign could not be archived. It may have changed; reload and try again.',
              'campaignbridge'
            )}
            keyPrefix='archive'
          />
        ),
      },
    ],
    [load]
  );

  return (
    <div className='campaignbridge-campaigns__app'>
      <OnboardingChecklist
        initial={config.onboarding}
        onCreateCampaign={() => setCreating(true)}
      />
      <div className='campaignbridge-campaigns__toolbar'>
        <Button variant='primary' onClick={() => setCreating(true)}>
          {__('New campaign', 'campaignbridge')}
        </Button>
      </div>

      <div aria-live='polite'>
        {notice && (
          <Notice status='success' onRemove={() => setNotice(null)}>
            {notice}
          </Notice>
        )}
      </div>
      {error && (
        <Notice status='error' isDismissible={false}>
          {error}{' '}
          <Button variant='link' onClick={load}>
            {__('Try again', 'campaignbridge')}
          </Button>
        </Notice>
      )}

      <DataViews<Campaign>
        data={collection?.items ?? []}
        fields={fields}
        view={view}
        onChangeView={setView}
        search={false}
        actions={actions}
        isLoading={loading}
        defaultLayouts={DEFAULT_LAYOUTS}
        paginationInfo={{
          totalItems: collection?.pagination.total ?? 0,
          totalPages: collection?.pagination.total_pages ?? 0,
        }}
        getItemId={item => item.id}
        empty={
          <div className='campaignbridge-campaigns__empty'>
            <p>
              {view.filters?.length
                ? __('No campaigns match these filters.', 'campaignbridge')
                : __('No campaigns yet.', 'campaignbridge')}
            </p>
            {!view.filters?.length && (
              <Button variant='secondary' onClick={() => setCreating(true)}>
                {__('Create your first campaign', 'campaignbridge')}
              </Button>
            )}
          </div>
        }
      />

      {creating && (
        <CreateCampaignModal
          config={config}
          onClose={() => setCreating(false)}
          onCreated={campaign => {
            globalThis.location.assign(
              campaignUrl(config.screenUrl, campaign.id)
            );
          }}
        />
      )}
    </div>
  );
}

interface ConfirmActionProps {
  message: string;
  confirmLabel: string;
  destructive?: boolean;
  onCancel: () => void;
  perform: (idempotencyKey: string) => Promise<unknown>;
  onDone: () => void;
  failure: string;
  keyPrefix: string;
}

/**
 * Confirmation body for one row action. The idempotency key is fixed when the
 * dialog opens, so a repeated click or retry is the same request.
 */
function ConfirmAction({
  message,
  confirmLabel,
  destructive = false,
  onCancel,
  perform,
  onDone,
  failure,
  keyPrefix,
}: ConfirmActionProps): JSX.Element {
  const [key] = useState(() => newIdempotencyKey(keyPrefix));
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  return (
    <div className='campaignbridge-campaigns__confirm'>
      <p>{message}</p>
      {error && (
        <Notice status='error' isDismissible={false}>
          {error}
        </Notice>
      )}
      <div className='campaignbridge-campaigns__form-actions'>
        <Button variant='tertiary' onClick={onCancel} disabled={busy}>
          {__('Cancel', 'campaignbridge')}
        </Button>
        <Button
          variant='primary'
          isDestructive={destructive}
          isBusy={busy}
          disabled={busy}
          accessibleWhenDisabled
          onClick={() => {
            setBusy(true);
            setError(null);
            perform(key)
              .then(onDone)
              .catch(caught => {
                setError(requestErrorMessage(caught, failure));
                setBusy(false);
              });
          }}
        >
          {confirmLabel}
        </Button>
      </div>
    </div>
  );
}
