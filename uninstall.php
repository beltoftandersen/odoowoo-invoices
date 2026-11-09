<?php
/**
 * Removes the saved secret. Stored invoices are customer records and are left in place.
 *
 * @package OdooWooInvoices
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'odoowoo_invoices_secret' );
