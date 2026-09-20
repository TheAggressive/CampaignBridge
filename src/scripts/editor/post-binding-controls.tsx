import {
  InspectorControls,
  store as blockEditorStore,
  useBlockBindingsUtils,
} from '@wordpress/block-editor';
import { PanelBody, RangeControl, SelectControl } from '@wordpress/components';
import { useRegistry, useSelect } from '@wordpress/data';
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import type { ComponentType } from 'react';

import {
  POST_BINDING_ATTRIBUTES,
  POST_BINDING_SOURCE,
  type PostBindingRule,
} from '../../blocks/shared/post-bindings';

/**
 * Contract-aware controls for the CampaignBridge post binding source.
 *
 * WordPress' own bindings panel reads one source-wide field list and cannot be
 * scoped per block attribute, so it cannot express which fields CampaignBridge
 * accepts on which attribute. This panel offers exactly the combinations
 * `includes/Email_Blocks/email-blocks.json` documents — the same file the
 * compiler validates against — and writes them through the public
 * `useBlockBindingsUtils()` API. No Core edit component is forked and no block
 * attribute is invented: the choice lives in `metadata.bindings` where
 * WordPress already keeps it.
 */

interface BindingArgs {
  field?: string;
  [arg: string]: unknown;
}

interface Binding {
  source?: string;
  args?: BindingArgs;
}

interface BlockEditProps {
  name: string;
  clientId: string;
  attributes?: {
    metadata?: { bindings?: Record<string, Binding> };
    [attribute: string]: unknown;
  };
  setAttributes: (attributes: Record<string, unknown>) => void;
}

const NOT_CONNECTED = '';

/** Human labels for the snapshot fields the contract exposes. */
const FIELD_LABELS: Record<string, string> = {
  title: __('Title', 'campaignbridge'),
  titleLink: __('Title, linked to the post', 'campaignbridge'),
  excerpt: __('Excerpt', 'campaignbridge'),
  content: __('Content', 'campaignbridge'),
  url: __('Post URL', 'campaignbridge'),
  postParentUrl: __('Parent post URL', 'campaignbridge'),
  postTypeArchiveUrl: __('Post type archive URL', 'campaignbridge'),
};

/** Read the binding CampaignBridge owns for one attribute. */
function boundArgs(
  props: BlockEditProps,
  attribute: string
): BindingArgs | null {
  const binding = props.attributes?.metadata?.bindings?.[attribute];

  return binding?.source === POST_BINDING_SOURCE ? (binding.args ?? {}) : null;
}

function AttributeControl({
  attribute,
  rule,
  args,
  onChange,
}: {
  attribute: string;
  rule: PostBindingRule;
  args: BindingArgs | null;
  onChange: (args: BindingArgs | undefined) => void;
}): JSX.Element {
  const fields = Object.keys(rule.fields);
  const selected = args?.field ?? NOT_CONNECTED;

  return (
    <>
      <SelectControl
        label={
          attribute === 'url'
            ? __('Link to', 'campaignbridge')
            : __('Show', 'campaignbridge')
        }
        value={typeof selected === 'string' ? selected : NOT_CONNECTED}
        options={[
          {
            label: __('Not connected', 'campaignbridge'),
            value: NOT_CONNECTED,
          },
          ...fields.map(field => ({
            label: FIELD_LABELS[field] ?? field,
            value: field,
          })),
        ]}
        onChange={value =>
          onChange(
            value === NOT_CONNECTED
              ? undefined
              : {
                  field: value,
                  ...Object.fromEntries(
                    Object.entries(rule.args).map(([arg, schema]) => [
                      arg,
                      typeof args?.[arg] === 'number'
                        ? args[arg]
                        : schema.default,
                    ])
                  ),
                }
          )
        }
        __next40pxDefaultSize
        __nextHasNoMarginBottom
      />
      {args !== null &&
        Object.entries(rule.args).map(([arg, schema]) => (
          <RangeControl
            key={arg}
            label={__('Maximum words', 'campaignbridge')}
            help={__(
              'Caps the text at send time. The compiled preview shows the exact result.',
              'campaignbridge'
            )}
            value={
              typeof args[arg] === 'number'
                ? (args[arg] as number)
                : schema.default
            }
            min={schema.min}
            max={schema.max}
            onChange={value =>
              onChange({ ...args, [arg]: Number(value) || schema.default })
            }
            __next40pxDefaultSize
            __nextHasNoMarginBottom
          />
        ))}
    </>
  );
}

function PostBindingPanel(props: BlockEditProps): JSX.Element | null {
  const { updateBlockBindings } = useBlockBindingsUtils(props.clientId);
  const registry = useRegistry();
  const inPostCard = useSelect(
    select =>
      (
        select(blockEditorStore) as {
          getBlockParentsByBlockName: (
            clientId: string,
            name: string,
            ascending?: boolean
          ) => string[];
        }
      ).getBlockParentsByBlockName(
        props.clientId,
        'campaignbridge/post-card',
        true
      ).length > 0,
    [props.clientId]
  );

  if (!inPostCard) {
    return null;
  }

  const rules = POST_BINDING_ATTRIBUTES[props.name] ?? {};

  return (
    <InspectorControls>
      <PanelBody title={__('Post content', 'campaignbridge')} initialOpen>
        {Object.entries(rules).map(([attribute, rule]) => (
          <AttributeControl
            key={attribute}
            attribute={attribute}
            rule={rule}
            args={boundArgs(props, attribute)}
            onChange={args =>
              registry.batch(() => {
                updateBlockBindings({
                  [attribute]:
                    args === undefined
                      ? undefined
                      : { source: POST_BINDING_SOURCE, args },
                });

                // The compiler refuses a bound attribute that also holds an
                // authored literal, so connecting clears that one attribute in
                // the same action. Only the bound attribute is touched: a
                // button keeps its authored label when its URL is bound.
                // Disconnecting leaves the block as Core left it rather than
                // restoring stale content.
                if (args !== undefined) {
                  props.setAttributes({ [attribute]: '' });
                }
              })
            }
          />
        ))}
      </PanelBody>
    </InspectorControls>
  );
}

export const withPostBindingControls = (
  BlockEdit: ComponentType<BlockEditProps>
) => {
  const WithPostBindingControls = (props: BlockEditProps): JSX.Element => {
    if (!POST_BINDING_ATTRIBUTES[props.name]) {
      return <BlockEdit {...props} />;
    }

    return (
      <>
        <BlockEdit {...props} />
        <PostBindingPanel {...props} />
      </>
    );
  };
  WithPostBindingControls.displayName = 'WithPostBindingControls';

  return WithPostBindingControls;
};

addFilter(
  'editor.BlockEdit',
  'campaignbridge/post-binding-controls',
  withPostBindingControls
);
