<?php
namespace app\app\services;
use think\Log;
use think\Monlog;
use think\Session;
use app\common\helpers\CommonLib;

/**
 * 开发：tinghu.liu
 * 功能：OrderService
 * 时间：2018-11-06
 */
class OrderService extends BaseService
{
    public $CommonService;
//    public $CouponService;
//    public $ProductService;
//    public $NocService;
    public function __construct()
    {
        parent::__construct();
        $this->CommonService = new CommonService();
//        $this->ProductService = new ProductService();
//        $this->CouponService = new CouponService();
//        $this->NocService = new NocService();
    }

    /**
     * 生成支付签名
     * 规则：
     * 1、参数字典排序，生成字符串。如：a=11&c=112&order=111&pay_type=sc
     * 2、字符串拼接上密钥key。如：a=11&c=112&order=111&pay_type=sc &secret_key=192006250b4c09247ec02edce69f6a2d
     * 3、生成MD5值且转大写。
     * @param array $params 支付接收的参数
     * @param string $secret_key 密钥key
     * @return string
     */
    public function generatePaySign(array $params, $secret_key=''){
        /*if ($secret_key === '')
            $secret_key = config('pay_secret_key');
        unset($params['/app/order/submitPay']);*/
        ksort($params);
        $md5_str = urldecode(http_build_query($params).'&secret_key='.$secret_key);
        Log::record('submitPay_sign_md5_str:'.$md5_str);
        $sing = strtoupper(md5($md5_str));
        Log::record('submitPay_sign:'.$sing);
        return $sing;
    }

    /**
     * 生成支付密钥key【前期先固定为一个值】
     * @param $CustomerId 用户ID
     * @param string $salt
     * @return string  071e76472162c0825a81de45031acd47
     */
    public function generateSecretKey($CustomerId, $salt='DXAPPTOPAY'){
        $CustomerId = 888;
        return md5(md5(base64_encode(md5(strtoupper(md5($salt)).md5($CustomerId), true)), true));
    }

    /**
     * 验证支付签名
     * @param $sign 要验证的签名
     * @param $params 支付接收的参数
     * @return bool
     */
    public function verifyPaySign($sign, $params){
        if ($sign === $this->generatePaySign($params,$this->generateSecretKey($params['CustomerId']))){
            return true;
        }else{
            return false;
        }
    }

    /**
     * 生成订单
     * @param $params
     * @return mixed
     */
    public function submitOrder($params){
        $_customer_id = $params['customer_id'];
        $_order_message = $params['order_message'];
        $_currency =  $params['currency'];
        $_lang =  $params['lang'];
        $_rate =  $params['rate'];
        $_pay_type = $params['pay_type'];
        $_pay_chennel = $params['pay_chennel'];
        $_is_buynow = $params['is_buynow'];
        $_is_paypal_quick = 2;
        $_cpf = isset($params['cpf'])?$params['cpf']:'';
        $_card_bank = isset($params['card_bank'])?$params['card_bank']:'';
        $_affiliate = $params['affiliate'];
        $_is_tariff_insurance = $params['is_tariff_insurance'];
        $_customer_address_id = $params['customer_address_id'];
        $_order_from = $params['order_from'];
        $_payment_system = $params['payment_system'];
        if($_is_buynow){
            $_cart_info = $this->redis->get(SHOPPINGCART_BUYNOW_.$_customer_id);

        }else{
            $_cart_info = $this->redis->get(SHOPPINGCART_CHECKOUT_.$_customer_id);
        }
        Log::record('$_cart_info_submitOrder'.$_customer_id.json_encode($_cart_info));
        //处理传过来的参数
        //编历购物车，组装成能写入数据库的数据格式
        if(isset($_cart_info[$_customer_id]) && count($_cart_info[$_customer_id]) > 0){
            $_params['customer_id'] = $_customer_id;
            $_params['order_message'] = $_order_message;
            $_params['currency'] = $_currency;
            $_params['lang'] = $_lang;
            $_params['rate'] = $_rate;
            $_params['pay_type'] = $_pay_type;
            $_params['pay_chennel'] = $_pay_chennel;
            $_params['cart_info'] = $_cart_info[$_customer_id];
            $_params['affiliate'] = $_affiliate;
            $_params['is_tariff_insurance'] = $_is_tariff_insurance;
            $_params['customer_address_id'] = $_customer_address_id;
            $_params['country'] = ucfirst(strtolower($params['country']));
            $_params['is_cod'] = $params['is_cod']?$params['is_cod']:0;
            $_params['ShippingAddress'] = isset($params['ShippingAddress'])?$params['ShippingAddress']:null;
            $_params['email'] = isset($params['email'])?$params['email']:null;
            $_params['cpf'] = $_cpf;
            $_params['card_bank'] = $_card_bank;
            $_params['nocnoc_tax_id'] = isset($params['nocnoc_tax_id'])?$params['nocnoc_tax_id']:null;
            $_params['order_from'] = $_order_from;
            $_params['payment_system'] = $_payment_system;
            $_params['is_paypal_quick'] = $_is_paypal_quick;
            $_cart_info_res = $this->checkCart($_params);

            $_order_master_number = isset($_cart_info_res['data']['master']['order_number'])?$_cart_info_res['data']['master']['order_number']:'';
            if(!isset($_cart_info_res['code']) || $_cart_info_res['code'] != 1){
                //购物车里没有选中的的商品
                $returnData['code'] = $_cart_info_res['code'];
                $returnData['msg'] = $_cart_info_res['msg'];
                Monlog::write(LOGS_MALL_CART,'error',__METHOD__,'submitOrder'.$_order_master_number,$params,MALL_API.'/mall/product/checkCart','checkCart is error, '.json_encode($_cart_info_res).$_customer_id.'_order_master_number'. $_order_master_number); //cart no product
                return $returnData;
            }

            //实际支付金额为0的不让下单 BY tinghu.liu IN 20190618
            if(
                isset($_cart_info_res['data']['master']['grand_total'])
                && $_cart_info_res['data']['master']['grand_total'] <= 0
            ){
                $returnData['code'] = 2;
                $returnData['msg'] = 'Payment must be greater than 0.';
                Log::record('submitOrder'.$_order_master_number.',data:'.json_encode($_cart_info_res).', Payment must be greater than 0.');
                Log::record('cart info:'.json_encode($_cart_info));
                Monlog::write(LOGS_MALL_CART,'error',__METHOD__,'submitOrder'.$_order_master_number,$_cart_info_res,MALL_API.'/mall/product/checkCart','Payment must be greater than 0.');
                return $returnData;
            }

            $Data['cart_info_res'] = $_cart_info_res['data'];
            $Url = MALL_API."/orderfrontend/order/submitOrder";//先进行订单数据创建，然后异步更新相关数据
            Log::record('doCurl$Data'.json_encode($Data));
            $res = doCurl($Url,$Data,null,true);
            Log::record('doCurl$res'.json_encode($res));
            if(isset($res['code']) && $res['code'] == 200 && isset($res['data'])){
                //调用清空购物车接口(为方便测试，暂不清空)
                $this->cleanCart($_params['cart_info'],$_customer_id,$_is_buynow);
                //写入队列，通知coupon管理中心对该coupon进行相应处理
                $this->orderCouponQueue($res['data']);
                //Cookie::set("suhmitOrderInfo",$res['data']);
                //Session::set("suhmitOrderInfo",$res['data']);
                ///**
                //对订单金额进行判断，金额为0
                if(!isset($Data['cart_info_res']['master']['grand_total']) || $Data['cart_info_res']['master']['grand_total'] == 0){
                    $returnData['code'] = 2;
                    //$returnData['msg'] = lang('payment_try_again');//'the grand total eq 0';
                    $returnData['msg'] = 'The grand total eq 0';//'the grand total eq 0';
                    $returnData['grand_total'] = 0;
                    $returnData['data']['orderInfo'] = $_cart_info_res['data'];
                    Monlog::write(LOGS_MALL_CART,'error',__METHOD__,'submitOrder'.$_order_master_number,$params,MALL_API.'/mall/product/checkCart','the grand total eq 0');
                    return $returnData;
                }
                //* **/

                //写入消息队列，与oms同步使用
                //$this->addQueueOMS($res['data']['master']);
                $_order_id_arr = isset($res['data']['slave'])?$res['data']['slave']:array();//获取插入数据库的订单表ID
                $_order_number_relation = isset($res['data']['order_number_relation'])?$res['data']['order_number_relation']:[];
                if(count($_order_id_arr) < 1){
                    $returnData['code'] = 2;
                    //$returnData['msg'] =lang('payment_try_again');//'create order id array is error';
                    $returnData['msg'] = 'Create order id array is error.';//'create order id array is error';
                    Monlog::write(LOGS_MALL_CART,'error',__METHOD__,'submitOrder'.$_order_master_number,$params,MALL_API.'/mall/product/checkCart','create order id array is error');
                    return $returnData;
                }else{
                    $res['orderInfo'] = $_cart_info_res['data'];
                    $returnData['code'] = 1;
                    $returnData['data'] = $res;
                    /********* 处理返回的订单号，为了处理订单改改变的情况 tinghu.liu 20191105 start **********/
                    //处理主单数据
                    if (isset($returnData['data']['orderInfo']['master']['order_number']) && isset($_order_number_relation[$returnData['data']['orderInfo']['master']['order_number']])){
                        $returnData['data']['orderInfo']['master']['order_number'] = $_order_number_relation[$returnData['data']['orderInfo']['master']['order_number']];
                    }
                    //处理子单数据
                    if (isset($returnData['data']['orderInfo']['slave'])){
                        foreach ($returnData['data']['orderInfo']['slave'] as $k500=>$v500){
                            if (isset($returnData['data']['orderInfo']['slave'][$k500]['order']['order_master_number']) && isset($_order_number_relation[$returnData['data']['orderInfo']['slave'][$k500]['order']['order_master_number']])){
                                $returnData['data']['orderInfo']['slave'][$k500]['order']['order_master_number'] = $_order_number_relation[$returnData['data']['orderInfo']['slave'][$k500]['order']['order_master_number']];
                            }
                            if (isset($returnData['data']['orderInfo']['slave'][$k500]['order']['order_number']) && isset($_order_number_relation[$returnData['data']['orderInfo']['slave'][$k500]['order']['order_number']])){
                                $returnData['data']['orderInfo']['slave'][$k500]['order']['order_number'] = $_order_number_relation[$returnData['data']['orderInfo']['slave'][$k500]['order']['order_number']];
                            }
                        }
                    }
                    /********* 处理返回的订单号，为了处理订单改改变的情况 end **********/
                    Monlog::write(LOGS_MALL_CART,'info',__METHOD__,'submitOrder'.$_order_master_number,$params,MALL_API.'/mall/product/checkCart',$res);
                    return $returnData;
                }
            }else{
                $err_msg = 'API create order is error!';
                //提交订单失败
                $returnData['code'] = 2;
                //$returnData['msg'] = lang('payment_try_again');//'API create order is error!';
                $returnData['msg'] = $err_msg;//'API create order is error!';
                Monlog::write(LOGS_MALL_CART,'error',__METHOD__,'submitOrder'.$_order_master_number,$params,MALL_API.'/mall/product/checkCart',$err_msg.', res:'.json_encode($res).', url'.$Url);
                Log::record($err_msg.', params:'.json_encode($Data).', res:'.json_encode($res).', url'.$Url);
                return $returnData;
            }
        }else{
            //购物车为空，返回错误
            $returnData['code'] = 2;
            $returnData['msg'] = 'Payment data is empty.';//'cart data is error';
            Monlog::write(LOGS_MALL_CART,'error',__METHOD__,'submitOrder',$params,MALL_API.'/mall/product/checkCart','cart data is error！');
            return $returnData;
        }
    }

