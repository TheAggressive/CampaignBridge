import {
  Panel,
  PanelBody,
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

interface TemplateSettingsProps {
  postType: string;
  postId: number;
}

/**
 * Template Settings Panel Component
 *
 * Provides configuration options for email template settings including
 * default subject line, sender name, and reply-to email address.
 * This component is displayed in the sidebar of the template editor.
 *
 * @returns {JSX.Element} The template settings panel with form controls
 *
 * @example
 * ```jsx
 * <TemplateSettings />
 * ```
 */
export default function TemplateSettings({
  postType,
  postId,
}: TemplateSettingsProps): JSX.Element {
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
  const getString = (key: string): string =>
    typeof values[key] === 'string' ? values[key] : '';

  // Template categories for dropdown
  const categoryOptions = [
    { label: __('General', 'campaignbridge'), value: 'general' },
    { label: __('Newsletter', 'campaignbridge'), value: 'newsletter' },
    { label: __('Promotional', 'campaignbridge'), value: 'promotional' },
    { label: __('Welcome', 'campaignbridge'), value: 'welcome' },
    { label: __('Custom', 'campaignbridge'), value: 'custom' },
  ];

  return (
    <>
      {/* Basic Settings */}
      <Panel>
        <PanelBody
          title={__('Template Settings', 'campaignbridge')}
          initialOpen={true}
        >
          <SelectControl
            label={__('Category', 'campaignbridge')}
            value={getString('campaignbridge_template_category') || 'general'}
            options={categoryOptions}
            onChange={value =>
              update('campaignbridge_template_category', value)
            }
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
        </PanelBody>
      </Panel>

      {/* Email Settings */}
      <Panel>
        <PanelBody
          title={__('Email Settings', 'campaignbridge')}
          initialOpen={false}
        >
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
              onChange={value =>
                update('campaignbridge_view_online_url', value)
              }
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
            help={__(
              'Can use merge tags like {unsubscribe_url}',
              'campaignbridge'
            )}
            __nextHasNoMarginBottom
            __next40pxDefaultSize
          />
        </PanelBody>
      </Panel>

      {/* Footer & Compliance */}
      <Panel>
        <PanelBody
          title={__('Footer & Compliance', 'campaignbridge')}
          initialOpen={false}
        >
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
            onChange={checked =>
              update('campaignbridge_footer_enabled', checked)
            }
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
        </PanelBody>
      </Panel>
    </>
  );
}
