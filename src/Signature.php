<?php
/**
 * Request signature, the same string the OdooWoo middleware signs.
 *
 * @package OdooWooInvoices
 */

namespace OdooWooInvoices;

defined( 'ABSPATH' ) || exit;

/**
 * Lowercase hex HMAC-SHA256 over "<timestamp>.<raw body>", key = the shared secret.
 */
final class Signature {

	public const MAX_SKEW = 300;

	/**
	 * The signature for a body.
	 *
	 * @param string $secret    Shared secret.
	 * @param string $timestamp Unix seconds, as sent.
	 * @param string $body      Raw request body.
	 */
	public static function sign( string $secret, string $timestamp, string $body ): string {
		return hash_hmac( 'sha256', $timestamp . '.' . $body, $secret );
	}

	/**
	 * Whether a request is fresh and signed with the secret.
	 *
	 * @param string $secret    Shared secret ('' means not configured: always false).
	 * @param string $timestamp Header value.
	 * @param string $signature Header value.
	 * @param string $body      Raw request body.
	 * @param int    $now       Current time.
	 */
	public static function valid( string $secret, string $timestamp, string $signature, string $body, int $now ): bool {
		if ( '' === $secret || ! preg_match( '/\A[0-9]{1,12}\z/', $timestamp ) || ! preg_match( '/\A[0-9a-f]{64}\z/', $signature ) ) {
			return false;
		}

		if ( abs( $now - (int) $timestamp ) > self::MAX_SKEW ) {
			return false;
		}

		return hash_equals( self::sign( $secret, $timestamp, $body ), $signature );
	}
}
