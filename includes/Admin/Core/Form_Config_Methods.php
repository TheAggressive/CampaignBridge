<?php
/**
 * Form Configuration Methods Trait - Provides form configuration API
 *
 * Contains all form configuration methods including save methods,
 * messaging, and general form settings.
 *
 * @package CampaignBridge\Admin\Core
 */

namespace CampaignBridge\Admin\Core;

/**
 * Form Configuration Methods Trait
 *
 * @package CampaignBridge\Admin\Core
 */
trait Form_Config_Methods {
	/**
	 * Save form data to WordPress Options API
	 *
	 * @param string $prefix Option prefix.
	 * @return static
	 */
	public function save_to_options( string $prefix = '' ): self {
		$this->builder->save_to_options( $prefix );
		return $this;
	}

	/**
	 * Save form data to post meta
	 *
	 * @param int $post_id Post ID.
	 * @return static
	 */
	public function save_to_post_meta( int $post_id = 0 ): self {
		$this->builder->save_to_post_meta( $post_id );
		return $this;
	}

	/**
	 * Save form data to WordPress Settings API
	 *
	 * @param string $group Settings group.
	 * @return static
	 */
	public function save_to_settings_api( string $group = '' ): self {
		$this->builder->save_to_settings_api( $group );
		return $this;
	}

	/**
	 * Save form data using custom callback
	 *
	 * @param callable $callback Save callback.
	 * @return static
	 */
	public function save_to_custom( callable $callback ): self {
		$this->builder->save_to_custom( $callback );
		return $this;
	}

	/**
	 * Set success message
	 *
	 * @param string $message Success message.
	 * @return static
	 */
	public function success( string $message = '' ): self {
		if ( empty( $message ) ) {
			$message = __( 'Saved successfully!', 'campaignbridge' );
		}
		$this->builder->success( $message );
		return $this;
	}

	/**
	 * Set error message
	 *
	 * @param string $message Error message.
	 * @return static
	 */
	public function error( string $message ): self {
		$this->builder->error( $message );
		return $this;
	}

	/**
	 * Set option prefix for field keys
	 *
	 * @param string $prefix Prefix for option keys.
	 * @return static
	 */
	public function prefix( string $prefix ): self {
		$this->builder->prefix( $prefix );
		return $this;
	}

	/**
	 * Set option suffix for field keys
	 *
	 * @param string $suffix Suffix for option keys.
	 * @return static
	 */
	public function suffix( string $suffix ): self {
		$this->builder->suffix( $suffix );
		return $this;
	}

	/**
	 * Set submit button configuration
	 *
	 * @param string $text Button text.
	 * @param string $type Button type (primary, secondary).
	 * @return static
	 */
	public function submit( string $text = 'Save', string $type = 'primary' ): self {
		$this->builder->submit( $text, $type );
		return $this;
	}
}
