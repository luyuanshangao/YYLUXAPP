<?php
namespace app\app\services;
use think\Log;
use think\Monlog;
use app\common\services\logService;

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
                $Tmp['Amount'] = sprintf("%.2f", $v['order']['grand_total']);
                $Params['Children'][] = $Tmp;
            }
        }else if ($from_flag == 2){ //repay
            $Params['OrderNumber'] = $_order_info['master']['order_number'];
            foreach ($_order_info['slave'] as $k=>$v){
                $Tmp['OrderNumber'] = $v['order_number'];
                $Tmp['Amount'] = sprintf("%.2f", $v['grand_total']);
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
                        'City' => isset($_param_shipping_info['City'])?trim($_param_shipping_info['City']):'',
                        'CountryCode' => isset($_param_shipping_info['CountryCode'])?trim($_param_shipping_info['CountryCode']):'',
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
                'CancelUrl' => MALL_DOCUMENT.'/home/order/cancel?from=2',
                'CurrencyCode' => trim($_params['currency']),
                'CustomField' => 1,//购物车ID
                'CustomerCountryCode' => trim($_params['country']),//
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
                    'City' => isset($_param_shipping_info['City'])?trim($_param_shipping_info['City']):'',
                    'CountryCode' => isset($_param_shipping_info['CountryCode'])?trim($_param_shipping_info['CountryCode']):'',
                    'Email' => isset($_param_shipping_info['Email'])?$_param_shipping_info['Email']:'',
                    'FirstName' => isset($_param_shipping_info['FirstName'])?$_param_shipping_info['FirstName']:'',
                    'LastName' => isset($_param_shipping_info['LastName'])?$_param_shipping_info['LastName']:'',
                    'Mobile' => isset($_param_shipping_info['Mobile'])?$_param_shipping_info['Mobile']:'',
                    'Phone' => isset($_param_shipping_info['Phone'])?$_param_shipping_info['Phone']:'',
                    'PostalCode' => isset($_param_shipping_info['PostalCode'])?trim($_param_shipping_info['PostalCode']):'',
                    'State' => isset($_param_shipping_info['StateCode'])?trim($_param_shipping_info['StateCode']):'',
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
                        'City' => isset($_params['BillingAddress']['City'])?trim($_params['BillingAddress']['City']):'',
                        'CountryCode' => isset($_params['BillingAddress']['CountryCode'])?trim($_params['BillingAddress']['CountryCode']):'',
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
                        'City' => isset($_param_shipping_info['City'])?trim($_param_shipping_info['City']):'',
                        'CountryCode' => isset($_param_shipping_info['CountryCode'])?trim($_param_shipping_info['CountryCode']):'',
//						'CountryCode' => 'AT',
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
                        'City' => isset($_param_shipping_info['City'])?trim($_param_shipping_info['City']):'',
                        'CountryCode' => isset($_param_shipping_info['CountryCode'])?trim($_param_shipping_info['CountryCode']):'',
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
                    'CancelUrl' => MALL_DOCUMENT.'/home/order/cancel?from=2',
                    'CurrencyCode' => trim($_params['currency']),
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
                        'City' => isset($_param_shipping_info['City'])?trim($_param_shipping_info['City']):'',
                        'CountryCode' => isset($_param_shipping_info['CountryCode'])?trim($_param_shipping_info['CountryCode']):'',
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
                        'City' => isset($_params['BillingAddress']['City'])?trim($_params['BillingAddress']['City']):'',
                        'CountryCode' => isset($_params['BillingAddress']['CountryCode'])?trim($_params['BillingAddress']['CountryCode']):'',
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
                        'City' => isset($_param_shipping_info['City'])?trim($_param_shipping_info['City']):'',
                        'CountryCode' => isset($_param_shipping_info['CountryCode'])?trim($_param_shipping_info['CountryCode']):'',
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
                        'City' => isset($_params['BillingAddress']['City'])?trim($_params['BillingAddress']['City']):'',
                        'CountryCode' => isset($_params['BillingAddress']['CountryCode'])?trim($_params['BillingAddress']['CountryCode']):'',
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
                        'City' => isset($_param_shipping_info['City'])?trim($_param_shipping_info['City']):'',
                        'CountryCode' => isset($_param_shipping_info['CountryCode'])?trim($_param_shipping_info['CountryCode']):'',
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
                        'City' => isset($_param_shipping_info['City'])?trim($_param_shipping_info['City']):'',
                        'CountryCode' => isset($_param_shipping_info['CountryCode'])?trim($_param_shipping_info['CountryCode']):'',
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
                'CancelUrl' => MALL_DOCUMENT.'/home/order/cancel?from=2',
                'CurrencyCode' => trim($_params['currency']),
                'CustomField' => 1,//购物车ID
                'CustomerCountryCode' => trim($_params['country']),//
                'CountryCode' => trim($_params['country']),//
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
                    'City' => isset($_param_shipping_info['City'])?trim($_param_shipping_info['City']):'',
                    'CityCode' => isset($_param_shipping_info['CityCode'])?trim($_param_shipping_info['CityCode']):'',
                    'Country' => isset($_param_shipping_info['Country'])?trim($_param_shipping_info['Country']):'',
                    'CountryCode' => isset($_param_shipping_info['CountryCode'])?trim($_param_shipping_info['CountryCode']):'',
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
                        'City' => isset($_param_shipping_info['City'])?trim($_param_shipping_info['City']):'',
                        'CountryCode' => isset($_param_shipping_info['CountryCode'])?trim($_param_shipping_info['CountryCode']):'',
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

    /**
     * 公用支付
     * @param $_params
     * @return mixed
     */
    public function payCommon($_params){
        $_param_cartinfo = $this->CommonService->calPayInfo($_params);
        $_param_shipping_info = $_param_cartinfo['shiping_address'];
        $order_master_number = isset($_params['order_number'])?$_params['order_number']:'';
        $log_key = 'submitOrder'.$order_master_number;
        $_payment_method = !empty($_params['payment_method'])?$_params['payment_method']:'';
        $_order_total_usd = $_param_cartinfo['order_total_usd'];
        $_post_data = array(
            "TransactionChannel" => $_params['transaction_channel'],
            "TransactionSouce" => TRANSACTION_SOUCE_DX_APP,
            "TransactionType" => $_params['transaction_type'],
            'CurrencyCode' => $_params['currency'],
            "CurrencyType" => $_params['currency_type'],
            'ExchangeRate' => $_params['rate'],
            'OrderMasterNumber' => isset($_params['order_number']) ? $_params['order_number'] : '',
            'Amount' => isset($_param_cartinfo['order_total']) ? $_param_cartinfo['order_total'] : 0,//订单总额（包括运费等）
            'CustomerId' => $_params['customer_id'],
            'CustomerEmail' => $_params['email'],
            'CustomerIp' => $this->CommonService->getIp(),
            "ChildOrderList" => $_params['ChildOrderList'],
            "ChildOrderPrice" => $_params['ChildOrderPrice'], //{"180410011171034422":120.23}
            'Items' =>  isset($_param_cartinfo['item']) ? $_param_cartinfo['item'] : array(),
            'ShippingAddress' => array(
                'Country' => isset($_param_shipping_info['Country']) ? trim($_param_shipping_info['Country']):'',
                'City' => isset($_param_shipping_info['City']) ? trim($_param_shipping_info['City']) : '',
                'CountryCode' => isset($_param_shipping_info['CountryCode']) ? trim($_param_shipping_info['CountryCode']) : '',
                'Email' => isset($_param_shipping_info['Email']) ? $_param_shipping_info['Email'] : '',
                'FirstName' => isset($_param_shipping_info['FirstName']) ? $_param_shipping_info['FirstName'] : '',
                'LastName' => isset($_param_shipping_info['LastName']) ? $_param_shipping_info['LastName'] : '',
                'PhoneNumber' => isset($_param_shipping_info['Mobile']) ? $_param_shipping_info['Mobile'] : '',
                'ZipPostal' => isset($_param_shipping_info['PostalCode']) ? $_param_shipping_info['PostalCode'] : '',
                'State' => isset($_param_shipping_info['State']) ? $_param_shipping_info['State'] : '',
                'Address1' => isset($_param_shipping_info['Street1']) ? $_param_shipping_info['Street1'] : '',
                'Address2' => isset($_param_shipping_info['Street2']) ? $_param_shipping_info['Street2'] : '',
                'CpfNo' => isset($_params['cpf']) ? $_params['cpf'] : '',
                "PhoneCountryCode" => "",
            ),
        );

        switch($_params['transaction_channel']){
            //pagsmile支付
            case TRANSACTION_CHANNEL_PAGSMILE:
                $_post_data = array_merge($_post_data,[
                    'Token'=>isset($_params['psToken']) ? $_params['psToken'] : '',
                    'PaymentMethodId' => isset($_params['CardInfo']['psPaymentMethodId']) ? $_params['CardInfo']['psPaymentMethodId'] : '',
                    'Installments' => 1,//分期期数，1不分期
                    'Subject' => 'Subject',
                    'Content' => 'Content',
                ]);
                $_result = doCurl(PAYMENT_API.'pagsmile/front/create',$_post_data);
                break;
            //paypal支付
            case TRANSACTION_CHANNEL_PAYPAL:
                //处理paypal支付coupon使用赠送券提示“Product price must be greater than 0 and only have two decimal places.”导致支付失败问题 tinghu.liu 20190415
                $_goods = isset($_param_cartinfo['item'])?$_param_cartinfo['item']:array();
                $_post_data['Rapid'] = 0;
                $_post_data['Items'] = $this->paypalGoodsHandle($_goods);
                $Amount_usd=$_post_data['Amount']/ $_post_data['ExchangeRate'];
                if($Amount_usd>7){
                    $_post_data['ThirdpartyMethod'] = TRANSACTION_PAYMENT_ACCOUNT_ID_APP;
                }else{
                    $_post_data['ThirdpartyMethod'] = TRANSACTION_PAYMENT_ACCOUNT_ID_PAYPAL;
                }
                $_post_data['ReturnUrl'] = PAYPAL_APP_URL.'app/Order/captureOrder';
                $_post_data['CancelUrl'] = PAYPAL_APP_URL.'app/Order/captureOrder';
                $_result = doCurl(PAYMENT_API.'paypal/front/create',$_post_data);
                if(isset($_result['data']['invoice_id']) &&!empty($_result['data']['invoice_id'])){
                    $_result['order_master_number'] = $order_master_number;
                    $_result['rapid'] = 0;
                    $_result['currency_code'] = $_params['currency'];
                    $_result['order_total'] = $_param_cartinfo['order_total'];
                    $this->CommonService->loadRedis()->set(
                        "PAYPALSETEXPRESSCHECKOUT".$_result['data']['invoice_id'],
                        [
                            'params'=>$_params,
                            'result'=>$_result,
                            'address'=>$_post_data['ShippingAddress']
                        ],
                        CACHE_DAY*3
                    );
                }
                break;
            //SC支付
            case TRANSACTION_CHANNEL_SC:
                $_post_data['ThirdpartyMethod'] = TRANSACTION_PAYMENT_ACCOUNT_ID_SC;
                $_result = doCurl(PAYMENT_API.'sc/front/create',$_post_data);
                break;
            //Astropay支付
            case TRANSACTION_CHANNEL_ASTROPAY:
                $_card_bank = $_params['card_bank'];
                //新接口AstroPay信用卡特殊处理，和银行转账、boleto不一样
                if ($_payment_method == TRANSACTION_TYPE_ASTROPAY_CREDIT_CARD){
                    $_credit_card_token_id = (isset($_params['credit_card_token_id']) && $_params['credit_card_token_id']) ? $_params['credit_card_token_id'] : '';
                    $_card_bank = 'CARD';
                    $_post_data['CardInfo'] = [
                        'CvvCode' => isset($_params['CardInfo']['CVVCode'])?$_params['CardInfo']['CVVCode']:'',
                        'CardHolder' => isset($_params['CardInfo']['CardHolder'])?$_params['CardInfo']['CardHolder']:'',
                        'CardNumber' => isset($_params['CardInfo']['CardNumber'])?$_params['CardInfo']['CardNumber']:'',
                        'ExpiryMonth' => isset($_params['CardInfo']['ExpireMonth'])?$_params['CardInfo']['ExpireMonth']:'',
                        'ExpiryYear' => isset($_params['CardInfo']['ExpireYear'])?(string)$_params['CardInfo']['ExpireYear']:'',
                        //信用卡分期数据 tinghu.liu 20191023
                        'Installments' => isset($_params['CardInfo']['Installments'])?$_params['CardInfo']['Installments']:'',
                        'InstallmentsId' => isset($_params['CardInfo']['InstallmentsId'])?$_params['CardInfo']['InstallmentsId']:'',
                    ];
                    $_post_data['BillingAddress'] = [
                        'City' => isset($_params['BillingAddress']['City'])?trim($_params['BillingAddress']['City']):'',
                        'CountryCode' => isset($_params['BillingAddress']['CountryCode'])?trim($_params['BillingAddress']['CountryCode']):'',
                        'Email' => isset($_params['BillingAddress']['Email'])?$_params['BillingAddress']['Email']:'',
                        'FirstName' => isset($_params['BillingAddress']['FirstName'])?$_params['BillingAddress']['FirstName']:'',
                        'LastName' => isset($_params['BillingAddress']['LastName'])?$_params['BillingAddress']['LastName']:'',
                        'PhoneNumber' => isset($_params['BillingAddress']['Mobile'])?$_params['BillingAddress']['Mobile']:'',
                        "PhoneCountryCode" => "",
                        'ZipPostal' => isset($_params['BillingAddress']['PostalCode'])?$_params['BillingAddress']['PostalCode']:'',
                        'State' => isset($_params['BillingAddress']['State'])?trim($_params['BillingAddress']['State']):'',
                        'Address1' => isset($_params['BillingAddress']['Street1'])?$_params['BillingAddress']['Street1']:'',
                        'Address2' => isset($_params['BillingAddress']['Street2'])?$_params['BillingAddress']['Street2']:'',
                    ];
                    $_post_data['EnableToken'] = $_params['save_card'];
                    $_post_data['CreditCardTokenID'] = $_credit_card_token_id;
                    //是否是Token支付
                    $_post_data['IsToken'] = !empty($_credit_card_token_id)?1:0;
                }
                $_post_data = array_merge($_post_data,[
                    'Bank' => $_card_bank,
                    'Bdate' => '',
                    'PaymentMethod' => $_params['payment_method'],
                    'ThirdpartyMethod' => TRANSACTION_PAYMENT_ACCOUNT_ID_ASTROPAY,
                ]);
                /**
                 * 如果是ARS，需要转换支付金额为美元，且支付币种转换为USD
                 * （因为DX收款去掉ARS，但是ARS仍然可以下单）
                 * tinghu.liu 20191109
                 * start
                 */
                if ($_params['currency'] == 'ARS'){
                    Log::record('payCommonParamsPostData001:'.json_encode($_post_data));
                    $_rate = $_post_data['ExchangeRate'];
                    //重置金额和币种
                    $_post_data['Amount'] = $_order_total_usd;
                    $_post_data['CurrencyCode'] = DEFAULT_CURRENCY;
                    //重置产品价格
                    foreach ($_post_data['Items'] as $k300=>$v300){
                        $_post_data['Items'][$k300]['Price'] = sprintf("%.2f", $v300['Price']/$_rate);
                        $_post_data['Items'][$k300]['UnitPrice'] = sprintf("%.2f", $v300['UnitPrice']/$_rate);
                    }
                    //重置子单金额
                    foreach ($_post_data['ChildOrderPrice'] as $k301=>$v301){
                        $_post_data['ChildOrderPrice'][$k301] = sprintf("%.2f", $v301/$_rate);
                    }
                    //重置汇率
                    $_post_data['ExchangeRate'] = 1;
                    Log::record('payCommonParamsPostData002:'.json_encode($_post_data));
                }
                $_result = doCurl(PAYMENT_API.'dlocal/front/create',$_post_data);
                break;
            //asiabill支付
            case TRANSACTION_CHANNEL_ASIABILL:
                $_post_data['ThirdpartyMethod'] = TRANSACTION_PAYMENT_ACCOUNT_ID_ASIABILL;
                if($_params['transaction_type'] == TRANSACTION_TYPE_CREDITCARD){
                    //聚宝支付(不带token)
                    $_post_data = array_merge($_post_data,[
                        'EnableToken' => $_params['save_card'],
                        'CardInfo' => array(
                            'CvvCode' => isset($_params['CardInfo']['CVVCode'])?$_params['CardInfo']['CVVCode']:'',
                            'CardHolder' => isset($_params['CardInfo']['CardHolder'])?$_params['CardInfo']['CardHolder']:'',
                            'CardNumber' => isset($_params['CardInfo']['CardNumber'])?$_params['CardInfo']['CardNumber']:'',
                            'ExpiryMonth' => isset($_params['CardInfo']['ExpireMonth'])?$_params['CardInfo']['ExpireMonth']:'',
                            'ExpiryYear' => isset($_params['CardInfo']['ExpireYear'])?$_params['CardInfo']['ExpireYear']:''
                        ),
                        'BillingAddress' => array(
                            'City' => isset($_params['BillingAddress']['City'])?trim($_params['BillingAddress']['City']):'',
                            'CountryCode' => isset($_params['BillingAddress']['CountryCode'])?trim($_params['BillingAddress']['CountryCode']):'',
                            'Email' => isset($_params['BillingAddress']['Email'])?$_params['BillingAddress']['Email']:'',
                            'FirstName' => isset($_params['BillingAddress']['FirstName'])?$_params['BillingAddress']['FirstName']:'',
                            'LastName' => isset($_params['BillingAddress']['LastName'])?$_params['BillingAddress']['LastName']:'',
                            'PhoneNumber' => isset($_params['BillingAddress']['Mobile'])?$_params['BillingAddress']['Mobile']:'',
                            "PhoneCountryCode" => "",
                            'ZipPostal' => isset($_params['BillingAddress']['PostalCode'])?$_params['BillingAddress']['PostalCode']:'',
                            'State' => isset($_params['BillingAddress']['State'])?$_params['BillingAddress']['State']:'',
                            'Address1' => isset($_params['BillingAddress']['Street1'])?$_params['BillingAddress']['Street1']:'',
                            'Address2' => isset($_params['BillingAddress']['Street2'])?$_params['BillingAddress']['Street2']:'',
                        )
                    ]);
                    $_result = doCurl(PAYMENT_API.'asiabill/front/create',$_post_data);
                }elseif($_params['transaction_type'] == TRANSACTION_TYPE_CREDITCARD_TOKEN){
                    //聚宝支付(带token)
                    $_post_data = array_merge($_post_data,[
                        'CreditCardTokenID' => isset($_params['credit_card_token_id']) ? $_params['credit_card_token_id'] : '',
                        //'CvvCode' => isset($_params['CVVCode']) ? $_params['CVVCode'] : '',
                        'CardInfo' => array(
                            'CvvCode' => isset($_params['CVVCode']) ? $_params['CVVCode'] : '',
                        )
                    ]);
                    $_result = doCurl(PAYMENT_API.'asiabill/front/create',$_post_data);
                }
                break;
            //IDEAL支付
            case TRANSACTION_CHANNEL_IDEAL:
                $_post_data['Bic'] = $_params['bic'];
                $_post_data['ThirdpartyMethod'] = TRANSACTION_PAYMENT_ACCOUNT_ID_IDEAL;

                $_post_data['SuccessRedirectUrl'] = PAYPAL_APP_URL.'/app/Order/iDealPaymentSuccess';
                $_post_data['FailedRedirectUrl'] = PAYPAL_APP_URL.'/app/Order/iDealPaymentFailure';

                $_result = doCurl(PAYMENT_API.'ideal/front/create',$_post_data);
                //返回数据处理

                Log::record($log_key.$_params['transaction_channel'].',params:'.json_encode($_post_data).', res:'.json_encode($_result));

                //组织返回的数据
                if(isset($_result['code']) && $_result['code'] == 200 && isset($_result['data']['status']) && $_result['data']['status'] != 'failure'){
                    $_value = isset($_param_cartinfo['order_total']) ? $_param_cartinfo['order_total'] : 0;
                    $_return_data = array(
//                        'customerEmail' => isset($_param_shipping_info['Email']) ? $_param_shipping_info['Email'] : '',
//                        'customerName' => isset($_params['user_name']) ? $_params['user_name'] : '',
//                        'logoUrl' => 'https://e.dx.com/Pattaya/publicImg/logo-dx.png',
//                        'publicKey' => $_result['data']['publicKey'],
                        'TransactionID' => $_result['data']['transaction_id'],
                        'InvoiceId' => $_result['data']['invoice_id'],
                        'url' => $_result['data']['url'],
                        'value' => $_value*100,
                        'code' => 4 //ideal返回前端状态
                    );
                    //缓存token,回调使用
                    $this->CommonService->loadRedis()->set("IDealSubmitInfo_".strtoupper($_result['data']['invoice_id']), $_params);
                }else{
                    $err_msg = '('.$_result['code'].') '.(isset($_result['data']['error_info'])?$_result['data']['error_info']:'Payment failed, please try again.');
                    $_return_data['code'] = 1;
                    $_return_data['reason'] = $err_msg;
                    $_return_data['url'] = url('/paymentError?order_number='.$order_master_number.'&reason='.$err_msg, false, false);
                }
                $_return_data['currency_code'] = !empty($_params['currency'])?$_params['currency']:'';
                $_return_data['payment_status'] =!empty($_result['status'])?$_result['status']:'';
                $_return_data['order_number'] = $order_master_number;
                $_return_data['order_total'] = (string)$_param_cartinfo['order_total'];
                return $_return_data;
                break;
            //EGP支付
            case TRANSACTION_CHANNEL_EGP:
                //信用卡支付(不带token)
                if($_params['transaction_type'] == TRANSACTION_TYPE_CREDITCARD){
                    $_post_data = array_merge($_post_data,[
                        'ThirdpartyMethod' => TRANSACTION_PAYMENT_ACCOUNT_ID_EGP,
                        'EnableToken' => $_params['save_card'],
                        'CardInfo' => array(
                            'CvvCode' => isset($_params['CardInfo']['CVVCode'])?$_params['CardInfo']['CVVCode']:'',
                            'CardHolder' => isset($_params['CardInfo']['CardHolder'])?$_params['CardInfo']['CardHolder']:'',
                            'CardNumber' => isset($_params['CardInfo']['CardNumber'])?$_params['CardInfo']['CardNumber']:'',
                            'ExpiryMonth' => isset($_params['CardInfo']['ExpireMonth'])?$_params['CardInfo']['ExpireMonth']:'',
                            'ExpiryYear' => isset($_params['CardInfo']['ExpireYear'])?$_params['CardInfo']['ExpireYear']:''
                        ),
                        'BillingAddress' => array(
                            'City' => isset($_params['BillingAddress']['City'])?trim($_params['BillingAddress']['City']):'',
                            'CountryCode' => isset($_params['BillingAddress']['CountryCode'])?trim($_params['BillingAddress']['CountryCode']):'',
                            'Email' => isset($_params['BillingAddress']['Email'])?$_params['BillingAddress']['Email']:'',
                            'FirstName' => isset($_params['BillingAddress']['FirstName'])?$_params['BillingAddress']['FirstName']:'',
                            'LastName' => isset($_params['BillingAddress']['LastName'])?$_params['BillingAddress']['LastName']:'',
                            'PhoneNumber' => isset($_params['BillingAddress']['Mobile'])?$_params['BillingAddress']['Mobile']:'',
                            "PhoneCountryCode" => "",
                            'ZipPostal' => isset($_params['BillingAddress']['PostalCode'])?$_params['BillingAddress']['PostalCode']:'',
                            'State' => isset($_params['BillingAddress']['State'])?$_params['BillingAddress']['State']:'',
                            'Address1' => isset($_params['BillingAddress']['Street1'])?$_params['BillingAddress']['Street1']:'',
                            'Address2' => isset($_params['BillingAddress']['Street2'])?$_params['BillingAddress']['Street2']:'',
                        )
                    ]);
                    $_result = doCurl(PAYMENT_API.'egp/front/create', $_post_data);
                }elseif($_params['transaction_type'] == TRANSACTION_TYPE_CREDITCARD_TOKEN){
                    //信用卡支付(带token)
                    $_post_data = array_merge($_post_data,[
                        'ThirdpartyMethod' => TRANSACTION_PAYMENT_ACCOUNT_ID_EGP,
                        'CreditCardTokenID' => isset($_params['credit_card_token_id']) ? $_params['credit_card_token_id'] : '',
//                        'CvvCode' => isset($_params['CVVCode']) ? $_params['CVVCode'] : '',
                        'CardInfo' => array(
                            'CvvCode' => isset($_params['CVVCode']) ? $_params['CVVCode'] : '',
                        )
                    ]);
                    $_result = doCurl(PAYMENT_API.'egp/front/create', $_post_data);
                }
                break;
            default:
                $_result = array();
                break;
        }
        $_return_data['currency_code'] = $_params['currency'];
        $_return_data['payment_status'] = !empty($_result['status'])?$_result['status']:'';
        $_return_data['Items'] = isset($_param_cartinfo['item'])?$_param_cartinfo['item']:array();
        $_return_data['order_number'] = $order_master_number;
        $_return_data['OrderTotal'] = (string)$_param_cartinfo['order_total'];
        $_return_data['PayerEmail'] = isset($_param_shipping_info['Email'])?$_param_shipping_info['Email']:'';
        $_return_data['Items'] = isset($_param_cartinfo['item'])?$_param_cartinfo['item']:array();
        $_return_data['result'] = $_result;
        Log::record('payCommon'.$log_key.$_params['transaction_channel'].',params:'.json_encode($_post_data).', res:'.json_encode($_result));
        $order_master_number = isset($_params['order_number'])?$_params['order_number']:'';
        //logService::write(LOGS_MALL_CART,'info',__METHOD__,$log_key,$_post_data,MALL_API.'/home/order/submitOrder/payCommon_res', $_result);
        return $_return_data;
    }

    public function getIdealBank(){
        $cache_key = 'Mall_getIdealBankBicQuery';
        $_result = doCurl(PAYMENT_API.'ideal/after/bicquery', ['method'=>7]);
        $rtn['code'] = 0;
        $rtn['data'] = [];
        $data = $this->CommonService->loadRedis()->get($cache_key);
        if (empty($data)){
            if (
                isset($_result['code'])
                && $_result['code'] == 200
                && isset($_result['data'])
                && !empty($_result['data'])
            ){
                $data = $_result['data'];
                $rtn['code'] = 200;
                $rtn['data'] = $data;
                $this->CommonService->loadRedis()->set($cache_key, $data, CACHE_DAY);
            }
        }else{
            $rtn['code'] = 1;
            $rtn['data'] = $data;
        }
        return $rtn;
    }

    /**
     * paypal支付购买产品处理，去掉价格为0的产品
     * 解决payment支付报错：“Product price must be greater than 0 and only have two decimal places.”
     * @param $data
     * @return mixed
     */
    private function paypalGoodsHandle($data){
        if (!empty($data) && count($data) >1){
            foreach ($data as $k10=>$v10){
                if ($v10['Price'] == 0){
                    unset($data[$k10]);
                }
            }
        }
        return $data;
    }

    /**
     * 快捷支付SET阶段【新payment系统】
     * @param unknown $_params
     * @return unknown
     */
    public function setExpressCheckoutToShortcutV2($_params){
        //calCartInfo($_cart_info,$user_id,$lang = null)
        $_cart_info = $_params['cart_info'];
        $_user_id = $_params['customer_id'];
        $_lang = $_params['lang'];
        $_currency = $_params['currency'];
        $_param_cartinfo = $this->CommonService->ExpressCheckoutToShortcutcalCartInfo($_cart_info,$_user_id,$_lang,$_currency);
        $rapid = 1;
        if($_param_cartinfo['code'] == 0){
            return $_param_cartinfo;
        }
        if(in_array($_params['currency'],config('paypal_support_currency'))){
            $CurrencyCode = $_params['currency'];
            $DiscountTotal = $_param_cartinfo['discount_total'];
            $HandlingTotal = $_param_cartinfo['handling_total'];
            $ItemsTotal = $_param_cartinfo['items_totals'];
            $OrderTotal = $_param_cartinfo['order_total'];
            $ShippingTotal = $_param_cartinfo['shipping_total'];
        }else{
            //把cookies的币种切换成USD
            $CurrencyCode = 'USD';

            $DiscountTotal = sprintf("%.2f", $_param_cartinfo['discount_total'] / $_params['rate']);
            $HandlingTotal = sprintf("%.2f", $_param_cartinfo['handling_total'] / $_params['rate']);
            $ItemsTotal = sprintf("%.2f", $_param_cartinfo['items_totals'] / $_params['rate']);
            $OrderTotal = sprintf("%.2f", $_param_cartinfo['order_total'] / $_params['rate']);
            $ShippingTotal = sprintf("%.2f", $_param_cartinfo['shipping_total'] / $_params['rate']);
            foreach ($_param_cartinfo['item'] as $k => $v){
                $_param_cartinfo['item'][$k]['Price'] = sprintf("%.2f", $v['Price'] / $_params['rate']);
                $_param_cartinfo['item'][$k]['UnitPrice'] = sprintf("%.2f", $v['UnitPrice'] / $_params['rate']);
            }
            //解决PayPal不支持的币种转换为USD后汇率不为1的情况
            $_params['rate'] = 1;
        }
        //处理paypal支付coupon使用赠送券提示“Product price must be greater than 0 and only have two decimal places.”导致支付失败问题 tinghu.liu 20190415
        $_goods = isset($_param_cartinfo['item'])?$_param_cartinfo['item']:array();
        $_goods = $this->paypalGoodsHandle($_goods);

        //快捷支付的不计算邮费等信息
        $_post_data = array(
            "TransactionChannel" => TRANSACTION_CHANNEL_PAYPAL,
            "TransactionSouce" => TRANSACTION_SOUCE_DX_WEB,
            "TransactionType" => TRANSACTION_TYPE_PAYPAL,
            'CurrencyCode' => trim($CurrencyCode),
            "CurrencyType" => PAYMENT_CURRENCY_TYPE_CASH,
            'ExchangeRate' => $_params['rate'],
            'OrderMasterNumber' => '',
            'Amount' => $OrderTotal,//订单总额（包括运费等）
            'CustomerId' => $_params['customer_id'],
            'CustomerEmail' => '',//$_params['email'],
            'CustomerIp' => $this->CommonService->getIp(),
            "ChildOrderList" => '',//$_params['ChildOrderList'],
            "ChildOrderPrice" => '',//$_params['ChildOrderPrice'], //{"180410011171034422":120.23}
            'Items' =>  $_goods,
            'ShippingAddress' => [
                /*'Country' => '',
                'City' => '',
                'CountryCode' => '',
                'Email' => '',
                'FirstName' => '',
                'LastName' => '',
                'PhoneNumber' => '',
                'ZipPostal' => '',
                'State' => '',
                'Address1' => '',
                'Address2' =>  '',
                'CpfNo' => '',
                'PhoneCountryCode' => ''*/
            ],
        );
        $_post_data['ThirdpartyMethod'] = TRANSACTION_PAYMENT_ACCOUNT_ID_PAYPAL;
        $_post_data['Rapid'] = $rapid;
        $return = doCurl(PAYMENT_API.'paypal/front/create',$_post_data);

        $invoiceId = isset($return['data']['invoice_id']) ? (string)$return['data']['invoice_id'] : '';
        $transactionId = isset($return['data']['transaction_id']) ? $return['data']['transaction_id'] : 0;

        Log::record('QuickPaypalSetExpressCheckoutToShortcut-params:'.json_encode($_post_data).'-result:'.json_encode($return));

        if (isset($return['data']['status']) && $return['data']['status'] != 'failure'){

            $this->CommonService->loadRedis()->set('QuickPaypalCaptrueDataV2'.$invoiceId, ['transaction_id'=>$transactionId,'invoice_id'=>$invoiceId, 'rapid'=>$rapid], CACHE_DAY*3);

            $ReturnData['code'] = 200;
            $ReturnData['data']['OrderID'] = $invoiceId;
            $ReturnData['data']['TransactionID'] = $transactionId;
            return json($ReturnData);
        }else{
            $Reason = (isset($return['data']['error_info']) && !empty($return['data']['error_info']))?$return['data']['error_info']:'Payment failed, please try again.';
            //通知前端跳转支付失败页面
            $ReturnData['code'] = 100;
            $ReturnData['msg'] = $Reason;//'payment system is error!';
            $ReturnData['url'] = url('/paymentError?error=&reason='.$Reason, false, false);
            return json($ReturnData);
        }
    }
}