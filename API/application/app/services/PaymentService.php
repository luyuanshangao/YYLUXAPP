<?php
namespace app\app\services;
use think\Log;
use think\Monlog;


/**
 * 开发：tinghu.liu
 * 功能：支付处理服务类
 * 时间：2018-11-06
 */

class PaymentService extends BaseService
{
    private $CommonService = '';
    public function __construct(){
        parent::__construct();
        $this->CommonService = new CommonService();//公共服务类
    }

    /**
     * 通知payment订单之间的关系
     * @param $_order_info
     * @param int $from_flag 来源标识：1-正常支付，2-repay
     * @return bool
     */
    public function informOrderRelation($_order_info, $from_flag=1){
        $post_config = config('synchro_payment_post');
        $post_header = [];
        $post_header[] = "Content-Type: application/json";
        $post_header[] = "Authorization: Basic ".base64_encode($post_config['user_name'].":".$post_config['pass_word']);
        $Params = array();
        if ($from_flag == 1){ //正常情况
            $Params['OrderNumber'] = $_order_info['master']['order_number'];
            foreach ($_order_info['slave'] as $k=>$v){
                $Tmp['OrderNumber'] = $v['order']['order_number'];
                $Tmp['Amount'] = $v['order']['grand_total'];
                $Params['Children'][] = $Tmp;
            }
        }else if ($from_flag == 2){ //repay
            $Params['OrderNumber'] = $_order_info['master']['order_number'];
            foreach ($_order_info['slave'] as $k=>$v){
                $Tmp['OrderNumber'] = $v['order_number'];
                $Tmp['Amount'] = $v['grand_total'];
                $Params['Children'][] = $Tmp;
            }
        }
        $ParamsTmp[] = $Params;
        Log::record('informOrderRelation-url:'.json_encode($post_config));
        Log::record('informOrderRelation:'.json_encode($ParamsTmp));
        $res = doCurl($post_config['url'],$ParamsTmp,null,true,$post_header);
        Log::record('informOrderRelation-res:'.json_encode($res));
        Monlog::write(LOGS_MALL_CART,'info',__METHOD__,'submitOrder'.$_order_info['master']['order_number'],$ParamsTmp,MALL_API.'/home/order/submitOrder/dxPost_informOrderRelation_res', $res);
        Monlog::write(LOGS_MALL_CART,'info',__METHOD__,'submitOrder'.$_order_info['master']['order_number'],$post_config,MALL_API.'/home/order/submitOrder/dxPost_informOrderRelation_$post_config', $post_config);
        if($res['IsSuccess']){
            return true;
        }else{
            return false;
        }
    }

    /**
     * SC支付
     */
    public function doStoreCreditCheckout($_params){
        $_param_cartinfo = $this->CommonService->calPayInfo($_params);
        $_param_shipping_info = $_param_cartinfo['shiping_address'];

        $_post_data = array(
            'DoStoreCreditCheckout' => array(
                'request' => array(
                    'CurrencyCode' => $_params['currency'],
                    //'CurrencyType' => 1,
                    'CustomField' => 1,
                    'CustomerID' => $_params['customer_id'],
                    'CustomerIP' => $this->CommonService->getIp(),
                    'HandlingTotal' => isset($_param_cartinfo['handling_total'])?$_param_cartinfo['handling_total']:0,//手续价总额
                    'Items' => array(
                        'Goods' => isset($_param_cartinfo['item'])?$_param_cartinfo['item']:array()
                    ),
                    'ItemsTotal' => isset($_param_cartinfo['items_totals'])?$_param_cartinfo['items_totals']:0,//订单的商品总额
                    'NotificationUrl' => '',
                    'OrderNumber' => isset($_params['order_number'])?$_params['order_number']:'',
                    'OrderTotal' => isset($_param_cartinfo['order_total'])?$_param_cartinfo['order_total']:0,//订单总额（包括运费等）
                    //'OrderType' => 0,
                    'PaymentAction' => 'Purchase',
                    'ShippingAddress' => array(
                        'City' => isset($_param_shipping_info['City'])?$_param_shipping_info['City']:'',
                        'CountryCode' => isset($_param_shipping_info['CountryCode'])?$_param_shipping_info['CountryCode']:'',
                        'Email' => isset($_param_shipping_info['Email'])?$_param_shipping_info['Email']:' ',
                        'FirstName' => isset($_param_shipping_info['FirstName'])?$_param_shipping_info['FirstName']:'',
                        'LastName' => isset($_param_shipping_info['LastName'])?$_param_shipping_info['LastName']:'',
                        'Mobile' => isset($_param_shipping_info['Mobile'])?$_param_shipping_info['Mobile']:'',
                        'Phone' => isset($_param_shipping_info['Phone'])?$_param_shipping_info['Phone']:'',
                        'PostalCode' => isset($_param_shipping_info['PostalCode'])?$_param_shipping_info['PostalCode']:'',
                        //'State' => isset($_param_shipping_info['State'])?$_param_shipping_info['State']:'',
                        'Street1' => isset($_param_shipping_info['Street1'])?$_param_shipping_info['Street1']:'',
                        'Street2' => isset($_param_shipping_info['Street2'])?$_param_shipping_info['Street2']:'',
                    ),
                    'ShippingMethod' => isset($_param_cartinfo['shiping_model'])?$_param_cartinfo['shiping_model']:'',
                    'ShippingTotal' => isset($_param_cartinfo['shipping_total'])?$_param_cartinfo['shipping_total']:0,
                )
            )
        );

        $_result = $this->CommonService->dxSoap('DoStoreCreditCheckout',$_post_data);
        $_return_data['OrderTotal'] = sprintf("%.2f", $_param_cartinfo['order_total']);
        $_return_data['PayerEmail'] = isset($_param_shipping_info['Email'])?$_param_shipping_info['Email']:'';
        $_return_data['Items'] = isset($_param_cartinfo['item'])?$_param_cartinfo['item']:array();
        $_return_data['result'] = $_result;
        Log::record('doStoreCreditCheckout'.json_encode($_post_data));
        $order_master_number = isset($_params['order_number'])?$_params['order_number']:'';
        Monlog::write(LOGS_MALL_CART,'info',__METHOD__,'submitOrder'.$order_master_number,$_post_data,MALL_API.'/home/order/submitOrder/dxSoap_DoStoreCreditCheckout_res', $_result);
        return $_return_data;

    }

