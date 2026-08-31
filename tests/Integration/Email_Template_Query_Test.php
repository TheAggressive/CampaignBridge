<?php
/**
 * Email template query integration tests.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Tests\Integration;

use CampaignBridge\Post_Types\Post_Type_Email_Template;
use WP_UnitTestCase;

/**
 * Verify template retrieval remains complete when queries are bounded.
 */
class Email_Template_Query_Test extends WP_UnitTestCase {
	/**
	 * Bounded queries preserve the existing all-template return contract.
	 */
	public function test_template_queries_collect_every_batch(): void {
		Post_Type_Email_Template::register_post_type();

		for ( $index = 0; $index < 103; ++$index ) {
			$this->factory->post->create(
				array(
					'post_type'   => Post_Type_Email_Template::POST_TYPE,
					'post_status' => 'publish',
					'post_title'  => sprintf( 'Template %03d', $index ),
					'meta_input'  => array(
						'campaignbridge_template_category' => $index < 101 ? 'newsletter' : 'promotional',
					),
				)
			);
		}

		$templates            = Post_Type_Email_Template::get_templates();
		$newsletter_templates = Post_Type_Email_Template::get_templates_by_category( 'newsletter' );

		$this->assertCount( 103, $templates );
		$this->assertCount( 101, $newsletter_templates );
		$this->assertSame( 'Template 000', $templates[0]->post_title );
		$this->assertSame( 'Template 102', $templates[102]->post_title );
	}
}