    /**
     * 对购物车的信息进行生成订单前的最后一次验证,和组装数据
     * @param $params
     * @return mixed
     */
    public function checkCart($params){
        $_cart_info = $params['cart_info'];
        $_customer_id = $params['customer_id'];
        $_order_message = $params['order_message'];
        $_currency =  $params['currency'];
        $_lang =  $params['lang'];
        $_rate =  $params['rate'];
        $_pay_type = $params['pay_type'];
        $_pay_chennel = $params['pay_chennel'];
        $_affiliate = $params['affiliate'];
        $_customer_address_id = $params['customer_address_id'];
        $_country = $params['country'];
        $_is_cod = $params['is_cod'];
        //$_tax_id = Cookie::get("nocnoc_tax_id");
        $_tax_id = $params['nocnoc_tax_id'];
        $_order_from = $params['order_from'];
        $_cpf = $params['cpf'];
        $_card_bank = $params['card_bank'];
        Log::record('$_tax_id_$params'.json_encode($params));
        //获取用户收货地址信息
        $_get_address_params['CustomerID'] = $_customer_id;
        $_get_address_params['AddressID'] = $_customer_address_id;
        $_is_tariff_insurance = $params['is_tariff_insurance'];//关税保险
        $_payment_system = $params['payment_system'];//使用的支付系统。1-旧系统（.net）;2-新系统（php）
        $msec_time  = CommonLib::getMsecTime();
        $create_on  = intval(substr($msec_time,0,10));  //订单创建时间(UTC) 精确到秒 (2019/01/22 modified by wangyj, used for statistics)
        $add_time   = date('Y-m-d H:i:s', ($create_on+8*3600)).'.'.substr($msec_time,10);   //订单创建时间(PRC) 精确到毫秒 (2019/01/22 modified by wangyj, used for statistics)
        $_is_paypal_quick = 2;//
        if(is_array($_order_message)){
            foreach ($_order_message as $k12 => $v12){
                if(mb_strlen($v12['messages'],'UTF8') > 500){
                    $returnData['code'] = 2;//返回给前端表示不跳转,弹出msg
                    $returnData['msg'] = 'The message is to long!';//'the message is to long!';
                    Monlog::write(LOGS_MALL_CART,'error',__METHOD__,'submitOrder',$params,MALL_API.'/mall/product/checkCart','The message is to long');
                    return $returnData;
                }
                $_order_message_tmp[$v12['storeid']][$v12['productid']][$v12['skuid']] = $v12['messages'];
            }
        }
        if($_get_address_params['AddressID']){
            //使用用户地址ID的处理
            $Url = CIC_APP."/cic/address/getAddress";
            $_get_address_res = doCurl($Url,$_get_address_params,null,true);

            if($_get_address_res['code'] == 200){
                $_get_address_res = isset($_get_address_res['data'])?$_get_address_res['data']:array();
                $_get_address_res['Email'] = $params['email'];
                $_get_address_res['Mobile'] = !empty($_get_address_res['Mobile'])?$_get_address_res['Mobile']:$_get_address_res['Phone'];
            }else{
                $returnData['code'] = 2;
                $returnData['msg'] = 'The address data is error,Please check.';//'the address data is error';
                Monlog::write(LOGS_MALL_CART,'error',__METHOD__,'submitOrder',$params,MALL_API.'/mall/product/checkCart','the address data is error');
                return $returnData;
            }
        }else{
            //使用收货地址具体值的处理,比如快捷支付的时候使用的是从paypal返回的地址
            $_get_address_res['FirstName'] = isset($params['ShippingAddress']['FirstName'])?$params['ShippingAddress']['FirstName']:'';
            $_get_address_res['LastName'] = isset($params['ShippingAddress']['LastName'])?$params['ShippingAddress']['LastName']:'';
            $_get_address_res['Phone'] = isset($params['ShippingAddress']['Phone'])?$params['ShippingAddress']['Phone']:'';
            $_get_address_res['Mobile'] = !empty($params['ShippingAddress']['Mobile'])?$params['ShippingAddress']['Mobile']:$params['ShippingAddress']['Phone'];
            $_get_address_res['PostalCode'] = isset($params['ShippingAddress']['PostalCode'])?$params['ShippingAddress']['PostalCode']:'';
            $_get_address_res['Street1'] = isset($params['ShippingAddress']['Street1'])?$params['ShippingAddress']['Street1']:'';
            $_get_address_res['Street2'] = isset($params['ShippingAddress']['Street2'])?$params['ShippingAddress']['Street2']:'';
            $_get_address_res['City'] = isset($params['ShippingAddress']['City'])?$params['ShippingAddress']['City']:'';
            $_get_address_res['CityCode'] = isset($params['ShippingAddress']['CityCode'])?$params['ShippingAddress']['CityCode']:'';
            $_get_address_res['Province'] = isset($params['ShippingAddress']['State'])?$params['ShippingAddress']['State']:'';
            $_get_address_res['ProvinceCode'] = isset($params['ShippingAddress']['StateCode'])?$params['ShippingAddress']['StateCode']:'';
            $_get_address_res['Country'] = isset($params['ShippingAddress']['Country'])?ucfirst(strtolower($params['ShippingAddress']['Country'])):'';
            $_get_address_res['CPF'] = !empty($params['ShippingAddress']['CPF'])?trim($params['ShippingAddress']['CPF']):$_cpf;
            $_get_address_res['CountryCode'] = isset($params['ShippingAddress']['CountryCode'])?$params['ShippingAddress']['CountryCode']:'';

        }
        //对收货地址进行处理,自动加-
        $_get_address_res['PostalCode']=$this->getPostalCode($_get_address_res['PostalCode'],$_get_address_res['CountryCode']);
        $_get_address_res['Country'] = isset($_get_address_res['Country'])?ucfirst(strtolower($_get_address_res['Country'])):'';

        //对收货地址进行判断
        $address_res = $this->CommonService->verifyOrderPayAddressParams($_get_address_res, $_pay_type);
        if ($address_res['code'] != 200){
            $returnData['code'] = 3; //地址数据校验不通过，需要通知前端弹出地址输入框，再次填写收货地址
            $returnData['msg'] = $address_res['msg']; //'The address data is error';
            $returnData['true_msg'] = $address_res['msg'];
            Monlog::write(LOGS_MALL_CART,'error',__METHOD__,'submitOrder',$_get_address_res,MALL_API.'/mall/product/checkCart/verifyOrderPayAddressParams_res', $address_res);
            return $returnData;
        }

        //重新获取国家名称，为了保证下单收货地址国家统一，避免老地址数据出现国家名称不一致情况 tinghu.liu 20190723
        //$country_arr = (new IndexService())->getCountryInfo(['Code'=>$_get_address_res['CountryCode']]);
        if(
            !empty($country_arr)
            && isset($country_arr['Name'])
            && !empty($country_arr['Name'])
        ){
            $_get_address_res['Country'] = $country_arr['Name'];
            Log::record('$country_arrgetCountryInfo_Name:'.$country_arr['Name']);
        }

        //快捷支付，如果收货国家是巴西，则增加CPF税号非空判断 tinghu.liu 20191127
        //初始化扩展表cpf，分三步走后，创建订单没有CPF，除了快捷支付巴西国家有，和地址绑定
        $_cpf = isset($_get_address_res['CPF'])?$_get_address_res['CPF']:$_cpf;//$_cpf
        Log::record('checkCart$_cpf'.$_cpf);
        /*
         * 第一步不限制
        if ($_get_address_res['CountryCode'] == 'BR' && empty($_get_address_res['CPF'])){
            $returnData['code'] = 3;
            $returnData['msg'] = 'The receiving address is Brazil. Please fill in the CPF. Please update APP.';
            $returnData['CountryCode'] = $_get_address_res['CountryCode'];
            logService::write(LOGS_MALL_CART,'error',__METHOD__,'submitOrder'.$_customer_id,$params,MALL_API.'/mall/product/checkCart',$returnData, $_customer_id);
            return $returnData;
        }*/


        $_data = array();
        $flag = 0;//标记，如果为真则返回数据
        //获取系统汇率数据源
        $rate_source =[];//
        if(strtoupper($_currency) != DEFAULT_CURRENCY ){
            $rate_source = $this->CommonService->getRateDataSource(false);
        }
        /**
         * 计算生成一张主订单
         */
        $master_number = $this->createOrderNumner();

        $_data['master']['order_number'] = $master_number;//订单ID
        $_data['master']['order_master_number'] = 0;//主订单ID
        $_data['master']['customer_id'] = $_customer_id;//用户ID
        $_data['master']['store_name'] = '';
        $_data['master']['customer_name'] = '';
        $_data['master']['payment_status'] = 0;//支付状态
        $_data['master']['order_status'] = 100;//订单状态
        $_data['master']['currency_code'] = $_currency;//币种
        $_data['master']['exchange_rate'] = $_rate;//汇率不可以从浏览器传回来吧？？TODO
        $_data['master']['language_code'] = $_lang;
        $_data['master']['create_on'] = $create_on;//创建时间(UTC)
        $_data['master']['add_time'] = $add_time;//创建时间(PRC)
        $_data['master']['order_type'] = 1;//
        $_data['master']['fulfillment_status'] = 30;//
        $_data['master']['pay_type'] = $_pay_type;//
        $_data['master']['pay_channel'] = $_pay_chennel;//
        $_data['master']['is_cod'] = $_is_cod;//
        $_data['master']['payment_system'] = $_payment_system;//
        $_data['master']['order_from'] = $_order_from;//
        $_master_goods_count = 0;
        $_master_shipping_fee = 0;
        $_master_goods_total = 0;
        $_master_discount_total = 0;
        $_seller_count = 0;
        //计算付款中有多少个seller
        foreach ($_cart_info['StoreData'] as $k13=>$v13){
            $_seller_sku_count = 0;
            if(isset($v13['ProductInfo'])){
                foreach ($v13['ProductInfo'] as $k14=>$v14){
                    if(isset($v14) && count($v14) > 0){
                        foreach ($v14 as $k15=>$v15){
                            if((isset($v15['IsBuy']) && $v15['IsBuy'] == 1) && isset($v15['IsChecked']) && $v15['IsChecked'] && (isset($v15['ShippModelStatusType']) && $v15['ShippModelStatusType'] < 3)){
                                $_seller_sku_count++;
                            }
                        }
                    }
                }
            }
            if($_seller_sku_count > 0){
                $_seller_count++;
            }
        }
        foreach ($_cart_info['StoreData'] as $k=>$v){
            if($_seller_count > 1){
                $_slave_order_number = $this->createOrderNumner();//生成规则
            }else{
                $_slave_order_number = $master_number;//生成规则
            }
            if(isset($v['ProductInfo'])){
                //以商家ID来拆分订单，组装数据的时候也是根据商家ID来组装
                $_order_goods_total = 0;//这个是订单级别的
                $_order_is_active = 0;
                $_order_active_type_text = '';
                $_order_is_mvp = 0;
                $_order_goods_count = 0;
                $_order_shipping_fee = 0;
                $_order_discount_total = 0;
                $_order_grand_total = 0;
                //订单级别coupon对应价格
                $_order_level_coupon_price = 0;

                ##################对seller级别的coupon进行处理START#################################################################
                $_coupon_shipping_moel = $_coupon_delivery_time = '';
                foreach ($v['ProductInfo'] as $k200=>$v200){
                    if(isset($v200) && count($v200) > 0) {
                        foreach ($v200 as $k201 => $v202) {
                            if ((isset($v202['IsBuy']) && $v202['IsBuy'] == 1) && isset($v202['IsChecked'])
                                && (isset($v202['ShippModelStatusType']) && $v202['ShippModelStatusType'] < 3)) {
                                //$_coupon_shipping_moel = $v202['ShippingMoel'];
                                $_coupon_shipping_moel = isset($v202['OldShippingMoel'])?$v202['OldShippingMoel']:$v202['ShippingMoel'];//运送方式
                                $_coupon_delivery_time = $v202['ShippingDays'];
                            }
                        }
                    }
                }
                $CouponTmp = array();
                $CouponSkuTmp = array();

                if(isset($v['isUsedCoupon']['DiscountInfo']['Type']) && $v['isUsedCoupon']['DiscountInfo']['Type']){
                    /**对couponCode进行验证(使用),如果接口返回正确应用coupon的信息，
                     * 把coupon信息写入订单信息,如果couponCode应用失败，则给出提示
                     */
                    $useCouponParams['id'] = isset($v['isUsedCoupon']['CicCouponId'])?$v['isUsedCoupon']['CicCouponId']:0;
                    //如果ID为0 则不需要传 tinghu.liu 20191031
                    if ($useCouponParams['id'] == 0){
                        unset($useCouponParams['id']);
                    }
                    $useCouponParams['coupon_id'] = $v['isUsedCoupon']['CouponId'];
                    $useCouponParams['coupon_code'] = $v['isUsedCoupon']['CouponCode'];
                    $useCouponParams['customer_id'] = $_customer_id;
                    $useCouponParams['order_number'] = $_slave_order_number;
                    $useCouponParams['start_time'] = $v['isUsedCoupon']['DiscountInfo']['StartTime'];
                    $useCouponParams['end_time'] = $v['isUsedCoupon']['DiscountInfo']['EndTime'];
                    $Url = CIC_APP."/cic/MyCoupon/usedCouponByCode";
                    $checkCouponRes = doCurl($Url,$useCouponParams,null,true);
                    if(!$checkCouponRes['code'] || $checkCouponRes['code'] != 200){
                        $returnData['code'] = 2;
                        $returnData['msg'] = 'sellerID:'.$k.' coupon is error';
                        return $returnData;
                    }
                    if($v['isUsedCoupon']['DiscountInfo']['Type'] == 2){ //这个是什么Coupon
                        //赠品
                        if(isset($v['isUsedCoupon']['DiscountInfo']['SkuInfo']) && count($v['isUsedCoupon']['DiscountInfo']['SkuInfo']) > 0){
                            $Tmp = $v['isUsedCoupon']['DiscountInfo']['SkuInfo'];
                            foreach ($Tmp as $ck => $cv){
                                $CouponSkuTmp[$ck]['product_id'] = isset($cv['ProductId'])?$cv['ProductId']:0;
                                $CouponSkuTmp[$ck]['sku_id'] = isset($cv['SkuId'])?$cv['SkuId']:0;
                                $CouponSkuTmp[$ck]['sku_num'] = isset($cv['SkuCode'])?$cv['SkuCode']:'';//sku编号
                                $CouponSkuTmp[$ck]['first_category_id'] = 0;
                                $CouponSkuTmp[$ck]['second_category_id'] = 0;
                                $CouponSkuTmp[$ck]['third_category_id'] = 0;
                                $CouponSkuTmp[$ck]['discount_total'] = 0;//折扣价
                                $CouponSkuTmp[$ck]['product_price'] = 0;
                                $CouponSkuTmp[$ck]['product_name'] = isset($cv['Title'])?$cv['Title']:'';
                                $CouponSkuTmp[$ck]['product_img'] = isset($cv['ProductImg'])?$cv['ProductImg']:'';
                                $CouponSkuTmp[$ck]['product_attr_ids'] = '';//产品属性ID组
                                $CouponSkuTmp[$ck]['product_attr_desc'] = '';//产品属性描述组
                                $CouponSkuTmp[$ck]['product_nums'] = $cv['Qty'];
                                $CouponSkuTmp[$ck]['product_unit'] = '';
                                $CouponSkuTmp[$ck]['shipping_model'] = $_coupon_shipping_moel;
                                $CouponSkuTmp[$ck]['shipping_fee'] = 0;
                                $CouponSkuTmp[$ck]['delivery_time'] = $_coupon_delivery_time;
                                $CouponSkuTmp[$ck]['order_product_status'] = 0;
                                $CouponSkuTmp[$ck]['message'] = '';
                                $CouponSkuTmp[$ck]['order_item_type'] = 1;

                                $CouponTmp ['coupon_id'] = isset($v['isUsedCoupon']['CouponId'])?$v['isUsedCoupon']['CouponId']:0;
                                $CouponTmp ['coupon_code'] = isset($v['isUsedCoupon']['CouponCode'])?$v['isUsedCoupon']['CouponCode']:'';
                                $CouponTmp ['coupon_desc'] = isset($v['isUsedCoupon']['DiscountInfo']['Name'])?$v['isUsedCoupon']['DiscountInfo']['Name']:'';
                                $CouponTmp ['captured_discount'] = 0;
                                $CouponTmp ['sku_id'] = 0;
                                $CouponTmp ['create_on'] = $create_on;
                                //如果当前贷币不是美元，则需要用当前币种转换成美元
                                $CouponTmp ['USD_discount'] = 0;//以美元为单位的优惠额度
                                $_data['slave'][$k]['coupon'][] = $CouponTmp;
                                $_data['slave'][$k]['order']['coupon_id'] = $v['isUsedCoupon']['CouponId'];//如果使用了coupon则有相应的值
                            }
                        }
                    }else{
                        //折扣
                        $CouponTmp ['coupon_id'] = isset($v['isUsedCoupon']['CouponId'])?$v['isUsedCoupon']['CouponId']:0;
                        $CouponTmp ['coupon_code'] = isset($v['isUsedCoupon']['CouponCode'])?$v['isUsedCoupon']['CouponCode']:'';
                        $CouponTmp ['coupon_desc'] = isset($v['isUsedCoupon']['DiscountInfo']['Name'])?$v['isUsedCoupon']['DiscountInfo']['Name']:'';
                        $OrderDiscountPriceTmp = isset($v['isUsedCoupon']['DiscountInfo']['DiscountPrice'])?$v['isUsedCoupon']['DiscountInfo']['DiscountPrice']:0;

                        $OrderDiscountPriceTmp = sprintf("%.2f",$OrderDiscountPriceTmp);
                        $OrderDiscountPriceTmpUsd = $OrderDiscountPriceTmp;
                        if($_currency != DEFAULT_CURRENCY){
                            //如果当前币种不是美元的话要转成美元币种存一份
                            $OrderDiscountPriceTmpUsd = sprintf("%.2f", $OrderDiscountPriceTmp/$_rate);
                        }
                        $_order_level_coupon_price = $OrderDiscountPriceTmp;
                        //$CouponTmp ['captured_discount'] = isset($v['isUsedCoupon']['DiscountInfo']['DiscountPrice'])?$v['isUsedCoupon']['DiscountInfo']['DiscountPrice']:0;
                        $CouponTmp ['captured_discount'] = $OrderDiscountPriceTmp;
                        $CouponTmp ['sku_id'] = 0;
                        $CouponTmp ['create_on'] = $create_on;
                        //如果当前贷币不是美元，则需要用当前币种转换成美元
                        $CouponTmp ['USD_discount'] = $OrderDiscountPriceTmpUsd;//以美元为单位的优惠额度
                        $_data['slave'][$k]['coupon'][] = $CouponTmp;
                        $_data['slave'][$k]['order']['coupon_id'] = $v['isUsedCoupon']['CouponId'];//如果使用了coupon则有相应的值
                    }
                }
                $CouponTmp = array();
                if(isset($v['isUsedCouponDX'])){
                    //对couponCode进行验证(使用),如果接口返回正确应用coupon的信息，把coupon信息写入订单信息,如果couponCode应用失败，则给出提示
                    $useCouponParams['id'] = isset($v['isUsedCoupon']['CicCouponId'])?$v['isUsedCoupon']['CicCouponId']:0;

                    $useCouponParams['coupon_id'] = $v['isUsedCouponDX']['CouponId'];
                    $useCouponParams['coupon_code'] = $v['isUsedCouponDX']['CouponCode'];
                    $useCouponParams['customer_id'] = $_customer_id;
                    $useCouponParams['order_number'] = $_slave_order_number;
                    $useCouponParams['start_time'] = $v['isUsedCouponDX']['DiscountInfo']['StartTime'];
                    $useCouponParams['end_time'] = $v['isUsedCouponDX']['DiscountInfo']['EndTime'];
                    $Url = CIC_APP."/cic/MyCoupon/usedCouponByCode";
                    $checkCouponRes = doCurl($Url,$useCouponParams,null,true);
                    if(!$checkCouponRes['code'] || $checkCouponRes['code'] != 200){
                        $returnData['code'] = 2;
                        $returnData['msg'] = 'sellerID:'.$k.' coupon is error';//'sellerID:'.$k.' coupon is error';
                        Monlog::write(LOGS_MALL_CART,'error',__METHOD__,'submitOrder'.$master_number,$params,MALL_API.'/mall/product/checkCart','sellerID:'.$k.' coupon is error');
                        return $returnData;
                    }

                    $CouponTmp ['coupon_id'] = isset($v['isUsedCouponDX']['CouponId'])?$v['isUsedCouponDX']['CouponId']:0;
                    $CouponTmp ['coupon_code'] = isset($v['isUsedCouponDX']['CouponCode'])?$v['isUsedCouponDX']['CouponCode']:'';
                    $CouponTmp ['captured_discount'] = isset($v['isUsedCouponDX']['useDiscount'])?$v['isUsedCouponDX']['useDiscount']:0;
                    $CouponTmp ['coupon_desc'] = isset($v['isUsedCoupon']['Name'])?$v['isUsedCoupon']['Name']:'';
                    $CouponTmp ['sku_id'] = 0;
                    $CouponTmp ['create_on'] = $create_on;
                    //如果当前贷币不是美元，则需要用当前币种转换成美元
                    $CouponTmp ['USD_discount'] = isset($v['isUsedCouponDX']['useDiscount'])?$v['isUsedCouponDX']['useDiscount']:0;//以美元为单位的优惠额度
                    $_data['slave'][$k]['coupon'][] = $CouponTmp;
                    //$_data['slave'][$k]['order']['coupon_id'] = $v['isUsedCoupon']['CouponId'];
                }
                //将订单级别coupon的价格算到折扣价中去
                $_order_discount_total = $_order_level_coupon_price;

                ##################对seller级别的coupon进行处理END#################################################################
                foreach ($v['ProductInfo'] as $k2=>$v2){
                    if($_is_tariff_insurance){
                        //买了关税保险的,固定金额1.5美元
                        $tariff_insurance=config('tariff_insurance');
                        $_tariff_insurance_price = $this->CommonService->calculateRate(DEFAULT_CURRENCY,$_currency,$tariff_insurance,$rate_source);
                        $_order_goods_total += $_tariff_insurance_price;
                    }
                    if(isset($v2) && count($v2) > 0){
                        foreach ($v2 as $k3=>$v3){

                            $ProductID = isset($v3['ProductID'])?$v3['ProductID']:0;
                            $SkuID = isset($v3['SkuID'])?$v3['SkuID']:0;
                            //if(isset($v3['IsChecked']) && $v3['IsChecked']){
                            if((isset($v3['IsBuy']) && $v3['IsBuy'] == 1) && isset($v3['IsChecked'])
                                && (isset($v3['ShippModelStatusType']) && $v3['ShippModelStatusType'] < 3)){
                                $_sku_coupon_discount = 0;
                                $_sku_discount_total = 0;
                                $_old_coupon_shipping_moel = isset($v3['OldShippingMoel'])?$v3['OldShippingMoel']:'';

                                ###SKU_conpon START#############################################################
                                if(isset($v3['isUsedCoupon']['CouponCode']) && $v3['isUsedCoupon']['CouponCode'] && isset($v3['isUsedCoupon']['DiscountInfo']['Type']) && isset($v3['isUsedCoupon']['DiscountInfo']['Type'])){
                                    //对couponCode进行验证(使用),如果接口返回正确应用coupon的信息，把coupon信息写入订
                                    //单信息,如果couponCode应用失败，则给出提示
                                    $useCouponParams['id'] = isset($v3['isUsedCoupon']['CicCouponId'])?$v3['isUsedCoupon']['CicCouponId']:0;
                                    //如果ID为0 则不需要传 tinghu.liu 20191031
                                    if ($useCouponParams['id'] == 0){
                                        unset($useCouponParams['id']);
                                    }
                                    $useCouponParams['coupon_id'] = $v3['isUsedCoupon']['CouponId'];
                                    $useCouponParams['coupon_code'] = $v3['isUsedCoupon']['CouponCode'];
                                    $useCouponParams['customer_id'] = $_customer_id;
                                    $useCouponParams['order_number'] = $_slave_order_number;
                                    $useCouponParams['start_time'] = $v3['isUsedCoupon']['DiscountInfo']['StartTime'];
                                    $useCouponParams['end_time'] = $v3['isUsedCoupon']['DiscountInfo']['EndTime'];
                                    $Url = CIC_APP."/cic/MyCoupon/usedCouponByCode";
                                    $checkCouponRes = doCurl($Url,$useCouponParams,null,true);
                                    if(!$checkCouponRes['code'] || $checkCouponRes['code'] != 200){
                                        $returnData['code'] = 2;
                                        $returnData['msg'] = 'productID:'.$ProductID.' skuID:'.$SkuID.' coupon is error';
                                        return $returnData;
                                    }
                                    if($v3['isUsedCoupon']['DiscountInfo']['Type'] == 2){
                                        //赠品
                                        if(isset($v3['isUsedCoupon']['DiscountInfo']['SkuInfo'])
                                            && count($v3['isUsedCoupon']['DiscountInfo']['SkuInfo']) > 0){
                                            $Tmp = $v3['isUsedCoupon']['DiscountInfo']['SkuInfo'];
                                            foreach ($Tmp as $skuCouponK => $skuCouponV){
                                                $CouponSkuTmp[$skuCouponK]['product_id'] = isset($skuCouponV['ProductId'])?$skuCouponV['ProductId']:0;
                                                $CouponSkuTmp[$skuCouponK]['sku_id'] = isset($skuCouponV['SkuId'])?$skuCouponV['SkuId']:0;
                                                $CouponSkuTmp[$skuCouponK]['sku_num'] = isset($skuCouponV['SkuCode'])?$skuCouponV['SkuCode']:'';//sku编号
                                                $CouponSkuTmp[$skuCouponK]['first_category_id'] = 0;
                                                $CouponSkuTmp[$skuCouponK]['second_category_id'] = 0;
                                                $CouponSkuTmp[$skuCouponK]['third_category_id'] = 0;
                                                $CouponSkuTmp[$skuCouponK]['discount_total'] = 0;//折扣价
                                                $CouponSkuTmp[$skuCouponK]['product_price'] = 0;
                                                $CouponSkuTmp[$skuCouponK]['product_name'] = isset($skuCouponV['Title'])?$skuCouponV['Title']:'';
                                                $CouponSkuTmp[$skuCouponK]['product_img'] = isset($skuCouponV['ProductImg'])?$skuCouponV['ProductImg']:'';
                                                $CouponSkuTmp[$skuCouponK]['product_attr_ids'] = '';//产品属性ID组
                                                $CouponSkuTmp[$skuCouponK]['product_attr_desc'] = '';//产品属性描述组
                                                $CouponSkuTmp[$skuCouponK]['product_nums'] = 1;
                                                $CouponSkuTmp[$skuCouponK]['product_unit'] = '';
                                                //运输方式，专线特殊处理，入库的是OldShippingMoel字段
                                                /*if (strtolower($v3['ShippingMoel']) == 'exclusive'){
                                                    $CouponSkuTmp[$skuCouponK]['shipping_model'] = isset($v3['OldShippingMoel'])?$v3['OldShippingMoel']:'';//运送方式
                                                }else{
                                                    $CouponSkuTmp[$skuCouponK]['shipping_model'] = $v3['ShippingMoel'];//运送方式
                                                }*/
                                                //默认使用OldShippingMoel，为了解决切换非默认语种时存储数据库的为非英文的问题
                                                $CouponSkuTmp[$skuCouponK]['shipping_model'] = isset($v3['OldShippingMoel'])?$v3['OldShippingMoel']:$v3['ShippingMoel'];//运送方式
                                                if($v3['ShippingMoel'] =='NOCNOC'){
                                                    $CouponSkuTmp[$skuCouponK]['tax_id'] =  $_tax_id;
                                                }
                                                $CouponSkuTmp[$skuCouponK]['shipping_fee'] = 0;
                                                $CouponSkuTmp[$skuCouponK]['delivery_time'] = $v3['ShippingDays'];
                                                $CouponSkuTmp[$skuCouponK]['order_product_status'] = 0;
                                                $CouponSkuTmp[$skuCouponK]['message'] = '';
                                                $CouponSkuTmp[$skuCouponK]['order_item_type'] = 1;
                                            }
                                            $CouponTmp ['coupon_id'] = $v3['isUsedCoupon']['CouponId'];
                                            $CouponTmp ['coupon_code'] = isset($v3['isUsedCoupon']['CouponCode'])?$v3['isUsedCoupon']['CouponCode']:'';
                                            $CouponTmp ['captured_discount'] = 0;
                                            $CouponTmp ['coupon_desc'] = isset($v3['isUsedCoupon']['DiscountInfo']['Name'])?$v3['isUsedCoupon']['DiscountInfo']['Name']:'';
                                            $CouponTmp ['sku_id'] = $v3['SkuID'];
                                            $CouponTmp ['create_on'] = $create_on;
                                            //如果当前贷币不是美元，则需要用当前币种转换成美元
                                            $CouponTmp ['USD_discount'] = 0;//以美元为单位的优惠额度
                                            $_data['slave'][$k]['coupon'][] = $CouponTmp;
                                        }
                                    }else{
                                        //折扣，20190711 单位为当前币种
                                        $tmp_price = isset($v3['isUsedCoupon']['DiscountInfo']['DiscountPrice'])?$v3['isUsedCoupon']['DiscountInfo']['DiscountPrice']:0;
                                        $tmp_price_usd = $tmp_price;
                                        $_sku_coupon_discount = $tmp_price;
//                                        $_sku_coupon_discount = $this->CommonService->calculateRate(DEFAULT_CURRENCY,$_currency,$tmp_price,$rate_source);
                                        if($_currency != DEFAULT_CURRENCY){
                                            //如果当前币种不是美元的话要转成美元币种存一份
                                            $tmp_price_usd = sprintf("%.2f", $tmp_price_usd/$_rate);
                                        }
                                        $_sku_coupon_discount = $_sku_coupon_discount/$v3['Qty'];
                                        $CouponTmp ['coupon_id'] = $v3['isUsedCoupon']['CouponId'];
                                        $CouponTmp ['coupon_code'] = isset($v3['isUsedCoupon']['CouponCode'])?$v3['isUsedCoupon']['CouponCode']:'';
                                        $CouponTmp ['captured_discount'] = $_sku_coupon_discount;
                                        $CouponTmp ['coupon_desc'] = isset($v3['isUsedCoupon']['DiscountInfo']['Name'])?$v3['isUsedCoupon']['DiscountInfo']['Name']:'';
                                        $CouponTmp ['sku_id'] = $v3['SkuID'];
                                        $CouponTmp ['create_on'] = $create_on;
                                        //如果当前贷币不是美元，则需要用当前币种转换成美元
                                        $CouponTmp ['USD_discount'] = $tmp_price_usd;//以美元为单位的优惠额度
                                        $_data['slave'][$k]['coupon'][] = $CouponTmp;
                                    }
                                }

                                ###SKU_conpon END###################################################
                                $flag = 1;
                                //$v3['ShipTo'] 区域定价 added by zhongning in 20190523
                                $ProductInfo = $this->CommonService->ProductInfoByID($ProductID,$SkuID,$_lang,$_currency,$v3['ShipTo'],false);
                                if(isset($ProductInfo['data'])){
                                    $ProductInfo = $ProductInfo['data'];
                                    if(isset($ProductInfo['Skus'])){
                                        sort($ProductInfo['Skus']);
                                    }else{
                                        //如果没有相关的产品信息
                                        $returnData['code'] = 2;
                                        $returnData['msg'] = 'productID:'.$ProductID.' skuID:'.$SkuID.' data is error';
                                        return $returnData;
                                        break;
                                    }
                                    /*if(!$ProductInfo){
                                        //如果没有相关的产品信息
                                        $returnData['code'] = 2;
                                        $returnData['msg'] = 'productID:'.$ProductID.' skuID:'.$SkuID.' data is error';
                                        return $returnData;
                                        break;
                                    }*/
                                }else{
                                    //如果没有相关的产品信息
                                    $returnData['code'] = 2;
                                    $returnData['msg'] = 'productID:'.$ProductID.' skuID:'.$SkuID.' data is error';
                                    return $returnData;
                                }
                                //处理产品属性数据
                                $_sales_attrs = isset($ProductInfo['Skus'][0]['SalesAttrs'])?$ProductInfo['Skus'][0]['SalesAttrs']:'';
                                $_product_attr = $this->productAttrHandle($_sales_attrs);
                                //获取可供选择的优惠信息和计算价格
                                $_product_price_info = $this->CommonService->getProductPrice($ProductInfo,$SkuID,$v3['Qty']);
                                $_product_price = $this->CommonService->calculateRate(DEFAULT_CURRENCY,$_currency,$ProductInfo['Skus'][0]['SalesPrice'],$rate_source);
                                $_product_price = sprintf("%.2f", $_product_price);
                                //标记该sku使用了批发价还是活动价
                                $_active_price = $_product_price;
//								if(isset($_product_price_info['code']) && $_product_price_info['code']){
//									$_active_price = $_product_price_info['product_price'];
//									//转换汇率
//									$_active_price = $this->CommonService->calculateRate('USD',$_currency,$_active_price);
//								}
                                $_coupon_price = 0;
                                /*
                                 * 运费重新计算
                                 * (checkout页面的运费暂不重新计算，给checkout页面一个超时时间，让其在页面初始化的时候重新获取运费信息)
                                 * */
// 								$params['spu'] = $k2;
// 								$params['count'] = isset($params['count']) ? $params['count'] : 1;
// 								$params += [
// 								'lang' => $_lang ,//当前语种
// 								'currency' => $_currency,//当前币种
// 								'country' => $_country
// 								];
// 								$ShippingInfo = $this->CommonService->countProductShipping($params,$this->productService);
                                //产品的售价(不折扣不优惠)
                                $_sku_goods_count = $v3['Qty'];//SKU里的SKU个数
                                if(is_numeric($v3['ShippingFee'])){
                                    $_sku_hipping_fee = $v3['ShippingFee'];//拿到的运费已经转好了汇率
                                }else{
                                    $_sku_hipping_fee = 0;
                                }
                                if(isset($v3['active_type'])){//如果有活动价,或者批发价
                                    //标记该sku使用了批发价还是活动价
                                    if(isset($_product_price_info['code']) && $_product_price_info['code'] && $_product_price_info['code'] == 1){
                                        $_active_price = $_product_price_info['product_price'];
                                        //转换汇率
                                        $_active_price = $this->CommonService->calculateRate(DEFAULT_CURRENCY,$_currency,$_active_price,$rate_source);
                                        $_active_price = sprintf("%.2f", $_active_price);
                                    }else{
                                        $returnData['code'] = 2;
                                        $returnData['msg'] = 'productID:'.$ProductID.' skuID:'.$SkuID.' '.$_product_price_info['msg'];
                                        return $returnData;
                                    }
                                    $_sku_discount_total = $_product_price - $_active_price;//原价减掉活动价，等于折扣的价钱
                                }
                                if($_sku_coupon_discount){
                                    $_sku_discount_total += $_sku_coupon_discount;//总的折扣(包括起批，活动，coupon)
                                }
                                //将$order_active_type记录到order表
                                if(isset($v3['active_type']) && $v3['active_type']){
                                    $_order_active_type_text = $v3['active_type_text'];
                                    $_order_is_active = 1;
                                }
                                if(isset($v3['IsMvp']) && $v3['IsMvp']){
                                    $_order_is_mvp = 1;
                                }
                                //$_sku_discount_total = $this->CommonService->calculateRate('USD',$_currency,$_sku_discount_total);//汇率转换
                                $_sku_discount_total = sprintf("%.2f", $_sku_discount_total);
                                $_sku_coupon_discount = sprintf("%.2f", $_sku_coupon_discount);
                                $_sku_captured_price = $_product_price-$_sku_discount_total;
                                if($_sku_captured_price >= 0){
                                    $_sku_captured_price_usd = $_sku_captured_price;//实收单价等于产品售价－折扣价－coupon折扣(以美元为单位)
                                    if($_currency != DEFAULT_CURRENCY){
                                        //如果当前币种不是美元的话要转成美元币种存一份
                                        $_sku_captured_price_usd = $this->CommonService->calculateRate($_currency,DEFAULT_CURRENCY,$_sku_captured_price_usd,$rate_source);
                                    }
                                }else{
                                    $_sku_captured_price = 0;//实收单价等于产品售价－折扣价－coupon折扣
                                    $_sku_captured_price_usd = 0;//实收单价等于产品售价－折扣价－coupon折扣(以美元为单位)
                                }
                                //组装订单子表数据
                                $_data['slave'][$k]['order_item'][$k3]['product_id'] = isset($ProductInfo['_id'])?$ProductInfo['_id']:0;
                                $_data['slave'][$k]['order_item'][$k3]['sku_id'] = isset($ProductInfo['Skus'][0]['_id'])?$ProductInfo['Skus'][0]['_id']:0;
                                $_data['slave'][$k]['order_item'][$k3]['sku_num'] = isset($ProductInfo['Skus'][0]['Code'])?$ProductInfo['Skus'][0]['Code']:'';//sku编号
                                $_data['slave'][$k]['order_item'][$k3]['first_category_id'] = isset($ProductInfo['FirstCategory'])?$ProductInfo['FirstCategory']:0;;
                                $_data['slave'][$k]['order_item'][$k3]['second_category_id'] = isset($ProductInfo['SecondCategory'])?$ProductInfo['SecondCategory']:0;;
                                $_data['slave'][$k]['order_item'][$k3]['third_category_id'] = isset($ProductInfo['ThirdCategory'])?$ProductInfo['ThirdCategory']:0;;
                                $_data['slave'][$k]['order_item'][$k3]['discount_total'] = $_sku_discount_total;//折扣价
                                $_data['slave'][$k]['order_item'][$k3]['product_price'] = $_product_price;//产品价格
                                $_data['slave'][$k]['order_item'][$k3]['active_price'] = $_active_price;//销售价格
                                $_data['slave'][$k]['order_item'][$k3]['coupon_price'] = $_sku_coupon_discount;//coupon折扣价
                                $_data['slave'][$k]['order_item'][$k3]['product_name'] = isset($ProductInfo['Title'])?$ProductInfo['Title']:'';
                                $_data['slave'][$k]['order_item'][$k3]['product_img'] = isset($ProductInfo['ImageSet']['ProductImg'][0])?$ProductInfo['ImageSet']['ProductImg'][0]:0;
                                $_data['slave'][$k]['order_item'][$k3]['product_attr_ids'] = isset($_product_attr['product_attr_ids'])?$_product_attr['product_attr_ids']:'';//产品属性ID组
                                $_data['slave'][$k]['order_item'][$k3]['product_attr_desc'] = isset($_product_attr['product_attr_descs'])?$_product_attr['product_attr_descs']:'';//产品属性描述组
                                $_data['slave'][$k]['order_item'][$k3]['product_nums'] = $v3['Qty'];
                                $_data['slave'][$k]['order_item'][$k3]['product_unit'] = $v3['ProductUnit'];

                                //运输方式，专线特殊处理，入库的是OldShippingMoel字段
                                /*if (strtolower($v3['ShippingMoel']) == 'exclusive'){
                                    $_data['slave'][$k]['order_item'][$k3]['shipping_model'] = isset($v3['OldShippingMoel'])?$v3['OldShippingMoel']:'';//运送方式
                                }else{
                                    $_data['slave'][$k]['order_item'][$k3]['shipping_model'] = $v3['ShippingMoel'];//运送方式
                                }*/
                                //默认使用OldShippingMoel，为了解决切换非默认语种时存储数据库的为非英文的问题
                                $_data['slave'][$k]['order_item'][$k3]['shipping_model'] = isset($v3['OldShippingMoel'])?$v3['OldShippingMoel']:$v3['ShippingMoel'];//运送方式
                                Log::record('$vShippingMoel'.json_encode($v3['ShippingMoel']));
                                Log::record('$_tax_id'.$_tax_id);
                                if($v3['ShippingMoel'] =='NOCNOC'){
                                    $_data['slave'][$k]['order_item'][$k3]['tax_id'] = $_tax_id;
                                }
                                $_data['slave'][$k]['order_item'][$k3]['shipping_fee'] = $_sku_hipping_fee;
                                $_data['slave'][$k]['order_item'][$k3]['delivery_time'] = $v3['ShippingDays'];
                                $_data['slave'][$k]['order_item'][$k3]['active_id'] = isset($v3['type_id'])?$v3['type_id']:0;//如果是参与了活动的要记下活动的ID
                                $_data['slave'][$k]['order_item'][$k3]['order_product_status'] = 0;
                                $_data['slave'][$k]['order_item'][$k3]['order_item_type'] = 2; //sku类型 1:赠送商品，2:普通商品
                                $_data['slave'][$k]['order_item'][$k3]['active_type'] = isset($v3['active_type'])?$v3['active_type']:0; //该sku参与的活动类型(0：不参与任何活动，1：批发价，2：活动价，3：coupon价)
                                $_data['slave'][$k]['order_item'][$k3]['message'] = isset($_order_message_tmp[$k][$k2][$k3])?$_order_message_tmp[$k][$k2][$k3]:'';
                                $_data['slave'][$k]['order_item'][$k3]['captured_price'] = $_sku_captured_price;
                                $_data['slave'][$k]['order_item'][$k3]['captured_price_usd'] = $_sku_captured_price_usd;
                                $_data['slave'][$k]['order_item'][$k3]['create_on'] = $create_on;

                                //整理order级别的价格相关数据
                                $_order_goods_count += $v3['Qty'];//订单的总SKU个数，
                                $_order_goods_total += ($_product_price*$_sku_goods_count);//原价*数量
                                //20181030 排除NOCNOC运输方式，因为NOCNOC运费不在这里计算，不以这里为准
                                if(is_numeric($v3['ShippingFee']) && strtoupper($v3['ShippingMoel']) != 'NOCNOC'){
                                    $_order_shipping_fee += $v3['ShippingFee'];//拿到的运费已经转好了汇率
                                }
                                $_order_shipping_fee = sprintf("%.2f", $_order_shipping_fee);
                                $_order_grand_total = $_order_goods_total + $_order_shipping_fee;
                                $_order_discount_total += ($_sku_discount_total*$v3['Qty']);
                                if($_order_grand_total-$_order_discount_total < 0){
                                    $_finnal_money = 0;
                                }else{
                                    $_finnal_money = $_order_grand_total-$_order_discount_total;
                                }

                            }
                        }
                    }else{
                        unset($_data['slave'][$k]);
                    }
                }
                if(isset($_data['slave'][$k]['order_item'])){
                    $_data['slave'][$k]['shipping_address']['address_id'] = $_customer_address_id;
                    $_data['slave'][$k]['shipping_address']['first_name'] = isset($_get_address_res['FirstName'])?$_get_address_res['FirstName']:'';
                    $_data['slave'][$k]['shipping_address']['last_name'] = isset($_get_address_res['LastName'])?$_get_address_res['LastName']:'';
                    $_data['slave'][$k]['shipping_address']['phone_number'] = isset($_get_address_res['Phone'])?$_get_address_res['Phone']:'';
                    $_data['slave'][$k]['shipping_address']['mobile'] = isset($_get_address_res['Mobile'])?$_get_address_res['Mobile']:'';
                    $_data['slave'][$k]['shipping_address']['postal_code'] = isset($_get_address_res['PostalCode'])?$_get_address_res['PostalCode']:'';
                    $_data['slave'][$k]['shipping_address']['street1'] = isset($_get_address_res['Street1'])?$_get_address_res['Street1']:'';
                    $_data['slave'][$k]['shipping_address']['street2'] = isset($_get_address_res['Street2'])?$_get_address_res['Street2']:'';
                    $_data['slave'][$k]['shipping_address']['city'] = isset($_get_address_res['City'])?$_get_address_res['City']:'';
                    $_data['slave'][$k]['shipping_address']['city_code'] = isset($_get_address_res['CityCode'])?$_get_address_res['CityCode']:'';
                    $_data['slave'][$k]['shipping_address']['state'] = isset($_get_address_res['Province'])?$_get_address_res['Province']:'';
                    $_data['slave'][$k]['shipping_address']['state_code'] = isset($_get_address_res['ProvinceCode'])?$_get_address_res['ProvinceCode']:'';
                    $_data['slave'][$k]['shipping_address']['country'] = isset($_get_address_res['Country'])?$_get_address_res['Country']:'';
                    $_data['slave'][$k]['shipping_address']['country_code'] = isset($_get_address_res['CountryCode'])?$_get_address_res['CountryCode']:'';
                    $_data['slave'][$k]['shipping_address']['email'] = isset($_get_address_res['Email'])?$_get_address_res['Email']:'';
                    $_data['slave'][$k]['shipping_address']['cpf'] = $_cpf;
                    $_data['slave'][$k]['shipping_address']['create_on'] = $create_on;
                    //20190107 组装订单扩展表数据，暂时只有Astropay支付方式时再记录，后期可根据需要开发其他支付方式扩展字段记录
                    $_data['slave'][$k]['order_other']['is_paypal_quick'] = 2; //是否是PayPal快捷支付，0-默认值，1-是，2-不是

                    $_data['slave'][$k]['order_other']['cpf'] = $_cpf; //Astropay - CPF
                    $_data['slave'][$k]['order_other']['card_bank'] = $_card_bank; //Astropay - card bank
                    $_data['slave'][$k]['order_other']['ref1'] = ''; //订单扩展字段1
                    $_data['slave'][$k]['order_other']['ref2'] = ''; //订单扩展字段2
                    $_data['slave'][$k]['order_other']['ref3'] = ''; //订单扩展字段3
                    $_data['slave'][$k]['order_other']['ref4'] = ''; //订单扩展字段4
                    $_data['slave'][$k]['order_other']['create_on'] = $create_on;

                    //组装订单主表数据
                    $_data['slave'][$k]['order']['order_master_number'] = $master_number;//主订单ID
                    $_data['slave'][$k]['order']['order_number'] = $_slave_order_number;//生成规则
                    $_data['slave'][$k]['order']['customer_id'] = $_customer_id;//用户ID
                    $_data['slave'][$k]['order']['store_name'] = isset($v['StoreInfo']['StoreName'])?$v['StoreInfo']['StoreName']:'';//
                    $_data['slave'][$k]['order']['customer_name'] = isset($v['StoreInfo']['CustomerName'])?$v['StoreInfo']['CustomerName']:'';;
                    $_data['slave'][$k]['order']['payment_status'] = 0;//支付状态
                    $_data['slave'][$k]['order']['order_status'] = 100;//订单状态
                    $_data['slave'][$k]['order']['currency_code'] = $_currency;//币种
                    $_data['slave'][$k]['order']['order_type'] = 0;//普通订单
                    $_data['slave'][$k]['order']['exchange_rate'] = $_rate;//汇率
                    $_data['slave'][$k]['order']['language_code'] = $_lang;
                    $_data['slave'][$k]['order']['create_on'] = $create_on;//创建时间(UTC)
                    $_data['slave'][$k]['order']['add_time']  = $add_time;//创建时间(PRC)
                    $_data['slave'][$k]['order']['fulfillment_status'] = 30;//
                    $_data['slave'][$k]['order']['pay_type'] = $_pay_type;//
                    $_data['slave'][$k]['order']['pay_channel'] = $_pay_chennel;//
                    $_data['slave'][$k]['order']['store_id'] = $v3['StoreID'];
                    $_data['slave'][$k]['order']['goods_count'] = $_order_goods_count;//订单商品数
                    $_data['slave'][$k]['order']['goods_total'] = $_order_goods_total;//订单总价
                    $_data['slave'][$k]['order']['discount_total'] = $_order_discount_total;//折扣总价
                    $_data['slave'][$k]['order']['shipping_fee'] = $_order_shipping_fee;//运费总额
                    $_data['slave'][$k]['order']['handling_fee'] = 0;//手续费总额
                    $_data['slave'][$k]['order']['total_amount'] = $_order_grand_total;//包含产品总金额、运费总金额、手续费等、含优惠的金额
                    $_data['slave'][$k]['order']['grand_total'] = $_finnal_money;//实收总金额
                    $_data['slave'][$k]['order']['captured_amount_usd'] = $_finnal_money/$_rate;//美元实收金额（如果退款，这个金额会变动）
                    $_data['slave'][$k]['order']['captured_amount'] = $_finnal_money;//实收金额（如果退款，这个金额会变动）
                    $_data['slave'][$k]['order']['bulk_rate_enabled'] = 0;//是否批发价格1:是批发价,0:非批发价
                    $_data['slave'][$k]['order']['receivable_shipping_fee'] = $_order_shipping_fee;//实收运费
                    $_data['slave'][$k]['order']['shipping_insurance_enabled'] = 0;//是否购买运费险
                    $_data['slave'][$k]['order']['shipping_insurance_fee'] = 0;//运费险金额
                    $_data['slave'][$k]['order']['affiliate'] = $_affiliate;
                    $_data['slave'][$k]['order']['country'] = isset($_get_address_res['Country'])?$_get_address_res['Country']:'';
                    $_data['slave'][$k]['order']['country_code'] = isset($_get_address_res['CountryCode'])?$_get_address_res['CountryCode']:'';
                    //是否是活动，批发
                    $_data['slave'][$k]['order']['is_active'] = $_order_is_active;
                    $_data['slave'][$k]['order']['active_type'] = $_order_active_type_text;
                    //是否是Mvp
                    $_data['slave'][$k]['order']['is_mvp'] = $_order_is_mvp;
                    $_data['slave'][$k]['order']['is_tariff_insurance'] = $_is_tariff_insurance;
                    $_data['slave'][$k]['order']['is_cod'] = $_is_cod;
                    $_data['slave'][$k]['order']['payment_system'] = $_payment_system;
                    $_data['slave'][$k]['order']['order_from'] = $_order_from;
                    if($_finnal_money == 0){
                        //对于订单金额为0的订单不走payment，直接改状态为支付成功，并锁定
                        $_data['slave'][$k]['order']['order_status'] = 200;
                        $_data['slave'][$k]['order']['payment_status'] = 200;//支付状态
                        $_data['slave'][$k]['order']['lock_status'] = 73;
                    }
                    if(isset($CouponSkuTmp)){
                        foreach ($CouponSkuTmp as $ck => $cv){
                            $_data['slave'][$k]['order_item_coupon'][$cv['sku_id']] = $cv;
                        }
                    }


                }
                if(isset($CouponSkuTmp) && count($CouponSkuTmp) > 0 && isset($_data['slave'][$k]['order_item'])){
                    $item_len = count($_data['slave'][$k]['order_item']);
                    foreach ($CouponSkuTmp as $ck => $cv){
                        $_data['slave'][$k]['order_item_coupon'][$cv['sku_id']] = $cv;
                    }
                }
                $_master_discount_total += $_order_discount_total;
                $_master_goods_total += $_order_goods_total;
                $_master_goods_count += $_order_goods_count;
                $_master_shipping_fee += $_order_shipping_fee;
            }
        }
        $_master_grand_total = $_master_goods_total+$_master_shipping_fee-$_master_discount_total;
        if($_master_grand_total < 0){
            $_master_grand_total = 0;
            //对于订单金额为0的订单不走payment，直接改状态为支付成功，并锁定
            $_data['master']['order_status'] = 200;
            $_data['master']['payment_status'] = 200;//支付状态
            $_data['master']['lock_status'] = 73;
            Monlog::write(LOGS_MALL_CART,'error',__METHOD__,'submitOrder'.$_data['master']['order_number'], $params,MALL_API.'/mall/product/checkCart','order grand_total is 0');
        }
        $_data['master']['store_id'] = 0;
        $_data['master']['goods_count'] = $_master_goods_count;//订单商品数
        $_data['master']['goods_total'] = $_master_goods_total;//订单总价
        $_data['master']['discount_total'] = $_master_discount_total;//折扣总价
        $_data['master']['shipping_fee'] = $_master_shipping_fee;//运费总额
        $_data['master']['handling_fee'] = 0;//手续费总额
        $_data['master']['total_amount'] = $_master_goods_total+$_master_shipping_fee;//包含产品总金额、运费总金额、手续费等、含优惠的金额
        $_data['master']['grand_total'] = $_master_grand_total;//实收总金额
        $_data['master']['captured_amount_usd'] = $_master_grand_total/$_rate;//美元实收金额（如果退款，这个金额会变动）
        $_data['master']['captured_amount'] = $_master_grand_total;//实收金额（如果退款，这个金额会变动）
        $_data['master']['bulk_rate_enabled'] = 0;//是否批发价格1:是批发价,0:非批发价
        $_data['master']['receivable_shipping_fee'] = $_master_shipping_fee;//实收运费
        $_data['master']['shipping_insurance_enabled'] = 0;//是否购买运费险
        $_data['master']['shipping_insurance_fee'] = 0;//运费险金额
        $_data['master']['affiliate'] = $_affiliate;
        $_data['master']['is_tariff_insurance'] = $_is_tariff_insurance;//是否购买关税保险
        /*** NOCNOC情况处理，添加NOCNOC运费 start 2018-08-15 **/
        if (isset($_data['slave'])){
            foreach ($_data['slave'] as $k10=>&$v10){
                $store_id = $v10['order']['store_id'];
                foreach ($v10['order_item'] as $k11=>$v11){
                    if (strtolower($v11['shipping_model']) == 'nocnoc'){
                        $nocdata = isset($_cart_info['nocdata'])?$_cart_info['nocdata']:[];
                        if (!empty($nocdata)){
                            // 美元，需要转为订单对应币种金额【币种不需要转换，因为nocnoc询价返回时已做币种转化处理】
                            $noc_shipping_fee_usd = $nocdata[$store_id]['shipping_usd'] + $nocdata[$store_id]['tax_handling_usd'];
                            //$noc_shipping_fee = sprintf("%.2f", $noc_shipping_fee_usd*$v10['order']['exchange_rate']);
                            $noc_shipping_fee = $noc_shipping_fee_usd;
                            //1、更新slave运费、total_amount、实收金额
                            $v10['order']['shipping_fee'] += $noc_shipping_fee;
                            $v10['order']['receivable_shipping_fee'] += $noc_shipping_fee;

                            $v10['order']['total_amount'] += $noc_shipping_fee;//包含产品总金额、运费总金额、手续费等、含优惠的金额
                            $v10['order']['grand_total'] += $noc_shipping_fee;//实收总金额
                            $v10['order']['captured_amount_usd'] += $noc_shipping_fee_usd;//美元实收金额（如果退款，这个金额会变动）
                            $v10['order']['captured_amount'] += $noc_shipping_fee;//实收金额（如果退款，这个金额会变动）

                            //2、更新master运费、total_amount、实收金额
                            $_data['master']['shipping_fee'] += $noc_shipping_fee;
                            $_data['master']['receivable_shipping_fee'] += $noc_shipping_fee;

                            $_data['master']['total_amount'] += $noc_shipping_fee;//包含产品总金额、运费总金额、手续费等、含优惠的金额
                            $_data['master']['grand_total'] += $noc_shipping_fee;//实收总金额
                            $_data['master']['captured_amount_usd'] += $noc_shipping_fee_usd;//美元实收金额（如果退款，这个金额会变动）
                            $_data['master']['captured_amount'] += $noc_shipping_fee;//实收金额（如果退款，这个金额会变动）
                            break;
                        }
                    }
                }
            }
        }
        /*** NOCNOC情况处理，添加NOCNOC运费 end 2018-08-15 **/
        if($flag){
            sort($_data['slave']);
            $returnData['code'] = 1;
            $returnData['data'] = $_data;
            $returnData['url'] = '/cart';
            return $returnData;
        }else{
            $returnData['code'] = 2;
//            $returnData['msg'] = lang('payment_try_again');//'the data is error!';
            $returnData['msg'] = 'The data is error!';//'the data is error!';
            $returnData['url'] = '/cart';
            Monlog::write(LOGS_MALL_CART,'error',__METHOD__,'submitOrder'.$_data['master']['order_number'],$params,MALL_API.'/mall/product/checkCart','order the data is error');
            return $returnData;
        }
    }