    /**
     * paypal支付的set阶段处理
     * @param $_params
     * @return array
     */
    public function setExpressCheckout($_params){
        $_param_cartinfo = $this->CommonService->calPayInfo($_params);
        $_param_shipping_info = $_param_cartinfo['shiping_address'];
        $_order_number = isset($_params['order_number'])?$_params['order_number']:'';

        $_post_data = array('SetExpressCheckout'=>array(
            'request'=>array(
                'CancelUrl' => MALL_DOCUMENT.'/home/order/cancel/',
                'CurrencyCode' => $_params['currency'],
                'CustomField' => 1,//购物车ID
                'CustomerCountryCode' => $_params['country'],//
                'CustomerID' => $_params['customer_id'],
                'CustomerIP' => $this->CommonService->getIp(),
                'DiscountTotal' => isset($_param_cartinfo['discount_total'])?$_param_cartinfo['discount_total']:0,//优惠总额
                'ExchangeRate' => $_params['rate'],
                'HandlingTotal' => isset($_param_cartinfo['handling_total'])?$_param_cartinfo['handling_total']:0,//手续价总额
                'Items' => array(
                    'Goods' => isset($_param_cartinfo['item'])?$_param_cartinfo['item']:array()
                ),
                'ItemsTotal' => isset($_param_cartinfo['items_totals'])?$_param_cartinfo['items_totals']:0,//订单的商品总额
                'LocaleCode' => isset(config("LocalCode")[$_params['lang']])?config("LocalCode")[$_params['lang']]:'en_US',//一个语种对应的种localeCode
                //'LogoUrl' => '',
                'NotificationUrl' => '',//
                'OrderTotal' => isset($_param_cartinfo['order_total'])?$_param_cartinfo['order_total']:0,//订单总额（包括运费等）
                //'OrderType' => 1,
                'PaymentAction' => 'Purchase',//
                'ReturnUrl' => MALL_DOCUMENT.'/home/order/mNoShortcutExpressCheckoutReturnUrl/?p='.base64_encode(json_encode([
                        'customer_id'=>$_params['customer_id'],
                        'lang'=>$_params['lang'],
                        'currency'=>$_params['currency'],
                        'order_from'=>$_params['order_from'],
                        'order_number'=>$_order_number,
                    ])),//快捷支付和非快捷支付的URL不一样
                'ShippingAddress' => array(
                    'City' => isset($_param_shipping_info['City'])?$_param_shipping_info['City']:'',
                    'CountryCode' => isset($_param_shipping_info['CountryCode'])?$_param_shipping_info['CountryCode']:'',
                    'Email' => isset($_param_shipping_info['Email'])?$_param_shipping_info['Email']:'',
                    'FirstName' => isset($_param_shipping_info['FirstName'])?$_param_shipping_info['FirstName']:'',
                    'LastName' => isset($_param_shipping_info['LastName'])?$_param_shipping_info['LastName']:'',
                    'Mobile' => isset($_param_shipping_info['Mobile'])?$_param_shipping_info['Mobile']:'',
                    'Phone' => isset($_param_shipping_info['Phone'])?$_param_shipping_info['Phone']:'',
                    'PostalCode' => isset($_param_shipping_info['PostalCode'])?$_param_shipping_info['PostalCode']:'',
                    'State' => isset($_param_shipping_info['StateCode'])?$_param_shipping_info['StateCode']:'',
                    //'StateCode' => isset($_param_shipping_info['StateCode'])?$_param_shipping_info['StateCode']:'',
                    'Street1' => isset($_param_shipping_info['Street1'])?$_param_shipping_info['Street1']:'',
                    'Street2' => isset($_param_shipping_info['Street2'])?$_param_shipping_info['Street2']:'',
                ),
                'ShippingMethod' => isset($_param_cartinfo['shiping_model'])?$_param_cartinfo['shiping_model']:'',
                'ShippingTotal' => isset($_param_cartinfo['shipping_total'])?$_param_cartinfo['shipping_total']:0,
            )));
        $this->redis->set("App_PayPalSubmitInfo_".$_order_number,$_post_data,config('pay_pal_submit_info_time'));
        $_result = $this->CommonService->dxSoap('SetExpressCheckout',$_post_data);
        Log::record('setExpressCheckout'.json_encode($_post_data));
        $order_master_number = $_order_number;
        Monlog::write(LOGS_MALL_CART,'log',__METHOD__,'submitOrder'.$order_master_number,$_post_data,MALL_API.'/home/order/submitOrder/dxSoap_SetExpressCheckout_res', $_result);
        return $_result;
    }


