<?php
/**
 * Plugin Name: Nem-Alderstjek
 * Description: This plugin adds age verification to your WooCommerce store using MitID. Products marked as 'requires age confirmation' will trigger the age verification flow at checkout.
 * Author: NemAlderstjek.dk
 * Author URI: https://nemalderstjek.dk
 * Tags: nemalderstjek, nem alderstjek, na8k, mitid, denmark, criipto, age verification, legal drinking age, id verification, online store, ecommerce, shop, shopping cart, sell online
 * Requires at least: 6.5
 * Tested up to: 7.0
 * Requires PHP: 7.4
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Version: 1.1.0
 * Text Domain: na8k
 */

const NA8K_VERSION = '1.1.0';

if (!defined('NA8K_BASE_URL')) {
    define('NA8K_BASE_URL', 'https://v1.nemalderstjek.dk');
}

require_once('updater.php');

/**
 * The available age requirement options, shared between the product setting,
 * the cart logic and the integration settings page.
 *
 * @return array<string, string>
 */
function na8k_age_options() {
    return array(
        '0' => __('No age requirements', 'na8k'),
        '15' => __('Min. 15 years', 'na8k'),
        '16' => __('Min. 16 years', 'na8k'),
        '18' => __('Min. 18 years', 'na8k'),
        '21' => __('Min. 21 years', 'na8k'),
    );
}

/**
 * The store-wide default age requirement, configured on the integration
 * settings page. Used for any product that has no explicit value set.
 *
 * @return int
 */
function na8k_default_required_age() {
    $settings = get_option('woocommerce_na8k_settings');
    if (!is_array($settings)) return 0;
    return (int)($settings['default_required_age'] ?? 0);
}

add_action('woocommerce_product_options_advanced', function () {
    $product_object = wc_get_product();

    $default_label = na8k_age_options()[(string)na8k_default_required_age()] ?? __('No age requirements', 'na8k');

    woocommerce_wp_select(
        array(
            'id' => '_na8k_requires_age_verification',
            'value' => $product_object->get_meta('_na8k_requires_age_verification', true),
            'options' => array(
                '' => sprintf(__('Use store default (%s)', 'na8k'), $default_label),
            ) + na8k_age_options(),
            'wrapper_class' => 'show_if_simple show_if_variable',
            'label' => __('Requires age verification', 'na8k'),
            'description' => __('The customer will need to go through age verification with their MitID at checkout. Leave on "Use store default" to follow the default configured under WooCommerce → Settings → Integration → Nem Alderstjek.', 'na8k'),
            'desc_tip' => true,
        )
    );
});

add_action('woocommerce_process_product_meta', function ($post_id) {
    $product = wc_get_product($post_id);
    if (!isset($_POST['_na8k_requires_age_verification'])) return;

    $value = wp_unslash($_POST['_na8k_requires_age_verification']);
    if ($value === '') {
        // "Use store default": remove the explicit value so the product falls back to the default.
        $product->delete_meta_data('_na8k_requires_age_verification');
    } else {
        $product->update_meta_data('_na8k_requires_age_verification', (int)$value);
    }
    $product->save();
});

function na8k_cart_minimum_age() {
	$cart = WC()->cart->get_cart();
    if (!$cart) return 0;

    // if the billing address it NOT in denmark, just skip
    $billingCountry = WC()->customer->get_billing_country();
    if ($billingCountry !== 'DK' && $billingCountry) return 0;

	$neededAge = 0;
	foreach ($cart as $cart_item) {
		$age = na8k_product_minimum_age($cart_item['product_id']);
		$neededAge = max($neededAge, $age);
	}

	return $neededAge;
}

/**
 * What age does this product require?
 * @param $product_id
 * @return int
 */
function na8k_product_minimum_age($product_id) {
    $product = wc_get_product($product_id);
    if (!$product) return 0;

    $value = $product->get_meta('_na8k_requires_age_verification', true);

    // No explicit value set on the product: fall back to the store-wide default.
    if ($value === '' || $value === null) {
        return na8k_default_required_age();
    }

    return (int)$value;
}

function na8k_start_mitid_session($age) {
	$res = wp_remote_post(NA8K_BASE_URL.'/api/a8k/session-start', [
		'body' => json_encode([
			'apiKey' => get_option('woocommerce_na8k_settings')['api_token'],
			'age' => $age
		]),
		'headers' => [
			'Content-Type' => 'application/json'
		],
	]);
	$statusCode = wp_remote_retrieve_response_code($res);
	$body = json_decode(wp_remote_retrieve_body($res) ? : '{"success": false}', true);

	if ($statusCode < 200 || $statusCode >= 300 || !$body['success']) {
		$msg = $body['message'] ? $body['message'].'.' : '';
		wp_die('Unfortunately it is currently not possible to do age verification. '.$msg.' Please try again later or contact the administrator of this website.');
	}
	WC()->session->set('na8k_session_token', $body['token']);
	die(wp_redirect($body['url']));
}

