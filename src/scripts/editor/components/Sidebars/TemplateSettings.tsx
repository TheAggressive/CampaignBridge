import {
  SelectControl,
  TextControl,
  TextareaControl,
  ToggleControl,
} from '@wordpress/components';
import { useEntityProp } from '@wordpress/core-data';
import { useCallback, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

type TemplateMetaValue = boolean | string;
type TemplateMeta = Record<string, TemplateMetaValue | undefined>;

export interface TemplateSettingsProps {
  postType: string;
  postId: number;
}

interface TemplateMetaControls {
  values: TemplateMeta;
  getString: (key: string) => string;
  update: (key: string, value: TemplateMetaValue) => void;
}

function useTemplateMeta({
  postType,
  postId,
}: TemplateSettingsProps): TemplateMetaControls {
  const [rawValues = {}, setValues] = useEntityProp(
    'postType',
    postType,
    'meta',
    postId
  ) as [TemplateMeta, (values: TemplateMeta) => void, unknown];
  const values = useMemo(() => rawValues ?? {}, [rawValues]);
  const update = useCallback(
    (key: string, value: TemplateMetaValue) => {
      setValues({ ...values, [key]: value });
    },
    [setValues, values]
  );
  const getString = useCallback(
    (key: string): string =>
      typeof values[key] === 'string' ? values[key] : '',
    [values]
  );

  return { values, getString, update };
}

/** Basic template metadata for the native editor document sidebar. */
export function TemplateBasicSettings(
  props: TemplateSettingsProps
): JSX.Element {
  const { getString, update } = useTemplateMeta(props);
  const categoryOptions = [
    { label: __('General', 'campaignbridge'), value: 'general' },
    { label: __('Newsletter', 'campaignbridge'), value: 'newsletter' },
    { label: __('Promotional', 'campaignbridge'), value: 'promotional' },
    { label: __('Welcome', 'campaignbridge'), value: 'welcome' },
    { label: __('Custom', 'campaignbridge'), value: 'custom' },
  ];

  return (
    <>
      <SelectControl
        label={__('Category', 'campaignbridge')}
        value={getString('campaignbridge_template_category') || 'general'}
        options={categoryOptions}
        onChange={value => update('campaignbridge_template_category', value)}
        __nextHasNoMarginBottom
        __next40pxDefaultSize
      />
      <TextControl
        label={__('Subject Line', 'campaignbridge')}
        value={getString('campaignbridge_subject')}
        onChange={value => update('campaignbridge_subject', value)}
        placeholder={__('Enter email subject...', 'campaignbridge')}
        __nextHasNoMarginBottom
        __next40pxDefaultSize
      />
      <TextControl
        label={__('Preheader Text', 'campaignbridge')}
        value={getString('campaignbridge_preheader')}
        onChange={value => update('campaignbridge_preheader', value)}
        placeholder={__('Hidden preview text...', 'campaignbridge')}
        help={__('Shown in email client previews', 'campaignbridge')}
        __nextHasNoMarginBottom
        __next40pxDefaultSize
      />
      <TextControl
        label={__('Audience Tags', 'campaignbridge')}
        value={getString('campaignbridge_audience_tags')}
        onChange={value => update('campaignbridge_audience_tags', value)}
        placeholder={__('tag1, tag2, tag3', 'campaignbridge')}
        help={__('Comma-separated list of audience tags', 'campaignbridge')}
        __nextHasNoMarginBottom
        __next40pxDefaultSize
      />
    </>
  );
}

/** Sender and browser-view settings for the native editor. */
export function TemplateEmailSettings(
  props: TemplateSettingsProps
): JSX.Element {
  const { values, getString, update } = useTemplateMeta(props);

  return (
    <>
      <TextControl
        label={__('Sender Name', 'campaignbridge')}
        value={getString('campaignbridge_sender_name')}
        onChange={value => update('campaignbridge_sender_name', value)}
        placeholder={__('Your Name', 'campaignbridge')}
        __nextHasNoMarginBottom
        __next40pxDefaultSize
      />
      <TextControl
        label={__('Sender Email', 'campaignbridge')}
        value={getString('campaignbridge_sender_email')}
        onChange={value => update('campaignbridge_sender_email', value)}
        type='email'
        placeholder={__('sender@domain.com', 'campaignbridge')}
        __nextHasNoMarginBottom
        __next40pxDefaultSize
      />
      <ToggleControl
        label={__('Enable View Online Link', 'campaignbridge')}
        checked={values.campaignbridge_view_online_enabled === true}
        onChange={checked =>
          update('campaignbridge_view_online_enabled', checked)
        }
        __nextHasNoMarginBottom
      />
      {values.campaignbridge_view_online_enabled === true && (
        <TextControl
          label={__('View Online URL', 'campaignbridge')}
          value={getString('campaignbridge_view_online_url')}
          onChange={value => update('campaignbridge_view_online_url', value)}
          type='url'
          placeholder={__('https://...', 'campaignbridge')}
          __nextHasNoMarginBottom
          __next40pxDefaultSize
        />
      )}
      <TextControl
        label={__('Unsubscribe URL', 'campaignbridge')}
        value={getString('campaignbridge_unsubscribe_url')}
        onChange={value => update('campaignbridge_unsubscribe_url', value)}
        type='url'
        placeholder={__('https://unsubscribe...', 'campaignbridge')}
        help={__('Can use merge tags like {unsubscribe_url}', 'campaignbridge')}
        __nextHasNoMarginBottom
        __next40pxDefaultSize
      />
    </>
  );
}

/** Compliance, tracking, and footer settings for the native editor. */
export function TemplateComplianceSettings(
  props: TemplateSettingsProps
): JSX.Element {
  const { values, getString, update } = useTemplateMeta(props);

  return (
    <>
      <TextareaControl
        label={__('Address / Compliance', 'campaignbridge')}
        value={getString('campaignbridge_address_html')}
        onChange={value => update('campaignbridge_address_html', value)}
        placeholder={__(
          'Physical address and compliance info...',
          'campaignbridge'
        )}
        help={__('HTML allowed for formatting', 'campaignbridge')}
        rows={3}
        __nextHasNoMarginBottom
      />
      <ToggleControl
        label={__('Enable UTM Tracking', 'campaignbridge')}
        checked={values.campaignbridge_utm_enabled === true}
        onChange={checked => update('campaignbridge_utm_enabled', checked)}
        __nextHasNoMarginBottom
      />
      {values.campaignbridge_utm_enabled === true && (
        <TextControl
          label={__('UTM Template', 'campaignbridge')}
          value={getString('campaignbridge_utm_template')}
          onChange={value => update('campaignbridge_utm_template', value)}
          placeholder={'utm_source=newsletter&utm_campaign={post_slug}'}
          help={__('Template for UTM query parameters', 'campaignbridge')}
          __nextHasNoMarginBottom
          __next40pxDefaultSize
        />
      )}
      <ToggleControl
        label={__('Enable Default Footer', 'campaignbridge')}
        checked={values.campaignbridge_footer_enabled === true}
        onChange={checked => update('campaignbridge_footer_enabled', checked)}
        __nextHasNoMarginBottom
      />
      {values.campaignbridge_footer_enabled === true && (
        <TextControl
          label={__('Footer Pattern', 'campaignbridge')}
          value={getString('campaignbridge_footer_pattern')}
          onChange={value => update('campaignbridge_footer_pattern', value)}
          placeholder={__('Footer template slug', 'campaignbridge')}
          __nextHasNoMarginBottom
          __next40pxDefaultSize
        />
      )}
    </>
  );
}