    /**
     * EGP3.0信用卡支付(非token)
     */
    public function doCreditCheckout($_params){
        //接收信息卡信息
        $_param_cartinfo = $this->CommonService->calPayInfo($_params);
        $_param_shipping_info = $_param_cartinfo['shiping_address'];
        $_post_data = array(
            'DoCreditCheckout' => array(
                'request' => array(
                    'BillingAddress' => array(
                        'City' => isset($_params['BillingAddress']['City'])?$_params['BillingAddress']['City']:'',
                        'CountryCode' => isset($_params['BillingAddress']['CountryCode'])?$_params['BillingAddress']['CountryCode']:'',
                        'Email' => isset($_params['BillingAddress']['Email'])?$_params['BillingAddress']['Email']:'',
                        'FirstName' => isset($_params['BillingAddress']['FirstName'])?$_params['BillingAddress']['FirstName']:'',
                        'LastName' => isset($_params['BillingAddress']['LastName'])?$_params['BillingAddress']['LastName']:'',
                        'Mobile' => isset($_params['BillingAddress']['Mobile'])?$_params['BillingAddress']['Mobile']:'',
                        'Phone' => isset($_params['BillingAddress']['Phone'])?$_params['BillingAddress']['Phone']:'',
                        'PostalCode' => isset($_params['BillingAddress']['PostalCode'])?$_params['BillingAddress']['PostalCode']:'',
                        'State' => isset($_params['BillingAddress']['State'])?$_params['BillingAddress']['State']:'',
                        'Street1' => isset($_params['BillingAddress']['Street1'])?$_params['BillingAddress']['Street1']:'',
                        'Street2' => isset($_params['BillingAddress']['Street2'])?$_params['BillingAddress']['Street2']:'',
                    ),
                    'CardInfo' => array(
                        'CVVCode' => isset($_params['CardInfo']['CVVCode'])?$_params['CardInfo']['CVVCode']:'',
                        'CardHolder' => isset($_params['CardInfo']['CardHolder'])?$_params['CardInfo']['CardHolder']:'',
                        'CardNumber' => isset($_params['CardInfo']['CardNumber'])?$_params['CardInfo']['CardNumber']:'',
                        'ExpireMonth' => isset($_params['CardInfo']['ExpireMonth'])?$_params['CardInfo']['ExpireMonth']:'',
                        'ExpireYear' => isset($_params['CardInfo']['ExpireYear'])?$_params['CardInfo']['ExpireYear']:'',
                        'IssuingBank' => isset($_params['CardInfo']['IssuingBank'])?$_params['CardInfo']['IssuingBank']:'',
                    ),
                    'CurrencyCode' => $_params['currency'],
                    'CustomField' => 1,
                    'CustomerID' => $_params['customer_id'],
                    'CustomerIP' => $this->CommonService->getIp(),
                    'EnableTokenCheckout' => $_params['save_card'],
                    'ExchangeRate' => $_params['rate'],
                    'HandlingTotal' => isset($_param_cartinfo['handling_total'])?$_param_cartinfo['handling_total']:0,
                    'Items' => array(
                        'Goods' => isset($_param_cartinfo['item'])?$_param_cartinfo['item']:array()
                    ),
                    'ItemsTotal' => isset($_param_cartinfo['items_totals'])?$_param_cartinfo['items_totals']:0,
                    'Notes' => '',
                    'OrderNumber' => isset($_params['order_number'])?$_params['order_number']:'',
                    'OrderTotal' => isset($_param_cartinfo['order_total'])?$_param_cartinfo['order_total']:0,
                    //'OrderType' => 0,
                    'PaymentAction' => 'Purchase',
                    'ShippingAddress' => array(
                        'City' => isset($_param_shipping_info['City'])?$_param_shipping_info['City']:'',
                        //'CountryCode' => isset($_param_shipping_info['CountryCode'])?$_param_shipping_info['CountryCode']:'',
                        'CountryCode' => 'AT',
                        'Email' => isset($_param_shipping_info['Email'])?$_param_shipping_info['Email']:'',
                        'FirstName' => isset($_param_shipping_info['FirstName'])?$_param_shipping_info['FirstName']:'',
                        'LastName' => isset($_param_shipping_info['LastName'])?$_param_shipping_info['LastName']:'',
                        'Mobile' => isset($_param_shipping_info['Mobile'])?$_param_shipping_info['Mobile']:'',
                        'Phone' => isset($_param_shipping_info['Phone'])?$_param_shipping_info['Phone']:'',
                        'PostalCode' => isset($_param_shipping_info['PostalCode'])?$_param_shipping_info['PostalCode']:'',
                        'State' => isset($_param_shipping_info['State'])?$_param_shipping_info['State']:'',
                        'Street1' => isset($_param_shipping_info['Street1'])?$_param_shipping_info['Street1']:'',
                        'Street2' => isset($_param_shipping_info['Street2'])?$_param_shipping_info['Street2']:'',
                    ),
                    'ShippingMethod' => isset($_param_cartinfo['shiping_model'])?$_param_cartinfo['shiping_model']:'',
                    'ShippingTotal' => isset($_param_cartinfo['shipping_total'])?$_param_cartinfo['shipping_total']:0,
                    'UCI' => ''
                )
            )
        );
        $_result = $this->CommonService->dxSoap('DoCreditCheckout',$_post_data);
        $_return_data['OrderTotal'] = sprintf("%.2f", $_param_cartinfo['order_total']);
        $_return_data['PayerEmail'] = isset($_param_shipping_info['Email'])?$_param_shipping_info['Email']:'';
        $_return_data['Items'] = isset($_param_cartinfo['item'])?$_param_cartinfo['item']:array();
        $_return_data['result'] = $_result;
        Log::record('doCreditCheckout'.json_encode($_post_data));
        $order_master_number = isset($_params['order_number'])?$_params['order_number']:'';
        Monlog::write(LOGS_MALL_CART,'info',__METHOD__,'submitOrder'.$order_master_number,$_post_data,MALL_API.'/home/order/submitOrder/dxSoap_DoCreditCheckout_res', $_result);
        return $_return_data;
    }

