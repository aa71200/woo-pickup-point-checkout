<?php
declare(strict_types=1);

namespace CodexaWP\PickupPoint;

defined( 'ABSPATH' ) || exit;

final class Pickup_Method extends \WC_Shipping_Method {
	public function __construct( $instance_id = 0 ) {
		$this->id = METHOD;
		$this->instance_id = absint( $instance_id );
		$this->method_title = __( 'Pickup Point Demo', 'woo-pickup-point-checkout' );
		$this->method_description = __( 'Offer a pickup point from a sample regional catalogue.', 'woo-pickup-point-checkout' );
		$this->supports = [ 'shipping-zones', 'instance-settings', 'instance-settings-modal' ];
		$this->instance_form_fields = [
			'catalogue' => [
				'title'   => __( 'Sample catalogue', 'woo-pickup-point-checkout' ),
				'type'    => 'select',
				'options' => [
					'north' => __( 'North District', 'woo-pickup-point-checkout' ),
					'south' => __( 'South District', 'woo-pickup-point-checkout' ),
				],
				'default' => 'north',
			],
		];
		$this->title = __( 'Pickup Point', 'woo-pickup-point-checkout' );
		$this->tax_status = 'none';

		add_action( 'woocommerce_update_options_shipping_' . $this->id, [ $this, 'process_admin_options' ] );
	}

	public function calculate_shipping( $package = [] ): void {
		if ( ! WC()->cart || 1 !== count( WC()->cart->get_shipping_packages() ) || ! active_points( $this->get_option( 'catalogue' ) ) ) {
			return;
		}

		$this->add_rate( [
			'label'   => $this->get_title(),
			'cost'    => 0,
			'package' => $package,
		] );
	}
}
