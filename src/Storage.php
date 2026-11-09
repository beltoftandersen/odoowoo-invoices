<?php
/**
 * Where invoice PDFs live, and which order they belong to.
 *
 * @package OdooWooInvoices
 */

namespace OdooWooInvoices;

defined( 'ABSPATH' ) || exit;

/**
 * Files go in uploads/odoowoo-invoices under random names and are only ever served through
 * Download, which checks who is asking. The random name is the safety net for servers that
 * ignore .htaccess (nginx): block the folder there too, see the README.
 */
final class Storage {

	public const META  = '_odoowoo_invoices';
	public const MAX   = 8388608;
	public const FOLDER = 'odoowoo-invoices';

	/**
	 * Absolute folder path, created with deny files when missing.
	 */
	public static function dir(): string {
		$upload = wp_upload_dir( null, false );
		$dir    = trailingslashit( $upload['basedir'] ) . self::FOLDER;

		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
			file_put_contents( $dir . '/index.php', "<?php\n// Silence.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		return $dir;
	}

	/**
	 * The invoices stored on an order, keyed by Odoo invoice id.
	 *
	 * @param \WC_Order $order Order.
	 * @return array<string, array<string, mixed>>
	 */
	public static function all( \WC_Order $order ): array {
		$list = $order->get_meta( self::META, true );

		return is_array( $list ) ? $list : array();
	}

	/**
	 * Store (or replace) one invoice on an order.
	 *
	 * @param \WC_Order $order Order.
	 * @param array     $data  Validated payload: invoice_id, invoice_number, invoice_date, amount_total, currency, filename.
	 * @param string    $pdf   Raw PDF bytes.
	 * @return bool False when the file could not be written.
	 */
	public static function save( \WC_Order $order, array $data, string $pdf ): bool {
		$list = self::all( $order );
		$id   = (string) $data['invoice_id'];
		$file = bin2hex( random_bytes( 16 ) ) . '.pdf';
		$path = self::dir() . '/' . $file;

		if ( false === file_put_contents( $path, $pdf ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			return false;
		}

		$replacing = isset( $list[ $id ] );
		if ( $replacing ) {
			self::delete_file( (string) $list[ $id ]['file'] );
		}

		$list[ $id ] = array(
			'invoice_id' => (int) $data['invoice_id'],
			'number'     => (string) $data['invoice_number'],
			'date'       => (string) $data['invoice_date'],
			'total'      => (string) $data['amount_total'],
			'currency'   => (string) $data['currency'],
			'filename'   => (string) $data['filename'],
			'file'       => $file,
			'received'   => time(),
		);

		$order->update_meta_data( self::META, $list );
		if ( ! $replacing ) {
			/* translators: %s: invoice number. */
			$order->add_order_note( sprintf( __( 'Invoice %s received from Odoo.', 'odoowoo-invoices' ), $data['invoice_number'] ) );
		}
		$order->save();

		return true;
	}

	/**
	 * Full path of a stored file, or '' when the name is not one of ours or is missing.
	 *
	 * @param string $file Stored file name.
	 */
	public static function path( string $file ): string {
		if ( ! preg_match( '/\A[0-9a-f]{32}\.pdf\z/', $file ) ) {
			return '';
		}

		$path = self::dir() . '/' . $file;

		return is_file( $path ) ? $path : '';
	}

	/**
	 * Remove a stored file.
	 *
	 * @param string $file Stored file name.
	 */
	public static function delete_file( string $file ): void {
		$path = self::path( $file );
		if ( '' !== $path ) {
			wp_delete_file( $path );
		}
	}
}
