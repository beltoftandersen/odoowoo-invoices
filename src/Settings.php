<?php
/**
 * Where the shared secret is kept.
 *
 * @package OdooWooInvoices
 */

namespace OdooWooInvoices;

defined( 'ABSPATH' ) || exit;

/**
 * Holds the secret the middleware signs with: the webhook secret of the OdooWoo dashboard.
 */
final class Settings {

	public const OPTION = 'odoowoo_invoices_secret';
	public const PAGE   = 'odoowoo-invoices';

	/**
	 * Hooks.
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_init', array( self::class, 'init' ) );
	}

	/**
	 * The secret: the ODOOWOO_INVOICES_SECRET constant wins over the saved option.
	 */
	public static function secret(): string {
		if ( defined( 'ODOOWOO_INVOICES_SECRET' ) && is_string( ODOOWOO_INVOICES_SECRET ) ) {
			return trim( ODOOWOO_INVOICES_SECRET );
		}

		return trim( (string) get_option( self::OPTION, '' ) );
	}

	/**
	 * Menu entry under WooCommerce.
	 */
	public static function menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'OdooWoo Invoices', 'odoowoo-invoices' ),
			__( 'OdooWoo Invoices', 'odoowoo-invoices' ),
			'manage_woocommerce',
			self::PAGE,
			array( self::class, 'render' )
		);
	}

	/**
	 * Register the option.
	 */
	public static function init(): void {
		register_setting(
			self::PAGE,
			self::OPTION,
			array(
				'type'              => 'string',
				'sanitize_callback' => static function ( $value ): string {
					return trim( sanitize_text_field( (string) $value ) );
				},
				'default'           => '',
			)
		);
	}

	/**
	 * The page.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$locked = defined( 'ODOOWOO_INVOICES_SECRET' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'OdooWoo Invoices', 'odoowoo-invoices' ); ?></h1>
			<p><?php echo esc_html__( 'Paste the webhook secret of your OdooWoo dashboard (the one WooCommerce webhooks are signed with). Invoices sent by OdooWoo are accepted only when signed with it.', 'odoowoo-invoices' ); ?></p>
			<p><?php echo esc_html__( 'Then switch on invoice sending in your OdooWoo dashboard settings.', 'odoowoo-invoices' ); ?></p>
			<?php if ( $locked ) : ?>
				<p><strong><?php echo esc_html__( 'The secret is set in wp-config.php (ODOOWOO_INVOICES_SECRET).', 'odoowoo-invoices' ); ?></strong></p>
			<?php else : ?>
				<form method="post" action="options.php">
					<?php settings_fields( self::PAGE ); ?>
					<table class="form-table" role="presentation"><tr>
						<th scope="row"><label for="odoowoo_invoices_secret"><?php echo esc_html__( 'Shared secret', 'odoowoo-invoices' ); ?></label></th>
						<td><input type="password" id="odoowoo_invoices_secret" name="<?php echo esc_attr( self::OPTION ); ?>" value="<?php echo esc_attr( (string) get_option( self::OPTION, '' ) ); ?>" class="regular-text" autocomplete="off" /></td>
					</tr></table>
					<?php submit_button(); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}
}
