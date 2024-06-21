<?php
if(!defined('ABSPATH')){
	exit;
}

/* ==================================================
 * Collect Payment gateway for WooCommerce
 *
 * Provides a Collect Payment Gateway for WooCommerce.
 *
 * @class 		WC_Gateway_CWP_Collect
 * @extends		WC_Payment_Gateway
 * @version		1.0.1
 * @package		WooCommerce/Classes/Payment
 * @author 		JanaLEE
 * ==================================================
**/

/*<makotostudio />*/

class WC_Gateway_CWP_Collect extends WC_Payment_Gateway{

	static $refund;

	function __construct(){

		self::$refund=false;

		$this->id='cpgfw';

		$this->has_fields=false;

		$this->method_title='客樂得';

		if(($strExpireDate=get_option('_Collect_PG_for_WooCommerce'))===false){
			$strExpireDate=$this->CheckDate();
			add_option('_Collect_PG_for_WooCommerce', $strExpireDate, '', 'no');
		}

		if(date(strip_tags($strExpireDate))<date('Y-m-d')){
			$strExpireDate=$this->CheckDate();
		}

		$this->method_description	='客樂得線上付款 - Collect payment gateway for WooCommerce|Expired date: '.$strExpireDate;

		$this->supports=array('products', 'refunds');

		$this->init_form_fields();
		$this->init_settings();

		$this->title							=$this->get_option('title');
		$this->description				=$this->get_option('description');
		$this->order_button_text	=$this->get_option('order_button_text');
		$this->icon								=$this->get_option('icon');
		$this->link_id						=$this->get_option('link_id');
		$this->hash_base					=$this->get_option('hash_base');
		$this->acquirer_type			=$this->get_option('acquirer_type');

		add_action('woocommerce_update_options_payment_gateways_'.$this->id, array($this, 'process_admin_options'));
		add_action('woocommerce_thankyou', array($this, 'OrderReceived'), 1);
		add_action('woocommerce_order_status_cancelled', array($this, 'CancelOrder'));
		add_action('woocommerce_order_refunded', array($this, 'ManualRefund'), 10, 2);
	}

	public function OrderReceived($order_id){
		if(get_post_meta($order_id, '_payment_method', true)==$this->id){
			if(isset($_POST['auth_code'])){
				add_post_meta($order_id, '_collectCompletePayment', $_POST, true);
			}

			$order=new WC_Order($order_id);
			if($order->get_status()=='pending'){
				$order->update_status('processing');
				$order->reduce_order_stock();
			}

		}
	}

	public function CancelOrder($order_id){
		$arrPostMeta=get_post_meta($order_id, '_collectCompletePayment', true);
		if(is_array($arrPostMeta)){
			$arrData=array(	'link_id'				=>$this->link_id, 
											'hash_base'			=>$this->hash_base, 
											'cust_order_no'	=>$arrPostMeta['cust_order_no'], 
											'order_amount'	=>$arrPostMeta['order_amount']);

			$strResult=$this->CurlPost($arrData, 'https://auth.woocloud.io/Collect/?type=cancel');
			$this->AddOrderNote('cancel', $strResult, $order_id);
		}
	}

	public function ManualRefund($order_id){
		if(!self::$refund){
			return $this->DoRefund($order_id);
		}
	}

	public function DoRefund($order_id){
		$arrPostMeta=get_post_meta($order_id, '_collectCompletePayment', true);
		$arrData=array(	'link_id'				=>$this->link_id, 
										'hash_base'			=>$this->hash_base, 
										'cust_order_no'	=>$arrPostMeta['cust_order_no'], 
										'order_amount'	=>$arrPostMeta['order_amount']);

		$strResult=$this->CurlPost($arrData, 'https://auth.woocloud.io/Collect/?type=refund');
		$arrResult=$this->AddOrderNote('refund', $strResult, $order_id);
		if(self::$refund){
			if($arrResult['status']=='OK'){
				return true;
			}
		}
		return false;
	}

	private function AddOrderNote($strType, $strResult, $order_id){
		$strResult=str_replace("\n", '&', $strResult);
		parse_str($strResult, $arrResult);

		$order=wc_get_order($order_id);

		if($strType=='cancel'){
			if($arrResult['status']=='OK'){
				$order->add_order_note('取消授權: '.$arrResult['status'].'<br />客樂得訂單編號: #'.$arrResult['cust_order_no']);
			}else{
				$order->add_order_note('取消授權異常: '.$arrResult['msg']);
				$order->update_status('failed');
			}
		}else{
			
		}

		return $arrResult;
	}

