<?php
/**
 * PHPUnit bootstrap: WooCommerce and this plugin in the WordPress test suite.
 *
 *   /root/plugin-tests/run.sh <path-to-this-plugin>
 *
 * @package OdooWooInvoices
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

$odoowoo_invoices_tests = getenv( 'WP_TESTS_DIR' ) ? getenv( 'WP_TESTS_DIR' ) : '/tmp/wordpress-tests-lib';
$odoowoo_invoices_site  = getenv( 'SITE_PLUGINS' );

require_once $odoowoo_invoices_tests . '/includes/functions.php';

if ( ! $odoowoo_invoices_site || ! file_exists( $odoowoo_invoices_site . '/woocommerce/woocommerce.php' ) ) {
	fwrite( STDERR, "Test bootstrap: SITE_PLUGINS must point at a plugins folder containing woocommerce/woocommerce.php.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
	exit( 1 );
}

tests_add_filter(
	'muplugins_loaded',
	static function () use ( $odoowoo_invoices_site ) {
		require $odoowoo_invoices_site . '/woocommerce/woocommerce.php';
		require dirname( __DIR__ ) . '/odoowoo-invoices.php';
	}
);

tests_add_filter(
	'init',
	static function () {
		if ( class_exists( 'WC_Install' ) ) {
			WC_Install::install();
		}
	},
	20
);

require $odoowoo_invoices_tests . '/includes/bootstrap.php';
