<?php
/**
 * Data Layer Event: add_payment_info.
 *
 * @package    GTM_Server_Side
 * @subpackage GTM_Server_Side/includes
 * @since      3.2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Data Layer Event: add_payment_info.
 */
class GTM_Server_Side_Event_AddPaymentInfo {
	use GTM_Server_Side_Singleton;

	/**
	 * Init.
	 *
	 * @return void
	 */
	public function init() {
		if ( ! GTM_Server_Side_WC_Helpers::instance()->is_enable_ecommerce() ) {
			return;
		}

		add_action( 'wp_footer', array( $this, 'wp_footer' ) );
	}

	/**
	 * WP footer hook.
	 *
	 * @return void
	 */
	public function wp_footer() {
		if ( ! is_checkout() ) {
			return;
		}

		$cart = WC()->cart->get_cart();
		if ( empty( $cart ) ) {
			return;
		}

		$data_layer = array(
			'event'     => GTM_Server_Side_Helpers::get_data_layer_event_name( 'add_payment_info' ),
			'ecommerce' => array(
				'currency' => esc_attr( get_woocommerce_currency() ),
				'value'    => GTM_Server_Side_WC_Helpers::instance()->formatted_price(
					GTM_Server_Side_WC_Helpers::instance()->get_cart_total()
				),
				'items'    => GTM_Server_Side_WC_Helpers::instance()->get_cart_data_layer_items( $cart ),
			),
		);

		if ( GTM_Server_Side_WC_Helpers::instance()->is_enable_user_data() ) {
			$data_layer['user_data'] = GTM_Server_Side_WC_Helpers::instance()->get_data_layer_user_data();
		}
		?>
		<script type="text/javascript">
			(function() {
				var gtmSSPaymentData = <?php echo GTM_Server_Side_Helpers::array_to_json( $data_layer ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
				var gtmSSPaymentFired = false;

				function getPaymentType() {
					var selected = document.querySelector( 'input[name="payment_method"]:checked' );
					if ( selected ) {
						var label = document.querySelector( 'label[for="' + selected.id + '"]' );
						if ( label ) {
							return label.textContent.trim();
						}
						return selected.value || '';
					}
					// Single payment method (no radio buttons).
					var hiddenMethod = document.querySelector( 'input[name="payment_method"]' );
					if ( hiddenMethod ) {
						return hiddenMethod.value || '';
					}
					return '';
				}

				function firePaymentInfo() {
					if ( gtmSSPaymentFired ) {
						return;
					}
					gtmSSPaymentFired = true;

					var paymentType = getPaymentType();
					if ( paymentType ) {
						gtmSSPaymentData.ecommerce.payment_type = paymentType;
					}

					dataLayer.push( { ecommerce: null } );
					dataLayer.push( gtmSSPaymentData );
				}

				// Fire when the place order button is clicked.
				document.addEventListener( 'click', function( e ) {
					var btn = e.target.closest( '#place_order, .wfacp_place_order, [name="woocommerce_checkout_place_order"]' );
					if ( btn ) {
						firePaymentInfo();
					}
				});

				// WooCommerce checkout form submission.
				if ( typeof jQuery !== 'undefined' ) {
					jQuery( 'form.woocommerce-checkout' ).on( 'checkout_place_order', function() {
						firePaymentInfo();
					});
				}
			})();
		</script>
		<?php
	}
}
