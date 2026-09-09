import { Button, Notice, Spinner, TextControl } from '@wordpress/components';
import {
  DataViews,
  filterSortAndPaginate,
  type Action,
  type Field,
  type View,
} from '@wordpress/dataviews/wp';
import apiFetch from '@wordpress/api-fetch';
import domReady from '@wordpress/dom-ready';
import {
  useCallback,
  createRoot,
  useEffect,
  useMemo,
  useState,
} from '@wordpress/element';
import { color as colorIcon } from '@wordpress/icons';
import { EditColorModal } from './EditColorModal';
import { addGoogleFont, saveBrandFonts, searchGoogleFonts } from './api';
import { webFontUrls } from './fonts';
import type {
  BrandKitConfig,
  BrandKitPayload,
  BrandSlot,
  FontOption,
  GoogleFontResult,
} from './types';

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

const FONT_DEFAULT_VIEW: View = {
  type: 'table',
  search: '',
  page: 1,
  perPage: 10,
  fields: ['name', 'font', 'preview', 'description'],
  filters: [],
  layout: {},
};

const FONT_DEFAULT_LAYOUTS = {
  table: {
    fields: ['name', 'font', 'preview', 'description'],
  },
  grid: {
    mediaField: 'preview',
    titleField: 'name',
    descriptionField: 'description',
  },
};

