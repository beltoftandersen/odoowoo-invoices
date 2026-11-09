<?php
/**
 * The only way a stored PDF leaves the server.
 *
 * @package OdooWooInvoices
 */

namespace OdooWooInvoices;

defined( 'ABSPATH' ) || exit;

/**
 * admin-post.php?action=odoowoo_invoice, for the order's owner, shop managers, or a guest
 * holding the order key of a guest order.
 */
final class Download {

	public const ACTION = 'odoowoo_invoice';

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'serve' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( self::class, 'serve' ) );
	}

	/**
	 * The link for one invoice of an order.
	 *
	 * @param \WC_Order $order      Order.
	 * @param int       $invoice_id Odoo invoice id.
	 */
	public static function url( \WC_Order $order, int $invoice_id ): string {
		$args = array(
			'action'  => self::ACTION,
			'order'   => $order->get_id(),
			'invoice' => $invoice_id,
		);

		// A guest has no login; the order key (as on the order-received page) stands in for it.
		if ( 0 === (int) $order->get_customer_id() ) {
			$args['key'] = $order->get_order_key();
		}

		return add_query_arg( $args, admin_url( 'admin-post.php' ) );
	}

	/**
	 * Whether this visitor may download this order's invoices.
	 *
	 * @param \WC_Order $order Order.
	 * @param string    $key   Order key from the request, if any.
	 * @param int       $user  Current user id (0 for a guest).
	 */
	public static function allowed( \WC_Order $order, string $key, int $user ): bool {
		if ( $user > 0 && user_can( $user, 'manage_woocommerce' ) ) {
			return true;
		}

		if ( $user > 0 && (int) $order->get_customer_id() === $user ) {
			return true;
		}

		return 0 === (int) $order->get_customer_id() && '' !== $key && hash_equals( $order->get_order_key(), $key );
	}

	/**
	 * Stream the PDF, or answer 404 for anything that is not exactly allowed.
	 */
	public static function serve(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Authorised by ownership or the order key, not a nonce: the link is opened from emails and pages.
		$order_id   = isset( $_GET['order'] ) ? absint( wp_unslash( $_GET['order'] ) ) : 0;
		$invoice_id = isset( $_GET['invoice'] ) ? absint( wp_unslash( $_GET['invoice'] ) ) : 0;
		$key        = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
		// phpcs:enable

		$order = $order_id ? wc_get_order( $order_id ) : false;
		$all   = $order instanceof \WC_Order ? Storage::all( $order ) : array();
		$entry = $all[ (string) $invoice_id ] ?? null;
		$path  = ( $order instanceof \WC_Order && is_array( $entry ) && self::allowed( $order, $key, get_current_user_id() ) ) ? Storage::path( (string) $entry['file'] ) : '';

		if ( '' === $path ) {
			status_header( 404 );
			nocache_headers();
			wp_die( esc_html__( 'Invoice not found.', 'odoowoo-invoices' ), '', array( 'response' => 404 ) );
		}

		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( (string) $entry['filename'] ) . '"' );
		header( 'Content-Length: ' . (string) filesize( $path ) );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}
}