    public function getPostalCode($PostalCode,$country_code){
        $new_postal_code=$PostalCode;//默认值
        if(is_numeric($PostalCode) && strlen($PostalCode) == 8 && $country_code == "BR"){
             $PostalCode = str_replace(' ', '', $PostalCode);
            /*巴西前五后三加-*/
            $PostalCodeArr[0] = substr($PostalCode,0,5);
            $PostalCodeArr[1] = substr($PostalCode,5,3);
            $new_postal_code = implode("-",$PostalCodeArr);
        }elseif(is_numeric($PostalCode) && strlen($PostalCode) == 7 && $country_code == "JP"){
            $PostalCode = str_replace(' ', '', $PostalCode);
            /*日本前三后五加-*/
            $PostalCodeArr[0] = substr($PostalCode,0,3);
            $PostalCodeArr[1] = substr($PostalCode,3,7);
            $new_postal_code = implode("-",$PostalCodeArr);
        }
        return $new_postal_code;
    }
    /**
     * 订单号生成规则
     * 1804 0012 5523658925
     * 年月日去掉前两位，4位站点数字，10位随机字符
     */
    private function createOrderNumner(){
        $_time = substr(date("Ymd"),2,6);
        $_machine_id = config("machine_id");
        $_rand = rand(intval(pow(10,(10-3))),intval(pow(10,8)-1));
        return $_time.$_machine_id.$_rand;
    }


