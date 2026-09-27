import apiFetch from '@wordpress/api-fetch';
import {
  Button,
  Notice,
  SelectControl,
  Spinner,
  TextControl,
} from '@wordpress/components';
import { useEntityProp } from '@wordpress/core-data';
import { useMemo, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
  DESIGN_FONT_META_KEY,
  MAX_DESIGN_FONTS,
  editorFontOptions,
  parseDesignFontRegistry,
  serializeDesignFontRegistry,
  type DesignFont,
  type DesignFontRegistry,
} from '../design-fonts';

interface GoogleFontResult {
  family: string;
  category: string;
  variants: string[];
}

interface DesignFontsPanelProps {
  postType: string;
  postId: number;
}

type TemplateMeta = Record<string, string | boolean | undefined>;

const DESIGN_FONTS_PATH = '/campaignbridge/v1/design-fonts';

export default function DesignFontsPanel({
  postType,
  postId,
}: DesignFontsPanelProps): JSX.Element {
  const [rawMeta = {}, setMeta] = useEntityProp(
    'postType',
    postType,
    'meta',
    postId
  ) as [TemplateMeta, (value: TemplateMeta) => void, unknown];
  const stored =
    typeof rawMeta[DESIGN_FONT_META_KEY] === 'string'
      ? rawMeta[DESIGN_FONT_META_KEY]
      : '';
  const registry = useMemo(() => parseDesignFontRegistry(stored), [stored]);
  const [query, setQuery] = useState('');
  const [results, setResults] = useState<GoogleFontResult[] | null>(null);
  const [searching, setSearching] = useState(false);
  const [adding, setAdding] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const save = (next: DesignFontRegistry) => {
    setMeta({
      ...rawMeta,
      [DESIGN_FONT_META_KEY]: serializeDesignFontRegistry(next),
    });
  };

  const choices = [
    { label: __('Use Brand Kit default', 'campaignbridge'), value: '' },
    ...editorFontOptions().map(font => ({
      label: font.name,
      value: font.slug,
    })),
  ];

  const updateSlot = (slot: 'heading' | 'body' | 'button', value: string) => {
    const slots = { ...registry.slots };
    if (value) slots[slot] = value;
    else delete slots[slot];
    save({ ...registry, slots });
  };

  const removeFont = (font: DesignFont) => {
    const slots = { ...registry.slots };
    for (const slot of ['heading', 'body', 'button'] as const) {
      if (slots[slot] === font.slug) delete slots[slot];
    }
    save({
      ...registry,
      fonts: registry.fonts.filter(item => item.slug !== font.slug),
      slots,
    });
  };

  const search = async () => {
    if (query.trim().length < 2 || searching) return;
    setSearching(true);
    setError(null);
    try {
      const response = await apiFetch<{ fonts: GoogleFontResult[] }>({
        path: `${DESIGN_FONTS_PATH}?search=${encodeURIComponent(query.trim())}`,
      });
      setResults(response.fonts);
    } catch {
      setError(
        __('Fonts could not be searched. Please try again.', 'campaignbridge')
      );
    } finally {
      setSearching(false);
    }
  };

  const add = async (family: string) => {
    if (registry.fonts.length >= MAX_DESIGN_FONTS || adding) return;
    const existing = editorFontOptions().find(
      option => option.name.toLowerCase() === family.toLowerCase()
    );
    if (existing) {
      setResults(null);
      setQuery('');
      setError(
        __(
          'That font is already available in the font selectors.',
          'campaignbridge'
        )
      );
      return;
    }

    setAdding(family);
    setError(null);
    try {
      const response = await apiFetch<{ font: DesignFont }>({
        path: DESIGN_FONTS_PATH,
        method: 'POST',
        data: { family },
      });
      const duplicate = registry.fonts.some(
        font =>
          font.slug === response.font.slug ||
          font.name.toLowerCase() === response.font.name.toLowerCase()
      );
      if (!duplicate) {
        save({ ...registry, fonts: [...registry.fonts, response.font] });
      }
      setResults(null);
      setQuery('');
    } catch {
      setError(
        __('That font could not be added. Please try again.', 'campaignbridge')
      );
    } finally {
      setAdding(null);
    }
  };

  return (
    <div className='campaignbridge-design-fonts'>
      <p>
        {__(
          'Add fonts for this email only. Supporting clients use the web font; other clients use its email-safe fallback.',
          'campaignbridge'
        )}
      </p>
      {error && (
        <Notice status='error' onRemove={() => setError(null)}>
          {error}
        </Notice>
      )}
      {(['heading', 'body', 'button'] as const).map(slot => (
        <SelectControl
          key={slot}
          label={sprintf(
            /* translators: %s: typography slot name. */
            __('%s font', 'campaignbridge'),
            slot[0].toUpperCase() + slot.slice(1)
          )}
          value={registry.slots[slot] ?? ''}
          options={choices}
          onChange={value => updateSlot(slot, value)}
          __nextHasNoMarginBottom
          __next40pxDefaultSize
        />
      ))}
      {registry.fonts.length > 0 && (
        <ul className='campaignbridge-design-fonts__list'>
          {registry.fonts.map(font => (
            <li key={font.slug}>
              <span style={{ fontFamily: font.family }}>{font.name}</span>
              <Button
                variant='tertiary'
                isDestructive
                onClick={() => removeFont(font)}
              >
                {__('Remove', 'campaignbridge')}
              </Button>
            </li>
          ))}
        </ul>
      )}
      <form
        onSubmit={event => {
          event.preventDefault();
          void search();
        }}
      >
        <TextControl
          label={__('Find a Google Font', 'campaignbridge')}
          value={query}
          onChange={setQuery}
          maxLength={80}
          disabled={registry.fonts.length >= MAX_DESIGN_FONTS}
          __nextHasNoMarginBottom
          __next40pxDefaultSize
        />
        <Button
          type='submit'
          variant='secondary'
          disabled={
            query.trim().length < 2 ||
            searching ||
            registry.fonts.length >= MAX_DESIGN_FONTS
          }
        >
          {searching ? <Spinner /> : __('Search fonts', 'campaignbridge')}
        </Button>
      </form>
      {results?.length === 0 && (
        <p>{__('No matching fonts were found.', 'campaignbridge')}</p>
      )}
      {results && results.length > 0 && (
        <ul className='campaignbridge-design-fonts__results'>
          {results.map(result => (
            <li key={result.family}>
              <span>
                <strong>{result.family}</strong> · {result.category}
              </span>
              <Button
                variant='secondary'
                isBusy={adding === result.family}
                disabled={adding !== null}
                onClick={() => void add(result.family)}
              >
                {__('Add', 'campaignbridge')}
              </Button>
            </li>
          ))}
        </ul>
      )}
      <small>
        {sprintf(
          /* translators: 1: current font count, 2: maximum font count. */
          __('%1$d of %2$d design fonts used.', 'campaignbridge'),
          registry.fonts.length,
          MAX_DESIGN_FONTS
        )}
      </small>
    </div>
  );
}
