<?php
/**
 * Plugin Name:          OdooWoo Invoices
 * Plugin URI:           https://github.com/beltoftandersen/odoowoo-invoices
 * Description:          Receives invoices from OdooWoo and lets customers download them from My Account, behind the shop's own login.
 * Version:              0.1.0
 * Requires at least:    6.5
 * Requires PHP:         8.1
 * Requires Plugins:     woocommerce
 * Author:               beltoft.net
 * Author URI:           https://beltoft.net/
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          odoowoo-invoices
 * Domain Path:          /languages
 * WC requires at least: 9.0
 *
 * @package OdooWooInvoices
 */

defined( 'ABSPATH' ) || exit;

define( 'ODOOWOO_INVOICES_FILE', __FILE__ );
define( 'ODOOWOO_INVOICES_PATH', plugin_dir_path( __FILE__ ) );
define( 'ODOOWOO_INVOICES_VERSION', '0.1.0' );

spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'OdooWooInvoices\\';

		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$file = ODOOWOO_INVOICES_PATH . 'src/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';

		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

add_action( 'plugins_loaded', array( \OdooWooInvoices\Plugin::class, 'boot' ) );

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', ODOOWOO_INVOICES_FILE, true );
		}
	}
);