    /**
     * 处理订单商品的商品属性信息
     * @param array $_sales_attrs
     * @return array|false
     */
    public function productAttrHandle($_sales_attrs){
        if(is_array($_sales_attrs)){
            $_product_attr_ids = '';
            $_product_attr_descs = '';
            $_data = array();
            foreach ($_sales_attrs as $k=>$v){
                $_id = isset($v['id'])?$v['id']:'';
                $_name = isset($v['Name'])?$v['Name']:'';
                if(isset($v['Image']) && $v['Image']){
                    if(isset($v['CustomValue']) && $v['CustomValue']){
                        $_value = $v['CustomValue'];
                    }else{
                        $_value = isset($v['DefaultValue'])?$v['DefaultValue']:'';
                    }
                    if(strlen($_value)==0){
                        $_value = isset($v['Value'])?$v['Value']:'';
                    }
                    $_value .= '|'.IMG_DXCDN.$v['Image'];
                }else{
                    if(isset($v['CustomValue']) && $v['CustomValue']){
                        $_value = $v['CustomValue'];
                    }else{
                        if(isset($v['DefaultValue']) && $v['DefaultValue']){
                            $_value = $v['DefaultValue'];
                        }else{
                            $_value = $v['Value'];
                        }
                    }
                }
                $_product_attr_ids .= $_id.',';
                $_product_attr_descs .= $_name.':'.$_value.',';
            }
            $_product_attr_ids = mb_substr($_product_attr_ids,0,-1,'utf-8');
            $_product_attr_descs = mb_substr($_product_attr_descs,0,-1,'utf-8');
            $_data['product_attr_ids'] = $_product_attr_ids;
            $_data['product_attr_descs'] = $_product_attr_descs;
            return $_data;
        }else{
            return false;
        }
    }

