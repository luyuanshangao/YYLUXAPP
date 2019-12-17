<?php
namespace app\app\controller;

use app\app\services\CheckoutService;
use app\app\services\CommonService;
use app\app\services\NocService;
use app\app\services\OrderService;
use app\app\services\PaymentService;
use app\common\controller\AppBase;
use app\common\helpers\RedisClusterBase;
use app\common\params\app\OrderParams;
use think\Cookie;
use think\Log;
use think\Monlog;

/**
 * 开发：tinghu.liu
 * 功能：Order
 * 时间：2018-11-01
 */
class Order extends AppBase
{
    //支付返回处理，文案提示
    const ORDER_HANDLEFLAG_NOTICE = 1;
    //支付返回处理，链接跳转
    const ORDER_HANDLEFLAG_JUMP = 20;
    //接口成功码
    const API_SECCUSS_CODE = 200;

    public $CheckoutService;
    public $CommonService;
    public $NocService;
    public $OrderService;
    public $redis;
    public $PayMent;
//    public $rateService;
//    public $productService;
    public function __construct()
    {
        parent::__construct();
        $this->CheckoutService = new CheckoutService();
        $this->CommonService = new CommonService();
        $this->redis = new RedisClusterBase();
//        $this->rateService = new rateService();
//        $this->productService = new ProductService();
        $this->OrderService = new OrderService();
        $this->NocService = new NocService();
        $this->PayMent = new PaymentService();
    }

    /**
     * 获取支付密钥key 不要开放，直接给APP一个密钥key即可【因为开放后只要请求这个接口都可以获取密钥key】
     * @return mixed
     */
//    public function getSecretKey(){
//        $params = request()->post();
//        $validate = $this->validate($params,(new OrderParams())->getSecretKeyRules());
//        if (true !== $validate){
//            return apiReturn(['code'=>1002, 'msg'=>$validate]);
//        }
//        try{
//            $secret_key = $this->OrderService->generateSecretKey($params['CustomerId']);
//            return apiReturn(['code'=>200, 'data'=>$secret_key]);
//        }catch (\Exception $e){
//            return apiReturn(['code'=>1003, 'msg'=>'System abnormality '.$e->getMessage()]);
//        }
//    }

    /**
     * 组装支付数据
     */
    private function submitOrderCommon($params){
        $_rate = 1;
        $_customer_id = $params['CustomerId'];
//        $_customer_id = $this->CstomerInfo['data']['ID'];
//        if(isset($this->CstomerInfo['data']['email']) && !empty($this->CstomerInfo['data']['email'])){
//            $_params['email'] = $this->CstomerInfo['data']['email'];
//        }else{
//            Monlog::write(LOGS_MALL_CART,'info',__METHOD__,__FUNCTION__,null,null,'email is null');
//            $_params['email'] = 'admin@comepro.com'; //以防万一 TODO
//        }

        $_params['email'] = $params['CustomerEmail'];

        //SC支付

        $_params['sc_password'] = isset($params['ScPassword'])?$params['ScPassword']:'';
        $_params['sc_price'] = isset($params['ScPrice'])?$params['ScPrice']:0;
        $_params['is_paypal_quick'] = isset($params['IsPaypalQuick'])?$params['IsPaypalQuick']:0;
        $_params['is_buynow'] = isset($params['IsBuyNow'])?$params['IsBuyNow']:0;
        $_params['customer_id'] = $_customer_id;
        $_params['order_message'] = isset($params['message'])?$params['message']:'';
        $_params['currency'] = $params['Currency'];
        $_params['lang'] = $params['Lang'];
        $_params['order_from'] = $params['OrderFrom'];//订单来源：10-PC，20-Android，30-iOS，40-Pad，50-Mobile
        $_params['pay_type'] = isset($params['PayType'])?$params['PayType']:'';//支付方式,支付方式的不同，调用不同的接口,并有不同的处理流程
        /**需要判断当前币种是否是我们的实收币种，如果不是，则强制转成美元来收取*/
        //$_params['country'] = $this->country;
        $_params['country'] = $params['ShipTo'];
        if(strtolower($_params['pay_type']) != 'paypal'){
            /**如果支付方式是非paypal的，要获取我们dx支付的币种进行比对，
             * 不在其中的，全部切换成USD
             */
            if(!in_array($_params['currency'],config('dx_support_currency'))){
                $_params['currency'] = 'USD';
            }
        }else{
            /**如果是paypal的支付方式的，要获取我们和paypal签订的币种进行比对，
             * 不在其中的全部切成USD
             */
            if(!in_array($_params['currency'],config('paypal_support_currency'))){
                $_params['currency'] = 'USD';
            }
        }

        $_params['card_type'] = isset($params['CardType'])?$params['CardType']:'';
        $_params['pay_chennel'] = $params['PayChennel'];
        if($_params['pay_chennel'] == 'Asiabill' && $_params['card_type'] && !in_array($_params['card_type'],config('asiabill_creditcard'))){
            $_params['pay_chennel'] = 'EGP';
        }

        //是否是affiliate订单，条件（缺一不可）：1、$this->affiliate不为空；2、产品SPU佣金比例大于0
        //这些判断逻辑均在Task项目生成订单佣金时处理了
        $_params['affiliate'] = '';//$this->affiliate; //TODO 待确认，APP有affiliate？？？？？？？？不做
        $_params['customer_address_id'] = $params['CustomerAddressId'];
        //拿到汇率
        if($_params['currency'] != DEFAULT_CURRENCY){
            $_rate = $this->CommonService->getOneRate( DEFAULT_CURRENCY,$_params['currency']);
        }

        $_params['rate'] = $_rate;
        //如果是信用卡支付的，接收信用卡信息(或者是选择了某张信用卡的，调取该信用卡信息)
        $_params['BillingAddress']['City'] = isset($params['City'])?$params['City']:'';
        $_params['BillingAddress']['CityCode'] = isset($params['CityCode'])?$params['CityCode']:'';
        $_params['BillingAddress']['Country'] = isset($params['Country'])?$params['Country']:'';
        $_params['BillingAddress']['CountryCode'] = isset($params['CountryCode'])?$params['CountryCode']:'';
        $_params['BillingAddress']['Email'] = isset($params['Email'])?$params['Email']:'';
        $_params['BillingAddress']['FirstName'] = isset($params['FirstName'])?$params['FirstName']:'';
        $_params['BillingAddress']['LastName'] = isset($params['LastName'])?$params['LastName']:'';
        $_params['BillingAddress']['Mobile'] = isset($params['Mobile'])?$params['Mobile']:'';
        $_params['BillingAddress']['Phone'] = isset($params['Phone'])?$params['Phone']:'';
        $_params['BillingAddress']['PostalCode'] = isset($params['PostalCode'])?$params['PostalCode']:'';
        $_params['BillingAddress']['State'] = isset($params['State'])?$params['State']:'';
        $_params['BillingAddress']['Street1'] = isset($params['Street1'])?$params['Street1']:'';
        $_params['BillingAddress']['Street2'] = isset($params['Street2'])?$params['Street2']:'';
        $_params['CardInfo']['CVVCode'] = isset($params['CVVCode'])?$params['CVVCode']:'';
        $_params['CardInfo']['CardHolder'] = (isset($params['FirstName'])?$params['FirstName']:'').(isset($params['LastName'])?$params['LastName']:'');
        $_params['CardInfo']['CardNumber'] = preg_replace('# #','',(isset($params['CardNumber'])?$params['CardNumber']:''));
        $_params['CardInfo']['ExpireMonth'] = isset($params['ExpireMonth'])?$params['ExpireMonth']:'';
        $_params['CardInfo']['ExpireYear'] = 2000+(isset($params['ExpireYear'])?$params['ExpireYear']:0);
        //$_params['CardInfo']['IssuingBank'] = 'Visa';//input('IssuingBank')

        $_params['payment_method'] = input("payment_method");//Astropay的支付方式()【具体支付时会处理，这里可以不用管】
        $_params['cpf'] = isset($params['CPF'])?$params['CPF']:'';//Astropay支付的CPF
        $_params['card_bank'] = isset($params['CardBank'])?$params['CardBank']:'';//Astropay
        $_params['credit_card_token_id'] = isset($params['CreditCardTokenId'])?$params['CreditCardTokenId']:'';//信用卡支付的tokenID
        $_params['querystring'] = isset($params['Querystring'])?htmlspecialchars_decode($params['Querystring']):'';//payment返回，order返回到前端，paypal支付get,do阶段使用
        //获取用户的地址信息
        //TODO ??????????
        $_params['order_master_number'] = input("OrderNumber")?input("OrderNumber"):0;
        //TODO ?????????
        $_params['NocNoc'] = input('nocnoc')?input('nocnoc'):0;

        $_params['CVVCode'] = isset($params['CVVCode'])?$params['CVVCode']:'956';
        //测试repay
        //$_params['order_master_number'] = '180610019742134905';
        //如果是快捷支付的，没有使用客户的地址信息，而是使用了从paypal带过来的地址
        $_paypal_address = isset($params['PaypalQuickAddress'])?$params['PaypalQuickAddress']:null;

        if(is_array($_paypal_address)){
            $_params['ShippingAddress']['City'] = isset($_paypal_address['city'])?$_paypal_address['city']:'';
            $_params['ShippingAddress']['CityCode'] = isset($_paypal_address['cityCode'])?$_paypal_address['cityCode']:'';
            $_params['ShippingAddress']['Country'] = isset($_paypal_address['country'])?$_paypal_address['country']:'';
            $_params['ShippingAddress']['CountryCode'] = isset($_paypal_address['countryCode'])?$_paypal_address['countryCode']:'';
            $_params['ShippingAddress']['Email'] = isset($_params['email'])?$_params['email']:'';
            $_params['ShippingAddress']['FirstName'] = isset($_paypal_address['firstName'])?$_paypal_address['firstName']:'';
            $_params['ShippingAddress']['LastName'] = isset($_paypal_address['lastName'])?$_paypal_address['lastName']:''; //lastName ?? TODO
            //$_params['ShippingAddress']['Mobile'] = '';//Mobile ?? TODO
            $_params['ShippingAddress']['Mobile'] = isset($_paypal_address['phonenumber'])?$_paypal_address['phonenumber']:'';
            $_params['ShippingAddress']['Phone'] = '';//Phone ?? TODO
            $_params['ShippingAddress']['PostalCode'] = isset($_paypal_address['postalcode'])?$_paypal_address['postalcode']:'';
            $_params['ShippingAddress']['State'] = isset($_paypal_address['province'])?$_paypal_address['province']:'';
            $_params['ShippingAddress']['StateCode'] = isset($_paypal_address['provinceCode'])?$_paypal_address['provinceCode']:'';
            $_params['ShippingAddress']['Street1'] = isset($_paypal_address['street1'])?$_paypal_address['street1']:'';
            $_params['ShippingAddress']['Street2'] = isset($_paypal_address['street2'])?$_paypal_address['street2']:'';
        }
        $_save_card = isset($params['SaveCard'])?$params['SaveCard']:false;
        if($_save_card == 'true'){
            $_params['save_card'] = 1;
        }else{
            $_params['save_card'] = 0;
        }
        //$_params['save_card'] = (input('saveCard') == true)?1:0;//是否保存信用卡信息
        //关税保险
        $_params['is_tariff_insurance'] = isset($params['IsTariffInsurance'])?$params['IsTariffInsurance']:0;
        /*//判断购物车里的信息有没有NOCNOC的
        $_check_nocnoc = $this->Noc->checkNocNoc($_params, 2);
        if($_check_nocnoc){
            $_params['NocNoc'] = 1;
        }else{
            $_params['NocNoc'] = 0;
        }*/
        //判断购物车里的信息有没有NOCNOC的（不是repay的情况）
        if (!$_params['order_master_number']){
            $_check_nocnoc = $this->NocService->checkNocNoc($_params, 2);
            if($_check_nocnoc){
                $_params['NocNoc'] = 1;
            }else{
                $_params['NocNoc'] = 0;
            }
        }
        $_params['paypal_create_order'] = isset($params['PaypalCreateOrder'])?$params['PaypalCreateOrder']:false;
        //记录支付方式等信息,在获取支付方式的时候要带出来给前端,什么情况下需要记录支付信息???
        $this->CommonService->recordParams($_params);
        //Cookie::set("payParams",json_encode($_params));
        $this->redis->set("payParams_".$_customer_id, json_encode($_params));
    }