	private function CurlPost($arrData, $strPostURL=false){

		if($strPostURL){
			$ch=curl_init();

			curl_setopt($ch, CURLOPT_URL, $strPostURL);
			curl_setopt($ch, CURLOPT_HEADER, false);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
			curl_setopt($ch, CURLOPT_POST, 1);

			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($arrData));

			$strResult=curl_exec($ch);
			curl_close($ch);

			return $strResult;
		}
		return false;

	}

	private function CheckDate(){
		header('Content-Type: text/html; charset=utf-8');
		$context=stream_context_create(array('http'=>array(	'method'			=>'GET', 
																												'header'			=>"Connection: close\r\n", 
																												'user_agent'	=>$_SERVER['HTTP_USER_AGENT'])));

		$strContentURL='https://auth.woocloud.io/Collect/?checkdate=';
		$strResult=@file_get_contents($strContentURL.get_site_url(), false, $context);
		return $strResult;
	}

	public function init_form_fields(){

		$this->form_fields=array(	'enabled'	=>array(	'title'		=>__('Enable/Disable', 'woocommerce'),
																									'type'		=>'checkbox',
																									'label'		=>'啟用客樂得線上付款',
																									'default'	=>'no'),

															'title'		=>array(	'title'				=>'客樂得標題',
																									'type'				=>'text',
																									'description'	=>'顯示在前台結帳頁面中，本服務的自訂名稱',
																									'default'			=>'客樂得線上付款',
																									'desc_tip'		=>true),

															'description'=>array(	'title'				=>'服務說明',
																										'type'				=>'text',
																										'description'	=>'顯示在前台結帳頁面中的自訂說明',
																										'default'			=>'宅急便|客樂得線上付款服務',
																										'desc_tip'		=>true),

															'order_button_text'=>array(	'title'				=>'結帳按鈕文字',
																													'type'				=>'text',
																													'description'	=>'顯示在前台結帳頁面中，當選擇本服務時所顯示的自訂按鈕文字',
																													'default'			=>'使用客樂得付款',
																													'desc_tip'		=>true),

															'icon'	=>array('title'				=>'圖示網址',
																							'type'				=>'text',
																							'description'	=>'請輸入圖片完整網址',
																							'default'			=>Collect_URL.'images/collect_logo.png',
																							'desc_tip'		=>true),

															'link_id'		=>array('title'				=>'Link ID',
																									'type'				=>'text',
																									'description'	=>'請輸入您申請串接客樂得的 Link ID',
																									'default'			=>'',
																									'desc_tip'		=>true),

															'acquirer_type'=>array(
																'title'				=>'收單銀行',
																'type'				=>'select',
																'options'			=>array(
																	'esun'				=>'玉山銀行', 
																	'chinatrust'	=>'中國信託商業銀行'), 
																'description'	=>'請選擇您開通的收單銀行',
																'default'			=>'esun',
																'desc_tip'		=>true),

															'hash_base'	=>array('title'				=>'Hash Base',
																									'type'				=>'text',
																									'description'	=>'請輸入您申請串接客樂得的 Hash Base',
																									'default'			=>'',
																									'desc_tip'		=>true), 

															'returnURL'	=>array('title'				=>'客樂得後台設定',
																									'type'				=>'text',
																									'description'	=>'<span style="display:block; font-style:normal;">請複製上列網址，於 <a href="https://4128888card.com.tw/index.php" target="_blank" style="color:#069; font-weight:bold;">客樂得後台</a> &gt; <strong style="color:#C00;">契客介面</strong> &gt; <strong style="color:#C00;">設定</strong> 中貼上，設定如下圖</p><img src="'.Collect_URL.'/images/collect_settings.png" style="display:block;" />',

																									'default'			=>'https://auth.woocloud.io/Collect/',

																									'desc_tip'		=>false));
	}

	public function process_refund($order_id, $amount=NULL, $reason=''){
		self::$refund=true;
		return $this->DoRefund($order_id);
	}

	public function process_payment($order_id){

		$order=new WC_Order($order_id);

		if(WC()->version<'3'){
			$intOrderTotal=$order->order_total;
		}else{
			$intOrderTotal=$order->get_total();
		}

		$arrPost=array(	'domain_name'		=>get_site_url(), 
										'timestamp'			=>time(), 
										'return_url'		=>$this->get_return_url($order), 
										'link_id'				=>$this->link_id, 
										'cust_order_no'	=>$order_id, 

										'acquirer_type'	=>$this->acquirer_type, 

										'order_amount'	=>$order->order_total, 
										'first_name'		=>get_post_meta($order_id, '_shipping_first_name', true), 
										'last_name'			=>get_post_meta($order_id, '_shipping_last_name', true), 
										'payer_mobile'	=>get_post_meta($order_id, '_billing_phone', true), 
										'payer_email'		=>get_post_meta($order_id, '_billing_email', true), 
										'hash_base'			=>$this->hash_base);

		$order=wc_get_order($order_id);

		$arrOrders=$order->get_items();

		foreach($arrOrders as $key=>$value){
			$arrOrder=array('name'			=>str_replace(' ', '%20', $value['name']), 
											'qty'				=>$value['qty'], 
											'subtotal'	=>$value['line_subtotal']);

			$arrPost['item_'.$key]=http_build_query($arrOrder);
		}

		$strRedirectURL=get_site_url();

		$strPostURL='https://auth.woocloud.io/Collect/';

		$strResult=$this->CurlPost($arrPost, $strPostURL);
		parse_str($strResult, $arrData);

		if($arrData['createOrder']>0){
			$strRedirectURL=$arrData['collectURL'];
		}

		return array(	'result'	=>'success',
									'redirect'=>$strRedirectURL);
	}
}

return new WC_Gateway_CWP_Collect();