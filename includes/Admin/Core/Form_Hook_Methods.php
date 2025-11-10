<?php
/**
 * Form Hook Methods Trait - Provides hook registration API
 *
 * Contains all lifecycle hook methods for form events
 * while maintaining perfect static analysis compatibility.
 *
 * @package CampaignBridge\Admin\Core
 */

namespace CampaignBridge\Admin\Core;

/**
 * Form Hook Methods Trait
 *
 * @package CampaignBridge\Admin\Core
 */
trait Form_Hook_Methods {
	/**
	 * Add a generic lifecycle hook
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Hook callback.
	 * @return static
	 */
	public function on( string $hook, callable $callback ): self {
		$this->builder->on( $hook, $callback );
		return $this;
	}

	/**
	 * Add before save hook
	 *
	 * @param callable $callback Hook callback.
	 * @return static
	 */
	public function before_save( callable $callback ): self {
		$this->builder->before_save( $callback );
		return $this;
	}

	/**
	 * Add after save hook
	 *
	 * @param callable $callback Hook callback.
	 * @return static
	 */
	public function after_save( callable $callback ): self {
		$this->builder->after_save( $callback );
		return $this;
	}

	/**
	 * Add before validate hook
	 *
	 * @param callable $callback Hook callback.
	 * @return static
	 */
	public function before_validate( callable $callback ): self {
		$this->builder->before_validate( $callback );
		return $this;
	}

	/**
	 * Add after validate hook
	 *
	 * @param callable $callback Hook callback.
	 * @return static
	 */
	public function after_validate( callable $callback ): self {
		$this->builder->after_validate( $callback );
		return $this;
	}

	/**
	 * Add on success hook
	 *
	 * @param callable $callback Hook callback.
	 * @return static
	 */
	public function on_success( callable $callback ): self {
		$this->builder->on_success( $callback );
		return $this;
	}

	/**
	 * Add on error hook
	 *
	 * @param callable $callback Hook callback.
	 * @return static
	 */
	public function on_error( callable $callback ): self {
		$this->builder->on_error( $callback );
		return $this;
	}
}
