import { Notice } from '@wordpress/components';
import {
  DataViews,
  filterSortAndPaginate,
  type Action,
  type Field,
  type View,
} from '@wordpress/dataviews/wp';
import apiFetch from '@wordpress/api-fetch';
import domReady from '@wordpress/dom-ready';
import { createRoot, useMemo, useState } from '@wordpress/element';
import { color as colorIcon } from '@wordpress/icons';
import { EditColorModal } from './EditColorModal';
import type { BrandKitConfig, BrandKitPayload, BrandSlot } from './types';

const DEFAULT_VIEW: View = {
  type: 'table',
  search: '',
  page: 1,
  perPage: 10,
  fields: ['color', 'name', 'description'],
  filters: [],
  layout: {},
};

const DEFAULT_LAYOUTS = {
  table: {
    fields: ['color', 'name', 'description'],
  },
  grid: {
    mediaField: 'color',
    titleField: 'name',
    descriptionField: 'description',
  },
};

function sourceLabel(
  config: BrandKitConfig,
  source: BrandKitPayload['source']
): string {
  if ('theme' === source) {
    return config.i18n.sourceTheme;
  }

  if ('custom' === source) {
    return config.i18n.sourceCustom;
  }

  return config.i18n.sourceDefaults;
}

function BrandKitApp({ config }: { config: BrandKitConfig }) {
  const [kit, setKit] = useState(config.kit);
  const [view, setView] = useState<View>(DEFAULT_VIEW);
  const [notice, setNotice] = useState<{
    type: 'success' | 'error';
    message: string;
  } | null>(null);

  const fields = useMemo<Field<BrandSlot>[]>(
    () => [
      {
        id: 'color',
        label: config.i18n.colour,
        enableSorting: false,
        enableHiding: false,
        enableGlobalSearch: false,
        render: ({ item }) => (
          <span
            className='campaignbridge-brand-kit__swatch'
            style={{ ['--campaignbridge-swatch' as string]: item.color }}
            title={item.color}
          />
        ),
      },
      {
        id: 'name',
        label: config.i18n.slot,
        enableSorting: false,
        getValue: ({ item }) => item.name,
      },
      {
        id: 'description',
        label: config.i18n.use,
        enableSorting: false,
        getValue: ({ item }) => item.description,
      },
    ],
    [config.i18n]
  );

  const actions = useMemo<Action<BrandSlot>[]>(
    () => [
      {
        id: 'edit-color',
        label: config.i18n.edit,
        isPrimary: true,
        icon: colorIcon,
        modalHeader: items => items[0]?.name ?? config.i18n.edit,
        modalSize: 'small',
        RenderModal: ({ items, closeModal, onActionPerformed }) => (
          <EditColorModal
            item={items[0] as BrandSlot}
            config={config}
            closeModal={closeModal}
            onActionPerformed={onActionPerformed}
            onSaved={next => {
              setKit(next);
              setNotice({ type: 'success', message: config.i18n.saved });
            }}
            onFailed={() =>
              setNotice({ type: 'error', message: config.i18n.saveFailed })
            }
          />
        ),
      },
    ],
    [config]
  );

  const { data, paginationInfo } = filterSortAndPaginate(
    kit.slots,
    view,
    fields
  );

  return (
    <>
      <p className='campaignbridge-brand-kit__source'>
        {sourceLabel(config, kit.source)}
      </p>
      {notice && (
        <Notice
          className='campaignbridge-brand-kit__notice'
          status={notice.type}
          onRemove={() => setNotice(null)}
        >
          {notice.message}
        </Notice>
      )}
      <DataViews
        data={data}
        fields={fields}
        view={view}
        onChangeView={setView}
        actions={actions}
        search={false}
        defaultLayouts={DEFAULT_LAYOUTS}
        paginationInfo={paginationInfo}
        getItemId={item => item.id}
        empty={<p>{config.i18n.empty}</p>}
      />
    </>
  );
}

domReady(() => {
  const root = document.getElementById('campaignbridge-brand-kit-root');
  const config = window.campaignbridgeBrandKit;

  if (!root || !config) {
    return;
  }

  apiFetch.use(apiFetch.createNonceMiddleware(config.nonce));
  createRoot(root).render(<BrandKitApp config={config} />);
});