    /**
     * EGP3.0信用卡支付(token)
     */
    public function doCreditCardTokenCheckout($_params){
        $_param_cartinfo = $this->CommonService->calPayInfo($_params);
        $_param_shipping_info = $_param_cartinfo['shiping_address'];

        $_post_data = array(
            'DoCreditCardTokenCheckout' => array(
                'request' => array(
                    'CreditCardTokenID' => isset($_params['credit_card_token_id'])?$_params['credit_card_token_id']:'',//tokenID
                    //'CreditCardTokenID' => 'EB92A3AF-2A16-C6A2-7DDB-F896D37E27F3',
                    'CVVCode' => isset($_params['CardInfo']['CVVCode'])?$_params['CardInfo']['CVVCode']:'',
                    'CurrencyCode' => $_params['currency'],
                    'CustomField' => 1,
                    'CustomerID' => $_params['customer_id'],
                    //'CustomerID' => '4404792',
                    'CustomerIP' => $this->CommonService->getIp(),
                    'EnableTokenCheckout' => false,
                    'ExchangeRate' => $_params['rate'],
                    'HandlingTotal' => isset($_param_cartinfo['handling_total'])?$_param_cartinfo['handling_total']:0,
                    'Items' => array(
                        'Goods' => isset($_param_cartinfo['item'])?$_param_cartinfo['item']:array()
                    ),
                    'ItemsTotal' => isset($_param_cartinfo['items_totals'])?$_param_cartinfo['items_totals']:0,
                    //'Notes' => '',
                    'OrderNumber' => isset($_params['order_number'])?$_params['order_number']:'',
                    'OrderTotal' => isset($_param_cartinfo['order_total'])?$_param_cartinfo['order_total']:0,
                    //'OrderType' => 0,
                    'PaymentAction' => 'Purchase',
                    'ShippingAddress' => array(
                        'City' => isset($_param_shipping_info['City'])?$_param_shipping_info['City']:'',
                        'CountryCode' => isset($_param_shipping_info['CountryCode'])?$_param_shipping_info['CountryCode']:'',
                        'Email' => isset($_param_shipping_info['Email'])?$_param_shipping_info['Email']:'',
                        'FirstName' => isset($_param_shipping_info['FirstName'])?$_param_shipping_info['FirstName']:'',
                        'LastName' => isset($_param_shipping_info['LastName'])?$_param_shipping_info['LastName']:'',
                        'Mobile' => isset($_param_shipping_info['Mobile'])?$_param_shipping_info['Mobile']:'',
                        'Phone' => isset($_param_shipping_info['Phone'])?$_param_shipping_info['Phone']:'',
                        'PostalCode' => isset($_param_shipping_info['PostalCode'])?$_param_shipping_info['PostalCode']:'',
                        'State' => isset($_param_shipping_info['State'])?$_param_shipping_info['State']:'',
                        //'State' => 'AF',
                        'Street1' => isset($_param_shipping_info['Street1'])?$_param_shipping_info['Street1']:'',
                        'Street2' => isset($_param_shipping_info['Street2'])?$_param_shipping_info['Street2']:'',
                    ),
                    'ShippingMethod' => isset($_param_cartinfo['shiping_model'])?$_param_cartinfo['shiping_model']:'',
                    'ShippingTotal' => isset($_param_cartinfo['shipping_total'])?$_param_cartinfo['shipping_total']:0,
                    //'UCI' => ''
                )
            )
        );
        $_result = $this->CommonService->dxSoap('DoCreditCardTokenCheckout',$_post_data);
        $_return_data['OrderTotal'] = sprintf("%.2f", $_param_cartinfo['order_total']);
        $_return_data['PayerEmail'] = isset($_param_shipping_info['Email'])?$_param_shipping_info['Email']:'';
        $_return_data['Items'] = isset($_param_cartinfo['item'])?$_param_cartinfo['item']:array();
        $_return_data['result'] = $_result;
        Log::record('doCreditCardTokenCheckout'.json_encode($_post_data));
        $order_master_number = isset($_params['order_number'])?$_params['order_number']:'';
        Monlog::write(LOGS_MALL_CART,'info',__METHOD__,'submitOrder'.$order_master_number,$_post_data,MALL_API.'/home/order/submitOrder/dxSoap_DoCreditCardTokenCheckout_res', $_result);
        return $_return_data;
    }

    /**
     * WebMoney支付
     */
    public function setWebMoneyCheckout($_params){
        $_param_cartinfo = $this->CommonService->calPayInfo($_params);
        $_param_shipping_info = $_param_cartinfo['shiping_address'];
        $_post_data = array(
            'SetWebMoneyCheckout' => array(
                'request' => array(
                    'CancelUrl' => MALL_DOCUMENT.'/home/order/cancel/',
                    'CurrencyCode' => $_params['currency'],
                    //'CurrencyType' => 1,//
                    'CustomField' => 1,
                    'CustomerID' => $_params['customer_id'],
                    'CustomerIP' => $this->CommonService->getIp(),
                    'DiscountTotal' => isset($_param_cartinfo['discount_total'])?$_param_cartinfo['discount_total']:0,//优惠总额
                    'HandlingTotal' => isset($_param_cartinfo['handling_total'])?$_param_cartinfo['handling_total']:0,
                    'Items' => array(
                        'Goods' => isset($_param_cartinfo['item'])?$_param_cartinfo['item']:array()
                    ),
                    'ItemsTotal' => isset($_param_cartinfo['items_totals'])?$_param_cartinfo['items_totals']:0,
                    'NotificationUrl' => '',
                    'OrderNumber' => $_params['order_number'],
                    'OrderTotal' => isset($_param_cartinfo['order_total'])?$_param_cartinfo['order_total']:0,
                    //'OrderType' => 0,
                    'PaymentAction' => 'Purchase',
                    'ReturnUrl' => MALL_DOCUMENT.'/home/order/WebMoneyReturnUrl/',
                    'ShippingAddress' => array(
                        'City' => isset($_param_shipping_info['City'])?$_param_shipping_info['City']:'',
                        'CountryCode' => isset($_param_shipping_info['CountryCode'])?$_param_shipping_info['CountryCode']:'',
                        'Email' => isset($_param_shipping_info['Email'])?$_param_shipping_info['Email']:'',
                        'FirstName' => isset($_param_shipping_info['FirstName'])?$_param_shipping_info['FirstName']:'',
                        'LastName' => isset($_param_shipping_info['LastName'])?$_param_shipping_info['LastName']:'',
                        'Mobile' => isset($_param_shipping_info['Mobile'])?$_param_shipping_info['Mobile']:'',
                        'Phone' => isset($_param_shipping_info['Phone'])?$_param_shipping_info['Phone']:'',
                        'PostalCode' => isset($_param_shipping_info['PostalCode'])?$_param_shipping_info['PostalCode']:'',
                        'State' => isset($_param_shipping_info['State'])?$_param_shipping_info['State']:'',
                        'Street1' => isset($_param_shipping_info['Street1'])?$_param_shipping_info['Street1']:'',
                        'Street2' => isset($_param_shipping_info['Street2'])?$_param_shipping_info['Street2']:'',
                    ),
                    'ShippingMethod' => isset($_param_cartinfo['shiping_model'])?$_param_cartinfo['shiping_model']:'',
                    'ShippingTotal' => isset($_param_cartinfo['shipping_total'])?$_param_cartinfo['shipping_total']:0,
                )
            )
        );
        $_result = $this->CommonService->dxSoap('SetWebMoneyCheckout',$_post_data);
        $_return_data['OrderTotal'] = sprintf("%.2f", $_param_cartinfo['order_total']);
        $_return_data['PayerEmail'] = isset($_param_shipping_info['Email'])?$_param_shipping_info['Email']:'';
        $_return_data['Items'] = isset($_param_cartinfo['item'])?$_param_cartinfo['item']:array();
        $_return_data['result'] = $_result;
        Log::record('setWebMoneyCheckout'.json_encode($_post_data));
        $order_master_number = isset($_params['order_number'])?$_params['order_number']:'';
        Monlog::write(LOGS_MALL_CART,'info',__METHOD__,'submitOrder'.$order_master_number,$_post_data,MALL_API.'/home/order/submitOrder/dxSoap_SetWebMoneyCheckout_res', $_result);
        return $_return_data;
    }