    /**
     * 清空购物车的已生成订单的数据
     * @param unknown $_submit_cart_info
     * 把已标识为已生成订单的SKU踢除出缓存
     * 并删除数据库的数据
     */
    public function cleanCart($_submit_cart_info,$_customer_id,$_is_buynow){
        /** 1、清空buynow、checkout **/
        $this->redis->rm(SHOPPINGCART_BUYNOW_.$_customer_id);
        $_checkout_info = $this->redis->get(SHOPPINGCART_CHECKOUT_.$_customer_id);
        $this->redis->rm(SHOPPINGCART_CHECKOUT_.$_customer_id);
        /** 2、清除购物车缓存 **/
        $spu_data = [];//要清除的cart产品数据集
        if (!$_is_buynow){
            foreach ($_checkout_info[$_customer_id]['StoreData'] as $k=>$v){
                foreach ($v['ProductInfo'] as $k1=>$v1){
                    foreach ($v1 as $k2=>$v2){
                        $tem = [];
                        $tem['ProductID'] = $v2['ProductID'];
                        $tem['SkuID'] = $v2['SkuID'];
                        $spu_data[] = $tem;
                    }
                }
            }
            if (!empty($spu_data)){
                $_cart_info = $this->redis->get(SHOPPINGCART_.$_customer_id);
                //cart去掉已经购买的产品
                foreach ($spu_data as $info){
                    $ProductID = $info['ProductID'];
                    $SkuID = $info['SkuID'];
                    foreach ($_cart_info[$_customer_id]['StoreData'] as $k5=>$v5){
                        foreach ($v5['ProductInfo'] as $k6=>$v6){
                            foreach ($v6 as $k7=>$v7){
                                if ($k6 == $ProductID && $k7 == $SkuID){
                                    unset($_cart_info[$_customer_id]['StoreData'][$k5]['ProductInfo'][$k6][$k7]);
                                }
                            }
                            if (empty($_cart_info[$_customer_id]['StoreData'][$k5]['ProductInfo'][$k6])){
                                unset($_cart_info[$_customer_id]['StoreData'][$k5]['ProductInfo'][$k6]);
                            }
                        }
                        if (empty($_cart_info[$_customer_id]['StoreData'][$k5]['ProductInfo'])){
                            unset($_cart_info[$_customer_id]['StoreData'][$k5]);
                        }
                    }
                    if (empty($_cart_info[$_customer_id]['StoreData'])){
                        $this->redis->rm(SHOPPINGCART_.$_customer_id);
                    }
                }
                //更新cart数据
                $this->redis->set(SHOPPINGCART_.$_customer_id, $_cart_info);
            }
        }
        /** 3、清除其他数据 **/
        //a、nocnoc初始化参数数据
        cookie('nocSubmitOrderParams', null);
        /*if(is_array($_submit_cart_info)){
            foreach ($_submit_cart_info['StoreData'] as $k=>$v){
                if(is_array($v)){
                    foreach ($v['ProductInfo'] as $k1=>$v1){
                        if(is_array($v1)){
                            foreach ($v1 as $k2=>$v2){
                                if(isset($v2['IsBuy'])){
                                    if($v2['IsBuy'] == 1){
                                        //删除
                                        unset($_submit_cart_info['StoreData'][$k]['ProductInfo'][$k1][$k2]);
                                    }
                                }else{
                                    //没有ischecked这个字段的说明数据有误，删除
                                    unset($_submit_cart_info['StoreData'][$k]['ProductInfo'][$k1][$k2]);
                                }
                            }
                        }
                    }
                }
            }
        }
        for($i = 0;$i < 2;$i++){
            //$this->cleanArray1($_submit_cart_info);
        }
        $_rewrite_cart_info[$_customer_id] = $_submit_cart_info;
        if(count($_rewrite_cart_info) < 1){
            //购物车里没有任何数据，清除
            $this->CommonService->loadRedis()->rm(SHOPPINGCART_.$_customer_id);
        }else{
            //购物车里还有数据，重新写入
            $this->CommonService->loadRedis()->set(SHOPPINGCART_.$_customer_id,$_rewrite_cart_info);
        }
        //修改购物车数据库中的数据状态	*/
    }


