<?php
/**
 * Age Verification Integration
 *
 * @version 3.9.0
 * @package WooCommerce\Integrations
 */

defined( 'ABSPATH' ) || exit;

/**
 * WC Integration MaxMind Geolocation
 *
 * @since 3.9.0
 */
class WC_Na8k_Integration extends WC_Integration {

	/**
	 * Initialize the integration.
	 */
	public function __construct() {
		$this->id                 = 'na8k';
		$this->method_title       = __( 'Nem Alderstjek', 'na8k' );
		$this->method_description = __( 'Add age verification to your WooCommerce store using MitID. Products marked as \'Requires age confirmation\' will trigger the age verification flow at checkout.<br /><br />Read more and sign up at <a href="https://nemalderstjek.dk" target="blank">nemalderstjek.dk</a>. There is no subscription, but just a flat fee per verification. New accounts include 10 free verifications.', 'na8k' );

		$this->init_form_fields();
		$this->init_settings();

		// Bind to the save action for the settings.
		add_action( 'woocommerce_update_options_integration_' . $this->id, array( $this, 'process_admin_options' ) , 20);
	}

	/**
	 * Initializes the settings fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'redirect_url' => array(
				'title'       => __( 'Callback URL', 'na8k' ),
				'type'        => 'text',
				'description' =>
					__(
						'The callback URL that you should add to your NemAlderstjek.dk integration. Ie. <strong>copy this value</strong> and paste into the "Redirect URL" field when creating your integration at NemAlderstjek.dk.',
						'na8k'
					),
				'desc_tip'    => false,
				'default'     => '',
                'placeholder' => 'https://yourdomain.com/?wc-ajax=na8k-callback',
			),
            'api_token' => array(
                'title'       => __( 'API Token', 'na8k' ),
                'type'        => 'text',
                'description' =>
	                __(
		                'Once you have created your integration in NemAlderstjek.dk, then an API token appears in the Integrations-table. Copy the generated API token into this field.',
		                'na8k'
	                ),
                'desc_tip'    => false,
                'default'     => '',
                'placeholder' => 'api-key-from-nemalderstjek-here',
            ),
            'default_required_age' => array(
                'title'       => __( 'Default required age', 'na8k' ),
                'type'        => 'select',
                'description' =>
	                __(
		                'The default age requirement applied to every product that does not have its own value set. Each product can override this under the product\'s Advanced tab.',
		                'na8k'
	                ),
                'desc_tip'    => false,
                'default'     => '0',
                'options'     => na8k_age_options(),
            )
		);
	}

	/**
     * Add the proper callback URL and make the field readonly.
     */
	public function admin_options() {
		parent::admin_options();

		// get woocommerce ajax prefix url
		$ajax_url = WC_AJAX::get_endpoint( '%%endpoint%%' );
		$ajax_url = str_replace( '%%endpoint%%', 'na8k-callback', $ajax_url );
		$ajax_url = home_url($ajax_url);
		?>
		<script>
			jQuery(($) => {
                const f = $('#woocommerce_na8k_redirect_url');
                f.val(<?= json_encode( $ajax_url ); ?>);
                f.click((ev) => {
                    ev.target.select();
                });
                f.attr('readonly', true);
			})
		</script>
        <?php
	}
}
