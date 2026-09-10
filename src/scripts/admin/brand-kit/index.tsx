import {
  Button,
  Modal,
  Notice,
  Spinner,
  TextControl,
} from '@wordpress/components';
import {
  DataViews,
  filterSortAndPaginate,
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
import { EditColorModal } from './EditColorModal';
import { addGoogleFont, saveBrandFonts, searchGoogleFonts } from './api';
import { contrastRatio } from './color';
import { requestErrorMessage } from './errors';
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
  fields: ['color', 'name', 'description', 'preview'],
  filters: [],
  layout: {},
};

const DEFAULT_LAYOUTS = {
  table: {
    fields: ['color', 'name', 'description', 'preview'],
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
      The quick brown fox jumps over the lazy dog.
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
  description: string;
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
  const [lookupOpen, setLookupOpen] = useState(false);
  const [saveStatus, setSaveStatus] = useState<string | null>(null);

  const webFontUrlsMemo = useMemo(
    () =>
      config.externalFontsEnabled
        ? webFontUrls(kit.fontOptions, kit.fonts)
        : [],
    [config.externalFontsEnabled, kit.fonts, kit.fontOptions]
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
        description:
          slot === 'heading'
            ? config.i18n.headingUse
            : slot === 'button'
              ? config.i18n.buttonUse
              : config.i18n.bodyUse,
      })),
    [config.i18n, kit.fonts, kit.fontSlots]
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
              setSaveStatus(config.i18n.saving);
              setError(null);
              try {
                const next = await saveBrandFonts(config.restUrl, {
                  ...kit.fonts,
                  [item.slot]: slug,
                });
                onSaved(next);
                setSaveStatus(config.i18n.savedStatus);
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
        label: config.i18n.preview,
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
        enableHiding: false,
        getValue: ({ item }) => item.description,
      },
    ],
    [config.i18n, config.restUrl, onSaved, kit.fonts, kit.fontOptions, saving]
  );

  const { data, paginationInfo } = filterSortAndPaginate(records, view, fields);

  return (
    <section className='cb-admin-card campaignbridge-brand-kit__table-card campaignbridge-brand-kit__typography'>
      <header className='campaignbridge-brand-kit__section-header'>
        <span
          className='campaignbridge-brand-kit__section-icon campaignbridge-brand-kit__section-icon--type'
          aria-hidden='true'
        >
          Tt
        </span>
        <div>
          <h3>{config.i18n.typography}</h3>
          <p>{config.i18n.typographyHelp}</p>
        </div>
        <div className='campaignbridge-brand-kit__section-actions'>
          {saveStatus && <small role='status'>{saveStatus}</small>}
          {config.externalFontsEnabled && (
            <Button
              variant='secondary'
              onClick={() => setLookupOpen(open => !open)}
            >
              {config.i18n.fontLookup}
            </Button>
          )}
        </div>
      </header>
      {error && (
        <Notice
          className='campaignbridge-brand-kit__notice'
          status='error'
          onRemove={() => setError(null)}
        >
          {error}
        </Notice>
      )}
      <div className='campaignbridge-brand-kit__view'>
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
      </div>
      {config.externalFontsEnabled && lookupOpen && (
        <div className='campaignbridge-brand-kit__font-lookup'>
          <p>{config.i18n.fontLookupHelp}</p>
          {lookupNotice && (
            <p
              className='campaignbridge-brand-kit__font-lookup-status'
              role='status'
            >
              <span
                className='dashicons dashicons-yes-alt'
                aria-hidden='true'
              />
              {lookupNotice}
            </p>
          )}
          <form
            className='campaignbridge-brand-kit__font-search'
            onSubmit={async event => {
              event.preventDefault();
              if (query.trim().length < 2 || searching) return;
              setSearching(true);
              setError(null);
              setLookupNotice(null);
              try {
                setResults(
                  await searchGoogleFonts(config.restUrl, query.trim())
                );
              } catch (caught) {
                setError(
                  requestErrorMessage(caught, config.i18n.fontSaveFailed)
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
                        setSaveStatus(config.i18n.savedStatus);
                        setResults(null);
                        setQuery('');
                        setLookupNotice(config.i18n.fontAdded);
                      } catch (caught) {
                        setError(
                          requestErrorMessage(
                            caught,
                            config.i18n.fontSaveFailed
                          )
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
      )}
    </section>
  );
}

function BrandKitApp({ config }: { config: BrandKitConfig }) {
  const [kit, setKit] = useState(config.kit);
  const [view, setView] = useState<View>(DEFAULT_VIEW);
  const [editing, setEditing] = useState<BrandSlot | null>(null);
  const [notice, setNotice] = useState<{
    type: 'success' | 'error';
    message: string;
  } | null>(null);

  const handleFontSaved = useCallback((next: BrandKitPayload) => {
    setKit(next);
  }, []);

  const fields = useMemo<Field<BrandSlot>[]>(
    () => [
      {
        id: 'color',
        label: config.i18n.colour,
        enableSorting: false,
        enableHiding: false,
        enableGlobalSearch: false,
        render: ({ item }) => (
          <Button
            className='campaignbridge-brand-kit__colour-control'
            variant='secondary'
            onClick={() => setEditing(item)}
            aria-label={`${config.i18n.edit}: ${item.name}`}
          >
            <span
              className='campaignbridge-brand-kit__swatch'
              style={{ ['--campaignbridge-swatch' as string]: item.color }}
            />
            <code>{item.color}</code>
            <span className='dashicons dashicons-edit' aria-hidden='true' />
          </Button>
        ),
      },
      {
        id: 'name',
        label: config.i18n.slot,
        enableSorting: false,
        enableHiding: false,
        getValue: ({ item }) => item.name,
      },
      {
        id: 'description',
        label: config.i18n.use,
        enableSorting: false,
        enableHiding: false,
        getValue: ({ item }) => item.description,
      },
      {
        id: 'preview',
        label: config.i18n.preview,
        enableSorting: false,
        enableGlobalSearch: false,
        render: ({ item }) => {
          const brandColor =
            kit.slots.find(slot => slot.id === 'brand')?.color ?? '#2563eb';
          const onBrandColor =
            kit.slots.find(slot => slot.id === 'on-brand')?.color ?? '#ffffff';
          const isSurface = item.id === 'background' || item.id === 'card';
          const foreground = item.id === 'brand' ? onBrandColor : item.color;
          const background =
            item.id === 'brand' || item.id === 'on-brand'
              ? brandColor
              : (kit.slots.find(slot => slot.id === 'background')?.color ??
                '#ffffff');
          const ratio = ['text', 'secondary', 'brand', 'on-brand'].includes(
            item.id
          )
            ? contrastRatio(foreground, background)
            : null;

          return (
            <span
              className={`campaignbridge-brand-kit__colour-preview campaignbridge-brand-kit__colour-preview--${item.id}`}
              style={{
                ['--campaignbridge-preview' as string]: item.color,
                ['--campaignbridge-preview-surface' as string]:
                  item.id === 'on-brand'
                    ? brandColor
                    : item.id === 'brand' || isSurface
                      ? item.color
                      : '#f5f8fc',
                ['--campaignbridge-preview-ink' as string]:
                  item.id === 'brand'
                    ? onBrandColor
                    : item.id === 'on-brand'
                      ? item.color
                      : isSurface
                        ? '#101828'
                        : item.color,
              }}
            >
              {item.id === 'brand'
                ? config.i18n.primaryButton
                : item.id === 'border'
                  ? ''
                  : item.description}
              {ratio !== null && (
                <small
                  className={`campaignbridge-brand-kit__contrast ${ratio >= 4.5 ? 'is-pass' : 'is-fail'}`}
                >
                  {ratio >= 4.5
                    ? config.i18n.contrastPass
                    : config.i18n.contrastFail}{' '}
                  · {ratio.toFixed(1)}:1
                </small>
              )}
            </span>
          );
        },
      },
    ],
    [config.i18n, kit.slots]
  );

  const { data, paginationInfo } = filterSortAndPaginate(
    kit.slots,
    view,
    fields
  );

  return (
    <>
      {notice && (
        <Notice
          className='campaignbridge-brand-kit__notice'
          status={notice.type}
          onRemove={() => setNotice(null)}
        >
          {notice.message}
        </Notice>
      )}
      <section className='cb-admin-card campaignbridge-brand-kit__table-card'>
        <header className='campaignbridge-brand-kit__section-header'>
          <span
            className='campaignbridge-brand-kit__section-icon'
            aria-hidden='true'
          >
            <span className='dashicons dashicons-art' />
          </span>
          <div>
            <h3>{config.i18n.coloursTitle}</h3>
            <p>{config.i18n.coloursHelp}</p>
          </div>
          <small className='campaignbridge-brand-kit__source'>
            {sourceLabel(config, kit.source)}
          </small>
        </header>
        <div className='campaignbridge-brand-kit__view'>
          <DataViews
            data={data}
            fields={fields}
            view={view}
            onChangeView={setView}
            search={false}
            defaultLayouts={DEFAULT_LAYOUTS}
            paginationInfo={paginationInfo}
            getItemId={item => item.id}
            empty={<p>{config.i18n.empty}</p>}
          />
        </div>
      </section>
      {editing && (
        <Modal
          title={`${config.i18n.edit}: ${editing.name}`}
          size='small'
          onRequestClose={() => setEditing(null)}
        >
          <EditColorModal
            item={editing}
            config={config}
            closeModal={() => setEditing(null)}
            onSaved={next => {
              setKit(next);
              setNotice({ type: 'success', message: config.i18n.saved });
            }}
            onFailed={() =>
              setNotice({ type: 'error', message: config.i18n.saveFailed })
            }
          />
        </Modal>
      )}
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