function fontFamilyStyle(family: string): string {
  return family
    .split(',')
    .map(part => {
      const trimmed = part.trim();
      return trimmed.includes(' ') && !/^["'].*["']$/.test(trimmed)
        ? `"${trimmed}"`
        : trimmed;
    })
    .join(',');
}

function FontSpecimen({
  slug,
  options,
}: {
  slug: string;
  options: FontOption[];
}) {
  const font = options.find(option => option.slug === slug);
  const family = font?.family ?? 'Arial,Helvetica,sans-serif';

  return (
    <span
      className='campaignbridge-brand-kit__font-preview'
      style={{ fontFamily: fontFamilyStyle(family) }}
    >
      AaBbCc 123
    </span>
  );
}

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

interface FontRecord {
  slot: string;
  name: string;
  font: string;
}

function FontsSection({
  config,
  kit,
  onSaved,
}: {
  config: BrandKitConfig;
  kit: BrandKitPayload;
  onSaved: (kit: BrandKitPayload) => void;
}) {
  const [view, setView] = useState<View>(FONT_DEFAULT_VIEW);
  const [saving, setSaving] = useState<Record<string, boolean>>({});
  const [error, setError] = useState<string | null>(null);
  const [query, setQuery] = useState('');
  const [results, setResults] = useState<GoogleFontResult[] | null>(null);
  const [searching, setSearching] = useState(false);
  const [adding, setAdding] = useState<string | null>(null);
  const [lookupNotice, setLookupNotice] = useState<string | null>(null);

  const webFontUrlsMemo = useMemo(
    () => webFontUrls(kit.fontOptions, kit.fonts),
    [kit.fonts, kit.fontOptions]
  );

  useEffect(() => {
    const linkElements: HTMLLinkElement[] = [];

    for (const href of webFontUrlsMemo) {
      const link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = href;
      link.dataset.campaignbridgeFont = '1';
      document.head.appendChild(link);
      linkElements.push(link);
    }

    return () => {
      linkElements.forEach(el => el.remove());
    };
  }, [webFontUrlsMemo]);

  const records = useMemo(
    () =>
      Object.entries(kit.fontSlots).map(([slot, name]) => ({
        slot,
        name,
        font: kit.fonts[slot],
      })),
    [kit.fonts, kit.fontSlots]
  );

  const fields = useMemo<Field<FontRecord>[]>(
    () => [
      {
        id: 'name',
        label: config.i18n.slot,
        enableSorting: false,
        enableHiding: false,
        getValue: ({ item }) => item.name,
      },
      {
        id: 'font',
        label: config.i18n.fontFamily,
        enableSorting: false,
        enableHiding: false,
        render: ({ item }) => (
          <select
            className='campaignbridge-brand-kit__font-select'
            value={item.font}
            aria-label={config.i18n.fontChange}
            disabled={Boolean(saving[item.slot])}
            onChange={async event => {
              const slug = event.target.value;
              if (slug === item.font || saving[item.slot]) {
                return;
              }
              setSaving(current => ({ ...current, [item.slot]: true }));
              setError(null);
              try {
                const next = await saveBrandFonts(config.restUrl, {
                  ...kit.fonts,
                  [item.slot]: slug,
                });
                onSaved(next);
              } catch {
                setError(config.i18n.fontSaveFailed);
              } finally {
                setSaving(current => ({ ...current, [item.slot]: false }));
              }
            }}
          >
            {kit.fontOptions.map(option => (
              <option key={option.slug} value={option.slug}>
                {option.name}
              </option>
            ))}
          </select>
        ),
      },
      {
        id: 'preview',
        label: 'Preview',
        enableSorting: false,
        enableHiding: false,
        enableGlobalSearch: false,
        render: ({ item }) => (
          <FontSpecimen slug={item.font} options={kit.fontOptions} />
        ),
      },
      {
        id: 'description',
        label: config.i18n.use,
        enableSorting: false,
        getValue: ({ item }) => item.name,
      },
    ],
    [config.i18n, config.restUrl, onSaved, kit.fonts, kit.fontOptions, saving]
  );

  const { data, paginationInfo } = filterSortAndPaginate(records, view, fields);

  return (
    <section className='campaignbridge-brand-kit__typography'>
      <h3>{config.i18n.typography}</h3>
      {error && (
        <Notice
          className='campaignbridge-brand-kit__notice'
          status='error'
          onRemove={() => setError(null)}
        >
          {error}
        </Notice>
      )}
      <DataViews
        data={data}
        fields={fields}
        view={view}
        onChangeView={setView}
        search={false}
        defaultLayouts={FONT_DEFAULT_LAYOUTS}
        paginationInfo={paginationInfo}
        getItemId={item => item.slot}
        empty={<p>{config.i18n.empty}</p>}
      />
      <div className='campaignbridge-brand-kit__font-lookup'>
        <h4>{config.i18n.fontLookup}</h4>
        <p>{config.i18n.fontLookupHelp}</p>
        <form
          className='campaignbridge-brand-kit__font-search'
          onSubmit={async event => {
            event.preventDefault();
            if (query.trim().length < 2 || searching) return;
            setSearching(true);
            setError(null);
            setLookupNotice(null);
            try {
              setResults(await searchGoogleFonts(config.restUrl, query.trim()));
            } catch (caught) {
              setError(
                caught instanceof Error
                  ? caught.message
                  : config.i18n.fontSaveFailed
              );
            } finally {
              setSearching(false);
            }
          }}
        >
          <TextControl
            label={config.i18n.fontSearch}
            value={query}
            onChange={setQuery}
            maxLength={80}
          />
          <Button
            variant='secondary'
            type='submit'
            aria-label={config.i18n.fontSearchButton}
            disabled={query.trim().length < 2 || searching}
          >
            {searching ? <Spinner /> : config.i18n.fontSearchButton}
          </Button>
        </form>
        {lookupNotice && (
          <Notice status='success' onRemove={() => setLookupNotice(null)}>
            {lookupNotice}
          </Notice>
        )}
        {results?.length === 0 && <p>{config.i18n.fontSearchEmpty}</p>}
        {results && results.length > 0 && (
          <ul className='campaignbridge-brand-kit__font-results'>
            {results.map(result => (
              <li key={result.family}>
                <span>
                  <strong>{result.family}</strong> · {result.category}
                </span>
                <Button
                  variant='secondary'
                  isBusy={adding === result.family}
                  disabled={null !== adding}
                  onClick={async () => {
                    setAdding(result.family);
                    setError(null);
                    try {
                      const next = await addGoogleFont(
                        config.restUrl,
                        result.family,
                        kit.fonts
                      );
                      onSaved(next);
                      setResults(null);
                      setQuery('');
                      setLookupNotice(config.i18n.fontAdded);
                    } catch (caught) {
                      setError(
                        caught instanceof Error
                          ? caught.message
                          : config.i18n.fontSaveFailed
                      );
                    } finally {
                      setAdding(null);
                    }
                  }}
                >
                  {config.i18n.fontAdd}
                </Button>
              </li>
            ))}
          </ul>
        )}
      </div>
    </section>
  );
}

function BrandKitApp({ config }: { config: BrandKitConfig }) {
  const [kit, setKit] = useState(config.kit);
  const [view, setView] = useState<View>(DEFAULT_VIEW);
  const [notice, setNotice] = useState<{
    type: 'success' | 'error';
    message: string;
  } | null>(null);

  const handleFontSaved = useCallback(
    (next: BrandKitPayload) => {
      setKit(next);
      setNotice({ type: 'success', message: config.i18n.fontSaved });
    },
    [config.i18n.fontSaved]
  );

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
      <FontsSection config={config} kit={kit} onSaved={handleFontSaved} />
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
