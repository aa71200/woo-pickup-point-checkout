<?php
declare(strict_types=1);

if ( PHP_SAPI !== 'cli' || ! getenv( 'CODEXAWP_TEST_WP_LOAD' ) ) {
	throw new RuntimeException( 'Set CODEXAWP_TEST_WP_LOAD to a disposable WordPress installation.' );
}
require getenv( 'CODEXAWP_TEST_WP_LOAD' );
if ( 'local' !== wp_get_environment_type() || ! function_exists( 'WC' ) ) {
	throw new RuntimeException( 'Use a local WooCommerce fixture.' );
}
require __DIR__ . '/catalog.php';

use CodexaWP\PickupPoint\Pickup_Method;

wc_load_cart();
WC()->shipping()->load_shipping_methods();
$product = new WC_Product_Simple();
$product->set_name( 'Pickup package fixture' );
$product->set_regular_price( '5.00' );
$product->set_status( 'publish' );
$product->save();
if ( ! WC()->cart->add_to_cart( $product->get_id() ) ) {
	throw new RuntimeException( 'Could not seed the fixture cart.' );
}
$package = WC()->cart->get_shipping_packages()[0];
update_option( 'woocommerce_codexawp_pickup_10011_settings', [ 'catalogue' => 'north' ] );
$method = new Pickup_Method( 10011 );
if ( 1 !== count( $method->get_rates_for_package( $package ) ) ) {
	throw new RuntimeException( 'Expected an available rate for the single fixture package.' );
}
$split = static fn( array $packages ): array => [ $packages[0], $packages[0] ];
add_filter( 'woocommerce_cart_shipping_packages', $split );
try {
	$method = new Pickup_Method( 10011 );
	if ( $method->get_rates_for_package( $package ) ) {
		throw new RuntimeException( 'Pickup should be unavailable with multiple packages.' );
	}
	echo "PASS: The pickup method is unavailable when WooCommerce splits the cart into multiple packages.\n";
} finally {
	remove_filter( 'woocommerce_cart_shipping_packages', $split );
}