    /**
     * 创建订单且支付
     *
     * 支付签名参数Sign生成规则：
     *
     * 1、参数组装成key=value形式，且根据参数名按照ASCII字典序排序。如：
     * "CustomerAddressId=25&Lang=en&ShipTo=US"
     * 2、字符串拼接上密钥secret_key。【现在先默认为一个值：071e76472162c0825a81de45031acd47】，如：
     * "CustomerAddressId=25&Lang=en&ShipTo=US&secret_key=071e76472162c0825a81de45031acd47"
     * 3、生成MD5值且转大写即可得到签名Sign。
     *
     * APP支付差别：
     * 1、没有paypal快捷支付
     *
     * handle_flag：
     *      1-文案提示，【失败】（未创建订单成功时）
     *      20-指定跳转，【成功】
     *      21-自定义处理【成功】（废弃）
     *
     * @return mixed
     */
    public function submitPay(){
        $params = request()->post();
        Log::record('submitPay-params:'.print_r($params, true));
        $validate = $this->validate($params,(new OrderParams())->submitPayRules());
        if (true !== $validate){
            return apiReturn(['code'=>1002, 'handle_flag'=>self::ORDER_HANDLEFLAG_NOTICE, 'msg'=>$validate]);
        }
        //sign权限校验。签名：strtoupper(MD5(秘钥+参数排序[参数名ASCII字典序排序]))
        $_sign = $params['Sign'];
        unset($params['Sign']);
        if(isset($params['access_token'])){
            unset($params['access_token']);
        }
        if (!$this->OrderService->verifyPaySign($_sign, $params)){
            //自测时可关闭，和APP联调和上线时要开启
            return apiReturn(['code'=>1003, 'handle_flag'=>self::ORDER_HANDLEFLAG_NOTICE, 'msg'=>'You do not have rights to complete the operation.']);
        }
        //支付方式 PayType 有效性校验
        if (!in_array($params['PayType'],config('app_allow_pay_type'))){
            return apiReturn(['code'=>1020, 'handle_flag'=>self::ORDER_HANDLEFLAG_NOTICE, 'msg'=>'Illegal payment.']);
        }
        //支付渠道 PayChennel 有效性校验
        if (!in_array($params['PayChennel'],config('app_allow_pay_chennel'))){
            return apiReturn(['code'=>1022, 'handle_flag'=>self::ORDER_HANDLEFLAG_NOTICE, 'msg'=>'Illegal payment.']);
        }
        //判断币种有效性
        $params['Currency'] = $this->CommonService->verifyCurrency(input("Currency"));
        //判断语种有效性
        $params['Lang'] = $this->CommonService->verifyLang(input("Lang"));
        //判断国家有效性
        $params['ShipTo'] = $this->CommonService->verifyCountry(input("ShipTo"));

        //判断用户是否有效且获取用户邮箱
//        $check_user_result = doCurl(CIC_API.'/cic/Customer/GetCustomerInfoByAccount',['AccountName'=>$params['CustomerEmail']], null, true);
        $check_user_result = doCurl(CIC_API.'/cic/Customer/GetEmailsByCID',['id'=>$params['CustomerId']], null, true);
        if(
            !isset($check_user_result['code'])
            || $check_user_result['code'] != self::API_SECCUSS_CODE
            || !isset($check_user_result['data'])
            || empty($check_user_result['data'])
        ){
            return apiReturn(['code'=>1004, 'handle_flag'=>self::ORDER_HANDLEFLAG_NOTICE, 'msg'=>'User information error.']);
        }
        $params['CustomerEmail'] = $check_user_result['data'];

        //调用接收参数接口
        $this->submitOrderCommon($params);
        $_customer_id = $params['CustomerId'];
        $_params = json_decode($this->redis->get("payParams_".$_customer_id),true);

        //判断用户收货地址ID有效性
        $check_address_result = doCurl(CIC_API.'/cic/address/getAddress',['CustomerID'=>$params['CustomerId'], 'AddressID'=>$params['CustomerAddressId']], null, true);
        if(
            $_params['order_master_number'] == 0
            && (
            !isset($check_address_result['code'])
            || $check_address_result['code'] != self::API_SECCUSS_CODE
            || !isset($check_address_result['data'])
            || empty($check_address_result['data'])
            || !isset($check_address_result['data']['AddressID'])
            || $check_address_result['data']['AddressID'] != $params['CustomerAddressId']
            )
        ){
            return apiReturn(['code'=>1005, 'handle_flag'=>self::ORDER_HANDLEFLAG_NOTICE, 'msg'=>'User address error.']);
        }

        //如果是NOC，需要跳转到TaxId输入页面，如果是已经传了TaxId的，则不需要跳到TaxId输入页面，直接走下面的流程（因为有TaxId，说明已经询价过了，这里不需要再处理）
        $tax_id = isset($params['NocNocTaxId'])?$params['NocNocTaxId']:'';
        $_params['nocnoc_tax_id'] = $tax_id;
        if($_params['NocNoc'] == 1 && empty($tax_id)){
            //Cookie::set('nocSubmitOrderParams', input(), 60*30);
            //跳到输入TaxId的页面
//            $ReturnData['code'] = 1;
//            $ReturnData['data'] = 'nocnoc';
//            $ReturnData['url'] = url('/home/Noc/index');
//            return json($ReturnData);
            return apiReturn(['code'=>1006, 'handle_flag'=>self::ORDER_HANDLEFLAG_NOTICE, 'msg'=>'NocNoc TaxId is require.']);
        }
        ######对正常支付与repay支付的区分处理__START############################
        if($_params['order_master_number']){
            //repay,把订单信息写入到$_params['orderInfo']，$_params['order_number'],摸拟新生成订单
            //要把新的支付方式传进去,更新原有的支付方式
            //获取repay支付数据
            $_repay_data = $this->getParamsForRepay($_customer_id, $_params['order_master_number'],$_params['lang'], $_params['currency']);
            if (
                isset($_repay_data['code'])
                && $_repay_data['code'] == 1
                && !empty($_repay_data['data'])
            ){
                $_create_order_res = $_repay_data['data'];
            }else{
                Monlog::write(LOGS_MALL_CART,'info',__METHOD__,__FUNCTION__.$_params['order_master_number'],$_params,MALL_API.'/order/getParamsForRepay_data',$_repay_data);
                return apiReturn(['code'=>1023, 'handle_flag'=>self::ORDER_HANDLEFLAG_NOTICE, 'msg'=>'The pay data is error!']);
            }
            Monlog::write(LOGS_MALL_CART,'info',__METHOD__,__FUNCTION__.$_params['order_master_number'],null,MALL_API.'/home/order/submitOrder_repay_data',$_create_order_res);
            if(!$_create_order_res){
                //缓存已过期，重新去到列表页面
//                $ReturnData['code'] = 1;
//                $ReturnData['msg'] = 'the order data is past due!';
//                $ReturnData['url'] = url('/cart');
//                return json($ReturnData);
                return apiReturn(['code'=>1007, 'handle_flag'=>self::ORDER_HANDLEFLAG_NOTICE, 'msg'=>'The order data is past due!']);
            }
            $_params['orderInfo'] = $_create_order_res['orderInfo'];
            $_create_order_res = $_create_order_res['data'];
            $_params['order_number'] = isset($_create_order_res['data']['master']['order_number'])?$_create_order_res['data']['master']['order_number']:'';
            $order_master_number = $_params['order_number'];
            //如果是多个seller的，需要先调用payment的另一个接口，告知payment订单的合并与拆分情况
            if(count($_create_order_res['data']['slave']) > 1){
                $informOrderRelationRes = $this->PayMent->informOrderRelation($_create_order_res['data'], 2);
                if(!$informOrderRelationRes){
//                    $ReturnData['code'] = 1;
//                    $ReturnData['msg'] = lang('payment_try_again');//'payment is error!!';
//                    $ReturnData['url'] = url('/paymentError?order_number='.$order_master_number.'&reason='.lang('payment_try_again'), false, false);
                    Monlog::write(LOGS_MALL_CART,'error',__METHOD__,__FUNCTION__.$order_master_number,$_create_order_res['data'],MALL_API.'/home/order/submitOrder_repay','PayMent->informOrderRelation-error');
//                    return json($ReturnData);
                    return apiReturn(['code'=>1008, 'handle_flag'=>self::ORDER_HANDLEFLAG_NOTICE, 'msg'=>'Payment is error!!!']);
                }
            }
        }else{
            $_params['is_cod'] =0;
            //正常支付
            //COD货到付款,增加一个标识，标识出他是COD订单
            if($_params['pay_type'] == 'COD'){
                /**根据配置计算COD运费*/
                $_params['is_cod'] = 1;
            }
            //创建订单
            $_params['is_submit_order'] = 1;
            $_create_order_res = $this->OrderService->submitOrder($_params);

            if(!isset($_create_order_res['code']) || $_create_order_res['code'] != 1){
                //返回信息
                if(isset($_create_order_res['data']['orderInfo']['master']['grand_total']) &&
                    $_create_order_res['data']['orderInfo']['master']['grand_total'] ==0){
                    //订单金额为0的，订单状态为200（在创建订单时已经将状态修改为了200）
                    $_params['OrderStatus'] = 200;
                    $_params['order_number'] = isset($_create_order_res['data']['orderInfo']['master']['order_number'])?$_create_order_res['data']['orderInfo']['master']['order_number']:'';
                    $this->paymentSuccessProcessHeader($_params);
//                    $ReturnData['code'] = 1;
                    $ReturnData['order_number'] = $_params['order_number'];
                    $ReturnData['order_total'] = 0;
                    $ReturnData['payment_status'] = 'success';
                    $currency_code = $this->CommonService->getCurrencyCode($_params['currency']);
                    $ReturnData['currency_code'] = isset($currency_code['Code'])?$currency_code['Code']:$currency_code;
//                    $ReturnData['url'] = '/paymentSuccess';
//                    return json($ReturnData);
                    return apiReturn([
                        'code'=>self::API_SECCUSS_CODE,
                        'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                        'url'=>MALL_DOCUMENT.'/mpaymentSuccess?order_number='.$_params['order_number'].'&order_total=0&payment_status=success&currency='.$_params['currency'],
                        //'data'=>$ReturnData,
                        ]);
                }else{
                    $ReturnData['code'] = $_create_order_res['code'];
                    $ReturnData['order_number'] = isset($_create_order_res['data']['orderInfo']['master']['order_number'])?$_create_order_res['data']['orderInfo']['master']['order_number']:'';
                    $ReturnData['order_total'] = isset($_create_order_res['data']['orderInfo']['master']['grand_total'])?$_create_order_res['data']['orderInfo']['master']['grand_total']:0;
                    //$ReturnData['url'] = '/paymentSuccess'; //TODO ???
                    $ReturnData['msg'] = isset($_create_order_res['msg'])?$_create_order_res['msg']:lang('payment_try_again');//'create order is error!';
//                    return json($ReturnData);
                    return apiReturn([
                        'code'=>1009,
                        'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                        'url'=>MALL_DOCUMENT.'/mpaymentError?reason='.urlencode($ReturnData['msg']),
                        'msg'=>$ReturnData['msg']
                    ]);
                }
            }
            $_create_order_res = $_create_order_res['data'];
            $_params['orderInfo'] = $_create_order_res['orderInfo'];
            $_params['order_number'] = isset($_create_order_res['data']['master']['order_number'])?$_create_order_res['data']['master']['order_number']:'';
            $order_master_number = $_params['order_number'];
            //如果是多个seller的，需要先调用payment的另一个接口，告知payment订单的合并与拆分情况
            if(count($_params['orderInfo']['slave']) > 1){
                $informOrderRelationRes = $this->PayMent->informOrderRelation($_params['orderInfo']);
                if(!$informOrderRelationRes){
//                    $ReturnData['code'] = 1;
//                    $ReturnData['msg'] = lang('payment_try_again');//'payment is error!!';
//                    $ReturnData['msg'] = 'payment is error!!';//'payment is error!!';
//                    $ReturnData['url'] = url('/paymentError?order_number='.$order_master_number.'&reason=System Error,try again later', false, false);
                    Monlog::write(LOGS_MALL_CART,'error',__METHOD__,__FUNCTION__.$order_master_number,$_params,MALL_API.'/home/order/submitOrder','PayMent->informOrderRelation-error');
//                    return json($ReturnData);
                    return apiReturn([
                        'code'=>1010,
                        'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                        'url'=>MALL_DOCUMENT.'/mpaymentError?order_number='.$order_master_number.'&reason='.urlencode('System Error,try again later'),
                        'msg'=>'payment is error!!'
                    ]);
                }
            }
        }

        if($_params['pay_type'] == 'COD'){
            //跳出下面的处理逻辑，直接跳到COD的处理方法
//            return json($this->paymentSuccesProcessToCOD($_params));
            return apiReturn($this->paymentSuccesProcessToCOD($_params));
        }

        //测试Ideals
//        $res = $this->PayMent->setIDealTokenCheckout($_params);//IDeal支付
//        print_r($res);
//        exit;
        ######对正常支付与repay支付的区分处理__END############################
        /*当有SC支付的时候，先调用SC支付接口，如果应该金额大于SC的金额，还需要调用相应的支付方式*/
        ###SC支付START#################################################
        //06-22定：当SC不够支付的时候，不给支付
        if($_params['pay_type'] == 'sc'){
            //获取用户的SC金额
            $_params['pay_type'] = 'SC';//重置支付方式跟支付渠道
            $_params['pay_chennel'] = 'SC';
            $_sc_amount = 0;
            //调用SC验证接口,
            $_sc_res = $this->OrderService->checkSC($_params['orderInfo']['master']['grand_total'],$_params['sc_password'],$_params['customer_id'],$_params['currency']);
            //拿到结果后进行汇率转换，判断是否可以使用
            if(!isset($_sc_res['code']) || $_sc_res['code'] != self::API_SECCUSS_CODE || !isset($_sc_res['data'])){
                //出错处理
                $reason = urldecode(isset($_sc_res['msg'])?$_sc_res['msg']:'sc data is error!!');
//                $url = url('/paymentError?order_number='.$_params['order_number'].'&reason='.$reason, false, false);
//                $ReturnData['code'] = 1;
//                $ReturnData['msg'] = isset($_sc_res['msg'])?$_sc_res['msg']:'sc data is error!!';
//                $ReturnData['url'] = $url;
                Monlog::write(LOGS_MALL_CART,'error',__METHOD__,__FUNCTION__.$order_master_number,$_params,MALL_API.'/home/order/submitOrder/checkSC_res',$_sc_res);
//                return json($ReturnData);
                return apiReturn([
                    'code'=>1011,
                    'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                    'url'=>MALL_DOCUMENT.'/mpaymentError?order_number='.$order_master_number.'&reason='.urlencode($reason),
                    'msg'=>$reason
                ]);
            }
            //币种不相同的情况处理
            if($_params['currency'] != $_sc_res['data']['CurrencyType']){
//                $ReturnData['code'] = 1;
//                $ReturnData['msg'] = 'the currency is error!!';
//                $ReturnData['url'] = url('/paymentError?order_number='.$order_master_number.'&reason=the currency is error!!', false, false);
                Monlog::write(LOGS_MALL_CART,'error',__METHOD__,__FUNCTION__.$order_master_number,$_params,MALL_API.'/home/order/submitOrder/sc','the currency is error!!');
//                return json($ReturnData);
                return apiReturn([
                    'code'=>1012,
                    'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                    'url'=>MALL_DOCUMENT.'/mpaymentError?order_number='.$order_master_number.'&reason='.urlencode('The currency is error!!'),
                    'msg'=>'The currency is error!!'
                ]);
            }
            //金额不够的情况处理
            if($_sc_res['data']['UsableAmount'] < $_params['sc_price']){
//                $ReturnData['code'] = 1;
//                $ReturnData['msg'] = lang('payment_try_again');//'sc is underpayment!!';
//                $ReturnData['url'] = url('/paymentError?order_number='.$order_master_number.'&reason='.lang('payment_try_again'), false, false);
                Monlog::write(LOGS_MALL_CART,'error',__METHOD__,__FUNCTION__.$order_master_number,$_params,MALL_API.'/home/order/submitOrder/sc',lang('payment_try_again'));
//                return json($ReturnData);
                return apiReturn([
                    'code'=>1013,
                    'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                    'url'=>MALL_DOCUMENT.'/mpaymentError?order_number='.$order_master_number.'&reason='.urlencode('SC is underpayment!!'),
                    'msg'=>'SC is underpayment!!'
                ]);
            }
            $res = array();
            //判断SC是否足够支付订单所需支付的总金额，如果是，则跳转到支付成功处理函数
            $res = $this->PayMent->doStoreCreditCheckout($_params);
            Monlog::write(LOGS_MALL_CART,'info',__METHOD__,__FUNCTION__.$order_master_number,$_params,MALL_API.'/home/order/submitOrder/doStoreCreditCheckout_res',$res);
            $return = $res['result'];
            if($return->DoStoreCreditCheckoutResult->ResponseResult == 'Success'){
                $_params['OrderTotal'] = isset($res['OrderTotal'])?sprintf("%.2f",$res['OrderTotal']):0;
                $res = $res['result']->DoStoreCreditCheckoutResult;
                //不跳状态，按正常单处理 edit by Carl 2018-08-15 11:14
                $_params['OrderStatus'] = 120;//SC支付的价格直接
                if(isset($res->RiskControlStatus) && $res->RiskControlStatus == 'CRCRisk'){
                    $_params['OrderStatus'] = 100;
                    $_params['risky'] = 1;
                }
                //进入风控的订单
                $_params['TransactionID'] = isset($res->ResponseTransactionInfo->TransactionID)?$res->ResponseTransactionInfo->TransactionID:0;
            }else{
                $res = $res['result']->DoStoreCreditCheckoutResult;
            }
            //先拿到SC的交易号？再和正常支付的交易号一起写到队列里去？
            //判断SC是否足够支付订单所需支付的总金额，如果否，则跳转到相应的支付接口进行支付
        }
        ####SC支付END################################################

        #######paypal支付非快捷支付START###############################

//        if($_params['paypal_create_order'] && $_params['is_paypal_quick'] == 0 && $_params['sc_price'] == 0){//paypal支付比较特殊
        if($_params['pay_type'] == 'PayPal' && $_params['sc_price'] == 0){//paypal支付比较特殊

            if(isset($_create_order_res['data']['slave'])){
                $this->redis->set("App_OrderMasterNumber_".$_params['customer_id'],$_create_order_res['data']['master']['order_number']);//保存用户提交的订单编号信息
                $this->redis->set("App_OrderNumberArr_".$_params['customer_id'],json_encode($_create_order_res['data']['slave']));//保存用户提交的订单编号信息
            }else{
                //生成订单失败操作
//                $ReturnData['code'] = 2;
//                $ReturnData['msg'] = 'order create is error!';
//                return json($ReturnData);
                return apiReturn([
                    'code'=>1014,
                    'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                    'url'=>MALL_DOCUMENT.'/mpaymentError?order_number='.$order_master_number.'&reason='.urlencode('Order create is error!'),
                    'msg'=>'Order create is error!'
                ]);
            }

            $res = $this->PayMent->setExpressCheckout($_params);
            if(!isset($res->SetExpressCheckoutResult->ResponseReult)){
//                $ReturnData['code'] = 1;
//                $ReturnData['msg'] = lang('payment_try_again');//'payment system is error!';
//                $ReturnData['url'] = url('/paymentError?order_number='.$order_master_number.'&reason='.lang('payment_try_again'), false, false);
                Monlog::write(LOGS_MALL_CART,'error',__METHOD__,__FUNCTION__.$order_master_number,$_params,MALL_API.'/home/order/submitOrder/setExpressCheckout_res',$res);
//                return json($ReturnData);
                return apiReturn([
                    'code'=>1015,
                    'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                    'url'=>MALL_DOCUMENT.'/mpaymentError?order_number='.$order_master_number.'&reason='.urlencode('payment system is error!'),
                    'msg'=>'payment system is error!'
                ]);
            }
            if($res->SetExpressCheckoutResult->ResponseReult == 'Success' || $res->SetExpressCheckoutResult->ResponseReult == 'SuccessWithWarning'){
                $_paypal_token = $res->SetExpressCheckoutResult->PreparingTransactionResponseInfo->Token;
                $_url = $res->SetExpressCheckoutResult->Url;
//                $ReturnData['code'] = 1;
//                $ReturnData['data'] = 'success';
//                $ReturnData['url'] = $_url;
//                return json($ReturnData);
                return apiReturn(['code'=>self::API_SECCUSS_CODE, 'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP, 'url'=>$_url, 'msg'=>'Success.']);
            }else{
//                $ReturnData['code'] = 1;
//                $ReturnData['msg'] = lang('payment_try_again');//'payment is error!!';
//                $ReturnData['url'] = url('/paymentError?order_number='.$order_master_number.'&reason='.lang('payment_try_again'), false, false);
                Monlog::write(LOGS_MALL_CART,'error',__METHOD__,__FUNCTION__.$order_master_number,$_params,MALL_API.'/home/order/submitOrder/setExpressCheckout_res',$res);
//                return json($ReturnData);
                return apiReturn([
                    'code'=>1016,
                    'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                    'url'=>MALL_DOCUMENT.'/mpaymentError?order_number='.$order_master_number.'&reason='.urlencode('Payment is error!!'),
                    'msg'=>'Payment is error!!'
                ]);
            }
        }
        #####paypal支付非快捷支付END############################################################################
        $_return_data['order_number'] = $_params['order_number'];
        #################################################################################
        //die();

        if($_params['pay_type'] == 'CreditCard' && $_params['pay_chennel'] == 'EGP' && !$_params['credit_card_token_id'] && $_params['sc_price'] == 0){
            $res = array();
            $res = $this->PayMent->doCreditCheckout($_params);//信用卡支付(非Token)
            $return = $res['result'];
            if (
                property_exists($return, 'DoCreditCheckoutResult')
            ){
                if($return->DoCreditCheckoutResult->ResponseResult == 'Success'){
                    $_params['OrderTotal'] = isset($res['OrderTotal'])?sprintf("%.2f",$res['OrderTotal']):0;
                    $res = $res['result']->DoCreditCheckoutResult;
                    if(isset($res->RiskControlStatus) && $res->RiskControlStatus == 'CRCRisk'){
                        $_params['risky'] = 1;
                    }//进入风控的订单
                    $_params['TransactionID'] = isset($res->ResponseTransactionInfo->TransactionID)?$res->ResponseTransactionInfo->TransactionID:0;
                }else{
                    $res = $res['result']->DoCreditCheckoutResult;
                }
            }else{
                return apiReturn([
                    'code'=>1021,
                    'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                    'url'=>MALL_DOCUMENT.'/mpaymentError?order_number='.$order_master_number.'&reason='.urlencode('CreditCard PayMent is error.'),
                    'msg'=>'CreditCard PayMent is error.'
                ]);
            }
        }

        if($_params['pay_type'] == 'CreditCard' && $_params['pay_chennel'] == 'EGP' && $_params['credit_card_token_id'] && $_params['sc_price'] == 0){
            $res = array();
            $res = $this->PayMent->doCreditCardTokenCheckout($_params);//信用卡支付EGP(Token)
            $return = $res['result'];
            if($return->DoCreditCardTokenCheckoutResult->ResponseResult == 'Success'){
                $_params['OrderTotal'] = isset($res['OrderTotal'])?sprintf("%.2f",$res['OrderTotal']):0;
                $res = $res['result']->DoCreditCardTokenCheckoutResult;
                if(isset($res->RiskControlStatus) && $res->RiskControlStatus == 'CRCRisk'){
                    $_params['risky'] = 1;
                }//进入风控的订单
                $_params['TransactionID'] = isset($res->ResponseTransactionInfo->TransactionID)?$res->ResponseTransactionInfo->TransactionID:0;
            }else{
                $res = $res['result']->DoCreditCardTokenCheckoutResult;
            }
        }

        if($_params['pay_type'] == 'WebMoney' && $_params['sc_price'] == 0){
            $res = array();
            $res = $this->PayMent->setWebMoneyCheckout($_params);//WebMoney
            $return = $res['result'];
            if($return->SetWebMoneyCheckoutResult->ResponseResult == 'Success'){
                //webmoney支付成功
//                $ReturnData['code'] = 1;
//                $ReturnData['data'] = 'success';
//                $ReturnData['url'] = $return->SetWebMoneyCheckoutResult->Url;
//                return json($ReturnData);
                return apiReturn([
                    'code'=>self::API_SECCUSS_CODE,
                    'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                    'url'=>$return->SetWebMoneyCheckoutResult->Url,
                    'msg'=>'Success.'
                ]);
            }else{
//                $ReturnData['code'] = 1;
//                $ReturnData['data'] = lang('payment_try_again');//'payment is error';
//                $ReturnData['url'] = url('/paymentError?order_number='.$order_master_number.'&reason='.lang('payment_try_again'), false, false);
                Monlog::write(LOGS_MALL_CART,'error',__METHOD__,__FUNCTION__.$order_master_number,$_params,MALL_API.'/home/order/submitOrder/setWebMoneyCheckout_res',$res);
//                return json($ReturnData);
                return apiReturn([
                    'code'=>1017,
                    'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                    'url'=>MALL_DOCUMENT.'/mpaymentError?order_number='.$order_master_number.'&reason='.urlencode('Payment is error!!'),
                    'msg'=>'Payment is error!!'
                ]);
            }
        }

        if($_params['pay_type'] == 'CreditCard' && $_params['pay_chennel'] == 'Asiabill' && !$_params['credit_card_token_id'] && $_params['sc_price'] == 0){
            $res = array();
            $res = $this->PayMent->doGlobebillCreditCardCheckout($_params);//信用卡支付聚宝Asiabill(非Token)
            $return = $res['result'];
            if($return->DoGlobebillCreditCardCheckoutResult->ResponseResult == 'Success'){
                $_params['OrderTotal'] = isset($res['OrderTotal'])?sprintf("%.2f",$res['OrderTotal']):0;
                $res = $res['result']->DoGlobebillCreditCardCheckoutResult;
                if(isset($res->RiskControlStatus) && $res->RiskControlStatus == 'CRCRisk'){
                    $_params['risky'] = 1;
                }//进入风控的订单
                $_params['TransactionID'] = isset($res->ResponseTransactionInfo->TransactionID)?$res->ResponseTransactionInfo->TransactionID:0;
            }else{
                $res = $res['result']->DoGlobebillCreditCardCheckoutResult;
            }
        }

        if($_params['pay_type'] == 'CreditCard' && $_params['pay_chennel'] == 'Asiabill' && $_params['credit_card_token_id'] && $_params['sc_price'] == 0){
            $res = array();
            $res = $this->PayMent->doGlobebillCreditCardTokenCheckout($_params);//信用卡支付聚宝Asiabill(Token)
            $return = $res['result'];
            if($return->DoGlobebillCreditCardTokenCheckoutResult->ResponseResult == 'Success'){
                $_params['OrderTotal'] = isset($res['OrderTotal'])?sprintf("%.2f",$res['OrderTotal']):0;
                $res = $res['result']->DoGlobebillCreditCardTokenCheckoutResult;
                if(isset($res->RiskControlStatus) && $res->RiskControlStatus == 'CRCRisk'){
                    $_params['risky'] = 1;
                }//进入风控的订单
                $_params['TransactionID'] = isset($res->ResponseTransactionInfo->TransactionID)?$res->ResponseTransactionInfo->TransactionID:0;
            }else{
                $res = $res['result']->DoGlobebillCreditCardTokenCheckoutResult;
            }
        }

        if($_params['pay_chennel'] == 'Astropay' && $_params['sc_price'] == 0){
            $_params['payment_method'] = $this->CheckoutService->astropayPaymentMethodTrans($_params['pay_type']);
            $res = $this->PayMent->SetAstropayCheckout($_params);//Astropay支付
            $this->packagingAstropayReturnParams($_params);
            $return = $res['result'];
            if($return->SetAstropayCheckoutResult->ResponseResult == 'Success'){
                if($_params['pay_type'] == 'Boleto-Astropay'){
                    $_params['boleto_url'] = $return->SetAstropayCheckoutResult->PaymentUrl;
                }
                $_params['redirect'] = 0;
                $this->paymentSuccessProcessHeader($_params);
//                $ReturnData['code'] = 1;
//                $ReturnData['data'] = 'success';
//                $ReturnData['url'] = $return->SetAstropayCheckoutResult->PaymentUrl;
//                return json($ReturnData);
                return apiReturn([
                    'code'=>self::API_SECCUSS_CODE,
                    'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                    'url'=>$return->SetAstropayCheckoutResult->PaymentUrl,
                    'msg'=>'Success.'
                ]);
            }else{
                $res = $res['result']->SetAstropayCheckoutResult;
            }
        }

        ######快捷支付的订单提交功能DO阶段#################################################################
        if($_params['is_paypal_quick'] == 1 && $_params['sc_price'] == 0){
            //快捷支付DO阶段调用
            $resDo = $this->PayMent->QuickDoExpressCheckout($_params);
            Monlog::write(LOGS_MALL_CART,'error',__METHOD__,__FUNCTION__,$_params,MALL_API.'/home/order/submitOrder/QuickDoExpressCheckout_res','QuickDoExpressCheckout_res:'.json_encode($resDo));
            if($resDo->DoExpressCheckoutResult->ResponseResult == 'Success' || $resDo->DoExpressCheckoutResult->ResponseResult == 'SuccessWithWarning'){
                $_params['TransactionID'] = $resDo->DoExpressCheckoutResult->TransactionResponseInfo->TransactionID;
                $_params['redirect'] = 0;
                $_params['payment_status'] = 'Success';
                if($resDo->DoExpressCheckoutResult->ResponseResult == 'SuccessWithWarning'){
                    $_params['risky'] = 1;//进入风控的订单
                }
                $ReturnData = $this->paymentSuccesProcess($_params);
                //return json($ReturnData);
                return apiReturn([
                    'code'=>self::API_SECCUSS_CODE,
                    'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                    'url'=>MALL_DOCUMENT.'/mpaymentSuccess?order_number='.$ReturnData['order_number'].'&order_total='.$ReturnData['order_total'].'&payment_status='.$ReturnData['payment_status'].'&currency_code='.$ReturnData['currency_code'],

                    /*'data'=>[
                        'currency_code'=>$ReturnData['currency_code'],
                        'order_number'=>$ReturnData['order_number'],
                        'order_total'=>$ReturnData['order_total'],
                        'payment_status'=>$ReturnData['payment_status'],
                    ]*/
                ]);
            }else{
                //失败，跳往支付失败页面
//                $ReturnData['code'] = 1;
//                $ReturnData['reason'] = $resDo->DoExpressCheckoutResult->Error->LongMessage;
                //$ReturnData['url'] = url('/home/order/paymentError', '', true, true);
//                $ReturnData['url'] = url('/paymentError?order_number='.$order_master_number.'&reason='.$resDo->DoExpressCheckoutResult->Error->LongMessage, false, false);
                Monlog::write(LOGS_MALL_CART,'error',__METHOD__,__FUNCTION__.$order_master_number,$_params,MALL_API.'/home/order/submitOrder/QuickDoExpressCheckout_res',$resDo);
//                return json($ReturnData);

                return apiReturn([
                    'code'=>1018,
                    'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                    'url'=>MALL_DOCUMENT.'/mpaymentError?order_number='.$order_master_number.'&reason='.urlencode($resDo->DoExpressCheckoutResult->Error->LongMessage),
                    'msg'=>$resDo->DoExpressCheckoutResult->Error->LongMessage
                ]);
            }
        }
        if($_params['pay_type'] == 'IDeal' && $_params['sc_price'] == 0){
            $res = $this->PayMent->setIDealTokenCheckout($_params);//IDeal支付
            Monlog::write(LOGS_MALL_CART,'error',__METHOD__,__FUNCTION__.$order_master_number,$_params,MALL_API.'/home/order/submitOrder/setIDealTokenCheckout_res',json_encode($res));
            exit; //TODO  等待对接
            return json($res['data']);//Ideal支付返回的数据不一样,特殊处理
        }

        //Monlog::write(LOGS_MALL_CART,'info',__METHOD__,__FUNCTION__,[1],MALL_API.'/home/order/submitOrder11111','33345345');
        if($res->ResponseResult == 'Success'){
            //成功处理机制
            $_params['redirect'] = 0;//不能直接跳转，需返回给ajax进行跳转
            $_params['payment_status'] = 'Success';
            //Monlog::write(LOGS_MALL_CART,'info',__METHOD__,__FUNCTION__,[2],MALL_API.'/home/order/submitOrder144','34444444');
            $_result = $this->paymentSuccesProcess($_params);
            Monlog::write(LOGS_MALL_CART,'info',__METHOD__,__FUNCTION__.$order_master_number,$_params,MALL_API.'/home/order/submitOrder/paymentSuccesProcess_res',json($_result));
//            return $_result;
            return apiReturn([
                'code'=>self::API_SECCUSS_CODE,
                'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                'url'=>MALL_DOCUMENT.'/mpaymentSuccess?order_number='.$_result['order_number'].'&order_total='.$_result['order_total'].'&payment_status='.$_result['payment_status'].'&currency_code='.$_result['currency_code'],
                /*
                'data'=>[
                    'currency_code'=>$_result['currency_code'],
                    'order_number'=>$_result['order_number'],
                    'order_total'=>$_result['order_total'],
                    'payment_status'=>$_result['payment_status'],
                ]*/
            ]);
        }else{
            //错误处理机制
            $_error_reason = $res->Error->Code.' '.$res->Error->LongMessage;
            $ReturnData['code'] = 1;
            $ReturnData['reason'] = $_error_reason;
            $ReturnData['order_number'] = $_params['order_number'];
            //$ReturnData['url'] = '/home/order/paymentError';
            $ReturnData['url'] = url('/paymentError?order_number='.$order_master_number.'&reason='.$res->Error->Code.' '.$res->Error->LongMessage, false, false);
            $_result = json($ReturnData);
            Monlog::write(LOGS_MALL_CART,'info',__METHOD__,__FUNCTION__.$order_master_number,$_params,MALL_API.'/home/order/submitOrder11',$ReturnData);
//            return $_result;
            return apiReturn([
                'code'=>1019,
                'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                'url'=>MALL_DOCUMENT.'/mpaymentError?order_number='.$order_master_number.'&reason='.urlencode($_error_reason),
                'msg'=>$_error_reason
            ]);
        }
    }

    /**
     * COD货到付款支付处理方法
     * @param $Params
     * @return mixed
     */
    private function paymentSuccesProcessToCOD($Params){
        if(!isset($Params['OrderTotal']) || !$Params['OrderTotal']){
            if(isset($Params['orderInfo']['master']['total_amount'])){
                $Params['OrderTotal'] = $Params['orderInfo']['master']['total_amount'];
            }
        }
        $data['OrderNumber'] = isset($Params['OrderNumber'])?$Params['OrderNumber']:$Params['order_number'];
        $data['OrderTotal'] = isset($Params['OrderTotal'])?$Params['OrderTotal']:0;
        $data['OrderTotal'] = sprintf("%.2f", $data['OrderTotal']);

//        $json_data = json_encode($data);
//
//        $this->CommonService->loadRedis()->lPush("mall_order_success_cod", $json_data);
        //不跳转的，直接返回给ajax
        $returnData['code'] = self::API_SECCUSS_CODE;
        $returnData['data']['order_number'] = $data['OrderNumber'];
        $returnData['data']['order_total'] = $data['OrderTotal'];
        $returnData['data']['payment_status'] = 'COD';
        $currency_code = $this->CommonService->getCurrencyCode($Params['currency']);
        $returnData['data']['currency_code'] = isset($currency_code['Code'])?$currency_code['Code']:$currency_code;
//        $returnData['url'] = '/paymentSuccess';
        return $returnData;

    }

    /**
     * 封装Astropay返回来需要使用到的参数
     * 因为回来的接口并不返回我们显示需要用到的参数
     * 先写到cookie里去
     * @param $_params
     */
    private function packagingAstropayReturnParams($_params){
        $_astropay_params['order_number'] = $_params['orderInfo']['master']['order_number'];
        $_astropay_params['order_total'] = $_params['orderInfo']['master']['grand_total'];
        Cookie::set('AstropayReturnParams',$_astropay_params);
    }

    /**
     * @param $Params 支付成功后组装数据进入队列
     * @param null $data
     */
    private function paymentSuccessProcessHeader($Params,&$data=null){
        //写入消息对列，需要处理的信息
        if(!isset($Params['OrderTotal']) || !$Params['OrderTotal']){
            if(isset($Params['orderInfo']['master']['total_amount'])){
                $Params['OrderTotal'] = $Params['orderInfo']['master']['total_amount'];
            }
        }
        //注意进入风控的订单(EGP订单全部都会进入风控)
        $data['Items'] = isset($Params['Items'])?$Params['Items']:array();
        $data['PayerEmail'] = isset($Params['PayerEmail'])?$Params['PayerEmail']:$Params['email'];
        $data['ShippingAddress'] = isset($Params['ShippingAddress'])?$Params['ShippingAddress']:'';
        $data['OrderNumber'] = isset($Params['OrderNumber'])?$Params['OrderNumber']:$Params['order_number'];
        $data['OrderTotal'] = isset($Params['OrderTotal'])?$Params['OrderTotal']:0;
        $data['OrderTotal'] = sprintf("%.2f", $data['OrderTotal']);
        $data['OrderStatus'] = isset($Params['OrderStatus'])?$Params['OrderStatus']:120;
        $data['TransactionID'] = isset($Params['TransactionID'])?$Params['TransactionID']:'';
        $data['customer_id'] = isset($Params['customer_id'])?$Params['customer_id']:'';
        $syncOmsQueueData['order_number'] = $data['OrderNumber'];
        $data['risky'] = $syncOmsQueueData['risky'] = isset($Params['risky'])?$Params['risky']:0;//是否进入风控的订单,1表示进入风控，0表示未进入风控
        $data['boleto_url'] = isset($Params['boleto_url'])?$Params['boleto_url']:'';
        $json_data = json_encode($data);
        $json_sync_oms_queue_data = json_encode($syncOmsQueueData);
        if(isset($Params['affiliate']) && $Params['affiliate']){
            $carts_history_affiliate_record_data['order_master_number'] = $data['OrderNumber'];
            $carts_history_affiliate_record_data = json_encode($carts_history_affiliate_record_data);
            //此处是为了兼容旧系统DAP计算积分
            $this->redis->lPush("carts_history_affiliate_record", $carts_history_affiliate_record_data);
        }
        Monlog::write(LOGS_MALL_CART,'info',__METHOD__,__FUNCTION__,['OrderStatus'=>$data['OrderStatus']],'order/paymentSuccessProcessHeader',$json_data);
        /**订单状态先调用API(暂时先不做，调API的速度跟放进队列直接写数据库的速度差不多)*/
        $this->redis->lPush("mall_order_success_transactionid", $json_data);//订单状态变化队列
        $this->redis->lPush("createOrderSyncOMS3", $json_sync_oms_queue_data);//创建OMS订单队列
        $this->redis->lPush("mall_order_success_active", $json_data);//？
        $this->redis->lPush("mall_order_success_send_message", $json_data);//发邮件队列
        $this->redis->lPush("mall_order_success_send_affiliate", $json_data);//计算Affiliate 佣金队列
        $this->redis->lPush("mall_order_success_order_points", $json_data);//计算积分队列
        //affiliate js跳转
        Cookie::set('affiliate_order',$data['OrderNumber']);
//        $this->CommonService->loadRedis()->lPush("affiliate_js_order_lists", $json_data);
    }

    /**
     * 支付成功跳转页面
     * 写入消息对列，需要处理的信息
     * 1:订单状态修改,写入交易唯一编号,库存扣减，佣金计算与写入
     * 2:具体业务里要判断是否是活动产品，如果参与活动的 库存已扣完，则要调用接口对该场景进行处理，也就是把活动状态改为已停止
     * 3:发送邮件通知用户
     * redis消息队列没有写成功的处理机制?(发送报告和补偿机制)
     */
    private function paymentSuccesProcess($Params){
        $data = array();
        $this->paymentSuccessProcessHeader($Params,$data);
        $currency_code = $this->CommonService->getCurrencyCode(isset($Params['currency'])?$Params['currency']:$Params['CurrencyCode']);
        $returnData['currency_code'] = isset($currency_code['Code'])?$currency_code['Code']:$currency_code;
        /** 快捷支付需要记录快捷支付地址 start **/
        $is_paypal_quick = isset($Params['is_paypal_quick'])?$Params['is_paypal_quick']:0;
        if ($is_paypal_quick == 1){
            $paypal_quick_adress_params = $this->redis->get('paypal_quick_adress_params');
            if (!empty($paypal_quick_adress_params)){
                $CustomerID = isset($paypal_quick_adress_params['CustomerID'])?$paypal_quick_adress_params['CustomerID']:0;
                $addres_res = $this->CheckoutService->editUserAddress($CustomerID, $paypal_quick_adress_params);
                $order_master_number = isset($Params['order_number'])?$Params['order_number']:'';
                Monlog::write(LOGS_MALL_CART,'info',__METHOD__,'submitOrder'.$order_master_number,$paypal_quick_adress_params,MALL_API.'/home/order/submitOrder/paypal_quick_editUserAddress_res',$addres_res);
                Log::record('paypal_quick_adress_params,params:'.json_encode($paypal_quick_adress_params).', res:'.json_encode($addres_res));
                $this->redis->rm('paypal_quick_adress_params');
            }
        }
        /** 快捷支付需要记录快捷支付地址 end **/

        //返回前端处理成功信息
        if($Params['redirect']){
            //跳转
            header('Location:/paymentSuccess?order_number='.$data['OrderNumber'].'&order_total='.$data['OrderTotal'].'&currency_code='.$returnData['currency_code']);
            //$this->redirect('Order/paymentSuccess',['order_number' => $data['OrderNumber'],'order_total' => $data['OrderTotal'],'currency_code'=>$returnData['currency_code']]);
        }
        //不跳转的，直接返回给ajax
        $returnData['code'] = 1;
        $returnData['order_number'] = $data['OrderNumber'];
        $returnData['order_total'] = $data['OrderTotal'];
        $returnData['payment_status'] = $Params['payment_status'];
        $returnData['url'] = '/paymentSuccess';
        return $returnData;
    }

    /**
     * repay支付获取参数数据
     * @return \think\response\Json
     */
    private function getParamsForRepay($customer_id, $order_master_number, $lang, $currency){
        $_params['customer_id'] = $customer_id;
        $_params['order_master_number'] = $order_master_number;
        $_params['Lang'] = $lang;
        $_params['Currency'] = $currency;
        $res = $this->OrderService->getParamsForRepay($_params);
        if(isset($res['code']) && $res['code'] == 1){
            /*$Currency = $res['currency'];
            $CurrencyCode = $this->CommonService->getCurrencyCode($Currency);
            $ReturnData['currencyCode'] = isset($CurrencyCode['Code'])?$CurrencyCode['Code']:$CurrencyCode;*/
            $ReturnData['code'] = 1;
            $ReturnData['data'] = $res['data'];
        }else{
            $ReturnData['code'] = 0;
            $ReturnData['msg'] = isset($res['msg'])?$res['msg']:'error';
        }
        return $ReturnData;
    }

    public function testSign(){

        $str = 'CVVCode=100&CardNumber=4111111111111111&City=hcb jjj&CityCode=hcbjjj&Country=BR&CountryCode=ES&Currency=USD&CustomerAddressId=182&CustomerId=121&Email=cxjjkh@qq.com&ExpireMonth=11&ExpireYear=22&FirstName=gcjbvvccvvb&IssuingBank=&Lang=en&LastName=gxcbbj&Mobile=ghvcchj&OrderFrom=30&PayChennel=EGP&PayType=CreditCard&Phone=ghvcchj&PostalCode=gcbbv&ProvinceCode=AO&ShipTo=BR&State=AO&Street1=jghkjxf&Street2=ffvbvdg&saveCard=0&secret_key=071e76472162c0825a81de45031acd47';

        $arr['name'] = 'si lang';
        $arr['sex'] = '2';
        $arr['tex'] = 'This is a test text.';

        echo urldecode(http_build_query($arr));die;
        echo strtoupper(md5(urldecode($str)));

        die;

        $params = request()->post();
        $validate = $this->validate($params,(new OrderParams())->submitPayRules());
        if (true !== $validate){
            return apiReturn(['code'=>1002, 'handle_flag'=>self::ORDER_HANDLEFLAG_NOTICE, 'msg'=>$validate]);
        }
        //sign权限校验。签名：strtoupper(MD5(秘钥+参数排序[参数名ASCII字典序排序]))
        $_sign = $params['Sign'];
        unset($params['Sign']);
        if(isset($params['access_token'])){
            unset($params['access_token']);
        }


        if (!$this->OrderService->verifyPaySign($_sign, $params)){
            //自测时可关闭，和APP联调和上线时要开启
            return apiReturn(['code'=>1003, 'handle_flag'=>self::ORDER_HANDLEFLAG_NOTICE, 'msg'=>'You do not have rights to complete the operation.']);
        }


    }


    /**
     * 改变订单状态
     * @return mixed
     */
    public function realChangeOrderStatus(){
        $params = request()->post();
        $validate = $this->validate($params,(new OrderParams())->realChangeOrderStatusRules());
        if(true !== $validate){
            return apiReturn(['code'=>2001, 'msg'=>$validate]);
        }
        try{
            $orderModel = model("orderfrontend/OrderModel");
            $order_where["customer_id"] = $params['customer_id'];
            $order_where["order_number"] = $params['order_number'];
            $order_basics = $orderModel->getOrderBasics($order_where);
            if($order_basics){
                $update['order_status_from'] = $order_basics['order_status'];
                $update['order_id'] = $order_basics['order_id'];
                $update['create_on'] = time();
                $update['create_by'] = "customer,username:".$order_basics['customer_name'];
                if($params['order_status']>900 && $params['order_status']<1400){
                    $update['order_branch_status'] = $params['order_status'];
                    $update['order_status'] = $order_basics['order_status'];
                }else{
                    $update['order_status'] = $params['order_status'];
                }
                $update['change_reason'] = $params['change_reason'];
                $update['order_status'] = $params['order_status'];
                $update['chage_desc'] = $params['change_reason'];
                $update['create_ip'] = $params['create_ip'];
            }else{
                return apiReturn(['code'=>1002, 'msg'=>'Order does not exist']);
            }
            $res = $orderModel->updateOrderStatus($update);
            if ($res){
                return apiReturn(['code'=>200]);
            }else{
                return apiReturn(['code'=>1001, 'msg'=>'修改状态失败']);
            }
        }catch (\Exception $e){
            $msg = $e->getMessage();
            Log::record('realChangeOrderStatus异常：'.$msg);
            return apiReturn(['code'=>1002, 'msg'=>$msg]);
        }
    }
}