/**
 * @param array $data An array of posted data.
 * @param WP_Error $errors Validation errors.
 */
add_action('woocommerce_after_checkout_validation', function($data, $errors) {
    if ($errors->has_errors()) return;
    static $wasCalled = false;
    if ($wasCalled) return;
	$wasCalled = true;

    $neededAge = na8k_cart_minimum_age();
    if (!$neededAge) return;

    $verifiedAge = WC()->session->get('na8k_age_verified');
    if (!$verifiedAge || $verifiedAge < $neededAge) {

	    $start_url = WC_AJAX::get_endpoint( '%%endpoint%%' );
	    $start_url = str_replace( '%%endpoint%%', 'na8k-start-verification', $start_url );
	    $start_url = home_url($start_url);
	    WC()->session->set('na8k_all_post_data', $_POST);

        $errors->add('age_verification',
            sprintf(__('You need to verify your age with MitID before you can proceed. Please wait while you are redirected automatically. <a href="%s" class="na8k-redirect-link"></a>', 'na8k'), $start_url)
        );
    }
    return $data;
}, 999, 2);

add_action('woocommerce_checkout_create_order', function($order) {
	$sessionToken = WC()->session->get('na8k_session_token');
	$minAge = WC()->session->get('na8k_age_verified');
	if ($sessionToken && $minAge) {
		$order->update_meta_data('na8k_session_token', $sessionToken);
		$order->update_meta_data('na8k_min_age', $minAge);
	}
});

add_action('wp_footer', function () {
	$pluginUrl = plugin_dir_url(__FILE__);
	$settings = get_option('woocommerce_na8k_settings');
	if (!$settings || !$settings['api_token']) return;
	?>
    <script src="<?= $pluginUrl; ?>/nem-alderstjek.js"></script>
    <?php
});

add_action('wc_ajax_na8k-start-verification', function() {
	$apiToken = get_option('woocommerce_na8k_settings')['api_token'];
	if (!$apiToken) wp_die('The age verification plugin is not configured correctly. Please contact the administrator of this website.');

	$age = na8k_cart_minimum_age();
	if (!$age) wp_die('No age verification is needed.');

	na8k_start_mitid_session($age);
});

add_action('wc_ajax_na8k-callback', function () {
    // this is the checkout page - some plugins require this to be set, to load their javascript
    add_filter('woocommerce_is_checkout', '__return_true');

    // get the settings
    $settings = get_option('woocommerce_na8k_settings');
    if (!$settings || !$settings['api_token']) wp_die('no_settings');

	$sessionToken = WC()->session->get('na8k_session_token');
    if (!$sessionToken) wp_die('missing_session_token');

    // get the status of the verification
    $res = wp_remote_post(NA8K_BASE_URL.'/api/a8k/session-result', [
        'body' => json_encode([
            'apiKey' => $settings['api_token'],
            'token' => $sessionToken
        ]),
        'headers' => [
            'Content-Type' => 'application/json'
        ],
    ]);
    $code = wp_remote_retrieve_response_code($res);
    $body = wp_remote_retrieve_body($res);
    $json = json_decode($body, true);
    if ($code < 200 || $code >= 300 || $json['status'] !== 'success') wp_die('na8k_service_error', $body);

    // express checkout?
    if (WC()->session->get('na8k_express_redirect')) {
        return na8k_callback_express($json['age'], $json['ageConfirmed']);
    }

	// check if users age was verified in token
	$cartMinAge = na8k_cart_minimum_age();
    if ($json['age'] && $json['ageConfirmed'] && $cartMinAge === $json['age']) {
	    // find the cart and set is as age verified
	    WC()->session->set('na8k_age_verified', $cartMinAge);
        $postData = WC()->session->get('na8k_all_post_data');
	    $imgSrc = plugin_dir_url(__FILE__).'LogoOutline.svg';
    ?>
    <html>
    <?php wp_head(); ?>
    <body>
        <div style="padding: 50px; text-align: center;">
            <h1>Tak, din alder er bekræftet</h1>
            <p>Din ordre viderebehandles nu - vent venligst.</p>

            <p style="margin-top: 100px"><small>MitID Alderskontrol udført af<br /><a href="https://nemalderstjek.dk" target="_blank"><img src="<?=$imgSrc;?>" style="height: 30px; margin-top: 10px" alt="NemAlderstjek.dk" /></a></small></p>
        </div>
    <?php wp_footer(); ?>
    <script>
jQuery(async ($) => {
    const res = await $.post('/?wc-ajax=checkout', <?= json_encode($postData) ?>);
    if (res.result === 'success') {
        if (res.reepay && res.reepay.url) {
            window.location.href = res.reepay.url;
        } else {
            window.location.href = res.redirect;
        }
    } else {
        alert('An error occurred while processing your order. Please try again.');
    }
})
    </script>
    </body>
    </html>
    </html>
<?php
        die();
    }

	wp_die('Vi beklager, men du er desværre ikke gammel nok til at gennemføre denne handel.');
});

