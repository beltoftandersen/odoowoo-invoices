<?php
/**
 * Boot.
 *
 * @package OdooWooInvoices
 */

namespace OdooWooInvoices;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the pieces together once WooCommerce is there.
 */
final class Plugin {

	/**
	 * Hook everything up, or do nothing without WooCommerce.
	 */
	public static function boot(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		Rest::register();
		Download::register();
		Display::register();
		Settings::register();
	}
}
