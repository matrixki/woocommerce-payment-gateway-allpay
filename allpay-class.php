<?php
/**
 * Allpay Payment Gateway
 * Description: Woocommerce Allpay 線上刷卡模組
 * Version: 1.0
 * Author URI: http://matrixki.com/
 * Author: Mike Ko
 * Plugin Name: Woocommerce Allpay
 * @class       Allpay
 * @extends     WC_Payment_Gateway
 * @version     1.0
 * @author      AFT Mike   
 */

add_action('plugins_loaded', 'allpay_gateway_init',0);

function allpay_gateway_init(){
	
	/* allpay gateway class extend woocommerce standard payment gateway class */
	class WC_Gateway_Allpay extends WC_Payment_Gateway
	{

        /**
         * Constructor for the gateway.
         *
         * @access public
         * @return void
         */
		public function __construct(){
			$this->id = 'allpay';
			$this->icon = apply_filters('woocommerce_allpay_icon', plugins_url('icon/creditcards.png', __FILE__));
			$this->has_fields = false;
			$this->method_title = __('allpay','woocommerce');
			$this->method_description = __('allpay module for woocommerce','woocommerce');

			$this->init_form_fields();
			$this->init_settings();

			// Define user set variables
            $this->title = $this->settings['title'];
            $this->description = $this->settings['description'];
            $this->mer_id = $this->settings['mer_id'];
            $this->gateway = $this->settings['gateway'];
            $this->hash_key = trim($this->settings['hash_key']);
            $this->hash_iv = trim($this->settings['hash_iv']);

            add_action( 'woocommerce_update_options_payment_gateways_'.$this->id, array( $this, 'process_admin_options'));
            add_action('woocommerce_thankyou_allpay', array($this, 'thankyou_page'));  //需與id名稱大小寫相同
            add_action('woocommerce_receipt_allpay', array($this, 'receipt_page'));            
		}

        /**
         * Initialise Gateway Settings Form Fields
         *
         * @access public
         * @return void
         */
		function init_form_fields(){
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Start Allpay module', 'woocommerce'),
                    'default' => 'yes'
                ),
                'title' => array(
                	'title' => __('Title', 'woocommerce'),
                	'type' => 'text',
                	'description' => __('The title client see during checkout','woocommerce'),
                	'default' => __('Allpay all in one payment', 'woocommerce')
                ),
                'description' => array(
                    'title' => __('Description', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Allpay all in one online payment','woocommerce'),
                    'default' => __('Allpay all in one online payment', 'woocommerce')
                ),                
                'mer_id' => array(
                	'title' => __('MerchandID','woocommerce'),
                	'type' => 'text',
                	'description' => __('Your allpay MerchantID','woocommerce'),
                	'default' => __('2000132','woocommerce')
                ),
                'gateway' => array(
                	'title' => __('gateway','woocommerce'),
                	'type' => 'text',
                	'description' => __('Your allpay payment gateway','woocommerce'),
                	'default' => __('http://payment-stage.allpay.com.tw/Cashier/AioCheckOut','woocommerce')
                ),
                'hash_key' => array(
                	'title' => __('HashKey','woocommerce'),
                	'type' => 'text',
                	'description' => __('Your allpay hashkey','woocommerce'),
                	'default' => __('5294y06JbISpM5x9','woocommerce')
                ), 
                'hash_iv' => array(
                	'title' => __('HashIV','woocommerce'),
                	'type' => 'text',
                	'description' => __('Your allpay hashiv','woocommerce'),
                	'default' => __('v77hoKGq4kWxNNIS','woocommerce')
                ),                               
            );			
		}


        /**
         * Admin Panel Options
         * - Options for bits like 'title' and availability on a country-by-country basis
         *
         * @access public
         * @return void
         */
        public function admin_options() {
            ?>
            <h3><?php _e('Allpay online payment module', 'woocommerce'); ?></h3>
            <p><?php _e('This is the module that connects to allpay payment gateway', 'woocommerce'); ?></p>
            <table class="form-table">
                <?php
                // Generate the HTML For the settings form.
                $this->generate_settings_html();
                ?>
            </table><!--/.form-table-->
            <?php
        }


        /**
         * Get Allpay Args for passing to Ecbank
         *
         * @access public
         * @param mixed $order
         * @return array
         */
        function get_allpay_args($order) {
            global $woocommerce;

            // $order_id = $order->id;

            // if ($this->debug == 'yes') {
            //     $this->log->add('allpay', 'Generating payment form for order #' . $order_id . '. Notify URL: ' . $this->notify_url);
            // }
            // $buyer_name = $order->billing_last_name . $order->billing_first_name;

            $MerchantID = $this->mer_id;
            $MerchantTradeNo = $order->id; 
            $MerchantTradeDate = date("Y/m/d H:i:s");
            $PaymentType = 'aio';
            $TotalAmount = intval($order->order_total);
            $TradeDesc = 'Sold item by Aleatoire';
            $ItemArray = $order->get_items();
            foreach ( $ItemArray as $item ) {
                $ItemName .= $item['name'].', ';
            }
            $ReturnURL = $this->get_return_url($order);
            $ClientBackURL = $this->get_return_url($order);
            $ChoosePayment = 'Credit';

            $allpay_args = array(
                "MerchantID" => $MerchantID,
                'MerchantTradeNo' => $MerchantTradeNo,
                'MerchantTradeDate' => $MerchantTradeDate,
                "PaymentType" => "aio",
                'TotalAmount' => $TotalAmount,
                'TradeDesc' => $TradeDesc,
                'ItemName' => $ItemName,
                'ReturnURL' => $ReturnURL,
                'ChoosePayment' => $ChoosePayment,
                "ClientBackURL" => $ClientBackURL,
                "ItemURL" => '',
                "Remark" => '',
                "ChooseSubPayment" => '',
                "OrderResultURL" => '',
            );

            ksort($allpay_args);    

            $hashString = "HashKey=" . $this->hash_key . "&" . urldecode(http_build_query($allpay_args)) . "&HashIV=" . $this->hash_iv;
            
            $hashString = urlencode($hashString);
            $hashString = strtolower($hashString);
            $CheckMacValue = strtoupper(md5($hashString));

            $allpay_args['CheckMacValue'] = $CheckMacValue;

            $allpay_args = apply_filters('woocommerce_allpay_args', $allpay_args);
            return $allpay_args;
        }


        /**
         * Output for the order received page.
         *
         * @access public
         * @return void
         */
        function thankyou_page($order_id) {  //接收回傳參數驗證
            global $woocommerce;
            $order = &new WC_Order($order_id);
            if ($description = $this->get_description())
                echo wpautop(wptexturize($description));

            if ( $_POST['RtnCode'] == 1) {
                $result_msg = '交易成功，Allpay 交易單號：' . $_POST['MerchantTradeNo'] . '，處理日期：' . $_POST['ProcessDate'];   //交易成功
                $order->update_status('processing', __('Payment received, awaiting fulfilment', 'allpay'));
				$woocommerce->cart->empty_cart();
            }
            else{
                $result_msg = '交易成功';
            }
            echo $result_msg;
        }


        /**
         * Generate the allpay button link (POST method)
         *
         * @access public
         * @param mixed $order_id
         * @return string
         */
        function generate_allpay_form($order_id) {///***********************************************************
            global $woocommerce;
            $order = new WC_Order($order_id);
            $allpay_args = $this->get_allpay_args($order);

            $allpay_gateway = $this->gateway;
            $allpay_args_array = array();
            foreach ($allpay_args as $key => $value) {
                $allpay_args_array[] = '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" />';
            }

            $woocommerce->add_inline_js('
			jQuery("body").block({
					message: "<img src=\"' . esc_url(apply_filters('woocommerce_ajax_loader_url', $woocommerce->plugin_url() . '/assets/images/ajax-loader.gif')) . '\" alt=\"Redirecting&hellip;\" style=\"float:left; margin-right: 10px;\" />' . __('感謝您的訂購，接下來畫面將導向到Allpay 歐付寶的刷卡頁面', 'allpay') . '",
					overlayCSS:
				{
					background: "#fff",
					opacity: 0.6
				},
					centerY: false,
					css: {
					top:			"20%",
					padding:        20,
					textAlign:      "center",
					color:          "#555",
					border:         "3px solid #aaa",
					backgroundColor:"#fff",
					cursor:         "wait",
					lineHeight:		"32px"
					}
					});
			jQuery("#submit_allpay_payment_form").click();				
			');

            return '<form id="allpay" name="allpay" action=" ' . $allpay_gateway . ' " method="post" target="_top">' . implode('', $allpay_args_array) . '
				<input type="submit" class="button-alt" id="submit_allpay_payment_form" value="' . __('Pay via allpay', 'allpay') . '" />
				</form>' . "<script>document.forms['allpay'].submit();</script>";
        
        }

        /**
         * Output for the order received page.
         *
         * @access public
         * @return void
         */
        function receipt_page($order) {
            echo '<p>' . __('感謝您的訂購，接下來將導向到刷卡頁面，請稍後.', 'allpay') . '</p>';
            echo $this->generate_allpay_form($order);
        }


        /**
         * Process the payment and return the result
         *
         * @access public
         * @param int $order_id
         * @return array
         */
		function process_payment($order_id){

			global $woocommerce;
			$order = new WC_Order($order_id);

            // Empty awaiting payment session
            unset($_SESSION['order_awaiting_payment']);
            
            $this->receipt_page($order);

            return array(
                'result' => 'success',
                'redirect' => add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
            );
		}

        /**
         * Payment form on checkout page
         *
         * @access public
         * @return void
         */
        function payment_fields() {
            if ($this->description)
                echo wpautop(wptexturize($this->description));
        }        
	}


    /**
     * Add the gateway to WooCommerce
     *
     * @access public
     * @param array $methods
     * @package     WooCommerce/Classes/Payment
     * @return array
     */
	function add_allpay_gateway($method){
		$methods[] = 'WC_Gateway_Allpay';
		return $methods;
	}
	add_filter('woocommerce_payment_gateways', 'add_allpay_gateway');

}

?>