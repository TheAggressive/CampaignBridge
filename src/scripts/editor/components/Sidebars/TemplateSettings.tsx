import {
  Panel,
  PanelBody,
  SelectControl,
  TextControl,
  TextareaControl,
  ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useAutoSaveMetaManager } from '../../hooks/useAutoSaveMetaManager';
import { useNotices } from '../../hooks/useNotices';

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
export default function TemplateSettings({ postType, postId }) {
  const { success, error } = useNotices();
  const {
    values: rawValues,
    update,
    saveStatus,
  } = useAutoSaveMetaManager({
    postType,
    postId,
    keys: [
      'cb_template_category',
      'cb_subject',
      'cb_preheader',
      'cb_audience_tags',
    ],
    onSuccess: success,
    onError: error,
  });

  const values: Record<string, string | undefined> = rawValues || {};
  const isLoading = saveStatus === 'saving';

  // Show loading state
  if (isLoading) {
    return (
      <Panel>
        <PanelBody title={__('Template Settings', 'campaignbridge')}>
          <p>{__('Loading template settings...', 'campaignbridge')}</p>
        </PanelBody>
      </Panel>
    );
  }

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
            value={values.cb_template_category || 'general'}
            options={categoryOptions}
            onChange={value => update('cb_template_category', value)}
            __nextHasNoMarginBottom
            __next40pxDefaultSize
          />

          <TextControl
            label={__('Subject Line', 'campaignbridge')}
            value={values.cb_subject || ''}
            onChange={value => update('cb_subject', value)}
            placeholder={__('Enter email subject...', 'campaignbridge')}
            __nextHasNoMarginBottom
            __next40pxDefaultSize
          />

          <TextControl
            label={__('Preheader Text', 'campaignbridge')}
            value={values.cb_preheader || ''}
            onChange={value => update('cb_preheader', value)}
            placeholder={__('Hidden preview text...', 'campaignbridge')}
            help={__('Shown in email client previews', 'campaignbridge')}
            __nextHasNoMarginBottom
            __next40pxDefaultSize
          />

          <TextControl
            label={__('Audience Tags', 'campaignbridge')}
            value={values.cb_audience_tags || ''}
            onChange={value => update('cb_audience_tags', value)}
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
            value={values.cb_sender_name || ''}
            onChange={value => update('cb_sender_name', value)}
            placeholder={__('Your Name', 'campaignbridge')}
            __nextHasNoMarginBottom
            __next40pxDefaultSize
          />

          <TextControl
            label={__('Sender Email', 'campaignbridge')}
            value={values.cb_sender_email || ''}
            onChange={value => update('cb_sender_email', value)}
            type='email'
            placeholder={__('sender@domain.com', 'campaignbridge')}
            __nextHasNoMarginBottom
            __next40pxDefaultSize
          />

          <ToggleControl
            label={__('Enable View Online Link', 'campaignbridge')}
            checked={values.cb_view_online_enabled === '1'}
            onChange={checked =>
              update('cb_view_online_enabled', checked ? '1' : '0')
            }
            __nextHasNoMarginBottom
          />

          {values.cb_view_online_enabled === '1' && (
            <TextControl
              label={__('View Online URL', 'campaignbridge')}
              value={values.cb_view_online_url || ''}
              onChange={value => update('cb_view_online_url', value)}
              type='url'
              placeholder={__('https://...', 'campaignbridge')}
              __nextHasNoMarginBottom
              __next40pxDefaultSize
            />
          )}

          <TextControl
            label={__('Unsubscribe URL', 'campaignbridge')}
            value={values.cb_unsubscribe_url || ''}
            onChange={value => update('cb_unsubscribe_url', value)}
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
            value={values.cb_address_html || ''}
            onChange={value => update('cb_address_html', value)}
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
            checked={values.cb_utm_enabled === '1'}
            onChange={checked => update('cb_utm_enabled', checked ? '1' : '0')}
            __nextHasNoMarginBottom
          />

          {(values.cb_utm_enabled === '1' || values.cb_utm_enabled === '1') && (
            <TextControl
              label={__('UTM Template', 'campaignbridge')}
              value={values.cb_utm_template || ''}
              onChange={value => update('cb_utm_template', value)}
              placeholder={'utm_source=newsletter&utm_campaign={post_slug}'}
              help={__('Template for UTM query parameters', 'campaignbridge')}
              __nextHasNoMarginBottom
              __next40pxDefaultSize
            />
          )}

          <ToggleControl
            label={__('Enable Default Footer', 'campaignbridge')}
            checked={
              values.cb_footer_enabled === '1' ||
              values.cb_footer_enabled === '1'
            }
            onChange={checked =>
              update('cb_footer_enabled', checked ? '1' : '0')
            }
            __nextHasNoMarginBottom
          />

          {(values.cb_footer_enabled === '1' ||
            values.cb_footer_enabled === '1') && (
            <TextControl
              label={__('Footer Pattern', 'campaignbridge')}
              value={values.cb_footer_pattern || ''}
              onChange={value => update('cb_footer_pattern', value)}
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
