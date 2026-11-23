<?php
/**
 * Plugin Name: Pickup Point Checkout for WooCommerce
 * Description: A portfolio demo for choosing and validating a pickup point at checkout.
 * Version: 1.0.0
 * Author: CodexaWP
 * Requires at least: 7.0
 * Requires PHP: 8.2
 * Requires Plugins: woocommerce
 * WC requires at least: 11.1
 * WC tested up to: 11.1
 * License: GPL-2.0-or-later
 * Text Domain: woo-pickup-point-checkout
 */

declare(strict_types=1);

namespace CodexaWP\PickupPoint;

use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema;
use Automattic\WooCommerce\Utilities\FeaturesUtil;

defined( 'ABSPATH' ) || exit;

const METHOD = 'codexawp_pickup';
const EXTENSION = 'codexawp-pickup';
const FIELD = 'codexawp_pickup_point_id';
const META = '_codexawp_pickup_point';

require_once __DIR__ . '/includes/catalog.php';

add_action( 'before_woocommerce_init', static function (): void {
	if ( class_exists( FeaturesUtil::class ) ) {
		FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
	}
} );

add_action( 'woocommerce_shipping_init', static function (): void {
	require_once __DIR__ . '/includes/class-pickup-method.php';
} );

add_filter( 'woocommerce_shipping_methods', static function ( array $methods ): array {
	$methods[METHOD] = Pickup_Method::class;
	return $methods;
} );

function pickup_context(): array {
	$packages = WC()->cart && WC()->cart->needs_shipping() ? WC()->shipping()->get_packages() : [];
	if ( 1 !== count( $packages ) ) {
		return [ 'rate' => '', 'points' => [] ];
	}

	$key = array_key_first( $packages );
	$package = $packages[$key];
	$chosen = (array) WC()->session->get( 'chosen_shipping_methods', [] );
	$rate_id = $chosen[$key] ?? '';
	$rate = $package['rates'][$rate_id] ?? null;

	if ( ! $rate instanceof \WC_Shipping_Rate || METHOD !== $rate->get_method_id() ) {
		return [ 'rate' => '', 'points' => [] ];
	}

	$method = \WC_Shipping_Zones::get_shipping_method( $rate->get_instance_id() );
	$points = $method instanceof Pickup_Method ? active_points( $method->get_option( 'catalogue' ) ) : [];

	return [ 'rate' => $rate_id, 'points' => $points ];
}

function invalid_selection_message(): string {
	return __( 'Choose an available pickup point for your current shipping method.', 'woo-pickup-point-checkout' );
}

function store_selection( \WC_Order $order, array $context, mixed $id ): void {
	$point = find_point( $context['points'], $id );
	if ( $point ) {
		$order->update_meta_data( META, $point );
	} else {
		$order->delete_meta_data( META );
	}
}

function classic_id(): mixed {
	// WooCommerce validates its checkout nonce before running checkout validation.
	$value = $_POST[FIELD] ?? null; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	return is_string( $value ) ? wp_unslash( $value ) : null;
}

add_action( 'woocommerce_after_checkout_validation', static function ( array $data, \WP_Error $errors ): void {
	$context = pickup_context();
	if ( $context['rate'] && ! find_point( $context['points'], classic_id() ) ) {
		$errors->add( 'codexawp_pickup_unavailable', invalid_selection_message() );
	}
}, 10, 2 );

add_action( 'woocommerce_checkout_create_order', static function ( \WC_Order $order ): void {
	store_selection( $order, pickup_context(), classic_id() );
} );

add_action( 'woocommerce_blocks_loaded', static function (): void {
	woocommerce_store_api_register_endpoint_data( [
		'endpoint'        => CartSchema::IDENTIFIER,
		'namespace'       => EXTENSION,
		'data_callback'   => __NAMESPACE__ . '\\pickup_context',
		'schema_callback' => static fn(): array => [
			'rate'   => [ 'type' => 'string', 'readonly' => true ],
			'points' => [
				'type' => 'array',
				'readonly' => true,
				'items' => [
					'type' => 'object',
					'properties' => [
						'id'      => [ 'type' => 'string' ],
						'label'   => [ 'type' => 'string' ],
						'address' => [ 'type' => 'string' ],
					],
				],
			],
		],
	] );
	woocommerce_store_api_register_endpoint_data( [
		'endpoint'        => CheckoutSchema::IDENTIFIER,
		'namespace'       => EXTENSION,
		'schema_callback' => static fn(): array => [
			'pickup_point_id' => [ 'type' => 'string', 'maxLength' => 12 ],
		],
	] );
} );

