import apiFetch from '@wordpress/api-fetch';
import { InspectorControls } from '@wordpress/block-editor';
import {
  Button,
  Notice,
  PanelBody,
  Spinner,
  TextControl,
} from '@wordpress/components';
import { useEntityProp } from '@wordpress/core-data';
import { useRegistry, useSelect } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import { useMemo, useState } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';
import { __, sprintf } from '@wordpress/i18n';
import type { ComponentType } from 'react';
import {
  DESIGN_FONT_META_KEY,
  MAX_DESIGN_FONTS,
  editorFontOptions,
  parseDesignFontRegistry,
  serializeDesignFontRegistry,
  type DesignFont,
  type DesignFontRegistry,
} from './design-fonts';

const DESIGN_FONTS_PATH = '/campaignbridge/v1/design-fonts';
const TEMPLATE_POST_TYPE = 'cb_templates';

export const GOOGLE_FONT_BLOCKS = [
  'core/heading',
  'core/paragraph',
  'core/button',
] as const;

interface GoogleFontResult {
  family: string;
  category: string;
  variants: string[];
}

interface BlockEditProps {
  name: string;
  attributes?: { fontFamily?: unknown; [attribute: string]: unknown };
  setAttributes: (attributes: Record<string, unknown>) => void;
}

type TemplateMeta = Record<string, string | boolean | undefined>;

interface EditorSelectors {
  getCurrentPostId: () => string | number;
  getCurrentPostType: () => string;
}

export function supportsGoogleFontControl(blockName: string): boolean {
  return (GOOGLE_FONT_BLOCKS as readonly string[]).includes(blockName);
}

/** Reuse a font already registered by CampaignBridge or this template. */
export function availableFontSlug(
  family: string,
  registry: DesignFontRegistry
): string | null {
  const needle = family.toLowerCase();
  const option = editorFontOptions().find(
    font => font.name.toLowerCase() === needle
  );
  if (option) return option.slug;

  return (
    registry.fonts.find(font => font.name.toLowerCase() === needle)?.slug ??
    null
  );
}

/** Validate a resolved REST record with the same client contract as metadata. */
function validatedFont(value: unknown): DesignFont | null {
  const parsed = parseDesignFontRegistry(
    JSON.stringify({ version: 1, fonts: [value], slots: {} })
  );
  return parsed.fonts[0] ?? null;
}

function validResults(value: unknown): GoogleFontResult[] {
  if (!Array.isArray(value)) return [];

  return value.filter(
    (font): font is GoogleFontResult =>
      Boolean(font) &&
      typeof font === 'object' &&
      typeof (font as Record<string, unknown>).family === 'string' &&
      typeof (font as Record<string, unknown>).category === 'string' &&
      Array.isArray((font as Record<string, unknown>).variants)
  );
}

