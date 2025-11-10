<?php
/**
 * Form Layout Methods Trait - Provides layout configuration API
 *
 * Contains all layout and display configuration methods
 * while maintaining perfect static analysis compatibility.
 *
 * @package CampaignBridge\Admin\Core
 */

namespace CampaignBridge\Admin\Core;

use CampaignBridge\Admin\Core\Form_Builder;

/**
 * Form Layout Methods Trait
 *
 * @package CampaignBridge\Admin\Core
 */
trait Form_Layout_Methods {
	/**
	 * Set table layout
	 *
	 * @return static
	 */
	public function table(): self {
		$this->builder->table();
		return $this;
	}

	/**
	 * Set div layout
	 *
	 * @return static
	 */
	public function div(): self {
		$this->builder->div();
		return $this;
	}

	/**
	 * Auto-detect optimal layout based on context
	 *
	 * @return static
	 */
	public function auto_layout(): self {
		$this->builder->auto_layout();
		return $this;
	}

	/**
	 * Set custom layout renderer
	 *
	 * @param callable $renderer Custom render function.
	 * @return static
	 */
	public function render_custom( callable $renderer ): self {
		$this->builder->render_custom( $renderer );
		return $this;
	}

	/**
	 * Set the form description.
	 *
	 * @param string $description Form description.
	 * @return static
	 */
	public function description( string $description ): self {
		$this->builder->description( $description );
		return $this;
	}
}
