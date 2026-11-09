<?php
/**
 * Signature, ingest, storage and download rules.
 *
 * @package OdooWooInvoices
 */

namespace OdooWooInvoices\Tests;

use OdooWooInvoices\Download;
use OdooWooInvoices\Signature;
use OdooWooInvoices\Storage;

/**
 * Tests.
 */
class InvoicesTest extends \WP_UnitTestCase {

	private const SECRET = 'shared-secret';

	public function set_up(): void {
		parent::set_up();
		update_option( 'odoowoo_invoices_secret', self::SECRET );
		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );
	}

	private function order( int $customer = 0 ): \WC_Order {
		$order = wc_create_order( array( 'customer_id' => $customer ) );
		$order->save();

		return $order;
	}

	/**
	 * @param array<string,mixed> $over Payload overrides.
	 * @return array<string,mixed>
	 */
	private function payload( int $order_id, array $over = array() ): array {
		return array_merge(
			array(
				'woocommerce_order_id' => (string) $order_id,
				'invoice_id'           => 42,
				'invoice_number'       => 'INV/2026/0042',
				'invoice_date'         => '2026-11-09',
				'amount_total'         => 125.0,
				'currency'             => 'DKK',
				'filename'             => 'INV_2026_0042.pdf',
				'pdf_base64'           => base64_encode( "%PDF-1.4\nfake" ),
			),
			$over
		);
	}

	/**
	 * @param array<string,mixed> $payload Body.
	 */
	private function send( array $payload, ?string $secret = self::SECRET, ?int $time = null ): \WP_REST_Response {
		$body    = (string) wp_json_encode( $payload );
		$time    = (string) ( $time ?? time() );
		$request = new \WP_REST_Request( 'POST', '/odoowoo-invoices/v1/invoice' );
		$request->set_body( $body );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_header( 'x-odoowoo-timestamp', $time );
		$request->set_header( 'x-odoowoo-signature', Signature::sign( (string) $secret, $time, $body ) );

		return rest_do_request( $request );
	}

	public function test_signature_matches_the_middleware_string(): void {
		$expected = hash_hmac( 'sha256', '1700000000.{"a":1}', 's' );
		$this->assertSame( $expected, Signature::sign( 's', '1700000000', '{"a":1}' ) );
		$this->assertTrue( Signature::valid( 's', '1700000000', $expected, '{"a":1}', 1700000100 ) );
		$this->assertFalse( Signature::valid( 's', '1700000000', $expected, '{"a":2}', 1700000100 ) );
		$this->assertFalse( Signature::valid( 's', '1700000000', $expected, '{"a":1}', 1700001000 ), 'stale' );
		$this->assertFalse( Signature::valid( '', '1700000000', $expected, '{"a":1}', 1700000100 ), 'no secret configured' );
	}

	public function test_signature_matches_the_python_middleware(): void {
		// Computed with Python's hmac over b'1700000000.{"a":1}', key b's3cret'.
		$this->assertSame( '1698a50bc74d1ff1db85c4e0a5297c2ad9fdba245d5737cdb789e4cc6e098940', Signature::sign( 's3cret', '1700000000', '{"a":1}' ) );
	}

	public function test_valid_push_stores_the_invoice_and_a_note(): void {
		$order = $this->order();
		$res   = $this->send( $this->payload( $order->get_id() ) );

		$this->assertSame( 200, $res->get_status() );
		$list = Storage::all( wc_get_order( $order->get_id() ) );
		$this->assertArrayHasKey( '42', $list );
		$this->assertSame( 'INV/2026/0042', $list['42']['number'] );
		$this->assertNotSame( '', Storage::path( $list['42']['file'] ) );
		$this->assertMatchesRegularExpression( '/\A[0-9a-f]{32}\.pdf\z/', $list['42']['file'] );
	}

	public function test_same_invoice_again_replaces_the_file(): void {
		$order = $this->order();
		$this->send( $this->payload( $order->get_id() ) );
		$first = Storage::all( wc_get_order( $order->get_id() ) )['42']['file'];
		$this->send( $this->payload( $order->get_id() ) );
		$list = Storage::all( wc_get_order( $order->get_id() ) );

		$this->assertCount( 1, $list );
		$this->assertNotSame( $first, $list['42']['file'] );
		$this->assertSame( '', Storage::path( $first ), 'old file removed' );
	}

	public function test_wrong_secret_and_stale_requests_are_refused(): void {
		$order = $this->order();
		$this->assertSame( 403, $this->send( $this->payload( $order->get_id() ), 'other' )->get_status() );
		$this->assertSame( 403, $this->send( $this->payload( $order->get_id() ), self::SECRET, time() - 3600 )->get_status() );
		$this->assertSame( array(), Storage::all( wc_get_order( $order->get_id() ) ) );
	}

	public function test_no_configured_secret_refuses_everything(): void {
		delete_option( 'odoowoo_invoices_secret' );
		$order = $this->order();
		$this->assertSame( 403, $this->send( $this->payload( $order->get_id() ), '' )->get_status() );
	}

	public function test_bad_payloads_are_refused(): void {
		$order = $this->order();
		$id    = $order->get_id();
		$this->assertSame( 400, $this->send( $this->payload( $id, array( 'pdf_base64' => base64_encode( 'not a pdf' ) ) ) )->get_status() );
		$this->assertSame( 400, $this->send( $this->payload( $id, array( 'pdf_base64' => '***' ) ) )->get_status() );
		$this->assertSame( 400, $this->send( $this->payload( $id, array( 'woocommerce_order_id' => '12; DROP' ) ) )->get_status() );
		$this->assertSame( 404, $this->send( $this->payload( 999999 ) )->get_status() );
	}

	public function test_only_our_file_names_resolve(): void {
		$this->assertSame( '', Storage::path( '../../wp-config.php' ) );
		$this->assertSame( '', Storage::path( 'index.php' ) );
	}

	public function test_download_rules(): void {
		$owner   = self::factory()->user->create( array( 'role' => 'customer' ) );
		$other   = self::factory()->user->create( array( 'role' => 'customer' ) );
		$manager = self::factory()->user->create( array( 'role' => 'shop_manager' ) );

		$mine = $this->order( $owner );
		$this->assertTrue( Download::allowed( $mine, '', $owner ) );
		$this->assertTrue( Download::allowed( $mine, '', $manager ) );
		$this->assertFalse( Download::allowed( $mine, '', $other ) );
		$this->assertFalse( Download::allowed( $mine, '', 0 ), 'logged out' );
		$this->assertFalse( Download::allowed( $mine, $mine->get_order_key(), 0 ), 'a registered customer must log in, the key is not enough' );

		$guest = $this->order( 0 );
		$this->assertTrue( Download::allowed( $guest, $guest->get_order_key(), 0 ) );
		$this->assertFalse( Download::allowed( $guest, 'wc_order_wrong', 0 ) );
		$this->assertFalse( Download::allowed( $guest, '', 0 ) );
		$this->assertFalse( Download::allowed( $guest, '', $other ) );
	}

	public function test_guest_link_carries_the_key_but_a_customer_link_does_not(): void {
		$guest = $this->order( 0 );
		$this->assertStringContainsString( 'key=', Download::url( $guest, 42 ) );
		$mine = $this->order( self::factory()->user->create( array( 'role' => 'customer' ) ) );
		$this->assertStringNotContainsString( 'key=', Download::url( $mine, 42 ) );
	}
}