add_action( 'woocommerce_store_api_checkout_update_order_from_request', static function ( \WC_Order $order, \WP_REST_Request $request ): void {
	if ( 'POST' !== $request->get_method() ) {
		return;
	}
	$context = pickup_context();
	$id = $request['extensions'][EXTENSION]['pickup_point_id'] ?? null;
	if ( $context['rate'] && ! find_point( $context['points'], $id ) ) {
		throw new RouteException( 'codexawp_pickup_unavailable', invalid_selection_message(), 400 );
	}
	store_selection( $order, $context, $id );
}, 10, 2 );

function classic_panel(): void {
	$context = pickup_context();
	?>
	<div id="codexawp-pickup-classic" data-rate="<?php echo esc_attr( $context['rate'] ); ?>" <?php echo $context['rate'] ? '' : 'hidden'; ?>>
		<?php if ( $context['rate'] ) : ?>
			<fieldset class="codexawp-pickup">
				<legend><?php esc_html_e( 'Choose a pickup point', 'woo-pickup-point-checkout' ); ?></legend>
				<label for="codexawp-classic-search"><?php esc_html_e( 'Search by name, address or code', 'woo-pickup-point-checkout' ); ?></label>
				<input type="search" id="codexawp-classic-search" autocomplete="off">
				<div class="codexawp-pickup-options">
					<?php foreach ( $context['points'] as $point ) : ?>
						<label class="codexawp-pickup-option">
							<input type="radio" name="<?php echo esc_attr( FIELD ); ?>" value="<?php echo esc_attr( $point['id'] ); ?>">
							<span><strong><?php echo esc_html( $point['label'] ); ?></strong><small><?php echo esc_html( $point['address'] . ' · ' . $point['id'] ); ?></small></span>
						</label>
					<?php endforeach; ?>
				</div>
				<p class="codexawp-pickup-empty" role="status" <?php echo $context['points'] ? 'hidden' : ''; ?>><?php esc_html_e( 'No matching pickup points.', 'woo-pickup-point-checkout' ); ?></p>
			</fieldset>
		<?php endif; ?>
	</div>
	<?php
}

add_action( 'woocommerce_checkout_before_order_review', __NAMESPACE__ . '\\classic_panel' );

add_filter( 'woocommerce_update_order_review_fragments', static function ( array $fragments ): array {
	ob_start();
	classic_panel();
	$fragments['#codexawp-pickup-classic'] = ob_get_clean();
	return $fragments;
} );

add_action( 'init', static function (): void {
	wp_register_script( 'codexawp-pickup-editor', plugins_url( 'assets/editor.js', __FILE__ ), [ 'wp-blocks', 'wp-element', 'wp-i18n' ], '1.0.0', true );
	wp_register_script( 'codexawp-pickup-block', plugins_url( 'assets/checkout-block.js', __FILE__ ), [ 'wc-blocks-checkout', 'wc-blocks-data-store', 'wp-data', 'wp-element', 'wp-i18n' ], '1.0.0', true );
	wp_register_style( 'codexawp-pickup', plugins_url( 'assets/pickup.css', __FILE__ ), [], '1.1.0' );
	register_block_type( __DIR__ . '/block.json', [
		'render_callback' => static fn(): string => '<div data-block-name="codexawp/pickup-point"></div>',
	] );
} );

add_action( 'wp_enqueue_scripts', static function (): void {
	$post = get_post();
	if ( $post && has_shortcode( $post->post_content, 'woocommerce_checkout' ) ) {
		wp_enqueue_style( 'codexawp-pickup' );
		wp_enqueue_script( 'codexawp-pickup-classic', plugins_url( 'assets/classic.js', __FILE__ ), [ 'jquery' ], '1.0.0', true );
	}
} );

add_action( 'woocommerce_admin_order_data_after_shipping_address', static function ( \WC_Order $order ): void {
	$point = $order->get_meta( META );
	if ( ! is_array( $point ) || ! isset( $point['id'], $point['label'], $point['address'] ) ) {
		return;
	}
	echo '<p><strong>' . esc_html__( 'Pickup point', 'woo-pickup-point-checkout' ) . '</strong><br>'
		. esc_html( $point['label'] . ' (' . $point['id'] . ')' ) . '<br>' . esc_html( $point['address'] ) . '</p>';
} );
