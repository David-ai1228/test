<?php
/**
 * Plugin Name:	CWP WooCommerce payment gateway Collect
 * Description:	WooCommerce payment gateway extends Collect by CWP.
 * Author:			CloudWP
 * Author URI:	https://cloudwp.pro/
 * Version:			1.0.2
**/
/*<makotostudio />*/


if(!defined('ABSPATH')){
	exit;
}

if(!class_exists('CWP_CPG_WC')):

class CWP_CPG_WC{

	const TITLE		='Woocommerce payment gateway extends Collect by CWP';
	const VERSION	='1.0';

	function __construct(){
		$this->Define('Collect_URL', plugin_dir_url(__FILE__));
		$this->Define('Collect_DIR', dirname(__FILE__));

		$this->Init();

		if(is_admin()){
			require 'plugin-updates/plugin-update-checker.php';
			PucFactory::buildUpdateChecker('https://auth.woocloud.io/update/collect/info.json', __FILE__);
		}
	}

	private function Init(){
		add_action('init', array($this, 'CollectPaymentGatewayClass'));
		add_filter('woocommerce_payment_gateways', array($this, 'AddCollectPaymentGateway'));
	}

	public function AddCollectPaymentGateway($methods){
		$methods[]='WC_Gateway_CWP_Collect';
		return $methods;
	}

	public function CollectPaymentGatewayClass(){
		include_once Collect_DIR.'/includes/class_collect_payment_gateway.php';
	}

	private function Define($strName, $strValue){
		if(!defined($strName)){
			define($strName, $strValue);
		}
	}
}

return new CWP_CPG_WC();

endif;