    /**
     * 聚宝支付(不带token)
     */
    public function doGlobebillCreditCardCheckout($_params){
        $_param_cartinfo = $this->CommonService->calPayInfo($_params);
        $_param_shipping_info = $_param_cartinfo['shiping_address'];
        $_post_data = array(
            'DoGlobebillCreditCardCheckout' => array(
                'request' => array(
                    'CurrencyCode' => $_params['currency'],
                    'CustomField' => 1,
                    'CustomerID' => $_params['customer_id'],
                    'CustomerIP' => $this->CommonService->getIp(),
                    'EnableTokenCheckout' => $_params['save_card'],
                    'ExchangeRate' => $_params['rate'],
                    'HandlingTotal' => isset($_param_cartinfo['handling_total'])?$_param_cartinfo['handling_total']:0,
                    'Items' => array(
                        'Goods' => isset($_param_cartinfo['item'])?$_param_cartinfo['item']:array()
                    ),
                    'Notes' => '',
                    'NotificationUrl' => '',
                    'OrderNumber' => isset($_params['order_number'])?$_params['order_number']:'',
                    'ItemsTotal' => isset($_param_cartinfo['items_totals'])?$_param_cartinfo['items_totals']:0,
                    'OrderTotal' => isset($_param_cartinfo['order_total'])?$_param_cartinfo['order_total']:0,
                    //'OrderType' => '1',
                    'PaymentAction' => 'Purchase',
                    'CardInfo' => array(
                        'CVVCode' => isset($_params['CardInfo']['CVVCode'])?$_params['CardInfo']['CVVCode']:'',
                        'CardHolder' => isset($_params['CardInfo']['CardHolder'])?$_params['CardInfo']['CardHolder']:'',
                        'CardNumber' => isset($_params['CardInfo']['CardNumber'])?$_params['CardInfo']['CardNumber']:'',
                        'ExpireMonth' => isset($_params['CardInfo']['ExpireMonth'])?$_params['CardInfo']['ExpireMonth']:'',
                        'ExpireYear' => isset($_params['CardInfo']['ExpireYear'])?$_params['CardInfo']['ExpireYear']:'',
                        'IssuingBank' => isset($_params['CardInfo']['IssuingBank'])?$_params['CardInfo']['IssuingBank']:'',
                    ),
                    'BillingAddress' => array(
                        'City' => isset($_params['BillingAddress']['City'])?$_params['BillingAddress']['City']:'',
                        'CountryCode' => isset($_params['BillingAddress']['CountryCode'])?$_params['BillingAddress']['CountryCode']:'',
                        'Email' => isset($_params['BillingAddress']['Email'])?$_params['BillingAddress']['Email']:'',
                        'FirstName' => isset($_params['BillingAddress']['FirstName'])?$_params['BillingAddress']['FirstName']:'',
                        'LastName' => isset($_params['BillingAddress']['LastName'])?$_params['BillingAddress']['LastName']:'',
                        'Mobile' => isset($_params['BillingAddress']['Mobile'])?$_params['BillingAddress']['Mobile']:'',
                        'Phone' => isset($_params['BillingAddress']['Phone'])?$_params['BillingAddress']['Phone']:'',
                        'PostalCode' => isset($_params['BillingAddress']['PostalCode'])?$_params['BillingAddress']['PostalCode']:'',
                        'State' => isset($_params['BillingAddress']['State'])?$_params['BillingAddress']['State']:'',
                        'Street1' => isset($_params['BillingAddress']['Street1'])?$_params['BillingAddress']['Street1']:'',
                        'Street2' => isset($_params['BillingAddress']['Street2'])?$_params['BillingAddress']['Street2']:'',
                    ),
                    'ShippingAddress' => array(
                        'City' => isset($_param_shipping_info['City'])?$_param_shipping_info['City']:'',
                        'CountryCode' => isset($_param_shipping_info['CountryCode'])?$_param_shipping_info['CountryCode']:'',
                        'Email' => isset($_param_shipping_info['Email'])?$_param_shipping_info['Email']:'',
                        'FirstName' => isset($_param_shipping_info['FirstName'])?$_param_shipping_info['FirstName']:'',
                        'LastName' => isset($_param_shipping_info['LastName'])?$_param_shipping_info['LastName']:'',
                        'Mobile' => isset($_param_shipping_info['Mobile'])?$_param_shipping_info['Mobile']:'',
                        'Phone' => isset($_param_shipping_info['Phone'])?$_param_shipping_info['Phone']:'',
                        'PostalCode' => isset($_param_shipping_info['PostalCode'])?$_param_shipping_info['PostalCode']:'',
                        'State' => isset($_param_shipping_info['State'])?$_param_shipping_info['State']:'',
                        'Street1' => isset($_param_shipping_info['Street1'])?$_param_shipping_info['Street1']:'',
                        'Street2' => isset($_param_shipping_info['Street2'])?$_param_shipping_info['Street2']:'',
                    ),
                    'ShippingMethod' => isset($_param_cartinfo['shiping_model'])?$_param_cartinfo['shiping_model']:'',
                    'ShippingTotal' => isset($_param_cartinfo['shipping_total'])?$_param_cartinfo['shipping_total']:0,
                    'UCI' => '',
                )
            )
        );

        $_result = $this->CommonService->dxSoap('DoGlobebillCreditCardCheckout',$_post_data);
        $_return_data['OrderTotal'] = sprintf("%.2f", $_param_cartinfo['order_total']);
        $_return_data['PayerEmail'] = isset($_param_shipping_info['Email'])?$_param_shipping_info['Email']:'';
        $_return_data['Items'] = isset($_param_cartinfo['item'])?$_param_cartinfo['item']:array();
        $_return_data['result'] = $_result;
        Log::record('doGlobebillCreditCardCheckout'.json_encode($_post_data));
        $order_master_number = isset($_params['order_number'])?$_params['order_number']:'';
        Monlog::write(LOGS_MALL_CART,'info',__METHOD__,'submitOrder'.$order_master_number,$_post_data,MALL_API.'/home/order/submitOrder/dxSoap_DoGlobebillCreditCardCheckout_res', $_result);
        return $_return_data;
    }