    /**
     * coupon使用写入队列，通知coupon管理中心
     * @param unknown $Params
     * @return true/false
     * 如果成功了：
     * 如果失败了：
     */
    private function orderCouponQueue($Params){
        static $replaceNum = 1;
        $res = $this->redis->lPush(
            'orderUseCouponQueue',
            json_encode($Params)
        );
        if(!$res){

        }else{

        }
    }


    /**
     * 验证某个额度的SC是否可以使用
     * @param $sc_price
     * @param $sc_password
     * @param $customer_id
     * @param $currency
     * @return mixed
     */
    public function checkSC($sc_price,$sc_password,$customer_id,$currency){
        $_params['CustomerID'] = $customer_id;
        $_params['Password'] = $sc_password;
        $_params['CurrencyType'] = $currency;
        $Url = CIC_APP."/cic/Customer/confirmScPaymentPassword";
        $_res = doCurl($Url,$_params,null,true);
        return $_res;
    }

    /**
     * 订单列表再支付功能
     * @param $Params
     * @return mixed
     */
    public function getParamsForRepay($Params){
        $ParamsWhere['order_master_number'] = $Params['order_master_number'];
        $Url = MALL_API."orderfrontend/order/getPayOrderInfo";
        $_order_res = doCurl($Url,$ParamsWhere,null,true);
        $Lang = $Params['Lang'];
        $Currency = $Params['Currency'];
        if(isset($_order_res['code']) && $_order_res['code'] == 200 && isset($_order_res['data']['order'])){
            /** 去掉订单状态为“取消”的订单数据 20180911 start **/
            foreach ($_order_res['data']['order'] as $k40 => $v40){
                if ($v40['order_status'] == 1400){
                    $_order_id = $v40['order_id'];//订单ID
                    $_captured_amount = $v40['captured_amount'];//实收金额（如果退款，这个金额会变动）
                    $_captured_amount_usd = $v40['captured_amount_usd'];//以美元为单的实收总金额（如果退款，这个金额会变动）
                    $_discount_total = $v40['discount_total']; //折扣总价
                    $_goods_count = $v40['goods_count']; //商品总数
                    $_goods_total = $v40['goods_total']; //订单总价
                    $_grand_total = $v40['grand_total']; //实收总金额
                    $_handling_fee = $v40['handling_fee'];// 手续费总额
                    $_shipping_fee = $v40['shipping_fee']; //运费总额
                    $_total_amount = $v40['total_amount']; //包含产品总金额、运费总金额、手续费等、含优惠的金额
                    $_shipping_insurance_fee = $v40['shipping_insurance_fee']; //运费险金额
                    $_receivable_shipping_fee = $v40['receivable_shipping_fee']; //实收运费
                    //$_shipping_fee_discount = $v40['shipping_fee_discount']; //运费折扣
                    $_tariff_insurance = $v40['tariff_insurance']; //关税险金额
                    //1、将主单相关金额扣减
                    $_order_res['data']['master_order']['captured_amount'] = sprintf("%.2f", $_order_res['data']['master_order']['captured_amount']-$_captured_amount);
                    $_order_res['data']['master_order']['captured_amount_usd'] = sprintf("%.2f", $_order_res['data']['master_order']['captured_amount_usd']-$_captured_amount_usd);
                    $_order_res['data']['master_order']['discount_total'] = sprintf("%.2f", $_order_res['data']['master_order']['discount_total']-$_discount_total);
                    $_order_res['data']['master_order']['goods_count'] = sprintf("%.2f", $_order_res['data']['master_order']['goods_count']-$_goods_count);
                    $_order_res['data']['master_order']['goods_total'] = sprintf("%.2f", $_order_res['data']['master_order']['goods_total']-$_goods_total);
                    $_order_res['data']['master_order']['grand_total'] = sprintf("%.2f", $_order_res['data']['master_order']['grand_total']-$_grand_total);
                    $_order_res['data']['master_order']['handling_fee'] = sprintf("%.2f", $_order_res['data']['master_order']['handling_fee']-$_handling_fee);
                    $_order_res['data']['master_order']['receivable_shipping_fee'] = sprintf("%.2f", $_order_res['data']['master_order']['receivable_shipping_fee']-$_receivable_shipping_fee);
                    $_order_res['data']['master_order']['shipping_fee'] = sprintf("%.2f", $_order_res['data']['master_order']['shipping_fee']-$_shipping_fee);
                    $_order_res['data']['master_order']['shipping_insurance_fee'] = sprintf("%.2f", $_order_res['data']['master_order']['shipping_insurance_fee']-$_shipping_insurance_fee);
                    $_order_res['data']['master_order']['tariff_insurance'] = sprintf("%.2f", $_order_res['data']['master_order']['tariff_insurance']-$_tariff_insurance);
                    $_order_res['data']['master_order']['total_amount'] = sprintf("%.2f", $_order_res['data']['master_order']['total_amount']-$_total_amount);
                    //2、删除符合条件的子单
                    unset($_order_res['data']['order'][$k40]);
                    //3、删除符合条件的产品item
                    foreach ($_order_res['data']['item'] as $k50 => $v50){
                        if ($v50['order_id'] == $_order_id){
                            unset($_order_res['data']['item'][$k50]);
                        }
                    }
                }
            }
            //4、重排删除的数据
            $_order_res['data']['order'] = array_merge($_order_res['data']['order']);
            $_order_res['data']['item'] = array_merge($_order_res['data']['item']);
            /** 去掉订单状态为“取消”的订单数据 end **/
            if(!isset($_order_res['data']['master_order']) || !isset($_order_res['data']['item']) || !isset($_order_res['data']['shipping_address'])){
                //出错处理
                $returnData['code'] = 0;
                $returnData['msg'] = 'order data is error,Please re-order';
                return $returnData;
            }
            $Currency = $_order_res['data']['order'][0]['currency_code'];
            //$shipTo 区域定价 added by zhongning in 20190220
            $shipTo = isset($_order_res['data']['shipping_address'][0]) ? $_order_res['data']['shipping_address'][0]['country_code'] : '';
            //处理返回来的数据
            /**对返回的数据进行支付前操作,
             * 1:验证价格是否有效，更新最新价格。(非活动非coupon的不用关心价格,运费问题,2018-07-30)
             * 2:验证coupon是否有效，无效的直接返回,支付失败
             * 3:验证活动是否有效，无效的直接返回，支付失败
             */

            foreach ($_order_res['data']['item'] as $k => $v){
                if(isset($v['active_id']) && $v['active_id']){
                    /**根据productId与skuId拿到活动信息，判断活动是否有效*/
                    $ProductID = $v['product_id'];
                    $SkuID = $v['sku_id'];
                    $ProductInfo = $this->CommonService->ProductInfoByID($ProductID,$SkuID,$Lang,$Currency,$shipTo);
                    if(!isset($ProductInfo['data']['IsActivity']) || !$ProductInfo['data']['IsActivity']){
                        $returnData['code'] = 0;
                        $returnData['msg'] = 'the active is over,Please re-order';
                        return $returnData;
                    }
                    /** 取消前端对判断 只认IsActivity字段||
                    !isset($ProductInfo['data']['Skus'][0]['ActivityInfo']['ActivityStartTime']) ||
                    !isset($ProductInfo['data']['Skus'][0]['ActivityInfo']['ActivityEndTime']*/
                    if(!isset($ProductInfo['data']['Skus'][0]['ActivityInfo']['SalesLimit'])){
                        $returnData['code'] = 0;
                        $returnData['msg'] = 'the active is error,Please re-order';
                        return $returnData;
                    }
                    /**活动的结构有变化，以下代码需要重新调整*/
//                    $StartTime = $ProductInfo['data']['Skus'][0]['ActivityInfo']['ActivityStartTime'];
//                    $EndTime = $ProductInfo['data']['Skus'][0]['ActivityInfo']['ActivityEndTime'];
//                    $SalesLimit = $ProductInfo['data']['Skus'][0]['ActivityInfo']['SalesLimit'];
//                    $ActiveId = $ProductInfo['data']['Skus'][0]['ActivityInfo']['ActivityId'];
//                    if($SalesLimit < $v['product_nums']){
//                        $returnData['code'] = 0;
//                        $returnData['msg'] = 'the active limit,Please re-order';
//                        return $returnData;
//                    }
//                    if($ActiveId != $v['active_id']){
//                        $returnData['code'] = 0;
//                        $returnData['msg'] = 'the active has expired,Please re-order';
//                        return $returnData;
//                    }
                }
            }
            $temCouponData = [];
            /**coupon处理,下单去支付的时候已经使用了coupon，此处只需要判断coupon是否还在有效期内*/
            foreach ($_order_res['data']['order_coupon'] as $k9 => $v9){
                $couponInfo = [];
                $GetCouponParams['coupon_id'] = $v9['coupon_id'];
                //根据couponId获取Coupon信息
                $Url = MALL_API."/mall/Coupon/getCouponInfoByCouponId";
                $CouponData = doCurl($Url,$GetCouponParams,null,true);
                if(!isset($CouponData['code']) || $CouponData['code'] != 200 || !isset($CouponData['data']) || count($CouponData['data']) < 1){
                    $returnData['code'] = 0;
                    $returnData['msg'] = 'the coupon is error,Please re-order';
                    return $returnData;
                }
                if(!isset($CouponData['data']['CouponTime']['EndTime']) || $CouponData['data']['CouponTime']['EndTime'] < time()){
                    $returnData['code'] = 0;
                    $returnData['msg'] = 'the coupon has expired,Please re-order';
                    return $returnData;
                }
                /** coupon数据组装 start **/
                $tmpCoupon = [];
                $tmpCoupon['sku_id'] = $v9['sku_id'];
                $tmpCoupon['coupon_id'] = $v9['coupon_id'];
                $tmpCoupon['coupon_desc'] = $v9['coupon_desc'];
                $tmpCoupon['coupon_info'] = $CouponData['data'];
                $temCouponData[] = $tmpCoupon;
                /** coupon数据组装 end **/
            }
            //组织好调用payment的数据格式(redis缓存，以repetitionPay_order_master_number_用户ID???)
            $_payment_order_info = $this->CommonService->orderInfoToPaymentInfo($_order_res['data']);
//            $this->CommonService->loadRedis()->set("repetitionPay_".$Params['order_master_number'].'_'.$Params['customer_id'],$_payment_order_info,config('repetitionPay_expire_time'));

            $returnData['code'] = 1;
            $returnData['currency'] = $Currency;
            $returnData['data'] = $_payment_order_info;
            return $returnData;
        }else{
            $returnData['code'] = 0;
            $returnData['msg'] = 'order data is error!';
            return $returnData;
        }
    }

