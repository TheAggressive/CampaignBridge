<?php
/**
 * Email compiler composition factory.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Services\Email;

use CampaignBridge\Domain\Email\Email_Design_Block_Defaults;
use CampaignBridge\Domain\Email\Renderer_Registry;
use CampaignBridge\Domain\Email\Resolved_Email_Design;
use CampaignBridge\Domain\Email\Token\Token_Resolver;
use CampaignBridge\Services\Email\Design\Email_Design_Factory;
use CampaignBridge\Services\Email\Renderer\Button_Renderer;
use CampaignBridge\Services\Email\Renderer\Buttons_Renderer;
use CampaignBridge\Services\Email\Renderer\Column_Renderer;
use CampaignBridge\Services\Email\Renderer\Columns_Renderer;
use CampaignBridge\Services\Email\Renderer\Compliance_Footer_Renderer;
use CampaignBridge\Services\Email\Renderer\Container_Renderer;
use CampaignBridge\Services\Email\Renderer\Divider_Renderer;
use CampaignBridge\Services\Email\Renderer\Heading_Renderer;
use CampaignBridge\Services\Email\Renderer\Image_Renderer;
use CampaignBridge\Services\Email\Renderer\List_Item_Renderer;
use CampaignBridge\Services\Email\Renderer\List_Renderer;
use CampaignBridge\Services\Email\Renderer\Post_Card_Renderer;
use CampaignBridge\Services\Email\Renderer\Post_Image_Renderer;
use CampaignBridge\Services\Email\Renderer\Preheader_Renderer;
use CampaignBridge\Services\Email\Renderer\Section_Renderer;
use CampaignBridge\Services\Email\Renderer\Spacer_Renderer;
use CampaignBridge\Services\Email\Renderer\Text_Renderer;
use CampaignBridge\Workflow\Email\Artifact_Fingerprinter;
use CampaignBridge\Workflow\Email\Email_Compiler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Composes the production compiler without service-locator lookups. */
final class Compiler_Factory {
	/**
	 * Create the production compiler graph.
	 *
	 * @param Resolved_Email_Design|null $design Canonical design, or packaged defaults.
	 */
	public static function create( ?Resolved_Email_Design $design = null ): Email_Compiler {
		return new Email_Compiler(
			self::registry(),
			new Document_Renderer(),
			new Artifact_Fingerprinter(),
			$design ?? Email_Design_Factory::resolve(),
			new Email_Design_Block_Defaults(),
			new Core_Block_Normalizer(),
			Token_Resolver::default()
		);
	}

	/** Create the immutable production renderer registry. */
	public static function registry(): Renderer_Registry {
		return new Renderer_Registry(
			array(
				new Container_Renderer(),
				new Preheader_Renderer(),
				new Section_Renderer(),
				new Columns_Renderer(),
				new Column_Renderer(),
				new Buttons_Renderer(),
				new Text_Renderer(),
				new Heading_Renderer(),
				new Image_Renderer(),
				new Button_Renderer(),
				new Divider_Renderer(),
				new Spacer_Renderer(),
				new List_Renderer(),
				new List_Item_Renderer(),
				new Post_Card_Renderer(),
				new Post_Image_Renderer(),
				new Compliance_Footer_Renderer(),
			)
		);
	}
}