    /**
     * 聚宝支付(带token)
     */
    public function doGlobebillCreditCardTokenCheckout($_params){
        $_param_cartinfo = $this->CommonService->calPayInfo($_params);
        $_param_shipping_info = $_param_cartinfo['shiping_address'];
        $_post_data = array(
            'DoGlobebillCreditCardTokenCheckout' => array(
                'request' => array(
                    'CreditCardTokenID' =>  isset($_params['credit_card_token_id'])?$_params['credit_card_token_id']:'',//tokenID
                    'CVVCode' => isset($_params['CVVCode'])?$_params['CVVCode']:'',
                    'CurrencyCode' => $_params['currency'],
                    'CustomField' => 1,
                    'CustomerID' => $_params['customer_id'],
                    //'CustomerID' => '4404792',
                    'CustomerIP' => $this->CommonService->getIp(),
                    'ExchangeRate' => $_params['rate'],
                    'HandlingTotal' => isset($_param_cartinfo['handling_total'])?$_param_cartinfo['handling_total']:0,
                    'Items' => array(
                        'Goods' => isset($_param_cartinfo['item'])?$_param_cartinfo['item']:array()
                    ),
                    'NotificationUrl' => '',
                    'OrderNumber' => isset($_params['order_number'])?$_params['order_number']:'',
                    'ItemsTotal' => isset($_param_cartinfo['items_totals'])?$_param_cartinfo['items_totals']:0,
                    'OrderTotal' => isset($_param_cartinfo['order_total'])?$_param_cartinfo['order_total']:0,
                    //'OrderType' => 0,
                    'PaymentAction' => 'Purchase',

                    'BillingAddress' => array(
                        'City' => isset($_params['BillingAddress']['City'])?$_params['BillingAddress']['City']:'',
                        'CountryCode' => isset($_params['BillingAddress']['CountryCode'])?$_params['BillingAddress']['CountryCode']:'',
                        'Email' => isset($_params['BillingAddress']['Email'])?$_params['BillingAddress']['Email']:'',
                        'FirstName' => isset($_params['BillingAddress']['FirstName'])?$_params['BillingAddress']['FirstName']:'',
                        'LastName' => isset($_params['BillingAddress']['LastName'])?$_params['BillingAddress']['LastName']:'',
                        'Mobile' => isset($_params['BillingAddress']['Mobile'])?$_params['BillingAddress']['Mobile']:'',
                        'Phone' => isset($_params['BillingAddress']['Phone'])?$_params['BillingAddress']['Phone']:'',
                        'PostalCode' => isset($_params['BillingAddress']['PostalCode'])?$_params['BillingAddress']['PostalCode']:'',
                        'State' => isset($_params['BillingAddress']['State'])?$_params['BillingAddress']['State']:'',
                        'Street1' => isset($_params['BillingAddress']['Street1'])?$_params['BillingAddress']['Street1']:'',
                        'Street2' => isset($_params['BillingAddress']['Street2'])?$_params['BillingAddress']['Street2']:'',
                    ),
                    'ShippingAddress' => array(
                        'City' => isset($_param_shipping_info['City'])?$_param_shipping_info['City']:'',
                        'CountryCode' => isset($_param_shipping_info['CountryCode'])?$_param_shipping_info['CountryCode']:'',
                        'Email' => isset($_param_shipping_info['Email'])?$_param_shipping_info['Email']:'',
                        'FirstName' => isset($_param_shipping_info['FirstName'])?$_param_shipping_info['FirstName']:'',
                        'LastName' => isset($_param_shipping_info['LastName'])?$_param_shipping_info['LastName']:'',
                        'Mobile' => isset($_param_shipping_info['Mobile'])?$_param_shipping_info['Mobile']:'',
                        'Phone' => isset($_param_shipping_info['Phone'])?$_param_shipping_info['Phone']:'',
                        'PostalCode' => isset($_param_shipping_info['PostalCode'])?$_param_shipping_info['PostalCode']:'',
                        'State' => isset($_param_shipping_info['State'])?$_param_shipping_info['State']:'',
                        'Street1' => isset($_param_shipping_info['Street1'])?$_param_shipping_info['Street1']:'',
                        'Street2' => isset($_param_shipping_info['Street2'])?$_param_shipping_info['Street2']:'',
                    ),
                    'ShippingMethod' => isset($_param_cartinfo['shiping_model'])?$_param_cartinfo['shiping_model']:'',
                    'ShippingTotal' => isset($_param_cartinfo['shipping_total'])?$_param_cartinfo['shipping_total']:0,
                )
            )
        );
        $_result = $this->CommonService->dxSoap('DoGlobebillCreditCardTokenCheckout',$_post_data);
        $_return_data['OrderTotal'] = sprintf("%.2f", $_param_cartinfo['order_total']);
        $_return_data['PayerEmail'] = isset($_param_shipping_info['Email'])?$_param_shipping_info['Email']:'';
        $_return_data['Items'] = isset($_param_cartinfo['item'])?$_param_cartinfo['item']:array();
        $_return_data['result'] = $_result;
        Log::record('doGlobebillCreditCardTokenCheckout'.json_encode($_post_data));
        $order_master_number = isset($_params['order_number'])?$_params['order_number']:'';
        Monlog::write(LOGS_MALL_CART,'info',__METHOD__,'submitOrder'.$order_master_number,$_post_data,MALL_API.'/home/order/submitOrder/dxSoap_DoGlobebillCreditCardTokenCheckout_res', $_result);
        return $_return_data;
    }

