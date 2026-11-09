<?php
/**
 * Where customers and shop managers see the links.
 *
 * @package OdooWooInvoices
 */

namespace OdooWooInvoices;

defined( 'ABSPATH' ) || exit;

/**
 * My Account order list, order details (also the order-received page), and the admin order screen.
 */
final class Display {

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_filter( 'woocommerce_my_account_my_orders_actions', array( self::class, 'list_action' ), 10, 2 );
		add_action( 'woocommerce_order_details_after_order_table', array( self::class, 'details' ) );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( self::class, 'admin_box' ) );
	}

	/**
	 * A "Download invoice" button on each order that has one (the latest).
	 *
	 * @param array     $actions Actions.
	 * @param \WC_Order $order   Order.
	 * @return array
	 */
	public static function list_action( $actions, $order ) {
		if ( ! is_array( $actions ) || ! $order instanceof \WC_Order ) {
			return $actions;
		}

		$all = Storage::all( $order );
		if ( ! $all ) {
			return $actions;
		}

		$last = end( $all );

		$actions['odoowoo_invoice'] = array(
			'url'  => Download::url( $order, (int) $last['invoice_id'] ),
			'name' => __( 'Download invoice', 'odoowoo-invoices' ),
		);

		return $actions;
	}

	/**
	 * A list of invoices under the order table.
	 *
	 * @param \WC_Order $order Order.
	 */
	public static function details( $order ): void {
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$all = Storage::all( $order );
		if ( ! $all ) {
			return;
		}

		echo '<section class="odoowoo-invoices"><h2>' . esc_html__( 'Invoices', 'odoowoo-invoices' ) . '</h2><ul>';
		foreach ( $all as $entry ) {
			printf(
				'<li><a href="%s">%s</a></li>',
				esc_url( Download::url( $order, (int) $entry['invoice_id'] ) ),
				/* translators: %s: invoice number. */
				esc_html( sprintf( __( 'Download invoice %s', 'odoowoo-invoices' ), $entry['number'] ) )
			);
		}
		echo '</ul></section>';
	}

	/**
	 * The same list for the shop manager on the order screen.
	 *
	 * @param \WC_Order $order Order.
	 */
	public static function admin_box( $order ): void {
		if ( ! $order instanceof \WC_Order || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$all = Storage::all( $order );
		if ( ! $all ) {
			return;
		}

		echo '<p><strong>' . esc_html__( 'OdooWoo invoices', 'odoowoo-invoices' ) . '</strong><br />';
		foreach ( $all as $entry ) {
			printf( '<a href="%s">%s</a><br />', esc_url( Download::url( $order, (int) $entry['invoice_id'] ) ), esc_html( (string) $entry['number'] ) );
		}
		echo '</p>';
	}
}
