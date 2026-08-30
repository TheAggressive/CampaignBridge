<?php
/**
 * Email compiler composition factory.
 *
 * @package CampaignBridge
 */

declare(strict_types=1);

namespace CampaignBridge\Services\Email;

use CampaignBridge\Domain\Email\Renderer_Registry;
use CampaignBridge\Services\Email\Renderer\Container_Renderer;
use CampaignBridge\Services\Email\Renderer\Post_Card_Renderer;
use CampaignBridge\Services\Email\Renderer\Post_Cta_Renderer;
use CampaignBridge\Services\Email\Renderer\Post_Excerpt_Renderer;
use CampaignBridge\Services\Email\Renderer\Post_Image_Renderer;
use CampaignBridge\Services\Email\Renderer\Post_Title_Renderer;
use CampaignBridge\Workflow\Email\Artifact_Fingerprinter;
use CampaignBridge\Workflow\Email\Email_Compiler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Composes the production compiler without service-locator lookups. */
final class Compiler_Factory {
	/** Create the production compiler graph. */
	public static function create(): Email_Compiler {
		$registry = new Renderer_Registry(
			array(
				new Container_Renderer(),
				new Post_Card_Renderer(),
				new Post_Title_Renderer(),
				new Post_Excerpt_Renderer(),
				new Post_Image_Renderer(),
				new Post_Cta_Renderer(),
			)
		);

		return new Email_Compiler(
			$registry,
			new Document_Renderer(),
			new Artifact_Fingerprinter()
		);
	}
}