    /**
     * 处理Astropay交易信息 巴西里尔，墨西哥，啊根廷
     */
    public function SetAstropayCheckout($_params){
        $_param_cartinfo = $this->CommonService->calPayInfo($_params);
        $_param_shipping_info = $_param_cartinfo['shiping_address'];
        $_post_data = array(
            'SetAstropayCheckout' => array(
                'request' => array(
                    'CPF' => $_params['cpf'],
                    'CardBank' => $_params['card_bank'],
                    'ClientRequestRecordID' => 0,
                    'CurrencyCode' => $_params['currency'],
                    //'CurrencyType' => 1,
                    'CustomField' => 1,
                    'CustomerID' => $_params['customer_id'],
                    'CustomerIP' => $this->CommonService->getIp(),
                    'ExchangeRate' => $_params['rate'],
                    'HandlingTotal' => isset($_param_cartinfo['handling_total'])?$_param_cartinfo['handling_total']:0,//手续价总额
                    'Items' => array(
                        'Goods' => isset($_param_cartinfo['item'])?$_param_cartinfo['item']:array()
                    ),
                    'ItemsTotal' => isset($_param_cartinfo['items_totals'])?$_param_cartinfo['items_totals']:0,//订单的商品总额
                    //'NotificationUrl' => MALL_DOCUMENT.'/home/order/astropayReturn/',
                    'NotificationUrl' => '',
                    'OrderNumber' => isset($_params['order_number'])?$_params['order_number']:'',
                    'OrderTotal' => isset($_param_cartinfo['order_total'])?$_param_cartinfo['order_total']:0,//订单总额（包括运费等）
                    //'OrderType' => 0,
                    'PaymentAction' => 'Purchase',
                    'PaymentMethod' => $_params['payment_method'],//
                    'ShippingAddress' => array(
                        'City' => isset($_param_shipping_info['City'])?$_param_shipping_info['City']:'',
                        'CountryCode' => isset($_param_shipping_info['CountryCode'])?$_param_shipping_info['CountryCode']:'',
                        'Email' => isset($_param_shipping_info['Email'])?$_param_shipping_info['Email']:'',
                        'FirstName' => isset($_param_shipping_info['FirstName'])?$_param_shipping_info['FirstName']:'',
                        'LastName' => isset($_param_shipping_info['LastName'])?$_param_shipping_info['LastName']:'',
                        'Mobile' => isset($_param_shipping_info['Mobile'])?$_param_shipping_info['Mobile']:'',
                        'Phone' => isset($_param_shipping_info['Phone'])?$_param_shipping_info['Phone']:'',
                        'PostalCode' => isset($_param_shipping_info['PostalCode'])?$_param_shipping_info['PostalCode']:'',
                        'State' => isset($_param_shipping_info['State'])?$_param_shipping_info['State']:'',
                        'Street1' => isset($_param_shipping_info['Street1'])?$_param_shipping_info['Street1']:'',
                        'Street2' => isset($_param_shipping_info['Street2'])?$_param_shipping_info['Street2']:'',
                    ),
                    'ShippingMethod' => isset($_param_cartinfo['shiping_model'])?$_param_cartinfo['shiping_model']:'',
                    'ShippingTotal' => isset($_param_cartinfo['shipping_total'])?$_param_cartinfo['shipping_total']:0,
                )
            )
        );
        $_result = $this->CommonService->dxSoap('SetAstropayCheckout',$_post_data);
        Log::record('SetAstropayCheckout'.json_encode($_post_data));
        $order_master_number = isset($_params['order_number'])?$_params['order_number']:'';
        Monlog::write(LOGS_MALL_CART,'info',__METHOD__,'submitOrder'.$order_master_number,$_post_data,MALL_API.'/home/order/submitOrder/dxSoap_SetAstropayCheckout_res', $_result);

        $_return_data['OrderTotal'] = sprintf("%.2f", $_param_cartinfo['order_total']);
        $_return_data['PayerEmail'] = isset($_param_shipping_info['Email'])?$_param_shipping_info['Email']:'';
        $_return_data['Items'] = isset($_param_cartinfo['item'])?$_param_cartinfo['item']:array();
        $_return_data['result'] = $_result;
        return $_return_data;
    }

    /**
     * paypal快捷支付的DO阶段
     * $_params参数是submitOrder返回来的
     * @param $_params
     * @return \Exception|mixed
     */
    public function QuickDoExpressCheckout($_params){
        $_param_cartinfo = $this->CommonService->calPayInfo($_params);
        $_param_shipping_info = $_param_cartinfo['shiping_address'];
        $_post_data = array(
            'DoExpressCheckout' => array('request' => array(
                'CancelUrl' => MALL_DOCUMENT.'/home/order/cancel/',
                'CurrencyCode' => $_params['currency'],
                'CustomField' => 1,//购物车ID
                'CustomerCountryCode' => $_params['country'],//
                'CountryCode' => $_params['country'],//
                'CustomerID' => $_params['customer_id'],
                'CustomerIP' => $this->CommonService->getIp(),
                'DiscountTotal' => isset($_param_cartinfo['discount_total'])?$_param_cartinfo['discount_total']:0,//优惠总额
                'ExchangeRate' => $_params['rate'],
                'HandlingTotal' => isset($_param_cartinfo['handling_total'])?$_param_cartinfo['handling_total']:0,//手续价总额
                'Items' => array(
                    'Goods' => isset($_param_cartinfo['item'])?$_param_cartinfo['item']:array()
                ),
                'ItemsTotal' => isset($_param_cartinfo['items_totals'])?$_param_cartinfo['items_totals']:0,//订单的商品总额
                'LocaleCode' => isset(config("LocalCode")[$_params['lang']])?config("LocalCode")[$_params['lang']]:'en_US',//一个语种对应的种localeCode
                //'LogoUrl' => '',
                'NotificationUrl' => '',//这个通知的Url是干什么用的
                'OrderTotal' => isset($_param_cartinfo['order_total'])?$_param_cartinfo['order_total']:0,//订单总额（包括运费等）
                //'OrderType' => 0,
                'PaymentAction' => 'Purchase',//Purchase
                'ReturnUrl' => MALL_DOCUMENT.'home/payment/getExpressCheckout/',//这个returnUrl又是干嘛用的
                'ShippingAddress' => array(
                    'City' => isset($_param_shipping_info['City'])?$_param_shipping_info['City']:'',
                    'CityCode' => isset($_param_shipping_info['CityCode'])?$_param_shipping_info['CityCode']:'',
                    'Country' => isset($_param_shipping_info['Country'])?$_param_shipping_info['Country']:'',
                    'CountryCode' => isset($_param_shipping_info['CountryCode'])?$_param_shipping_info['CountryCode']:'',
                    'Email' => isset($_param_shipping_info['Email'])?$_param_shipping_info['Email']:'',
                    'FirstName' => isset($_param_shipping_info['FirstName'])?$_param_shipping_info['FirstName']:'',
                    'LastName' => isset($_param_shipping_info['LastName'])?$_param_shipping_info['LastName']:'',
                    'Mobile' => isset($_param_shipping_info['Mobile'])?$_param_shipping_info['Mobile']:'',
                    'Phone' => isset($_param_shipping_info['Phone'])?$_param_shipping_info['Phone']:'',
                    'PostalCode' => isset($_param_shipping_info['PostalCode'])?$_param_shipping_info['PostalCode']:'',
                    'State' => isset($_param_shipping_info['State'])?$_param_shipping_info['State']:'',
                    'StateCode' => isset($_param_shipping_info['StateCode'])?$_param_shipping_info['StateCode']:'',
                    'Street1' => isset($_param_shipping_info['Street1'])?$_param_shipping_info['Street1']:'',
                    'Street2' => isset($_param_shipping_info['Street2'])?$_param_shipping_info['Street2']:'',
                ),
                'ShippingMethod' => isset($_param_cartinfo['shiping_model'])?$_param_cartinfo['shiping_model']:'',
                'ShippingTotal' => isset($_param_cartinfo['shipping_total'])?$_param_cartinfo['shipping_total']:0,
                'OrderNumber' => $_params['order_number'],
                'QueryString' => urldecode($_params['querystring']),
                'UCI' => ''
            ))
        );
        $_result = $this->CommonService->dxSoap('DoExpressCheckout',$_post_data);
        Log::record('QuickPaypalDoExpressCheckout-params:'.json_encode($_post_data));
        Log::record('QuickPaypalDoExpressCheckout-result:'.json_encode($_result));
        $order_master_number = isset($_params['order_number'])?$_params['order_number']:'';
        Monlog::write(LOGS_MALL_CART,'info',__METHOD__,'submitOrder'.$order_master_number,$_post_data,MALL_API.'/home/order/submitOrder/dxSoap_DoExpressCheckout_res', $_result);
        return $_result;//保存返回结果里的TransactionResponseInfo->TransactionID

    }

