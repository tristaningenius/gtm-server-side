<?php
/**
 * Data Layer Event: add_shipping_info.
 *
 * @package    GTM_Server_Side
 * @subpackage GTM_Server_Side/includes
 * @since      3.2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Data Layer Event: add_shipping_info.
 */
class GTM_Server_Side_Event_AddShippingInfo {
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
			'event'     => GTM_Server_Side_Helpers::get_data_layer_event_name( 'add_shipping_info' ),
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
				var gtmSSShippingData = <?php echo GTM_Server_Side_Helpers::array_to_json( $data_layer ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
				var gtmSSShippingFired = false;

				var requiredShippingFields = [
					'shipping_address_1',
					'shipping_city',
					'shipping_postcode',
					'shipping_country'
				];

				var requiredBillingFields = [
					'billing_address_1',
					'billing_city',
					'billing_postcode',
					'billing_country'
				];

				function getFieldValue( fieldId ) {
					var el = document.getElementById( fieldId );
					if ( el ) {
						return el.value ? el.value.trim() : '';
					}
					var elBySelector = document.querySelector( '#' + fieldId + '_field input, #' + fieldId + '_field select' );
					return elBySelector ? ( elBySelector.value ? elBySelector.value.trim() : '' ) : '';
				}

				function areFieldsFilled( fields ) {
					for ( var i = 0; i < fields.length; i++ ) {
						if ( ! getFieldValue( fields[i] ) ) {
							return false;
						}
					}
					return true;
				}

				function getShippingTier() {
					var selected = document.querySelector( 'input[name^="shipping_method"]:checked' );
					if ( ! selected ) {
						selected = document.querySelector( 'input[name^="shipping_method"]' );
					}
					if ( ! selected ) {
						return '';
					}
					var label = selected.closest( 'li' );
					if ( label ) {
						var labelEl = label.querySelector( 'label' );
						if ( labelEl ) {
							return labelEl.textContent.trim();
						}
					}
					return selected.value || '';
				}

				function checkAndFireShipping() {
					if ( gtmSSShippingFired ) {
						return;
					}

					var shipToDifferent = document.getElementById( 'ship-to-different-address-checkbox' );
					var useShippingFields = shipToDifferent && shipToDifferent.checked;
					var fieldsToCheck = useShippingFields ? requiredShippingFields : requiredBillingFields;

					if ( ! areFieldsFilled( fieldsToCheck ) ) {
						return;
					}

					gtmSSShippingFired = true;

					var shippingTier = getShippingTier();
					if ( shippingTier ) {
						gtmSSShippingData.ecommerce.shipping_tier = shippingTier;
					}

					dataLayer.push( { ecommerce: null } );
					dataLayer.push( gtmSSShippingData );
				}

				// Monitor field changes via blur and change.
				document.addEventListener( 'change', function( e ) {
					var el = e.target;
					if ( el && el.id && ( el.id.indexOf( 'shipping_' ) === 0 || el.id.indexOf( 'billing_' ) === 0 ) ) {
						checkAndFireShipping();
					}
				});
				document.addEventListener( 'blur', function( e ) {
					var el = e.target;
					if ( el && el.id && ( el.id.indexOf( 'shipping_' ) === 0 || el.id.indexOf( 'billing_' ) === 0 ) ) {
						setTimeout( checkAndFireShipping, 150 );
					}
				}, true );

				// WooCommerce checkout update.
				if ( typeof jQuery !== 'undefined' ) {
					jQuery( document.body ).on( 'updated_checkout', checkAndFireShipping );
					// FunnelKit multi-step: fire when moving to next step.
					jQuery( document.body ).on( 'wfacp_step_switching', function() {
						setTimeout( checkAndFireShipping, 200 );
					});
				}
			})();
		</script>
		<?php
	}
}