    /**
     * 获取订单信息
     */
    public function getOrderInfoByOrderMasterNumber($_order_master_number){
        $_params['order_master_number'] = $_order_master_number;
        $Url = MALL_API."/orderfrontend/order/getOrderInfoByOrderMasterNumber";
        $_res = doCurl($Url,$_params,null,true);
        return $_res;
    }

    /**
     * 订单列表再支付功能
     * @param $Params
     * @param int $flag 标识：0-默认，1-获取PayOrder数据
     * @return mixed
     */
    public function getRepetitionPayV2($Params, $flag=0){
        $payToken = isset($Params['pay_token'])?$Params['pay_token']:'';
        $PayType = isset($Params['pay_type'])?$Params['pay_type']:'';
        $ParamsWhere['order_master_number'] = '';
        $ParamsWhere['pay_token'] = $payToken;
        $ParamsWhere['is_check_order'] = 1;
        $Url = MALL_API."orderfrontend/order/getPayOrderInfo";

        $_order_res = doCurl($Url,$ParamsWhere,null,true);
        Log::record('$_order_res'.json_encode($_order_res));
        if(isset($_order_res['code']) && $_order_res['code'] == 200 && isset($_order_res['data']['order'])){
            $_is_update_order_activity_coupon = isset($_order_res['data']['is_update_order_activity_coupon'])?$_order_res['data']['is_update_order_activity_coupon']:0;
            //20190110 是否有关税保险
            $_is_tariff_insurance = isset($_order_res['data']['master_order']['is_tariff_insurance'])?$_order_res['data']['master_order']['is_tariff_insurance']:0;
            $_customer_id = isset($_order_res['data']['master_order']['customer_id'])?$_order_res['data']['master_order']['customer_id']:0;
            $_order_master_number = isset($_order_res['data']['master_order']['order_number'])?$_order_res['data']['master_order']['order_number']:'';
            $Lang = isset($_order_res['data']['master_order']['language_code'])?$_order_res['data']['master_order']['language_code']:'';
            $Currency = $trueCurrency = isset($_order_res['data']['master_order']['currency_code'])?$_order_res['data']['master_order']['currency_code']:'';
            $exchange_rate = isset($_order_res['data']['master_order']['exchange_rate'])?$_order_res['data']['master_order']['exchange_rate']:'';

            /******* 获取支持的币种 start ******/
            if(strtolower($PayType) == 'paypal'){
                if(
                    in_array($Currency,config('paypal_not_support_currency'))
                    || !in_array($Currency,config('paypal_support_currency'))
                ){
                    $trueCurrency = 'USD';
                }
            }else{
                if(!in_array($Currency,config('dx_support_currency'))){
                    $trueCurrency = 'USD';
                }
            }
            /******* 获取支持的币种 end ******/
            if ($trueCurrency == 'USD' && $Currency != $trueCurrency){
                $Currency = $trueCurrency;
                Log::record('$_order_res'.json_encode($_order_res['data']).'$exchange_rate'.$exchange_rate);
                $this->handleGetRepetitionV2PriceDataToUSD($_order_res['data'], $exchange_rate);
            }
            //20190110 关税保险金额
            $_tariff_insurance_amount = 0;
            /** 去掉订单状态为“取消”的订单数据 20180911 start **/
            foreach ($_order_res['data']['order'] as $k40 => $v40){
                //20190110 关税保险金额
                if (
                    isset($v40['is_tariff_insurance'])
                    && $v40['is_tariff_insurance'] == 1
                    && isset($v40['tariff_insurance'])
                ){
                    $_tariff_insurance_amount = $v40['tariff_insurance'];
                }
                if ($v40['order_status'] == 1400){
                    $_order_id = $v40['order_id'];//订单ID
                    $_captured_amount = $v40['captured_amount'];//实收金额（如果退款，这个金额会变动）
                    $_captured_amount_usd = $v40['captured_amount_usd'];//以美元为单的实收总金额（如果退款，这个金额会变动）
                    $_discount_total = $v40['discount_total']; //折扣总价
                    $_goods_count = $v40['goods_count']; //商品总数
                    $_goods_total = $v40['goods_total']; //订单总价
                    $_grand_total = $v40['grand_total']; //实收总金额
                    $_handling_fee = $v40['handling_fee'];// 手续费总额
                    $_shipping_fee = $v40['shipping_fee']; //运费总额
                    $_total_amount = $v40['total_amount']; //包含产品总金额、运费总金额、手续费等、含优惠的金额
                    $_shipping_insurance_fee = $v40['shipping_insurance_fee']; //运费险金额
                    $_receivable_shipping_fee = $v40['receivable_shipping_fee']; //实收运费
                    //$_shipping_fee_discount = $v40['shipping_fee_discount']; //运费折扣
                    $_tariff_insurance = $v40['tariff_insurance']; //关税险金额
                    //1、将主单相关金额扣减
                    $_order_res['data']['master_order']['captured_amount'] = sprintf("%.2f", $_order_res['data']['master_order']['captured_amount']-$_captured_amount);
                    $_order_res['data']['master_order']['captured_amount_usd'] = sprintf("%.2f", $_order_res['data']['master_order']['captured_amount_usd']-$_captured_amount_usd);
                    $_order_res['data']['master_order']['discount_total'] = sprintf("%.2f", $_order_res['data']['master_order']['discount_total']-$_discount_total);
                    $_order_res['data']['master_order']['goods_count'] = sprintf("%.2f", $_order_res['data']['master_order']['goods_count']-$_goods_count);
                    $_order_res['data']['master_order']['goods_total'] = sprintf("%.2f", $_order_res['data']['master_order']['goods_total']-$_goods_total);
                    $_order_res['data']['master_order']['grand_total'] = sprintf("%.2f", $_order_res['data']['master_order']['grand_total']-$_grand_total);
                    $_order_res['data']['master_order']['handling_fee'] = sprintf("%.2f", $_order_res['data']['master_order']['handling_fee']-$_handling_fee);
                    $_order_res['data']['master_order']['receivable_shipping_fee'] = sprintf("%.2f", $_order_res['data']['master_order']['receivable_shipping_fee']-$_receivable_shipping_fee);
                    $_order_res['data']['master_order']['shipping_fee'] = sprintf("%.2f", $_order_res['data']['master_order']['shipping_fee']-$_shipping_fee);
                    $_order_res['data']['master_order']['shipping_insurance_fee'] = sprintf("%.2f", $_order_res['data']['master_order']['shipping_insurance_fee']-$_shipping_insurance_fee);
                    $_order_res['data']['master_order']['tariff_insurance'] = sprintf("%.2f", $_order_res['data']['master_order']['tariff_insurance']-$_tariff_insurance);
                    $_order_res['data']['master_order']['total_amount'] = sprintf("%.2f", $_order_res['data']['master_order']['total_amount']-$_total_amount);
                    //2、删除符合条件的子单
                    unset($_order_res['data']['order'][$k40]);
                    //3、删除符合条件的产品item
                    foreach ($_order_res['data']['item'] as $k50 => $v50){
                        if ($v50['order_id'] == $_order_id){
                            unset($_order_res['data']['item'][$k50]);
                        }
                    }
                }
            }
            //4、重排删除的数据
            $_order_res['data']['order'] = array_merge($_order_res['data']['order']);
            $_order_res['data']['item'] = array_merge($_order_res['data']['item']);
            /** 去掉订单状态为“取消”的订单数据 end **/
            if(!isset($_order_res['data']['master_order']) || !isset($_order_res['data']['item']) || !isset($_order_res['data']['shipping_address'])){
                //出错处理
                $returnData['code'] = 0;
                $returnData['msg'] = $returnData['tips'] = 'order data is error,Please re-order';
                return $returnData;
            }
//            $Currency = $_order_res['data']['order'][0]['currency_code'];
            //$shipTo 区域定价 added by wangyj in 20190220
            $shipTo = isset($_order_res['data']['shipping_address'][0]) ? $_order_res['data']['shipping_address'][0]['country_code'] : '';
            //处理返回来的数据
            /**对返回的数据进行支付前操作,
             * 1:验证价格是否有效，更新最新价格。(非活动非coupon的不用关心价格,运费问题,2018-07-30)
             * 2:验证coupon是否有效，无效的直接返回,支付失败
             * 3:验证活动是否有效，无效的直接返回，支付失败
             */

            foreach ($_order_res['data']['item'] as $k => $v){
                $ProductID = $v['product_id'];
                $SkuID = $v['sku_id'];
                $ProductNums = $v['product_nums'];
                /**根据productId与skuId拿到活动信息*/
                $ProductInfo = $this->CommonService->ProductInfoByID($ProductID,$SkuID,$Lang,$Currency,$shipTo);

                /**判断活动是否有效*/
                if(isset($v['active_id']) && $v['active_id']){
//                    $ProductID = $v['product_id'];
//                    $SkuID = $v['sku_id'];
//
//                    $ProductInfo = $this->CommonService->ProductInfoByID($ProductID,$SkuID,$Lang,$Currency,$shipTo);
                    if(!isset($ProductInfo['data']['IsActivity']) || !$ProductInfo['data']['IsActivity']){
                        $returnData['code'] = 0;
                        $returnData['msg'] = $returnData['tips'] = 'The product activity is over. Please place the order again.';
                        return $returnData;
                    }
                    /** 取消前端对判断 只认IsActivity字段||
                    !isset($ProductInfo['data']['Skus'][0]['ActivityInfo']['ActivityStartTime']) ||
                    !isset($ProductInfo['data']['Skus'][0]['ActivityInfo']['ActivityEndTime']*/
                    if(
                        !isset($ProductInfo['data']['Skus'][0]['ActivityInfo']['SalesLimit'])
                        ||
                        (isset($ProductInfo['data']['Skus'][0]['ActivityInfo']['SalesLimit']) && $ProductInfo['data']['Skus'][0]['ActivityInfo']['SalesLimit'] <= 0)
                    ){
                        $returnData['code'] = 0;
                        $returnData['msg'] = $returnData['tips'] = 'The product activity is over. Please place the order again.';
                        return $returnData;
                    }
                    /**活动的结构有变化，以下代码需要重新调整*/
//                    $StartTime = $ProductInfo['data']['Skus'][0]['ActivityInfo']['ActivityStartTime'];
//                    $EndTime = $ProductInfo['data']['Skus'][0]['ActivityInfo']['ActivityEndTime'];
//                    $SalesLimit = $ProductInfo['data']['Skus'][0]['ActivityInfo']['SalesLimit'];
//                    $ActiveId = $ProductInfo['data']['Skus'][0]['ActivityInfo']['ActivityId'];
//                    if($SalesLimit < $v['product_nums']){
//                        $returnData['code'] = 0;
//                        $returnData['msg'] = 'the active limit,Please re-order';
//                        return $returnData;
//                    }
//                    if($ActiveId != $v['active_id']){
//                        $returnData['code'] = 0;
//                        $returnData['msg'] = 'the active has expired,Please re-order';
//                        return $returnData;
//                    }
                }
                /**
                 * 判断产品库存，没有库存不让支付
                 * Inventory
                 */

                if (isset($ProductInfo['data']['Skus'][0]['Inventory'])){
                    $_inventory = $ProductInfo['data']['Skus'][0]['Inventory'];
                    if ($_inventory <= 0){
                        $returnData['code'] = 0;
                        $returnData['msg'] = $returnData['tips'] = 'There is no stock in the order. Please place the order again.';
                        Log::record('$ProductInfo1'.json_encode($ProductInfo),'error');
                        return $returnData;
                    }
                    if ($ProductNums > $_inventory){
                        $returnData['code'] = 0;
                        $returnData['msg'] = $returnData['tips'] = 'There are products in the order that exceed the inventory. Please place the order again.';
                        return $returnData;
                    }
                }else{
                    $returnData['code'] = 0;
                    $returnData['msg'] = $returnData['tips'] = 'The system is busy. Please try again.';
                    Log::record('$ProductInfo2'.json_encode($ProductInfo),'error');
                    return $returnData;
                }
            }
            $tmpReturnData = [];
            $tmpReturnData['coupon'] = [];
            $temCouponData = [];
            /**coupon处理,下单去支付的时候已经使用了coupon，此处只需要判断coupon是否还在有效期内*/
            foreach ($_order_res['data']['order_coupon'] as $k9 => $v9){
                //如果coupon过期回滚了金额，则不需要处理过期的coupon，为了解决coupon过期回滚后支付页面调用接口报错问题 tinghu.liu 20191010
                if (
                    isset($v9['USD_discount'])
                    && isset($v9['captured_discount'])
                    && $v9['USD_discount'] == 0
                    && $v9['captured_discount'] == 0
                ){
                    continue;
                }
                $couponInfo = [];
                $GetCouponParams['coupon_id'] = $v9['coupon_id'];
                //根据couponId获取Coupon信息
                $Url = MALL_API."/mall/Coupon/getCouponInfoByCouponId";
                $CouponData = doCurl($Url,$GetCouponParams,null,true);
                if(!isset($CouponData['code']) || $CouponData['code'] != 200 || !isset($CouponData['data']) || count($CouponData['data']) < 1){
                    $returnData['code'] = 0;
                    $returnData['msg'] = $returnData['tips'] = 'the coupon is error,Please re-order';
                    return $returnData;
                }
                if(!isset($CouponData['data']['CouponTime']['EndTime']) || $CouponData['data']['CouponTime']['EndTime'] < time()){
                    $returnData['code'] = 0;
                    $returnData['msg'] = $returnData['tips'] = 'the coupon has expired,Please re-order';
                    return $returnData;
                }
                /** coupon数据组装 start **/
                $tmpCoupon = [];
                $tmpCoupon['sku_id'] = $v9['sku_id'];
                $tmpCoupon['coupon_id'] = $v9['coupon_id'];
                $tmpCoupon['coupon_desc'] = $v9['coupon_desc'];
                $tmpCoupon['coupon_info'] = $CouponData['data'];
                $temCouponData[] = $tmpCoupon;
                /** coupon数据组装 end **/
            }
            //组织好调用payment的数据格式(redis缓存，以repetitionPay_order_master_number_用户ID???)
            $_payment_order_info = $this->CommonService->orderInfoToPaymentInfo($_order_res['data']);
            $this->CommonService->loadRedis()->set("repetitionPayV2_".$_order_master_number.'_'.$_customer_id,$_payment_order_info,(config('repetitionPay_expire_time'))*24);
            //返回支付数据 tinghu.liu 20190819
            if ($flag == 1){
                $_payment_order_info['code'] = 1;
                return $_payment_order_info;
            }

            //组织好返回给客户端展示用的数据格式

            if(!isset($_order_res['data']['master_order'])){
                $returnData['code'] = 0;
                $returnData['msg'] = $returnData['tips'] = 'master order is error!';
                return $returnData;
            }
            $tmpReturnData['master_order'] = $_order_res['data']['master_order'];
            $adjust_price = 0;
            foreach ($_order_res['data']['order'] as $k => $v){
                $adjust_price = $v['adjust_price']*-1;
                $tmpReturnData['slave_order'][$k]['orderInfo']['goods_tota'] = $v['goods_total'];
                $tmpReturnData['slave_order'][$k]['orderInfo']['shipping_fee'] = $v['shipping_fee'];
                $tmpReturnData['slave_order'][$k]['orderInfo']['grand_total'] = $v['grand_total'];
                $tmpReturnData['slave_order'][$k]['orderInfo']['discount_total'] = $v['discount_total']+$adjust_price;
                $tmpReturnData['slave_order'][$k]['Coupon'] = array();
                $tmpReturnData['slave_order'][$k]['StoreInfo']['StoreID'] = $v['store_id'];
                $tmpReturnData['slave_order'][$k]['StoreInfo']['StoreName'] = $v['store_name'];
                $tmpReturnData['slave_order'][$k]['ProductInfo'] = array();
                if(!isset($_order_res['data']['item'])){
                    $returnData['code'] = 0;
                    $returnData['msg'] = $returnData['tips'] = 'order item is error!';
                    return $returnData;
                }
                if(!isset($_order_res['data']['shipping_address'])){
                    $returnData['code'] = 0;
                    $returnData['msg'] = $returnData['tips'] = 'shipping address is error!';
                    return $returnData;
                }
                $tmpReturnData['ShoppingAddress'] = $_order_res['data']['shipping_address'][0];
                foreach ($_order_res['data']['item'] as $itemk => $itemv){
                    if($itemv['order_id'] == $v['order_id']){
                        $tmpReturnData['slave_order'][$k]['ProductInfo'][] = array(
                            "StoreID" => $v['store_id'],
                            "StoreName" => $v['store_name'],
                            "ProductID" => $itemv['product_id'],
                            "SkuID" => $itemv['sku_id'],
                            "ProductTitle" => $itemv['product_name'],
                            "ProductImg" => $itemv['product_img'],
                            "Qty" => $itemv['product_nums'],
                            "Currency" => $v['currency_code'],
                            "ShippingMoel" => $itemv['shipping_model'],
                            "ShippingFee" => $itemv['shipping_fee'],
                            //0-免邮，2-有运费
                            "ShippingFeeType" => empty($itemv['shipping_fee'])||$itemv['shipping_fee']==0?0:2,
                            "ShippingDays" => $itemv['delivery_time'],
                            "ShipTo" => '',
                            "ProductUnit" => $itemv['product_unit'],
                            "IsChecked" => 1,
                            "AttrsDesc" => $this->CommonService->handleOrderProductaAttrDesc($itemv['product_attr_desc']),
                            "Weight" => '',
                            "ProductPrice" => $itemv['product_price'],
                            "enable_select_active" => '',
                            "OldProductPrice" => $itemv['product_price'],
                            "ShippModelStatusType" => 1,
                            //1:赠送商品，2:普通商品
                            "ItemType" => $itemv['order_item_type'],
                            "message" => $itemv['message'],
                        );
                    }
                }
            }
            /** 拼装coupon数据 20180904 start **/
            if (!empty($temCouponData)){
                foreach ($temCouponData as $cinfo){
                    //单品级别优惠,将coupon数据拼装到对应的sku上
                    if($cinfo['coupon_info']['DiscountLevel'] == 1){
                        foreach ($tmpReturnData['slave_order'] as $k10=>$v10){
                            foreach ($v10['ProductInfo'] as $k11=>$v11){
                                if ($v11['SkuID'] == $cinfo['sku_id']){
                                    //$tmpReturnData['slave_order'][$k10]['ProductInfo'][$k11]['coupon'][$cinfo['coupon_id']] = $cinfo['coupon_info'];
                                    $tmpReturnData['slave_order'][$k10]['ProductInfo'][$k11]['coupon'] = ['Name'=>$cinfo['coupon_desc']];
                                }
                            }
                        }
                    }else{
                        //订单级别优惠，将coupon数据拼装到对应的订单上
                        foreach ($tmpReturnData['slave_order'] as $k20=>$v20){
                            if ($v20['StoreInfo']['StoreID'] == $cinfo['coupon_info']['SellerId']){
                                //$tmpReturnData['slave_order'][$k20]['orderInfo']['coupon'][$cinfo['coupon_id']] = $cinfo['coupon_info'];
                                $tmpReturnData['slave_order'][$k20]['orderInfo']['coupon'] = ['Name'=>$cinfo['coupon_desc']];
                            }
                        }
                    }
                }
                //处理赠品数据 ItemType = 1，将赠品移动到orderInfo节点处
                foreach ($tmpReturnData['slave_order'] as $k30=>$v30) {
                    $coupon_gift_data = [];
                    foreach ($v30['ProductInfo'] as $k31 => $v31) {
                        if ($v31['ItemType'] == 1){
                            $coupon_gift_data[] = $tmpReturnData['slave_order'][$k30]['ProductInfo'][$k31];
                            unset($tmpReturnData['slave_order'][$k30]['ProductInfo'][$k31]);
                        }
                    }
                    $tmpReturnData['slave_order'][$k30]['orderInfo']['coupon_gift'] = $coupon_gift_data;
                }
            }
            /** 拼装coupon数据 20180904 end **/
            $returnData['code'] = 1;
            $returnData['currency'] = $Currency;
            $returnData['has_tariff_insurance'] = $_is_tariff_insurance;
            $returnData['tariff_insurance_amount'] = $_tariff_insurance_amount;
            $returnData['data'] = $tmpReturnData;
            $tips = '';
            if ($_is_update_order_activity_coupon){
//			    $tips = 'The product activity price (coupon) you purchased has expired and you will be purchasing it at the original price.';
                $tips = 'The product activity price (coupon) you purchased has expired and you can choose to purchase or re-order at the original price.';
            }
            $returnData['tips'] = $tips;
            return $returnData;
        }else{
            $returnData['code'] = 0;
            $returnData['msg'] = $returnData['tips'] = 'order data is error!';
            return $returnData;
        }
        exit;
    }