    /**
     * Ideal支付，创建token
     */
    public function setIDealTokenCheckout($_params){
        $_param_cartinfo = $this->CommonService->calPayInfo($_params);
        $_param_shipping_info = $_param_cartinfo['shiping_address'];

        $_post_data = array(
            'SetIDealTokenCheckout' => array(
                'request' => array(
                    'ClientRequestRecordID' => '',
                    'CurrencyCode' => $_params['currency'],
                    //'CurrencyType' => 1,
                    'CustomField' => 1,
                    'CustomerID' => $_params['customer_id'],
                    'CustomerIP' => $this->CommonService->getIp(),
                    'HandlingTotal' => isset($_param_cartinfo['handling_total'])?$_param_cartinfo['handling_total']:0,//手续价总额
                    'Items' => array(
                        'Goods' => isset($_param_cartinfo['item'])?$_param_cartinfo['item']:array()
                    ),
                    'ItemsTotal' => isset($_param_cartinfo['items_totals'])?$_param_cartinfo['items_totals']:0,//订单的商品总额
                    'NotificationUrl' => '',
                    'OrderNumber' => isset($_params['order_number'])?$_params['order_number']:'',
                    'OrderTotal' => isset($_param_cartinfo['order_total'])?$_param_cartinfo['order_total']:0,//订单总额（包括运费等）
                    //'OrderType' => 0,
                    'PaymentAction' => 'Purchase',
                    'ShippingAddress' => array(
                        'City' => isset($_param_shipping_info['City'])?$_param_shipping_info['City']:'',
                        'CountryCode' => isset($_param_shipping_info['CountryCode'])?$_param_shipping_info['CountryCode']:'',
                        'Email' => isset($_param_shipping_info['Email'])?$_param_shipping_info['Email']:'',
                        'FirstName' => isset($_param_shipping_info['FirstName'])?$_param_shipping_info['FirstName']:'',
                        'LastName' => isset($_param_shipping_info['LastName'])?$_param_shipping_info['LastName']:'',
                        'Mobile' => isset($_param_shipping_info['Mobile'])?$_param_shipping_info['Mobile']:'',
                        'Phone' => isset($_param_shipping_info['Phone'])?$_param_shipping_info['Phone']:'',
                        'PostalCode' => isset($_param_shipping_info['PostalCode'])?$_param_shipping_info['PostalCode']:'',
                        //'State' => isset($_param_shipping_info['State'])?$_param_shipping_info['State']:'',
                        'Street1' => isset($_param_shipping_info['Street1'])?$_param_shipping_info['Street1']:'',
                        'Street2' => isset($_param_shipping_info['Street2'])?$_param_shipping_info['Street2']:'',
                    ),
                    'ShippingMethod' => isset($_param_cartinfo['shiping_model'])?$_param_cartinfo['shiping_model']:'',
                    'ShippingTotal' => isset($_param_cartinfo['shipping_total'])?$_param_cartinfo['shipping_total']:0,
                )
            )
        );

        $_result = $this->CommonService->dxSoap('setIDealTokenCheckout',$_post_data);
        Log::record('setIDealTokenCheckout'.json_encode($_post_data));
        $order_master_number = isset($_params['order_number'])?$_params['order_number']:'';
        Monlog::write(LOGS_MALL_CART,'info',__METHOD__,'submitOrder'.$order_master_number,$_post_data,MALL_API.'/home/order/submitOrder/dxSoap_setIDealTokenCheckout_res', $_result);

        $_return_data['OrderTotal'] = sprintf("%.2f", $_param_cartinfo['order_total']);
        $_return_data['PayerEmail'] = isset($_param_shipping_info['Email'])?$_param_shipping_info['Email']:'';
        $_return_data['Items'] = isset($_param_cartinfo['item'])?$_param_cartinfo['item']:array();
        $_return_data['result'] = $_result;
        //组织返回的数据
        if($_result->SetIDealTokenCheckoutResult->ResponseResult == 'Success'){
            $_value = isset($_param_cartinfo['order_total'])?$_param_cartinfo['order_total']:0;
            $_return_data['code'] = 1;
            $_return_data['data'] = array(
                'customerEmail' => isset($_param_shipping_info['Email'])?$_param_shipping_info['Email']:'1219928893@qq.com',
                'customerName' => 'gongzhi',
                'logoUrl' => 'https://e.dx.com/Pattaya/publicImg/logo-dx.png',
                'publicKey' => $_result->SetIDealTokenCheckoutResult->PublicKey,
                'token' => $_result->SetIDealTokenCheckoutResult->Token,
                'value' => $_value*100,
            );
        }else{
            $_return_data['code'] = 0;
        }
        return $_return_data;
    }

}