function BlockGoogleFontControl({
  attributes = {},
  setAttributes,
}: BlockEditProps): JSX.Element | null {
  const { postId, postType } = useSelect(select => {
    const editor = select(editorStore) as unknown as EditorSelectors;
    const rawId = editor.getCurrentPostId();
    return {
      postId: typeof rawId === 'number' ? rawId : Number(rawId) || 0,
      postType: editor.getCurrentPostType(),
    };
  }, []);
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
  const dataRegistry = useRegistry();
  const [query, setQuery] = useState('');
  const [results, setResults] = useState<GoogleFontResult[] | null>(null);
  const [searching, setSearching] = useState(false);
  const [adding, setAdding] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  if (postType !== TEMPLATE_POST_TYPE || postId < 1) return null;

  const clearSearch = () => {
    setQuery('');
    setResults(null);
  };

  const search = async () => {
    const term = query.trim();
    if (term.length < 2 || searching) return;

    setSearching(true);
    setError(null);
    try {
      const response = await apiFetch<{ fonts?: unknown }>({
        path: `${DESIGN_FONTS_PATH}?search=${encodeURIComponent(term)}`,
      });
      setResults(validResults(response.fonts));
    } catch {
      setError(
        __('Fonts could not be searched. Please try again.', 'campaignbridge')
      );
    } finally {
      setSearching(false);
    }
  };

  const apply = async (family: string) => {
    if (adding) return;

    const existing = availableFontSlug(family, registry);
    if (existing) {
      setAttributes({ fontFamily: existing });
      clearSearch();
      return;
    }
    if (registry.fonts.length >= MAX_DESIGN_FONTS) {
      setError(
        sprintf(
          /* translators: %d: maximum number of fonts per template. */
          __(
            'This template already has its maximum of %d fonts.',
            'campaignbridge'
          ),
          MAX_DESIGN_FONTS
        )
      );
      return;
    }

    setAdding(family);
    setError(null);
    try {
      const response = await apiFetch<{ font?: unknown }>({
        path: DESIGN_FONTS_PATH,
        method: 'POST',
        data: { family },
      });
      const font = validatedFont(response.font);
      if (!font) throw new Error('Invalid design font response.');

      const next: DesignFontRegistry = {
        ...registry,
        fonts: [...registry.fonts, font],
      };
      dataRegistry.batch(() => {
        setMeta({
          ...rawMeta,
          [DESIGN_FONT_META_KEY]: serializeDesignFontRegistry(next),
        });
        setAttributes({ fontFamily: font.slug });
      });
      clearSearch();
    } catch {
      setError(
        __(
          'That font could not be applied. Please try again.',
          'campaignbridge'
        )
      );
    } finally {
      setAdding(null);
    }
  };

  const currentSlug =
    typeof attributes.fontFamily === 'string' ? attributes.fontFamily : '';
  const currentName = editorFontOptions().find(
    font => font.slug === currentSlug
  )?.name;

  return (
    <InspectorControls group='styles'>
      <PanelBody
        title={__('Google Font', 'campaignbridge')}
        initialOpen={false}
      >
        <p>
          {__(
            'Search and apply a Google Font to this block. CampaignBridge saves it with the template and loads it only when used.',
            'campaignbridge'
          )}
        </p>
        {currentName && (
          <p>
            {sprintf(
              /* translators: %s: currently selected font name. */
              __('Current block font: %s', 'campaignbridge'),
              currentName
            )}
          </p>
        )}
        {error && (
          <Notice status='error' onRemove={() => setError(null)}>
            {error}
          </Notice>
        )}
        <form
          onSubmit={event => {
            event.preventDefault();
            void search();
          }}
        >
          <TextControl
            label={__('Find a Google Font for this block', 'campaignbridge')}
            value={query}
            onChange={setQuery}
            maxLength={80}
            __nextHasNoMarginBottom
            __next40pxDefaultSize
          />
          <Button
            type='submit'
            variant='secondary'
            disabled={query.trim().length < 2 || searching}
          >
            {searching ? <Spinner /> : __('Search fonts', 'campaignbridge')}
          </Button>
        </form>
        {results?.length === 0 && (
          <p>{__('No matching fonts were found.', 'campaignbridge')}</p>
        )}
        {results && results.length > 0 && (
          <ul className='campaignbridge-design-fonts__results'>
            {results.map(result => {
              const available = availableFontSlug(result.family, registry);
              const atLimit =
                !available && registry.fonts.length >= MAX_DESIGN_FONTS;
              return (
                <li key={result.family}>
                  <span>
                    <strong>{result.family}</strong> · {result.category}
                  </span>
                  <Button
                    variant='secondary'
                    isBusy={adding === result.family}
                    disabled={adding !== null || atLimit}
                    label={sprintf(
                      available
                        ? __('Use %s', 'campaignbridge')
                        : __('Add and use %s', 'campaignbridge'),
                      result.family
                    )}
                    onClick={() => void apply(result.family)}
                  >
                    {available
                      ? __('Use', 'campaignbridge')
                      : __('Add and use', 'campaignbridge')}
                  </Button>
                </li>
              );
            })}
          </ul>
        )}
        <small>
          {sprintf(
            /* translators: 1: current font count, 2: maximum font count. */
            __('%1$d of %2$d template fonts registered.', 'campaignbridge'),
            registry.fonts.length,
            MAX_DESIGN_FONTS
          )}
        </small>
      </PanelBody>
    </InspectorControls>
  );
}

export const withBlockGoogleFontControl = (
  BlockEdit: ComponentType<BlockEditProps>
) => {
  const WithBlockGoogleFontControl = (props: BlockEditProps): JSX.Element => {
    if (!supportsGoogleFontControl(props.name)) {
      return <BlockEdit {...props} />;
    }

    return (
      <>
        <BlockEdit {...props} />
        <BlockGoogleFontControl {...props} />
      </>
    );
  };
  WithBlockGoogleFontControl.displayName = 'WithBlockGoogleFontControl';

  return WithBlockGoogleFontControl;
};

addFilter(
  'editor.BlockEdit',
  'campaignbridge/block-google-font-control',
  withBlockGoogleFontControl
);
