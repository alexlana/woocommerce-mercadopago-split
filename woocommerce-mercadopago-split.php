<?php

/**
 * Plugin Name: Mercado Pago Split (WooCommerce + WCFM)
 * Plugin URI: https://github.com/mercadopago/cart-woocommerce
 * Description: Configure the payment options and accept payments with cards, ticket and money of Mercado Pago account.
 * Version: 4.6.0
 * Author: Alex Lana (hack do Mercado Pago payments for WooCommerce)
 * Author URI: https://developers.mercadopago.com/
 * Text Domain: woocommerce-mercadopago-split
 * Domain Path: /i18n/languages/
 * WC requires at least: 3.0.0
 * WC tested up to: 4.7.0
 * @package MercadoPago
 * @category Core
 * @author Alex Lana (hack do Mercado Pago payments for WooCommerce)
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

if (!defined('WC_MERCADOPAGO_SPLT_BASENAME')) {
    define('WC_MERCADOPAGO_SPLT_BASENAME', plugin_basename(__FILE__));
}

if (!function_exists('is_plugin_active')) {
    include_once ABSPATH . 'wp-admin/includes/plugin.php';
}

if (!class_exists('WC_WooMercadoPagoSplit_Init')) {
    include_once dirname(__FILE__) . '/includes/module/WC_WooMercadoPagoSplit_Init.php';

    register_activation_hook(__FILE__, array('WC_WooMercadoPagoSplit_Init', 'mercadopago_plugin_activation'));
    add_action('plugins_loaded', array('WC_WooMercadoPagoSplit_Init', 'woocommerce_mercadopago_init'));
}







////////////////////////////////////////////////////
// GAMBIARRA PARA CRIAR USUARIOS DE TESTE, cria os usuarios, mas talvez nao seja tao util: https://www.mercadopago.com.br/developers/pt/guides/online-payments/checkout-pro/test-integration
// $ch = curl_init();
// curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
// curl_setopt($ch, CURLOPT_URL, 'https://api.mercadopago.com/users/test_user');
// curl_setopt($ch, CURLOPT_HTTPHEADER, array(
// 	'Content-Type: application/json',
// 	'Authorization: Bearer APP_USR-689232386237439-121601-5bfa83dc93c9f20756f22d1abac1263d-135168024',
// ));
// curl_setopt($ch, CURLOPT_POST, true);
// curl_setopt($ch, CURLOPT_POSTFIELDS, '{"site_id":"MLB"}');
// $output = curl_exec($ch);
// curl_close($ch);
// print_r($output);
// exit;




if ( !is_admin() ) {

	////////////////////////////////////////////////////
	// SHORTCODE PARA VINCULAR CONTAS
	function wcmps_integrar_contas () {
		global $_GET;

		?>
		<style type="text/css">
			#mp-box {
				max-width: 90%;
				width: 500px;
				margin: auto;
				padding: 30px;
				background: #F0F7F6;
			}
			#mp-box.existing p {
				margin-bottom: 0;
			}
			#desv-box {
				max-width: 90%;
				width: 500px;
				margin: auto;
				padding: 30px;
				line-height: 1.2;
				color: gray;
			}
			#desv-box * {
				line-height: 1.2;
			}
		</style>
		<?php

		if ( isset($_GET['code']) && !empty($_GET['code']) && (int)get_current_user_id() > 0 ) {
			if ( base64_decode( $_GET['state'] ) == get_current_user_id() ) {
				update_user_meta( get_current_user_id(), 'wcmps_authcode', $_GET['code'] );
				if ( wcmps_vendedor() ) {
				} elseif ( strlen( get_user_meta( get_current_user_id(), 'wcmps_authcode', true ) ) == 0 || strlen( $cred->refresh_token ) == 0 ) {
					?>
					<ul class="woocommerce-error" role="alert">
						<li>
							Erro ao tentar verificar sua conta junto ao Mercado Pago. Pode ser temporário, mas se o problema persistir, por favor, verifique se sua conta está vinculada.
						</li>
						<li>
							<a href="https://appstore.mercadolivre.com.br/apps/permissions" target="_blank">Verificar no Mercado Pago</a>
						</li>
						<li>
							<a href="<?php echo wcmps_auth_url(); ?>">Renovar vínculo</a>
						</li>
					</ul>
					<?php
				}
			} else {
				?>
				<ul class="woocommerce-error" role="alert">
					<li>
						Erro de usuário. Por favor, verifique o usuário logado e tente novamente.
					</li>
				</ul>
				<?php
			}
		} else if ( (int)get_current_user_id() == 0 ) {
			echo '<div class="message error">Por favor, faça login antes de tentar vincular a conta.</div>';
		}

		if ( (int)get_current_user_id() > 0 ) {
			$authcode = get_user_meta( get_current_user_id(), 'wcmps_authcode', true );
			$cred = json_decode( get_user_meta( get_current_user_id(), 'wcmps_credentials', true ) );
			if ( strlen($authcode) == 0 || strlen($cred->refresh_token) == 0 ) {
				?>
				<div id="mp-box">
					<p style="font-size:90%;">Para que você receba os pagamentos feitos no marketplace é necessário vincular sua conta do Mercado Pago à nossa loja. Clique no botão abaixo para vincular.</p>
					<a href="<?php echo wcmps_auth_url(); ?>" class="button">Vincular conta do Mercado Pago</a>
				</div>
				<?php
			} else {
				?>
				<div id="mp-box" class="existing">
					<p style="font-size:90%;">Parabéns, sua conta está vinculada! Por favor, por segurança, acesse <a href="https://appstore.mercadolivre.com.br/apps/permissions" target="_blank">aplicativos conectados</a> em sua conta no Mercado Pago para confirmar.</p>
				</div>
				<div id="desv-box">
					<p><small><b>Desvincular conta do Mercado Pago</b><br>ATENÇÃO: se você desvincular sua conta, não será possível receber seus pagamentos relativos a compras futuras.</small></p>
				</div>
				<?php
			}
		} else {
			?>
			<p>Por favor, faça login para ter acesso a esta página.</p>
			<?php
		}
	}
	add_shortcode( 'wcmps_integrar_contas', 'wcmps_integrar_contas' );


	////////////////////////////////////////////////////
	// URL DO MERCADO PAGO PARA VINCULAR CONTAS
	function wcmps_auth_url () {
		$meta = get_option('woocommerce_woo-mercado-pago-split-basic_settings');
		$url = 'https://auth.mercadopago.com.br/authorization?client_id='.$meta['_mp_appid'].'&response_type=code&platform_id=mp&state='.base64_encode(get_current_user_id()).'&redirect_uri=' . urlencode( $meta['_mp_returnurl'] );
		return $url;
	}

	////////////////////////////////////////////////////
	// DADOS DO VENDEDOR NO MERCADO PAGO
	function wcmps_vendedor () {

		$meta = get_option('woocommerce_woo-mercado-pago-split-basic_settings');

		if ( $meta['checkout_credential_prod'] == 'no' ) {
			$token = $meta['_mp_access_token_test'];
		} else {
			$token = $meta['_mp_access_token_prod'];
		}
		$dt = 'client_secret='.$token.'&grant_type=authorization_code&code='.get_user_meta( get_current_user_id(), 'wcmps_authcode', true ).'&redirect_uri='.urlencode( $meta['_mp_returnurl'] );

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_URL, 'https://api.mercadopago.com/oauth/token');
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'content-type: application/x-www-form-urlencoded',
			'accept: application/json',
		));
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $dt);

		$output = curl_exec($ch);
		curl_close($ch);
		$_output = json_decode($output);
		if ( !$_output->error ) {
			update_user_meta( get_current_user_id(), 'wcmps_credentials', $output );
			update_user_meta( get_current_user_id(), 'wcmps_refreshed', date('Ymd') );
			return true;
		} elseif ( $_output->status == 400 ) {
			// o erro do mercado livre nao eh claro, mas na maioria das vezes eh resultado do vinculo estar ativo
			return true;
		} else {
			return false;
		}
	}


	////////////////////////////////////////////////////
	// RENOVAR DADOS DO VENDEDOR NO MERCADO PAGO
	function wcmps_renovacao_cron () {
		global $wpdb;
		$meta = get_option('woocommerce_woo-mercado-pago-split-basic_settings');
		if ( $meta['checkout_credential_prod'] == 'no' ) {
			$token = $meta['_mp_access_token_test'];
		} else {
			$token = $meta['_mp_access_token_prod'];
		}
		$hoje = date( 'Ymd' );
		$limite = date( 'Ymd', strtotime( date('Y-m-d H:i:s')." - 1 month" ) );
		$res = $wpdb->get_results( 'select user_id from '.$wpdb->prefix.'usermeta where meta_key = "wcmps_refreshed" and meta_value <= '.$limite, ARRAY_A );
		for ( $i=0; $i<count($res); $i++ ) {
			wcmps_renovacao( $res[$i]['user_id'], $meta, $token );
		}
	}
	add_action( 'wcmps_renovacao_cron', 'wcmps_renovacao_cron' );

	function wcmps_renovacao ( $uid, $meta, $token ) {

		$um = get_user_meta( $uid, 'wcmps_credentials', true );
		$um = json_decode($um);
		$rtoken = json_decode( get_user_meta( $uid, 'wcmps_credentials', true ) );
		$dt = 'client_secret='.$token.'&grant_type=refresh_token&refresh_token='.$rtoken->refresh_token;

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_URL, 'https://api.mercadopago.com/oauth/token');
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'content-type: application/x-www-form-urlencoded',
			'accept: application/json',
		));
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $dt);

		$output = curl_exec($ch);
		curl_close($ch);

		$_output = json_decode($output);
		if ( !$_output->error ) {
			update_user_meta( $uid, 'wcmps_credentials', $output );
			update_user_meta( $uid, 'wcmps_refreshed', date('Ymd') );
			return true;
		} else {
			return false;
		}

	}

	function wcmps_start_cron() {
		if( !wp_next_scheduled( 'wcmps_renovacao_cron' ) ) {  
		   wp_schedule_event( time(), 'daily', 'wcmps_renovacao_cron' );  
		}
	}
	add_action('wp', 'wcmps_start_cron');

	function wcmps_stop_cron() {	
		$timestamp = wp_next_scheduled('wcmps_renovacao_cron');
		wp_unschedule_event($timestamp, 'wcmps_renovacao_cron');
	} 
	register_deactivation_hook(__FILE__, 'wcmps_stop_cron');


} // if !is_admin

////////////////////////////////////////////////////
// DADOS DO VENDEDOR NO MERCADO PAGO
function wcmps_get_cart_vendor () {
	if ( WC() && WC()->cart ) {
	    $produtos = WC()->cart->get_cart();
	    foreach ($produtos as $key => $data) {
	        // IMPORTANTE: isso soh funciona para vendas que tenham apenas 1 vendor. O Mercado Pago não permite split de pagamento entre mais de 2 partes (marketplace e 1 vendedor), esse eh o motivo
	        $v = wcfm_get_vendor_id_by_post( $data['product_id'] );
	        if ( (int)$v > 0 ) {
	            $vendedor = $v;
	        }
	    }
	    return $vendedor;
	}
}

