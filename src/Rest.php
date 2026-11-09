<?php
/**
 * POST /wp-json/odoowoo-invoices/v1/invoice, called by the OdooWoo middleware.
 *
 * @package OdooWooInvoices
 */

namespace OdooWooInvoices;

defined( 'ABSPATH' ) || exit;

/**
 * Accepts one signed invoice and stores it on the order.
 */
final class Rest {

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	/**
	 * The route. Authentication is the signature, checked before any other work.
	 */
	public static function routes(): void {
		register_rest_route(
			'odoowoo-invoices/v1',
			'/invoice',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'handle' ),
				'permission_callback' => array( self::class, 'authorise' ),
			)
		);
	}

	/**
	 * Signature and freshness. Replies are deliberately vague.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error
	 */
	public static function authorise( \WP_REST_Request $request ) {
		$ok = Signature::valid(
			Settings::secret(),
			(string) $request->get_header( 'x-odoowoo-timestamp' ),
			(string) $request->get_header( 'x-odoowoo-signature' ),
			$request->get_body(),
			time()
		);

		if ( ! $ok ) {
			return new \WP_Error( 'odoowoo_invoices_forbidden', __( 'Not allowed.', 'odoowoo-invoices' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Validate the payload and store the invoice.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle( \WP_REST_Request $request ) {
		$data = json_decode( $request->get_body(), true );

		if ( ! is_array( $data ) ) {
			return self::refuse( 'bad_payload', 400 );
		}

		$order_id = isset( $data['woocommerce_order_id'] ) ? (string) $data['woocommerce_order_id'] : '';
		if ( ! preg_match( '/\A[0-9]{1,12}\z/', $order_id ) || empty( $data['invoice_id'] ) || ! is_numeric( $data['invoice_id'] ) ) {
			return self::refuse( 'bad_payload', 400 );
		}

		$pdf = isset( $data['pdf_base64'] ) && is_string( $data['pdf_base64'] ) ? base64_decode( $data['pdf_base64'], true ) : false;
		if ( false === $pdf || strlen( $pdf ) > Storage::MAX || 0 !== strpos( $pdf, '%PDF-' ) ) {
			return self::refuse( 'bad_pdf', 400 );
		}

		$order = wc_get_order( (int) $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return self::refuse( 'unknown_order', 404 );
		}

		$clean = array(
			'invoice_id'     => (int) $data['invoice_id'],
			'invoice_number' => self::text( $data, 'invoice_number', 64 ),
			'invoice_date'   => self::text( $data, 'invoice_date', 10 ),
			'amount_total'   => self::text( $data, 'amount_total', 32 ),
			'currency'       => self::text( $data, 'currency', 8 ),
			'filename'       => sanitize_file_name( self::text( $data, 'filename', 120 ) ) ? sanitize_file_name( self::text( $data, 'filename', 120 ) ) : 'invoice.pdf',
		);

		if ( ! Storage::save( $order, $clean, $pdf ) ) {
			return self::refuse( 'storage_failed', 500 );
		}

		return new \WP_REST_Response( array( 'stored' => true ), 200 );
	}

	/**
	 * A short plain-text field from the payload.
	 *
	 * @param array  $data Payload.
	 * @param string $key  Field.
	 * @param int    $max  Max length.
	 */
	private static function text( array $data, string $key, int $max ): string {
		$value = isset( $data[ $key ] ) && ( is_string( $data[ $key ] ) || is_numeric( $data[ $key ] ) ) ? (string) $data[ $key ] : '';

		return substr( sanitize_text_field( $value ), 0, $max );
	}

	/**
	 * An error reply that says nothing about internals.
	 *
	 * @param string $code   Short code.
	 * @param int    $status HTTP status.
	 */
	private static function refuse( string $code, int $status ): \WP_Error {
		return new \WP_Error( 'odoowoo_invoices_' . $code, __( 'The invoice was not accepted.', 'odoowoo-invoices' ), array( 'status' => $status ) );
	}
}
