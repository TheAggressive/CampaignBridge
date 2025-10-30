<?php
/**
 * Test data factory for CampaignBridge tests.
 *
 * @package CampaignBridge\Tests
 */

namespace CampaignBridge\Tests\Helpers;

/**
 * Factory for creating test data.
 */
class Test_Factory {

	/**
	 * Create test email template data.
	 *
	 * @param array $overrides Data to override defaults.
	 * @return array
	 */
	public static function create_email_template_data( array $overrides = array() ): array {
		$defaults = array(
			'post_title'   => 'Test Email Template ' . uniqid(),
			'post_content' => '<!-- wp:paragraph --><p>Test email content</p><!-- /wp:paragraph -->',
			'post_status'  => 'publish',
			'post_type'    => 'cb_templates',
			'meta_input'   => array(
				'_campaignbridge_subject'           => 'Test Subject Line',
				'_campaignbridge_template_category' => 'newsletter',
				'_campaignbridge_template_active'   => '1',
			),
		);

		return wp_parse_args( $overrides, $defaults );
	}

	/**
	 * Create test campaign data.
	 *
	 * @param array $overrides Data to override defaults.
	 * @return array
	 */
	public static function create_campaign_data( array $overrides = array() ): array {
		$defaults = array(
			'post_title'  => 'Test Campaign ' . uniqid(),
			'post_status' => 'publish',
			'post_type'   => 'cb_campaign',
			'meta_input'  => array(
				'_campaignbridge_campaign_status'     => 'draft',
				'_campaignbridge_campaign_recipients' => 'subscribers',
				'_campaignbridge_campaign_schedule'   => '',
			),
		);

		return wp_parse_args( $overrides, $defaults );
	}

	/**
	 * Create test settings data.
	 *
	 * @param array $overrides Settings to override defaults.
	 * @return array
	 */
	public static function create_settings_data( array $overrides = array() ): array {
		$defaults = array(
			'mailchimp_api_key'    => 'test-api-key-123',
			'mailchimp_list_id'    => 'test-list-123',
			'default_from_name'    => 'Test Sender',
			'default_from_email'   => 'test@example.com',
			'enable_debug_logging' => false,
			'template_cache_ttl'   => 3600,
		);

		return wp_parse_args( $overrides, $defaults );
	}

	/**
	 * Create test user with CampaignBridge-specific data.
	 *
	 * @param array $overrides User data to override defaults.
	 * @return array
	 */
	public static function create_subscriber_data( array $overrides = array() ): array {
		$unique_id = uniqid();
		$defaults  = array(
			'user_login'    => "testsubscriber{$unique_id}",
			'user_email'    => "subscriber{$unique_id}@example.com",
			'user_pass'     => 'testpass123',
			'first_name'    => 'Test',
			'last_name'     => 'Subscriber',
			'role'          => 'subscriber',
			'user_status'   => 0,
			'display_name'  => 'Test Subscriber',
			'user_nicename' => "test-subscriber-{$unique_id}",
		);

		return wp_parse_args( $overrides, $defaults );
	}

	/**
	 * Create test Mailchimp API response data.
	 *
	 * @param string $endpoint API endpoint.
	 * @param array  $overrides Response data to override defaults.
	 * @return array
	 */
	public static function create_mailchimp_response( string $endpoint, array $overrides = array() ): array {
		$responses = array(
			'lists'     => array(
				'lists'       => array(
					array(
						'id'                => 'test-list-123',
						'name'              => 'Test List',
						'member_count'      => 100,
						'unsubscribe_count' => 5,
						'cleaned_count'     => 2,
					),
				),
				'total_items' => 1,
				'_links'      => array(),
			),
			'campaigns' => array(
				'campaigns'   => array(
					array(
						'id'           => 'test-campaign-123',
						'type'         => 'regular',
						'status'       => 'sent',
						'emails_sent'  => 95,
						'send_time'    => gmdate( 'c' ),
						'subject_line' => 'Test Campaign Subject',
					),
				),
				'total_items' => 1,
				'_links'      => array(),
			),
			'members'   => array(
				'members'     => array(
					array(
						'id'            => 'test-member-123',
						'email_address' => 'test@example.com',
						'status'        => 'subscribed',
						'merge_fields'  => array(
							'FNAME' => 'Test',
							'LNAME' => 'User',
						),
					),
				),
				'total_items' => 1,
				'_links'      => array(),
			),
		);

		$default = $responses[ $endpoint ] ?? array(
			'status' => 'success',
			'data'   => array(),
		);

		return wp_parse_args( $overrides, $default );
	}

	/**
	 * Create test WordPress REST API response.
	 *
	 * @param array $data Response data.
	 * @param int   $status HTTP status code.
	 * @return array
	 */
	public static function create_rest_response( array $data, int $status = 200 ): array {
		return array(
			'success' => $status >= 200 && $status < 300,
			'data'    => $data,
			'status'  => $status,
			'headers' => array(
				'Content-Type' => 'application/json',
			),
		);
	}

	/**
	 * Create test block content.
	 *
	 * @param string $block_name Block name.
	 * @param array  $attributes Block attributes.
	 * @param string $inner_content Inner content.
	 * @return string
	 */
	public static function create_block_content( string $block_name, array $attributes = array(), string $inner_content = '' ): string {
		$attributes_json = wp_json_encode( $attributes );

		return "<!-- wp:{$block_name} {$attributes_json} -->{$inner_content}<!-- /wp:{$block_name} -->";
	}

	/**
	 * Create test email template with blocks.
	 *
	 * @param array $overrides Template data to override defaults.
	 * @return array
	 */
	public static function create_block_email_template( array $overrides = array() ): array {
		$default_content = self::create_block_content(
			'campaignbridge/email-header',
			array( 'title' => 'Newsletter Header' )
		) .
		self::create_block_content(
			'core/paragraph',
			array(),
			'<p>This is a test email template with blocks.</p>'
		) .
		self::create_block_content(
			'campaignbridge/post-list',
			array(
				'postType'      => 'post',
				'numberOfPosts' => 3,
				'showExcerpt'   => true,
			)
		);

		$defaults = array(
			'post_title'   => 'Block Email Template ' . uniqid(),
			'post_content' => $default_content,
			'post_status'  => 'publish',
			'post_type'    => 'cb_templates',
			'meta_input'   => array(
				'_campaignbridge_subject'           => 'Block Template Subject',
				'_campaignbridge_template_category' => 'newsletter',
				'_campaignbridge_template_active'   => '1',
			),
		);

		return wp_parse_args( $overrides, $defaults );
	}
}