add_filter( 'woocommerce_integrations', function($integrations) {
    require_once('wc-integration.php');
    $integrations[] = 'WC_Na8k_Integration';
    return $integrations;
} );

add_action( 'woocommerce_thankyou', function($order_id) {
	$order = wc_get_order( $order_id );

	$token = $order->get_meta('na8k_session_token', true);
	$age = $order->get_meta('na8k_min_age', true);
	if (!$token || !$age) return;

	$imgSrc = plugin_dir_url(__FILE__).'LogoOutline.svg';
	?>
	<div>
		<p>Denne ordre er aldersverificeret med MitID til <strong>minimum <?= $age ?> år</strong> &nbsp; <a href="https://nemalderstjek.dk" target="_blank"><img src="<?=$imgSrc;?>" style="height: 30px; vertical-align: middle;" alt="Alderskontrol udført af NemAlderstjek.dk" /></a></p>
	</div>
    <?php
}, 5);

// support for Stripe Express Checkout buttons (Google Pay, Apple Pay) on product pages
add_filter( 'wc_stripe_payment_request_params', function ( $params ) {
    if ( ! $params['is_product_page'] ) {
        return $params;
    }
    $minAge = na8k_product_minimum_age( get_the_ID() );
    $verifiedAge = (int)WC()->session->get( 'na8k_age_verified' ) ?? 0;
	if (!$minAge) return $params;
    if ($verifiedAge >= $minAge) {
        $params['na8k_age_verified'] = $verifiedAge;
        return $params;
    }

    $message      = 'Du skal bekræfte din alder med MitID før du kan gennemføre betalingen (minimumsalder for dette produkt er '.$minAge.' år).';
	$redirect_url = home_url( add_query_arg( ['na8k_verified' => wp_create_nonce('na8k_verified')] ) );
    WC()->session->set('na8k_express_redirect', $redirect_url);
    WC()->session->set('na8k_express_minage', $minAge);

	wc_setcookie( 'wc_stripe_express_checkout_redirect_url', $redirect_url, time() + MINUTE_IN_SECONDS * 10 );

	$start_url = WC_AJAX::get_endpoint( '%%endpoint%%' );
	$start_url = str_replace( '%%endpoint%%', 'na8k-start-verification-express', $start_url );
	$start_url = home_url($start_url);

	$params['login_confirmation'] = [
        'message'      => $message,
        'redirect_url' => wp_sanitize_redirect( esc_url_raw( $start_url ) ),
    ];
    return $params;
}, 20 );

add_action('wc_ajax_na8k-start-verification-express', function() {
	$apiToken = get_option('woocommerce_na8k_settings')['api_token'];
	if (!$apiToken) wp_die('The age verification plugin is not configured correctly. Please contact the administrator of this website.');

    $redir = WC()->session->get('na8k_express_redirect');
    $age = WC()->session->get('na8k_express_minage');
	if (!$redir || !$age) wp_die('Session data is missing. Please go back and try again. It might be due to cookies being disabled in your browser?');

	na8k_start_mitid_session($age);
});

function na8k_callback_express($age, $ageConfirmed) {
	$redir = WC()->session->get( 'na8k_express_redirect' );
	if ( $age && $ageConfirmed ) {
		WC()->session->set( 'na8k_age_verified', $age );
		WC()->session->set( 'na8k_express_redirect', null );
		WC()->session->set( 'na8k_express_minage', null );
		wp_redirect( $redir );
		die();
	}
}

// admin UI: if plugin 'dibs-easy-for-woocommerce' is active, check if it is configured to 'embedded' as that will not work
add_action('admin_notices', function() {
    if (!is_plugin_active('dibs-easy-for-woocommerce/dibs-easy-for-woocommerce.php')) return;
    if (!isset($_GET['page']) || strpos($_GET['page'], 'wc-settings') === false) return;
    $settings = get_option('woocommerce_dibs_easy_settings');
    if ($settings['checkout_flow'] !== 'embedded') return;

    $settings_page = admin_url('admin.php?page=wc-settings&tab=checkout&section=dibs_easy#woocommerce_dibs_easy_dibs_invoice_fee');
    ?>
    <div class="notice notice-error">
        <p><strong>NemAlderstjek:</strong> Plugin'et 'Nexi Checkout' er konfigureret til at bruge 'embedded' checkout flow. 'Checkout flow' skal være 'Redirect' eller 'Overlay'. <a href="<?= $settings_page ?>">Direkte link til indstillingen</a>.</p>
        <p style="font-size: 0.9em; opacity: 0.8"><strong>Hvorfor?</strong> 'Embedded' tager imod betalingsoplysninger <em>før</em> alderskontrollen kan udføres og er derfor ikke lovligt ved produkter med aldersbegrænsning.</p>
    </div>
    <?php
});