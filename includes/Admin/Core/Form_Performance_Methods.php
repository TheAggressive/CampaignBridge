<?php
/**
 * Form Performance Methods Trait - Provides performance monitoring API
 *
 * Contains all performance monitoring and optimization methods
 * while maintaining perfect static analysis compatibility.
 *
 * @package CampaignBridge\Admin\Core
 */

namespace CampaignBridge\Admin\Core;

/**
 * Form Performance Methods Trait
 *
 * @package CampaignBridge\Admin\Core
 */
trait Form_Performance_Methods {
	/**
	 * Get the query optimizer instance for database performance.
	 *
	 * @return Forms\Form_Query_Optimizer Query optimizer instance.
	 */
	public function get_query_optimizer(): Forms\Form_Query_Optimizer {
		return $this->query_optimizer;
	}

	/**
	 * Monitor performance of a form operation.
	 *
	 * @param string   $operation_name Name of the operation.
	 * @param callable $operation      The operation to monitor.
	 * @return mixed The result of the operation.
	 */
	public function monitor_performance( string $operation_name, callable $operation ) {
		return $this->query_optimizer->monitor_query_performance( $operation_name, $operation );
	}

	/**
	 * Get database performance recommendations.
	 *
	 * @return array<int|string, mixed> Array of performance recommendations.
	 */
	public function get_performance_recommendations(): array {
		return $this->query_optimizer->get_performance_recommendations();
	}

	/**
	 * Get the asset optimizer instance for loading performance.
	 *
	 * @return Forms\Form_Asset_Optimizer Asset optimizer instance.
	 */
	public function get_asset_optimizer(): Forms\Form_Asset_Optimizer {
		return $this->asset_optimizer;
	}
}