    private function handleGetRepetitionV2PriceDataToUSD(&$data, $exchange_rate){
        if (isset($data['order']) && !empty($data['order'])){
            foreach ($data['order'] as $k101=>$v101){
                $data['order'][$k101]['discount_total'] = sprintf("%.2f", $data['order'][$k101]['discount_total']/$exchange_rate);
                $data['order'][$k101]['goods_total'] = sprintf("%.2f", $data['order'][$k101]['goods_total']/$exchange_rate);
                $data['order'][$k101]['shipping_fee'] = sprintf("%.2f", $data['order'][$k101]['shipping_fee']/$exchange_rate);
                $data['order'][$k101]['handling_fee'] = sprintf("%.2f", $data['order'][$k101]['handling_fee']/$exchange_rate);
                $data['order'][$k101]['total_amount'] = sprintf("%.2f", $data['order'][$k101]['total_amount']/$exchange_rate);
                $data['order'][$k101]['grand_total'] = sprintf("%.2f", $data['order'][$k101]['grand_total']/$exchange_rate);
                $data['order'][$k101]['captured_amount'] = sprintf("%.2f", $data['order'][$k101]['captured_amount']/$exchange_rate);
                $data['order'][$k101]['refunded_amount'] = sprintf("%.2f", $data['order'][$k101]['refunded_amount']/$exchange_rate);
                $data['order'][$k101]['adjust_price'] = sprintf("%.2f", $data['order'][$k101]['adjust_price']/$exchange_rate);
                $data['order'][$k101]['shipped_amount'] = sprintf("%.2f", $data['order'][$k101]['shipped_amount']/$exchange_rate);
                $data['order'][$k101]['shipping_insurance_fee'] = sprintf("%.2f", $data['order'][$k101]['shipping_insurance_fee']/$exchange_rate);
                $data['order'][$k101]['receivable_shipping_fee'] = sprintf("%.2f", $data['order'][$k101]['receivable_shipping_fee']/$exchange_rate);
                $data['order'][$k101]['tariff_insurance'] = sprintf("%.2f", $data['order'][$k101]['tariff_insurance']/$exchange_rate);
            }
        }
        if (isset($data['item']) && !empty($data['item'])){
            foreach ($data['item'] as $k102=>$v102){
                $data['item'][$k102]['shipping_fee'] = sprintf("%.2f", $data['item'][$k102]['shipping_fee']/$exchange_rate);
                $data['item'][$k102]['discount_total'] = sprintf("%.2f", $data['item'][$k102]['discount_total']/$exchange_rate);
                $data['item'][$k102]['product_price'] = sprintf("%.2f", $data['item'][$k102]['product_price']/$exchange_rate);
                $data['item'][$k102]['active_price'] = sprintf("%.2f", $data['item'][$k102]['active_price']/$exchange_rate);
                $data['item'][$k102]['captured_price'] = sprintf("%.2f", $data['item'][$k102]['captured_price']/$exchange_rate);
            }
        }
        if (isset($data['order_coupon']) && !empty($data['order_coupon'])){
            foreach ($data['order_coupon'] as $k103=>$v103){
                $data['order_coupon'][$k103]['captured_discount'] = sprintf("%.2f", $data['order_coupon'][$k103]['captured_discount']/$exchange_rate);
            }
        }
        if (isset($data['master_order']) && !empty($data['master_order'])){
            $data['master_order']['discount_total'] = sprintf("%.2f", $data['master_order']['discount_total']/$exchange_rate);
            $data['master_order']['goods_total'] = sprintf("%.2f", $data['master_order']['goods_total']/$exchange_rate);
            $data['master_order']['shipping_fee'] = sprintf("%.2f", $data['master_order']['shipping_fee']/$exchange_rate);
            $data['master_order']['handling_fee'] = sprintf("%.2f", $data['master_order']['handling_fee']/$exchange_rate);
            $data['master_order']['total_amount'] = sprintf("%.2f", $data['master_order']['total_amount']/$exchange_rate);
            $data['master_order']['grand_total'] = sprintf("%.2f", $data['master_order']['grand_total']/$exchange_rate);
            $data['master_order']['captured_amount'] = sprintf("%.2f", $data['master_order']['captured_amount']/$exchange_rate);
            $data['master_order']['refunded_amount'] = sprintf("%.2f", $data['master_order']['refunded_amount']/$exchange_rate);
            $data['master_order']['adjust_price'] = sprintf("%.2f", $data['master_order']['adjust_price']/$exchange_rate);
            $data['master_order']['shipped_amount'] = sprintf("%.2f", $data['master_order']['shipped_amount']/$exchange_rate);
            $data['master_order']['shipping_insurance_fee'] = sprintf("%.2f", $data['master_order']['shipping_insurance_fee']/$exchange_rate);
            $data['master_order']['receivable_shipping_fee'] = sprintf("%.2f", $data['master_order']['receivable_shipping_fee']/$exchange_rate);
            $data['master_order']['tariff_insurance'] = sprintf("%.2f", $data['master_order']['tariff_insurance']/$exchange_rate);
        }
    }
}
