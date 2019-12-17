<?php
namespace app\app\controller;
use app\app\model\SalesOrder;
use app\common\helpers\CommonLib;
use app\app\services\CheckoutService;
use app\app\services\CommonService;
use app\app\services\NocService;
use app\app\services\OrderService;
use app\app\services\PaymentService;
use app\app\services\CartService;
use app\common\controller\AppBase;
use app\common\helpers\RedisClusterBase;
use app\common\params\app\OrderParams;
use app\share\controller\Region;
use app\share\model\DxRegion;
use think\Cache;
use think\Cookie;
use think\Log;
use think\Monlog;
use app\common\services\logService;
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
    public $CartService;
    public $redis;
    public $PayMent;
    const PAYMENT_SYSTEM_OLD = 1;
    const PAYMENT_SYSTEM_NEW = 2;
    const PAYMENT_EXCEPTION_CODE_NEW = 2000;
    const PAYMENT_EXCEPTION_CODE = '0x8004FFFF';
//    public $rateService;
//    public $productService;
    public function __construct()
    {
        parent::__construct();
        $this->CheckoutService = new CheckoutService();
        $this->CommonService = new CommonService();
        $this->redis = new RedisClusterBase();
        $this->CartService = new CartService();
//      $this->productService = new ProductService();
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
        $_params['sc_password'] = isset($params['sc_password'])?$params['sc_password']:'';
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
        $_params['bic'] = input("bic")?input("bic"):'';//新的payment Ideal支付专有

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
        $_params['BillingAddress']['CountryCode'] = isset($params['CountryCode'])?trim($params['CountryCode']):'';
        $_params['BillingAddress']['Email'] = isset($params['Email'])?$params['Email']:'';
        $_params['BillingAddress']['FirstName'] = isset($params['FirstName'])?$params['FirstName']:'';
        $_params['BillingAddress']['LastName'] = isset($params['LastName'])?$params['LastName']:'';
        $_params['BillingAddress']['Mobile'] = isset($params['Mobile'])?$params['Mobile']:'';
        $_params['BillingAddress']['Phone'] = isset($params['Phone'])?$params['Phone']:'';
        $_params['BillingAddress']['PostalCode'] = isset($params['PostalCode'])?$params['PostalCode']:'';
        //洲名获取
        $DxRegion=new DxRegion();
        $StateName=$DxRegion->getState($params);
        Log::record('getState$Country4'.json_encode($StateName));
        $_params['BillingAddress']['State'] = !empty($StateName)?$StateName:'';
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
        $_params['NocNoc'] = input('NocNoc')?input('NocNoc'):0;

        $_params['CVVCode'] = isset($params['CVVCode'])?$params['CVVCode']:'956';
        //测试repay
        //$_params['order_master_number'] = '180610019742134905';
        //如果是快捷支付的，没有使用客户的地址信息，而是使用了从paypal带过来的地址
        $_paypal_address = isset($params['PaypalQuickAddress'])?$params['PaypalQuickAddress']:null;

        if(is_array($_paypal_address)){
            $_params['ShippingAddress']['City'] = isset($_paypal_address['city'])?$_paypal_address['city']:'';
            $_params['ShippingAddress']['CityCode'] = isset($_paypal_address['cityCode'])?$_paypal_address['cityCode']:'';
            $_params['ShippingAddress']['Country'] = isset($_paypal_address['country'])?$_paypal_address['country']:'';
            $_params['ShippingAddress']['CountryCode'] = isset($_paypal_address['countryCode'])?trim($_paypal_address['countryCode']):'';
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
//            $_check_nocnoc = $this->NocService->checkNocNoc($_params, 2);
//            if($_check_nocnoc){
//                $_params['NocNoc'] = 1;
//            }else{
//                $_params['NocNoc'] = 0;
//            }
            $_params['NocNoc'] = 0;
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
     * 商城的定义：
     *      code：
     *      0-刷新页面，1-跳转，2-文案提示，3-地址数据校验不通过，需要通知前端弹出地址输入框，再次填写收货地址，4-IDeal相关， 5-SC支付验证错误，需要将提示信息展示在SC密码框附近
     *
     * @return mixed
     */
    public function submitPayV2(){
        return;
        $params = request()->post();

        if(!empty($params['ShipTo'])){
            $params['ShipTo']=trim($params['ShipTo']);
        }
        if(!empty($params['CountryCode'])){
            $params['CountryCode']=trim($params['CountryCode']);
        }
        if(!empty($params['CPF'])){
            $params['CPF']=htmlspecialchars_decode($params['CPF']);
        }
        $UserName=!empty($params['UserName'])?!empty($params['UserName']):'';
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
        //增加去掉地址2再进行签名校验规则，为了解决老版本：客户端生成签名时去掉地址二（为空的情况），而后端生成签名没去掉空的地址二导致签名不一致的问题 tinghu.liu 20190718
        $sign_params = $params;
        unset($sign_params['Street2']);
        //默认数据
        $ReturnData['code'] = 0;
        $ReturnData['order_number'] = 0;
        $ReturnData['order_total'] = 0;
        $ReturnData['currency'] = '';
        $ReturnData['currency_code'] = '';
        $ReturnData['payment_status'] = '';
        $ReturnData['msg'] = '';

        if (
            !$this->OrderService->verifyPaySign($_sign, $params)
            && !$this->OrderService->verifyPaySign($_sign, $sign_params)
        ){
            //Log::record('verifyPaySign-params:'.print_r($params, true),'error');
            //自测时可关闭，和APP联调和上线时要开启
            //return apiReturn(['code'=>1003, 'handle_flag'=>self::ORDER_HANDLEFLAG_NOTICE, 'msg'=>'You do not have rights to complete the operation.']);
        }
        //支付方式 PayType 有效性校验
        /*
        if (!in_array($params['PayType'],config('app_allow_pay_type'))){
            return apiReturn(['code'=>1020, 'handle_flag'=>self::ORDER_HANDLEFLAG_NOTICE, 'msg'=>'Illegal payment.','data'=>$ReturnData]);
        }
        //支付渠道 PayChennel 有效性校验
        if (!in_array($params['PayChennel'],config('app_allow_pay_chennel'))){
            return apiReturn(['code'=>1022, 'handle_flag'=>self::ORDER_HANDLEFLAG_NOTICE, 'msg'=>'Illegal payment.']);
        }*/
        //判断币种有效性
        $params['Currency'] = $this->CommonService->verifyCurrency(input("Currency"));
        //判断语种有效性
        $params['Lang'] = $this->CommonService->verifyLang(input("Lang"));
        //判断国家有效性
        $params['ShipTo'] = $this->CommonService->verifyCountry(input("ShipTo"));

        //判断用户是否有效且获取用户邮箱
        //$check_user_result = doCurl(CIC_API.'/cic/Customer/GetCustomerInfoByAccount',['AccountName'=>$params['CustomerEmail']], null, true);
        $check_user_result = doCurl(CIC_APP.'/cic/Customer/GetEmailsByCID',['id'=>$params['CustomerId']], null, true);
        Log::record('check_user_result:'.print_r($check_user_result, true));
        if(
            !isset($check_user_result['code'])
            || $check_user_result['code'] != self::API_SECCUSS_CODE
            || !isset($check_user_result['data'])
            || empty($check_user_result['data'])
        ){
            return apiReturn(['code'=>1004, 'handle_flag'=>self::ORDER_HANDLEFLAG_NOTICE, 'msg'=>'User information error.','data'=>$ReturnData]);
        }
        $params['CustomerEmail'] = $check_user_result['data'];

        //调用接收参数接口
        $this->submitOrderCommon($params);
        $_customer_id = $params['CustomerId'];
        $_params = json_decode($this->redis->get("payParams_".$_customer_id),true);
        $pay_chennel=strtolower($_params['pay_chennel']);
        /*如果使用新的payment系统进行支付，则走新payment支付流程，否则走之前的支付逻辑 */
        $new_payment=config('new_payment');

        if( in_array($pay_chennel,$new_payment)) {
            $payment_system_for_repay_update= self::PAYMENT_SYSTEM_NEW;
        }else{
            $payment_system_for_repay_update= self::PAYMENT_SYSTEM_OLD;
        }
        //submitPayV2使用新版Astropay
        if($_params['pay_chennel'] == 'Astropay') {
          $payment_system_for_repay_update= self::PAYMENT_SYSTEM_NEW;
        }
        //入库的使用支付系统标识以$payment_system_for_repay_update为准
        $_params['payment_system'] = $payment_system_for_repay_update;
        if($_params['pay_type'] == 'IDeal') {
            if (empty($_params['bic'])) {
                $res['code'] = 2;
                $res['msg'] = 'Please select the bank information.';
                return json($res);
            }
        }
        //判断用户收货地址ID有效性
        $check_address_result = doCurl(CIC_APP.'/cic/address/getAddress',['CustomerID'=>$params['CustomerId'], 'AddressID'=>$params['CustomerAddressId']], null, true);
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
            return apiReturn(['code'=>1005, 'handle_flag'=>self::ORDER_HANDLEFLAG_NOTICE, 'msg'=>'User address error.','data'=>$ReturnData]);
        }

        //如果是NOC，需要跳转到TaxId输入页面，如果是已经传了TaxId的，则不需要跳到TaxId输入页面，直接走下面的流程（因为有TaxId，说明已经询价过了，这里不需要再处理）
        $tax_id = isset($params['NocNocTaxId'])?$params['NocNocTaxId']:'';
        $_params['nocnoc_tax_id'] = $tax_id;
        if($_params['NocNoc'] == 1 && empty($tax_id)){
            //Cookie::set('nocSubmitOrderParams', input(), 60*30);
            //跳到输入TaxId的页面
            $ReturnData['code'] = 1;
//            $ReturnData['data'] = 'nocnoc';
//            $ReturnData['url'] = url('/home/Noc/index');
//            return json($ReturnData);
            return apiReturn(['code'=>1006, 'handle_flag'=>self::ORDER_HANDLEFLAG_NOTICE, 'msg'=>'NocNoc TaxId is require.','data'=>$ReturnData]);
        }
        //20181221 如果是NOC，需要判断NOC运费是否正确，不正确不让提交
        if ($_params['NocNoc'] == 1){
            if(isset($_params['is_buynow']) && $_params['is_buynow']){
                $_cart_info = $this->CommonService->loadRedis()->get("ShoppingCartBuyNow_".$_customer_id);
            }else{
                $_cart_info = $this->CommonService->loadRedis()->get("ShoppingCart_CheckOut".$_customer_id);
            }
            if (!isset($_cart_info[$_customer_id]['nocdata'])){
                $_return_data['code'] = 2;
                $_return_data['msg'] = 'Nocnoc shipping fee error.';
                return json($_return_data);
            }
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
                return apiReturn(['code'=>1023, 'handle_flag'=>self::ORDER_HANDLEFLAG_NOTICE, 'msg'=>'The pay data is error!','data'=>$ReturnData]);
            }
            Monlog::write(LOGS_MALL_CART,'info',__METHOD__,__FUNCTION__.$_params['order_master_number'],null,MALL_API.'/home/order/submitOrder_repay_data',$_create_order_res);
            if(!$_create_order_res){
                //缓存已过期，重新去到列表页面
                $ReturnData['code'] = 1;
//                $ReturnData['msg'] = 'the order data is past due!';
//                $ReturnData['url'] = url('/cart');
//                return json($ReturnData);
                return apiReturn(['code'=>1007, 'handle_flag'=>self::ORDER_HANDLEFLAG_NOTICE, 'msg'=>'The order data is past due!','data'=>$ReturnData]);
            }
            $_params['orderInfo'] = $_create_order_res['orderInfo'];
            $_create_order_res = $_create_order_res['data'];
            $_params['order_number'] = isset($_create_order_res['data']['master']['order_number'])?$_create_order_res['data']['master']['order_number']:'';
            $ReturnData['order_number'] = $order_master_number = $_params['order_number'];
            $ReturnData['order_total'] = isset($_create_order_res['data']['master']['grand_total'])?$_create_order_res['data']['master']['grand_total']:0;
            $currency_code = $this->CommonService->getCurrencyCode($_params['currency']);
            $ReturnData['currency'] =$_params['currency'];
            $ReturnData['currency_code'] = isset($currency_code['Code'])?$currency_code['Code']:$currency_code;
            //如果是多个seller的，需要先调用payment的另一个接口，告知payment订单的合并与拆分情况
            if(count($_create_order_res['data']['slave']) > 1){
                $informOrderRelationRes = $this->PayMent->informOrderRelation($_create_order_res['data'], 2);
                if(!$informOrderRelationRes){
                    $ReturnData['code'] = 1;
                    $ReturnData['msg'] = lang('payment_try_again');//'payment is error!!';
//                    $ReturnData['url'] = url('/paymentError?order_number='.$order_master_number.'&reason='.lang('payment_try_again'), false, false);
                    Monlog::write(LOGS_MALL_CART,'error',__METHOD__,__FUNCTION__.$order_master_number,$_create_order_res['data'],MALL_API.'/home/order/submitOrder_repay','PayMent->informOrderRelation-error');
//                    return json($ReturnData);
                    return apiReturn(['code'=>1008, 'handle_flag'=>self::ORDER_HANDLEFLAG_NOTICE, 'msg'=>'Payment is error!!!','data'=>$ReturnData]);
                }
            }
            //获取子单数组，新payment支付用 tinghu.liu 20190816
            $payment_child_order_number = [];
            $payment_child_order_price = [];
            foreach ($_create_order_res['data']['slave'] as $k100=>$v100){
                $payment_child_order_number[] = $v100['order_number'];
                $payment_child_order_price[$v100['order_number']] = $v100['grand_total'];
            }

            $payment_child_order_price[$_create_order_res['data']['master']['order_number']] = $_create_order_res['data']['master']['grand_total'];
            $_params['ChildOrderList'] = $payment_child_order_number;
            $_params['ChildOrderPrice'] = $payment_child_order_price;

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
            Log::record('$_create_order_res2'.json_encode($_create_order_res));
            if(!isset($_create_order_res['code']) || $_create_order_res['code'] != 1){
                //返回信息
                if(isset($_create_order_res['data']['orderInfo']['master']['grand_total']) &&
                    $_create_order_res['data']['orderInfo']['master']['grand_total'] ==0){
                    //订单金额为0的，订单状态为200（在创建订单时已经将状态修改为了200）
                    $_params['OrderStatus'] = 200;
                    $_params['order_number'] = isset($_create_order_res['data']['orderInfo']['master']['order_number'])?$_create_order_res['data']['orderInfo']['master']['order_number']:'';
                    $this->paymentSuccessProcessHeader($_params);
                    $ReturnData['code'] = 1;
                    $ReturnData['order_number'] = $_params['order_number'];
                    $ReturnData['order_total'] = 0;
                    $ReturnData['msg'] = 'success';
                    $ReturnData['currency'] =$_params['currency'];
                    $currency_code = $this->CommonService->getCurrencyCode($_params['currency']);
                    $ReturnData['currency_code'] = isset($currency_code['Code'])?$currency_code['Code']:$currency_code;
//                    $ReturnData['url'] = '/paymentSuccess';
//                    return json($ReturnData);
                    return apiReturn([
                        'code'=>self::API_SECCUSS_CODE,
                        'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                        'url'=>MALL_DOCUMENT.'/mpaymentSuccess?order_number='.$_params['order_number'].'&order_total=0&payment_status=success&currency='.$_params['currency'],
                        'data'=>$ReturnData,
                    ]);
                }else{

                    $ReturnData['code'] = $_create_order_res['code'];
                    $ReturnData['order_number'] = isset($_create_order_res['data']['orderInfo']['master']['order_number'])?$_create_order_res['data']['orderInfo']['master']['order_number']:'';
                    $ReturnData['order_total'] = isset($_create_order_res['data']['orderInfo']['master']['grand_total'])?$_create_order_res['data']['orderInfo']['master']['grand_total']:0;
                    $ReturnData['currency'] =$_params['currency'];
                    $currency_code = $this->CommonService->getCurrencyCode($_params['currency']);
                    $ReturnData['currency_code'] = isset($currency_code['Code'])?$currency_code['Code']:$currency_code;
                    //$ReturnData['url'] = '/paymentSuccess'; //TODO ???
                    $ReturnData['msg'] = isset($_create_order_res['msg'])?$_create_order_res['msg']:lang('payment_try_again');//'create order is error!';
//                    return json($ReturnData);
                    return apiReturn([
                        'code'=>1009,
                        'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                        'url'=>MALL_DOCUMENT.'/mpaymentError?reason='.urlencode($ReturnData['msg']),
                        'data'=>$ReturnData,
                        'msg'=>$ReturnData['msg']
                    ]);
                }
            }

            $_create_order_res = $_create_order_res['data'];
            log::record('_create_order_res1:'.json_encode($_create_order_res));
            $_params['orderInfo'] = $_create_order_res['orderInfo'];
            $_params['order_number'] = isset($_create_order_res['data']['master']['order_number'])?$_create_order_res['data']['master']['order_number']:'';
            $ReturnData['order_number'] = $order_master_number = $_params['order_number'];
            $ReturnData['order_total'] = isset($_create_order_res['data']['orderInfo']['master']['grand_total'])?$_create_order_res['data']['orderInfo']['master']['grand_total']:0;
            $ReturnData['currency'] =$_params['currency'];
            $currency_code = $this->CommonService->getCurrencyCode($_params['currency']);
            $ReturnData['currency_code'] = isset($currency_code['Code'])?$currency_code['Code']:$currency_code;
            log::record('_create_order_res2:'.json_encode($ReturnData));
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

            //获取子单/主单数组，新payment支付用 tinghu.liu 20190816
            $payment_child_order_number = [];
            $payment_child_order_price = [];
            foreach ($_params['orderInfo']['slave'] as $k100=>$v100){
                $payment_child_order_number[] = $v100['order']['order_number'];
                $payment_child_order_price[$v100['order']['order_number']] = $v100['order']['grand_total'];
            }

            $payment_child_order_price[$_params['orderInfo']['master']['order_number']] = $_params['orderInfo']['master']['grand_total'];
            $_params['ChildOrderList'] = $payment_child_order_number;
            $_params['ChildOrderPrice'] = $payment_child_order_price;

        }

        ####SC支付start################################################
        //如果是SC，将SC校验放在创建订单之前，且文案给用户提示，不用跳转至支付失败页面 20190410 tinghu.liu
        if(strtolower($_params['pay_type']) == 'sc'){
            //获取用户的SC金额
            $_params['pay_type'] = 'SC';//重置支付方式跟支付渠道
            $_params['pay_chennel'] = 'SC';
            /** 获取支付金额 **/
            $grand_total = isset($_create_order_res['data']['orderInfo']['master']['grand_total'])?$_create_order_res['data']['orderInfo']['master']['grand_total']:0;

            //调用SC验证接口,
            Log::record('sc$grand_total'.$grand_total.$_params['sc_password'].$_customer_id.$_params['currency']);
            $_sc_res = $this->OrderService->checkSC($grand_total,$_params['sc_password'],$_customer_id,$_params['currency']);
            Log::record('$_sc_res'.json_encode($_sc_res));
            $_params['sc_price'] = $grand_total;
            //拿到结果后进行汇率转换，判断是否可以使用
            if(!isset($_sc_res['code']) || $_sc_res['code'] != 200 || !isset($_sc_res['data'])){
                //出错处理
                $ReturnData['code'] = 5;
                $ReturnData['data'] = '';
                $ReturnData['handle_flag'] =5;
                $ReturnData['msg'] = isset($_sc_res['msg'])?$_sc_res['msg']:'sc data is error!!';
                return json($ReturnData);
            }
            //币种不相同的情况处理
            if($_params['currency'] != $_sc_res['data']['CurrencyType']){
                $ReturnData['code'] = 5;
                $ReturnData['data'] = '';
                $ReturnData['handle_flag'] =5;
                $ReturnData['msg'] = 'The currency is error!!';
                return json($ReturnData);
            }
            //金额不够的情况处理
            if($_sc_res['data']['UsableAmount'] < $_params['sc_price']){
                $ReturnData['code'] = 5;
                $ReturnData['data'] = '';
                $ReturnData['handle_flag'] =5;
                $ReturnData['msg'] = 'SC balance insufficient.';
                return json($ReturnData);
            }
        }

        if(strtolower($_params['pay_type']) == 'sc') {
            $res = array();
            //使用新支付系统进行支付
            $_params['transaction_channel'] = TRANSACTION_CHANNEL_SC;
            $_params['transaction_type'] = TRANSACTION_TYPE_SC;
            $_params['currency_type'] = PAYMENT_CURRENCY_TYPE_CASH;
            Log::record('$_params_sc'.json_encode($_params));
            $res = $this->PayMent->payCommon($_params);
            Log::record('$res_sc'.json_encode($res));
            $return = $res['result'];
            //如果是 Pending也需要创建OMS订单(已和payment确认) BY tinghu.liu IN 20190215
            if (isset($return['data']['status']) && $return['data']['status'] != PAYMENT_RESULT_STATUS_FAILURE) {
                $_params['OrderTotal'] = isset($res['OrderTotal']) ? sprintf("%.2f", $res['OrderTotal']) : 0;
                //不跳状态，按正常单处理 edit by Carl 2018-08-15 11:14
                $_params['OrderStatus'] = 120;//SC支付的价格直接
                //进入风控的订单
                $_params['TransactionID'] = isset($return['data']['transaction_id']) ? $return['data']['transaction_id'] : 0;
            }
            $res = $res['result'];

        }
        ####SC支付END################################################


        #######paypal支付非快捷支付START###############################
        /*
             if($_params['pay_type'] == 'PayPal' && $_params['sc_price'] == 0){//paypal支付比较特殊
                 if(isset($_create_order_res['data']['slave'])){
                     $this->redis->set("App_OrderMasterNumber_".$_params['customer_id'],$_create_order_res['data']['master']['order_number']);//保存用户提交的订单编号信息
                     $this->redis->set("App_OrderNumberArr_".$_params['customer_id'],json_encode($_create_order_res['data']['slave']));//保存用户提交的订单编号信息
                 }else{
                     //生成订单失败操作
                     $ReturnData['code'] = 2;
                     $ReturnData['msg'] = 'order create is error!';
     //                return json($ReturnData);
                     return apiReturn([
                         'code'=>1014,
                         'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                         'url'=>MALL_DOCUMENT.'/mpaymentError?order_number='.$order_master_number.'&reason='.urlencode('Order create is error!'),
                         'data'=>$ReturnData,
                         'msg'=>'Order create is error!'
                     ]);
                 }

                 $res = $this->PayMent->setExpressCheckout($_params);
                 if(!isset($res->SetExpressCheckoutResult->ResponseReult)){
                     $ReturnData['code'] = 1;
                     $ReturnData['msg'] = lang('payment_try_again');//'payment system is error!';
     //                $ReturnData['url'] = url('/paymentError?order_number='.$order_master_number.'&reason='.lang('payment_try_again'), false, false);
                     Monlog::write(LOGS_MALL_CART,'error',__METHOD__,__FUNCTION__.$order_master_number,$_params,MALL_API.'/home/order/submitOrder/setExpressCheckout_res',$res);
     //                return json($ReturnData);
                     return apiReturn([
                         'code'=>1015,
                         'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                         'url'=>MALL_DOCUMENT.'/mpaymentError?order_number='.$order_master_number.'&reason='.urlencode('payment system is error!'),
                         'data'=>$ReturnData,
                         'msg'=>'payment system is error!'
                     ]);
                 }
                 if($res->SetExpressCheckoutResult->ResponseReult == 'Success' || $res->SetExpressCheckoutResult->ResponseReult == 'SuccessWithWarning'){
                     $_paypal_token = $res->SetExpressCheckoutResult->PreparingTransactionResponseInfo->Token;
                     $_url = $res->SetExpressCheckoutResult->Url;
                     $ReturnData['code'] = 1;
                     $ReturnData['msg'] = 'success';
                     $ReturnData['url'] = $_url;
     //              return json($ReturnData);
                     return apiReturn(['code'=>self::API_SECCUSS_CODE, 'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP, 'url'=>$_url, 'data'=>$ReturnData, 'msg'=>'Success.']);
                 }else{
                     $ReturnData['code'] = 1;
                     $ReturnData['msg'] = lang('payment_try_again');//'payment is error!!';
     //                $ReturnData['url'] = url('/paymentError?order_number='.$order_master_number.'&reason='.lang('payment_try_again'), false, false);
                     Monlog::write(LOGS_MALL_CART,'error',__METHOD__,__FUNCTION__.$order_master_number,$_params,MALL_API.'/home/order/submitOrder/setExpressCheckout_res',$res);
     //                return json($ReturnData);
                     return apiReturn([
                         'code'=>2016,
                         'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                         'url'=>MALL_DOCUMENT.'/mpaymentError?order_number='.$order_master_number.'&reason='.urlencode('Payment is error!!'),
                         'data'=>$ReturnData,
                         'msg'=>'Payment is error!!'
                     ]);
                 }
             }
        $_return_data['order_number'] = $_params['order_number'];
        */
        Log::record('PayPal$_params1'.json_encode($_params));
        if($_params['pay_type'] == 'PayPal' && $_params['sc_price'] == 0) {//paypal支付比较特殊
            if (isset($_create_order_res['data']['slave'])) {
                $this->redis->set("App_OrderMasterNumber_" . $_params['customer_id'], $_create_order_res['data']['master']['order_number']);//保存用户提交的订单编号信息
                $this->redis->set("App_OrderNumberArr_" . $_params['customer_id'], json_encode($_create_order_res['data']['slave']));//保存用户提交的订单编号信息
            } else {
                //生成订单失败操作
                $ReturnData['code'] = 2;
                $ReturnData['msg'] = 'order create is error!';
                return apiReturn([
                    'code' => 1014,
                    'handle_flag' => self::ORDER_HANDLEFLAG_JUMP,
                    'url' => MALL_DOCUMENT . '/mpaymentError?order_number=' . $order_master_number . '&reason=' . urlencode('Order create is error!'),
                    'data' => $ReturnData,
                    'msg' => 'Order create is error!'
                ]);
            }
            $_params['transaction_channel'] = TRANSACTION_CHANNEL_PAYPAL;
            $_params['transaction_type'] = TRANSACTION_TYPE_PAYPAL;
            $_params['currency_type'] = PAYMENT_CURRENCY_TYPE_CASH;
            Log::record('PayPal$_params' . json_encode($_params));
            $res = $this->PayMent->payCommon($_params);
            Log::record('PayPal$res' . json_encode($res));
            $return = $res['result'];
            if (isset($return['data']['status']) && $return['data']['status'] != 'failure') {
                $ReturnData['code'] = 1;
                $ReturnData['OrderID'] = isset($return['data']['invoice_id']) ? (string)$return['data']['invoice_id'] : 0;
                $ReturnData['TransactionID'] = isset($return['data']['transaction_id']) ? $return['data']['transaction_id'] : 0;
                Log::record('PayPal$return' . json_encode($return));
                $_url = isset($return['data']['url']) ? $return['data']['url'] : '';
                return apiReturn([
                    'code' => self::API_SECCUSS_CODE,
                    'handle_flag' => self::ORDER_HANDLEFLAG_JUMP,
                    'url' => $_url,
                    'data' => $ReturnData,
                    'msg' => 'Success.'
                ]);
            } else {
                $ReturnData['code'] = 1;
                $ReturnData['msg'] = 'payment system is error!';//;
                $ReturnData['url'] = url('/paymentError?order_number=' . $order_master_number . '&reason=' . lang('payment_try_again'), false, false);
                return apiReturn([
                    'code' => 2016,
                    'handle_flag' => self::ORDER_HANDLEFLAG_JUMP,
                    'url' => MALL_DOCUMENT . '/mpaymentError?order_number=' . $order_master_number . '&reason=' . urlencode('payment system is error!'),
                    'data' => $ReturnData,
                    'msg' => 'payment system is error!'
                ]);
            }

        }

        #####paypal支付非快捷支付END############################################################################
        #################################################################################
        if($_params['pay_type'] == 'CreditCard' && $_params['pay_chennel'] == 'EGP' && !$_params['credit_card_token_id'] && $_params['sc_price'] == 0){
            //使用新支付系统进行支付
            Log::record('$payment_system_for_repay_update'.$payment_system_for_repay_update);
            if ($payment_system_for_repay_update == self::PAYMENT_SYSTEM_NEW){
                $_params['transaction_channel'] = TRANSACTION_CHANNEL_EGP;
                $_params['transaction_type'] = TRANSACTION_TYPE_CREDITCARD;
                $_params['currency_type'] = PAYMENT_CURRENCY_TYPE_CASH;
                Log::record('$payment_system_for_repay_update2$_params'.json_encode($_params));
                $res = $this->PayMent->payCommon($_params);//信用卡支付(非Token)
                Log::record('EGP$res'.json_encode($res));
                $return = $res['result'];
                //如果是 Pending也需要创建OMS订单(已和payment确认) BY tinghu.liu IN 20190215
                if(isset($return['data']['status']) && $return['data']['status'] != PAYMENT_RESULT_STATUS_FAILURE){
                    $_params['OrderTotal'] = isset($res['OrderTotal']) ? (string)$res['OrderTotal'] : 0;
                    if(isset($return['data']['risky_status']) && $return['data']['risky_status'] == 1){
                        $_params['risky'] = 1;
                    }//进入风控的订单
                    $_params['TransactionID'] = isset($return['data']['transaction_id']) ? $return['data']['transaction_id'] : 0;
                }
                $res = $res['result'];
            }else{
                $res = array();
                $res = $this->PayMent->doCreditCheckout($_params);//信用卡支付(非Token)
                $return = $res['result'];
                if (property_exists($return, 'DoCreditCheckoutResult')){
                    if($return->DoCreditCheckoutResult->ResponseResult == 'Success'
                        || $return->DoCreditCheckoutResult->ResponseResult == 'Pending'){
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
                        'data'=>$ReturnData,
                        'msg'=>'CreditCard PayMent is error.'
                    ]);
                }
            }
        }

        if($_params['pay_type'] == 'CreditCard' && $_params['pay_chennel'] == 'EGP' && $_params['credit_card_token_id'] && $_params['sc_price'] == 0){
            //使用新支付系统进行支付
            if ($payment_system_for_repay_update == self::PAYMENT_SYSTEM_NEW){
                $_params['transaction_channel'] = TRANSACTION_CHANNEL_EGP;
                $_params['transaction_type'] = TRANSACTION_TYPE_CREDITCARD_TOKEN;
                $_params['currency_type'] = PAYMENT_CURRENCY_TYPE_CASH;
                Log::record('EGP2$_params'.json_encode($_params));
                $res = $this->PayMent->payCommon($_params);//信用卡支付EGP(Token)
                Log::record('EGP2$res'.json_encode($res));
                $return = $res['result'];
                //如果是 Pending也需要创建OMS订单(已和payment确认) BY tinghu.liu IN 20190215
                if(isset($return['data']['status']) && $return['data']['status'] != PAYMENT_RESULT_STATUS_FAILURE){
                    $_params['OrderTotal'] = isset($res['OrderTotal'])?sprintf("%.2f",$res['OrderTotal']):0;
                    if(isset($return['data']['risky_status']) && $return['data']['risky_status'] == 1){
                        $_params['risky'] = 1;
                    }//进入风控的订单
                    $_params['TransactionID'] = isset($return['data']['transaction_id']) ? $return['data']['transaction_id'] : 0;
                }
                $res = $res['result'];
            }else {
                $res = array();
                $res = $this->PayMent->doCreditCardTokenCheckout($_params);//信用卡支付EGP(Token)
                $return = $res['result'];
                if ($return->DoCreditCardTokenCheckoutResult->ResponseResult == 'Success') {
                    $_params['OrderTotal'] = isset($res['OrderTotal']) ? sprintf("%.2f", $res['OrderTotal']) : 0;
                    $res = $res['result']->DoCreditCardTokenCheckoutResult;
                    if (isset($res->RiskControlStatus) && $res->RiskControlStatus == 'CRCRisk') {
                        $_params['risky'] = 1;
                    }//进入风控的订单
                    $_params['TransactionID'] = isset($res->ResponseTransactionInfo->TransactionID) ? $res->ResponseTransactionInfo->TransactionID : 0;
                } else {
                    $res = $res['result']->DoCreditCardTokenCheckoutResult;
                }
            }

        }

        if($_params['pay_type'] == 'WebMoney' && $_params['sc_price'] == 0){
            $res = array();
            $res = $this->PayMent->setWebMoneyCheckout($_params);//WebMoney
            $return = $res['result'];
            if($return->SetWebMoneyCheckoutResult->ResponseResult == 'Success'){
                //webmoney支付成功
                $ReturnData['code'] = 1;
                $ReturnData['msg'] = 'success';
//                $ReturnData['url'] = $return->SetWebMoneyCheckoutResult->Url;
//                return json($ReturnData);
                return apiReturn([
                    'code'=>self::API_SECCUSS_CODE,
                    'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                    'url'=>$return->SetWebMoneyCheckoutResult->Url,
                    'data'=>$ReturnData,
                    'msg'=>'Success.'
                ]);
            }else{
                $ReturnData['code'] = 1;
                $ReturnData['data'] = lang('payment_try_again');//'payment is error';
//                $ReturnData['url'] = url('/paymentError?order_number='.$order_master_number.'&reason='.lang('payment_try_again'), false, false);
                Monlog::write(LOGS_MALL_CART,'error',__METHOD__,__FUNCTION__.$order_master_number,$_params,MALL_API.'/home/order/submitOrder/setWebMoneyCheckout_res',$res);
//                return json($ReturnData);
                return apiReturn([
                    'code'=>1017,
                    'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                    'url'=>MALL_DOCUMENT.'/mpaymentError?order_number='.$order_master_number.'&reason='.urlencode('Payment is error!!'),
                    'data'=>$ReturnData,
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

        ####new Astropay####
        if($_params['pay_chennel'] == 'Astropay' && $_params['sc_price'] == 0){
            $_params['payment_method'] = $this->CheckoutService->astropayPaymentMethodTransV2($_params['pay_type']);
            $_params['transaction_channel'] = TRANSACTION_CHANNEL_ASTROPAY;
            $_params['transaction_type'] = $_params['payment_method'];
            $_params['currency_type'] = PAYMENT_CURRENCY_TYPE_CASH;
            Log::record('Astropay$_params'.json_encode($_params));
            $res = $this->PayMent->payCommon($_params);//Astropay支付
            Log::record('Astropay$res'.json_encode($res));
            $_params['TransactionID'] = isset($res['TransactionID'])?$res['TransactionID']:0;
            $this->packagingAstropayReturnParams($_params);
            $return = $res['result'];
            if(isset($return['data']['status']) && $return['data']['status'] != PAYMENT_RESULT_STATUS_FAILURE){
                //信用卡支付的不用跳转至第三方页面，直接成功或失败页面 tinghu.liu 20191018
                if ($_params['payment_method'] != TRANSACTION_TYPE_ASTROPAY_CREDIT_CARD){
                    if($_params['pay_type'] == 'Boleto-Astropay'){
                        $_params['boleto_url'] = $return['data']['url'];
                    }
                    $_params['redirect'] = 0;
                    $this->paymentSuccessProcessHeader($_params);
                    $ReturnData['code'] = 1;
                    $ReturnData['data'] = 'success';
                    $url=!empty($return['data']['url'])?$return['data']['url']:'';
                    $ReturnData['url'] = $url;
                    return apiReturn([
                        'code'=>self::API_SECCUSS_CODE,
                        'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                        'url'=>$url,
                        'data'=>$ReturnData,
                        'msg'=>'Success.'
                    ]);
                }
                //是信用卡，且有返回url则跳转，因为印度需要做3d验证 tinghu.liu 20191030
                if ($_params['payment_method'] == TRANSACTION_TYPE_ASTROPAY_CREDIT_CARD && isset($return['data']['url']) && !empty($return['data']['url'])){
                    $_params['redirect'] = 0;
                    $this->paymentSuccessProcessHeader($_params);
                    $url=!empty($return['data']['url'])?$return['data']['url']:'';
                    $ReturnData['code'] = 1;
                    $ReturnData['data'] = 'success';
                    $ReturnData['url'] = $url;
                    return apiReturn([
                        'code'=>self::API_SECCUSS_CODE,
                        'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                        'url'=>$url,
                        'data'=>$ReturnData,
                        'msg'=>'Success.'
                    ]);
                }
            }
            $res = $res['result'];

        }

        ####new  Astropay####

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
                    'data'=>$ReturnData,
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
                $ReturnData['code'] = 1;
                $ReturnData['msg'] = $resDo->DoExpressCheckoutResult->Error->LongMessage;
                //$ReturnData['url'] = url('/home/order/paymentError', '', true, true);
//                $ReturnData['url'] = url('/paymentError?order_number='.$order_master_number.'&reason='.$resDo->DoExpressCheckoutResult->Error->LongMessage, false, false);
                Monlog::write(LOGS_MALL_CART,'error',__METHOD__,__FUNCTION__.$order_master_number,$_params,MALL_API.'/home/order/submitOrder/QuickDoExpressCheckout_res',$resDo);
//                return json($ReturnData);

                return apiReturn([
                    'code'=>1018,
                    'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                    'url'=>MALL_DOCUMENT.'/mpaymentError?order_number='.$order_master_number.'&reason='.urlencode($resDo->DoExpressCheckoutResult->Error->LongMessage),
                    'data'=>$ReturnData,
                    'msg'=>$resDo->DoExpressCheckoutResult->Error->LongMessage
                ]);
            }
        }

        if($_params['pay_type'] == 'IDeal' && $_params['sc_price'] == 0){
            //Bic
            /*if (empty($_params['bic'])){
                $res['code'] = 2;
                $res['msg'] = 'Please select the bank information.';
                return json($res);
            }*/
            $_params['user_name'] = $UserName;
            $_params['transaction_channel'] = TRANSACTION_CHANNEL_IDEAL;
            $_params['transaction_type'] = TRANSACTION_TYPE_IDEAL;
            $_params['currency_type'] = PAYMENT_CURRENCY_TYPE_CASH;
            Log::record('IDeal$_params'.json_encode($_params));
            $res = $this->PayMent->payCommon($_params);
            Log::record('IDeal$res'.json_encode($res));
            //只要是获取token成功的都需要创建OMS订单 20190221 tinghu.liu
            if ($res['code'] == 4){
                $url = !empty($res['url'])?$res['url']:'';
                $_params['TransactionID'] = $res['TransactionID'];
                $this->paymentSuccessProcessHeader($_params);
                $res['code'] = 1;
                return apiReturn([
                    'code'=>self::API_SECCUSS_CODE,
                    'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                    'url'=>$url,
                    'data'=>$res
                ]);
            }else{
                $Reason = (isset($res['data']['error_info']) && !empty($res['data']['error_info']))?$res['data']['error_info']:'Payment failure please retry.';
                return apiReturn([
                    'code'=>1019,
                    'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                    'url'=>MALL_DOCUMENT.'/mpaymentError?order_number='.$order_master_number.'&reason='.urlencode($Reason),
                    'data'=>$ReturnData,
                    'msg'=>$Reason
                ]);
            }
        }

        //使用的是新支付系统
        if ($payment_system_for_repay_update == self::PAYMENT_SYSTEM_NEW){
            //如果是 Pending也需要创建OMS订单(已和payment确认) BY tinghu.liu IN 20190215
            if(isset($res['data']['status']) && $res['data']['status'] != PAYMENT_RESULT_STATUS_FAILURE){
                //成功处理机制
                $_params['redirect'] = 0;//不能直接跳转，需返回给ajax进行跳转
                $_params['payment_status'] = 'Success';
                //logService::write(LOGS_MALL_CART,'info',__METHOD__,__FUNCTION__,[2],MALL_API.'/home/order/submitOrder144','34444444');
                $_result=$this->paymentSuccesProcess($_params);
                return apiReturn([
                    'code'=>self::API_SECCUSS_CODE,
                    'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                    'url'=>MALL_DOCUMENT.'/mpaymentSuccess?order_number='.$_result['order_number'].'&order_total='.$_result['order_total'].'&payment_status='.$_result['payment_status'].'&currency_code='.$_result['currency_code'],
                    'data'=>$_result
                ]);
            }else{
                //错误处理机制
                $Reason = (isset($res['data']['error_info']) && !empty($res['data']['error_info']))?$res['data']['error_info']:'Payment failure please retry.';
                $ReturnData['code'] = 1;
                $ReturnData['reason'] = /*$res->Error->Code.' '.*/$Reason;
                $ReturnData['order_number'] = $_params['order_number'];
                //$ReturnData['url'] = '/home/order/paymentError';
                $ReturnData['url'] = url('/paymentError?order_number='.$order_master_number.'&reason='.$Reason, false, false);
                return apiReturn([
                    'code'=>1019,
                    'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                    'url'=>MALL_DOCUMENT.'/mpaymentError?order_number='.$order_master_number.'&reason='.urlencode($Reason),
                    'data'=>$ReturnData,
                    'msg'=>$Reason
                ]);
            }
        }else{
            if($res->ResponseResult == 'Success'){
                //成功处理机制
                $_params['redirect'] = 0;//不能直接跳转，需返回给ajax进行跳转
                $_params['payment_status'] = 'Success';
                //Monlog::write(LOGS_MALL_CART,'info',__METHOD__,__FUNCTION__,[2],MALL_API.'/home/order/submitOrder144','34444444');
                $_result = $this->paymentSuccesProcess($_params);
                Monlog::write(LOGS_MALL_CART,'info',__METHOD__,__FUNCTION__.$order_master_number,$_params,MALL_API.'/home/order/submitOrder/paymentSuccesProcess_res',json($_result));
                $ReturnData['currency_code']=$_result['currency_code'];
                $ReturnData['order_number']=$_result['order_number'];
                $ReturnData['order_total']=$_result['order_total'];
                $ReturnData['payment_status']=$_result['payment_status'];
                return apiReturn([
                    'code'=>self::API_SECCUSS_CODE,
                    'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                    'url'=>MALL_DOCUMENT.'/mpaymentSuccess?order_number='.$_result['order_number'].'&order_total='.$_result['order_total'].'&payment_status='.$_result['payment_status'].'&currency_code='.$_result['currency_code'],
                    'data'=>$ReturnData
                ]);
            }else{
                //错误处理机制
                $_error_reason = $res->Error->Code.' '.$res->Error->LongMessage;
                $ReturnData['code'] = 1;
                $ReturnData['msg'] = $_error_reason;
                $ReturnData['order_number'] = $_params['order_number'];
                //$ReturnData['url'] = '/home/order/paymentError';
                $ReturnData['url'] = url('/mpaymentError?order_number='.$order_master_number.'&reason='.$res->Error->Code.' '.$res->Error->LongMessage, false, false);
                $_result = json($ReturnData);
                Monlog::write(LOGS_MALL_CART,'info',__METHOD__,__FUNCTION__.$order_master_number,$_params,MALL_API.'/home/order/submitOrder11',$ReturnData);
//            return $_result;
                return apiReturn([
                    'code'=>1019,
                    'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                    'url'=>MALL_DOCUMENT.'/mpaymentError?order_number='.$order_master_number.'&reason='.urlencode($_error_reason),
                    'data'=>$ReturnData,
                    'msg'=>$_error_reason
                ]);
            }
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
        $returnData['url'] = '/paymentSuccess';
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
        //。net服务关闭后，不再需要同步订单至OMS tinghu.liu 20191206
        //$this->redis->lPush("createOrderSyncOMS3", $json_sync_oms_queue_data);//创建OMS订单队列
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
        /*
        $_is_pc = true;
        if (
            isset($Params['order_from'])
            && in_array($Params['order_from'], [20,30])
        ){
            $_is_pc = false;
        }
        //返回前端处理成功信息
        if($Params['redirect']){
            //跳转
            header('Location:/paymentSuccess?order_number='.$data['OrderNumber'].'&order_total='.$data['OrderTotal'].'&currency_code='.$returnData['currency_code']);
            //$this->redirect('Order/paymentSuccess',['order_number' => $data['OrderNumber'],'order_total' => $data['OrderTotal'],'currency_code'=>$returnData['currency_code']]);
        }
        //TODO 20181213 如果是APP支付(即不是PC支付)，跳转到h5成功页面
        if (!$_is_pc){
            //跳转
            header('Location:/mpaymentSuccess?order_number='.$data['OrderNumber'].'&order_total='.$data['OrderTotal'].'&currency_code='.$returnData['currency_code']);exit();
        }*/
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
                $update['change_reason_id']=isset($params['change_reason_id'])?$params['change_reason_id']:0;
                $update['order_status'] = $params['order_status'];
                $update['chage_desc'] = $params['change_reason'];
                $update['create_ip'] = $params['create_ip'];
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

    /*
     * 支付成功或失败页面的推荐产品
     */
    public function recentDatat(){
        $data=$this->CommonService->getRecentHistoryForMobile();
        return apiReturn(['code'=>1002, 'data'=>$data]);
    }

    /**
     * 获取Ideal银行数据信息
     * /home/Checkout/getIdealBank
     * @return array
     */
    public function getIdealBank(){
        $data=$this->PayMent->getIdealBank();
        return apiJosn($data);
    }

    /**
     * paypal快捷支付
     * home/Order/ShortcutExpressCheckout
     * {"currency":"","lang":"","php_sessid":"PHPSESSID","dxsso":"DXSSO"}
     */
    public function ShortcutExpressCheckout(){
        /********* 接收参数，为了解决部分浏览器 不支持 新版PayPal问题（请求头没有传递cookie） start ********/
        $currency = input('currency')?input('currency'):'USD';
        $lang = input('lang')?input('lang'):'en';
        $_customer_id = input('customer_id')?input('customer_id'):'';
        $UserName = input('UserName')?input('UserName'):'';

        $_isconform = input('isconform');
        $_params['currency'] = $currency;//$this->currency;
        $_params['lang'] = $lang;//$this->lang;

        if(!in_array($_params['currency'],config('paypal_support_currency')) && $_isconform){
            $_params['currency'] = 'USD';
            $res = $this->CartService->getCartInfo($_customer_id,'USD',$this->country,$_params['lang'],'',null,$UserName);
        }
        $_rate = 1;
        if($_params['currency'] != 'USD'){
            //$_rate = $this->CommonService->getOneRate($_params['currency'], "USD");
            $_rate = $this->CommonService->getOneRate("USD", $_params['currency']);
        }

        $_params['rate'] = $_rate;
        $_cart_info = $this->CommonService->loadRedis()->get("ShoppingCart_".$_customer_id);
        $_checkout_cart_info = $_cart_info;
        //添加进入checkout前 需要重置购物车信息：添加IsBuy，以及处理一些空数据
        if ($_checkout_cart_info){
            foreach ($_checkout_cart_info[$_customer_id]['StoreData'] as $k=>$v){
                foreach ($v['ProductInfo'] as $k1=>$v1){
                    foreach ($v1 as $k3=>$v3){
                        if ($v3['IsChecked'] == 1){
                            $_checkout_cart_info[$_customer_id]['StoreData'][$k]['ProductInfo'][$k1][$k3]['IsBuy'] = 1;
                        }else{
                            unset($_checkout_cart_info[$_customer_id]['StoreData'][$k]['ProductInfo'][$k1][$k3]);
                        }
                    }
                    if (empty($_checkout_cart_info[$_customer_id]['StoreData'][$k]['ProductInfo'][$k1])){
                        unset($_checkout_cart_info[$_customer_id]['StoreData'][$k]['ProductInfo'][$k1]);
                    }
                }
                if (empty($_checkout_cart_info[$_customer_id]['StoreData'][$k]['ProductInfo'])){
                    unset($_checkout_cart_info[$_customer_id]['StoreData'][$k]);
                }
            }
        }
        $this->CommonService->loadRedis()->set("ShoppingCart_CheckOut".$_customer_id,$_checkout_cart_info);
        if(!$_cart_info){
            $returnData['code'] = 0;
            $returnData['msg'] = 'the cart data is error!';
            return json($returnData);
        }
        $_params['customer_id'] = $_customer_id;
        $_params['cart_info'] = $_cart_info;
        $_params['lang'] = $lang;//$this->lang;

        $res=$this->PayMent->setExpressCheckoutToShortcutV2($_params);
        return  $res;
    }

    public function verifySc(){
        $_params = $this->request->param();
        $singleRule=[
            'grand_total' => 'require',
            'sc_password' => 'require',
            'customer_id' => 'require',
            'currency' => 'require',
        ];
        $result = $this->validate($_params,$singleRule);
        if(true !== $result){
            // 验证失败 输出错误信息
            return $this->result('',1003,$result);
        }
        $sc_password_num=Cache::get('sc_password'.$_params['customer_id']);
        if($sc_password_num>50){
            return $this->result('',1003,'');
        }
        Cache::set('sc_password'.$_params['customer_id'],$sc_password_num+1);
        //调用SC验证接口,$
        $_sc_res = $this->OrderService->checkSC($_params['grand_total'],$_params['sc_password'],$_params['customer_id'],$_params['currency']);
        Log::record('verifySc'.json_encode($_params).'$_sc_res'.json_encode($_sc_res));
        return json($_sc_res);
    }

    /**
     * 新版PayPal capture接口【非快捷支付】
     * home/Order/captureOrder
     * @return \think\response\Json
     */
    public function captureOrder(){
        $params = input();
        Log::record('captureOrder:params:'.json_encode($params));
        $invoiceId = isset($params['token'])?$params['token']:'';

        $redisKey = 'PAYPALSETEXPRESSCHECKOUT'.$invoiceId;
        $captureData = $this->CommonService->loadRedis()->get($redisKey);
        //删除redis
        //$this->CommonService->loadRedis()->rm($redisKey);
        Log::record('captureOrderRedis:params:'.json_encode($captureData));
        if(isset($captureData['result']['data']['transaction_id']) && !empty($captureData['result']['data']['transaction_id'])){
            $processParams = $captureData['params'];
            $paypalInfo = $captureData['result'];
            $orderMasterNumber = isset($paypalInfo['order_master_number']) ? $paypalInfo['order_master_number'] : '';
            //是否是快捷支付：0-不是，1-是
            $rapid = isset($paypalInfo['rapid']) ? $paypalInfo['rapid'] : '';
            $logKey = 'submitOrder'.$orderMasterNumber;
            $currencyCode = isset($paypalInfo['currency_code']) ? $paypalInfo['currency_code'] : '';
            $orderTotal = isset($paypalInfo['order_total']) ? $paypalInfo['order_total'] : 0;
            $transactionId = isset($paypalInfo['data']['transaction_id']) ? $paypalInfo['data']['transaction_id'] : '';
            //请求参数
            $postData['TransactionId'] = $transactionId;
            $postData['InvoiceId'] = isset($paypalInfo['data']['invoice_id']) ? $paypalInfo['data']['invoice_id'] : '';
            $postData['Rapid'] = $rapid;
            $postData['CurrencyCode'] = $processParams['currency'];
            $postData['ExchangeRate'] = $processParams['rate'];
            $postData['Amount'] = $orderTotal;//订单总额（包括运费等）
            $postData['ShippingAddress'] = $captureData['address'];
            //payment接口
            Log::record('captureOrder:$postData:'.json_encode($postData));
            $res = doCurl(PAYMENT_API.'paypal/front/capture', $postData, null, true);
            Log::record($logKey.'captureOrder， params:'.json_encode($postData).', res:'.json_encode($res));
            if(isset($res['code']) && $res['code'] == 200 && isset($res['data']['status']) && $res['data']['status'] != 'failure'){
                //支付成功后处理逻辑（修改状态为120，发送支付邮件，积分等处理）
                $processParams['redirect'] = 0;//不能直接跳转，需返回给ajax进行跳转
                $processParams['payment_status'] = 'Success';
                $this->paymentSuccesProcess($processParams);

                $ReturnData['code'] = 1;
//                $ReturnData['order_number'] = $orderMasterNumber;
//                $ReturnData['order_total'] = $orderTotal;
                $currency_code_arr = $this->CommonService->getCurrencyCode($currencyCode);
                Log::record('$currency_code_str:'.print_r($currency_code_arr, true));
                $currency_code_str = !empty($currency_code_arr)?$currency_code_arr:'';
                $ReturnData['currency_code'] = $currency_code_str;
                $ReturnData['url'] = 'mpaymentSuccess?order_number='.$orderMasterNumber.'&order_total='.$orderTotal.'&currency_code='.$currency_code_str;
                $ReturnData['msg'] = 'success';
            }else{
                $Reason = (isset($res['data']['error_info']) && !empty($res['data']['error_info']))?$res['data']['error_info']:lang('payment_try_again');
                $ReturnData['code'] = 1;
                $ReturnData['msg'] = $Reason;
                $ReturnData['url'] = 'mpaymentError?order_number='.$orderMasterNumber.'&reason='.$Reason;
            }
        }else{
            $ReturnData['code'] = 1;
            $ReturnData['msg'] = 'System Error,try again later';
            $ReturnData['url'] = 'mpaymentError?error=&reason='.'System Error,try again later';
           // return json($ReturnData);
        }
        header('Location:'.MALL_URL.$ReturnData['url']);exit;
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
     * 商城的定义：
     *      code：
     *      0-刷新页面，1-跳转，2-文案提示，3-地址数据校验不通过，需要通知前端弹出地址输入框，再次填写收货地址，4-IDeal相关， 5-SC支付验证错误，需要将提示信息展示在SC密码框附近
     *
     * @return mixed
     */
    public function submitPay(){
        return;
        $params = request()->post();

        if(!empty($params['ShipTo'])){
            $params['ShipTo']=trim($params['ShipTo']);
        }
        if(!empty($params['CountryCode'])){
            $params['CountryCode']=trim($params['CountryCode']);
        }
        if(!empty($params['CPF'])){
            $params['CPF']=htmlspecialchars_decode($params['CPF']);
        }
        $UserName=!empty($params['UserName'])?!empty($params['UserName']):'';
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
        //增加去掉地址2再进行签名校验规则，为了解决老版本：客户端生成签名时去掉地址二（为空的情况），而后端生成签名没去掉空的地址二导致签名不一致的问题 tinghu.liu 20190718
        $sign_params = $params;
        unset($sign_params['Street2']);
        //默认数据
        $ReturnData['code'] = 0;
        $ReturnData['order_number'] = 0;
        $ReturnData['order_total'] = 0;
        $ReturnData['currency'] = '';
        $ReturnData['currency_code'] = '';
        $ReturnData['payment_status'] = '';
        $ReturnData['msg'] = '';

        if (
            !$this->OrderService->verifyPaySign($_sign, $params)
            && !$this->OrderService->verifyPaySign($_sign, $sign_params)
        ){
            //Log::record('verifyPaySign-params:'.print_r($params, true),'error');
            //自测时可关闭，和APP联调和上线时要开启
            //return apiReturn(['code'=>1003, 'handle_flag'=>self::ORDER_HANDLEFLAG_NOTICE, 'msg'=>'You do not have rights to complete the operation.']);
        }
        //支付方式 PayType 有效性校验
        /*
        if (!in_array($params['PayType'],config('app_allow_pay_type'))){
            return apiReturn(['code'=>1020, 'handle_flag'=>self::ORDER_HANDLEFLAG_NOTICE, 'msg'=>'Illegal payment.','data'=>$ReturnData]);
        }
        //支付渠道 PayChennel 有效性校验
        if (!in_array($params['PayChennel'],config('app_allow_pay_chennel'))){
            return apiReturn(['code'=>1022, 'handle_flag'=>self::ORDER_HANDLEFLAG_NOTICE, 'msg'=>'Illegal payment.']);
        }*/
        //判断币种有效性
        $params['Currency'] = $this->CommonService->verifyCurrency(input("Currency"));
        //判断语种有效性
        $params['Lang'] = $this->CommonService->verifyLang(input("Lang"));
        //判断国家有效性
        $params['ShipTo'] = $this->CommonService->verifyCountry(input("ShipTo"));

        //判断用户是否有效且获取用户邮箱
        //$check_user_result = doCurl(CIC_API.'/cic/Customer/GetCustomerInfoByAccount',['AccountName'=>$params['CustomerEmail']], null, true);
        $check_user_result = doCurl(CIC_APP.'/cic/Customer/GetEmailsByCID',['id'=>$params['CustomerId']], null, true);
        Log::record('check_user_result:'.print_r($check_user_result, true));
        if(
            !isset($check_user_result['code'])
            || $check_user_result['code'] != self::API_SECCUSS_CODE
            || !isset($check_user_result['data'])
            || empty($check_user_result['data'])
        ){
            return apiReturn(['code'=>1004, 'handle_flag'=>self::ORDER_HANDLEFLAG_NOTICE, 'msg'=>'User information error.','data'=>$ReturnData]);
        }
        $params['CustomerEmail'] = $check_user_result['data'];

        //调用接收参数接口
        $this->submitOrderCommon($params);
        $_customer_id = $params['CustomerId'];
        $_params = json_decode($this->redis->get("payParams_".$_customer_id),true);
        $pay_chennel=strtolower($_params['pay_chennel']);
        /*如果使用新的payment系统进行支付，则走新payment支付流程，否则走之前的支付逻辑 */
        $new_payment=config('new_payment');

        if( in_array($pay_chennel,$new_payment)) {
            $payment_system_for_repay_update= self::PAYMENT_SYSTEM_NEW;
        }else{
            $payment_system_for_repay_update= self::PAYMENT_SYSTEM_OLD;
        }

        //入库的使用支付系统标识以$payment_system_for_repay_update为准
        $_params['payment_system'] = $payment_system_for_repay_update;
        if($_params['pay_type'] == 'IDeal') {
            if (empty($_params['bic'])) {
                $res['code'] = 2;
                $res['msg'] = 'Please select the bank information.';
                return json($res);
            }
        }
        //判断用户收货地址ID有效性
        $check_address_result = doCurl(CIC_APP.'/cic/address/getAddress',['CustomerID'=>$params['CustomerId'], 'AddressID'=>$params['CustomerAddressId']], null, true);
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
            return apiReturn(['code'=>1005, 'handle_flag'=>self::ORDER_HANDLEFLAG_NOTICE, 'msg'=>'User address error.','data'=>$ReturnData]);
        }

        //如果是NOC，需要跳转到TaxId输入页面，如果是已经传了TaxId的，则不需要跳到TaxId输入页面，直接走下面的流程（因为有TaxId，说明已经询价过了，这里不需要再处理）
        $tax_id = isset($params['NocNocTaxId'])?$params['NocNocTaxId']:'';
        $_params['nocnoc_tax_id'] = $tax_id;
        if($_params['NocNoc'] == 1 && empty($tax_id)){
            //Cookie::set('nocSubmitOrderParams', input(), 60*30);
            //跳到输入TaxId的页面
            $ReturnData['code'] = 1;
//            $ReturnData['data'] = 'nocnoc';
//            $ReturnData['url'] = url('/home/Noc/index');
//            return json($ReturnData);
            return apiReturn(['code'=>1006, 'handle_flag'=>self::ORDER_HANDLEFLAG_NOTICE, 'msg'=>'NocNoc TaxId is require.','data'=>$ReturnData]);
        }
        //20181221 如果是NOC，需要判断NOC运费是否正确，不正确不让提交
        if ($_params['NocNoc'] == 1){
            if(isset($_params['is_buynow']) && $_params['is_buynow']){
                $_cart_info = $this->CommonService->loadRedis()->get("ShoppingCartBuyNow_".$_customer_id);
            }else{
                $_cart_info = $this->CommonService->loadRedis()->get("ShoppingCart_CheckOut".$_customer_id);
            }
            if (!isset($_cart_info[$_customer_id]['nocdata'])){
                $_return_data['code'] = 2;
                $_return_data['msg'] = 'Nocnoc shipping fee error.';
                return json($_return_data);
            }
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
                return apiReturn(['code'=>1023, 'handle_flag'=>self::ORDER_HANDLEFLAG_NOTICE, 'msg'=>'The pay data is error!','data'=>$ReturnData]);
            }
            Monlog::write(LOGS_MALL_CART,'info',__METHOD__,__FUNCTION__.$_params['order_master_number'],null,MALL_API.'/home/order/submitOrder_repay_data',$_create_order_res);
            if(!$_create_order_res){
                //缓存已过期，重新去到列表页面
                $ReturnData['code'] = 1;
//                $ReturnData['msg'] = 'the order data is past due!';
//                $ReturnData['url'] = url('/cart');
//                return json($ReturnData);
                return apiReturn(['code'=>1007, 'handle_flag'=>self::ORDER_HANDLEFLAG_NOTICE, 'msg'=>'The order data is past due!','data'=>$ReturnData]);
            }
            $_params['orderInfo'] = $_create_order_res['orderInfo'];
            $_create_order_res = $_create_order_res['data'];
            $_params['order_number'] = isset($_create_order_res['data']['master']['order_number'])?$_create_order_res['data']['master']['order_number']:'';
            $ReturnData['order_number'] = $order_master_number = $_params['order_number'];
            $ReturnData['order_total'] = isset($_create_order_res['data']['master']['grand_total'])?$_create_order_res['data']['master']['grand_total']:0;
            $currency_code = $this->CommonService->getCurrencyCode($_params['currency']);
            $ReturnData['currency'] =$_params['currency'];
            $ReturnData['currency_code'] = isset($currency_code['Code'])?$currency_code['Code']:$currency_code;
            //如果是多个seller的，需要先调用payment的另一个接口，告知payment订单的合并与拆分情况
            if(count($_create_order_res['data']['slave']) > 1){
                $informOrderRelationRes = $this->PayMent->informOrderRelation($_create_order_res['data'], 2);
                if(!$informOrderRelationRes){
                    $ReturnData['code'] = 1;
                    $ReturnData['msg'] = lang('payment_try_again');//'payment is error!!';
//                    $ReturnData['url'] = url('/paymentError?order_number='.$order_master_number.'&reason='.lang('payment_try_again'), false, false);
                    Monlog::write(LOGS_MALL_CART,'error',__METHOD__,__FUNCTION__.$order_master_number,$_create_order_res['data'],MALL_API.'/home/order/submitOrder_repay','PayMent->informOrderRelation-error');
//                    return json($ReturnData);
                    return apiReturn(['code'=>1008, 'handle_flag'=>self::ORDER_HANDLEFLAG_NOTICE, 'msg'=>'Payment is error!!!','data'=>$ReturnData]);
                }
            }
            //修改支付方式？？？？如果是repay需要修改支付方式和渠道、使用的支付系统 tinghu.liu 20190315
            $_update_order_params = ['order_master_number'=>$order_master_number, 'pay_type'=>$_params['pay_type'], 'pay_channel'=>$_params['pay_chennel'], 'payment_system'=>$payment_system_for_repay_update];
            $_update_order_res = doCurl(
                MALL_API."/orderfrontend/order/updateOrderPaytypeAndChannel",
                $_update_order_params,
                null, true);
            Log::record('updateOrderPaytypeAndChannel, params:'.json_encode($_update_order_params).', res:'.json_encode($_update_order_res));

            //获取子单数组，新payment支付用 tinghu.liu 20190816
            $payment_child_order_number = [];
            $payment_child_order_price = [];
            foreach ($_create_order_res['data']['slave'] as $k100=>$v100){
                $payment_child_order_number[] = $v100['order_number'];
                $payment_child_order_price[$v100['order_number']] = $v100['grand_total'];
            }

            $payment_child_order_price[$_create_order_res['data']['master']['order_number']] = $_create_order_res['data']['master']['grand_total'];
            $_params['ChildOrderList'] = $payment_child_order_number;
            $_params['ChildOrderPrice'] = $payment_child_order_price;

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
            Log::record('$_create_order_res2'.json_encode($_create_order_res));
            if(!isset($_create_order_res['code']) || $_create_order_res['code'] != 1){
                //返回信息
                if(isset($_create_order_res['data']['orderInfo']['master']['grand_total']) &&
                    $_create_order_res['data']['orderInfo']['master']['grand_total'] ==0){
                    //订单金额为0的，订单状态为200（在创建订单时已经将状态修改为了200）
                    $_params['OrderStatus'] = 200;
                    $_params['order_number'] = isset($_create_order_res['data']['orderInfo']['master']['order_number'])?$_create_order_res['data']['orderInfo']['master']['order_number']:'';
                    $this->paymentSuccessProcessHeader($_params);
                    $ReturnData['code'] = 1;
                    $ReturnData['order_number'] = $_params['order_number'];
                    $ReturnData['order_total'] = 0;
                    $ReturnData['msg'] = 'success';
                    $ReturnData['currency'] =$_params['currency'];
                    $currency_code = $this->CommonService->getCurrencyCode($_params['currency']);
                    $ReturnData['currency_code'] = isset($currency_code['Code'])?$currency_code['Code']:$currency_code;
//                    $ReturnData['url'] = '/paymentSuccess';
//                    return json($ReturnData);
                    return apiReturn([
                        'code'=>self::API_SECCUSS_CODE,
                        'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                        'url'=>MALL_DOCUMENT.'/mpaymentSuccess?order_number='.$_params['order_number'].'&order_total=0&payment_status=success&currency='.$_params['currency'],
                        'data'=>$ReturnData,
                    ]);
                }else{
                    $ReturnData['code'] = $_create_order_res['code'];
                    $ReturnData['order_number'] = isset($_create_order_res['data']['orderInfo']['master']['order_number'])?$_create_order_res['data']['orderInfo']['master']['order_number']:'';
                    $ReturnData['order_total'] = isset($_create_order_res['data']['orderInfo']['master']['grand_total'])?$_create_order_res['data']['orderInfo']['master']['grand_total']:0;
                    $ReturnData['currency'] =$_params['currency'];
                    $currency_code = $this->CommonService->getCurrencyCode($_params['currency']);
                    $ReturnData['currency_code'] = isset($currency_code['Code'])?$currency_code['Code']:$currency_code;
                    $ReturnData['url'] = '/paymentSuccess'; //TODO ???
                    $ReturnData['msg'] = isset($_create_order_res['msg'])?$_create_order_res['msg']:lang('payment_try_again');//'create order is error!';
//                    return json($ReturnData);
                    return apiReturn([
                        'code'=>1009,
                        'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                        'url'=>MALL_DOCUMENT.'/mpaymentError?reason='.urlencode($ReturnData['msg']),
                        'data'=>$ReturnData,
                        'msg'=>$ReturnData['msg']
                    ]);
                }
            }

            $_create_order_res = $_create_order_res['data'];
            log::record('_create_order_res1:'.json_encode($_create_order_res));
            $_params['orderInfo'] = $_create_order_res['orderInfo'];
            $_params['order_number'] = isset($_create_order_res['data']['master']['order_number'])?$_create_order_res['data']['master']['order_number']:'';
            $ReturnData['order_number'] = $order_master_number = $_params['order_number'];
            $ReturnData['order_total'] = isset($_create_order_res['data']['orderInfo']['master']['grand_total'])?$_create_order_res['data']['orderInfo']['master']['grand_total']:0;
            $ReturnData['currency'] =$_params['currency'];
            $currency_code = $this->CommonService->getCurrencyCode($_params['currency']);
            $ReturnData['currency_code'] = isset($currency_code['Code'])?$currency_code['Code']:$currency_code;
            log::record('_create_order_res2:'.json_encode($ReturnData));
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

            //获取子单/主单数组，新payment支付用 tinghu.liu 20190816
            $payment_child_order_number = [];
            $payment_child_order_price = [];
            foreach ($_params['orderInfo']['slave'] as $k100=>$v100){
                $payment_child_order_number[] = $v100['order']['order_number'];
                $payment_child_order_price[$v100['order']['order_number']] = $v100['order']['grand_total'];
            }

            $payment_child_order_price[$_params['orderInfo']['master']['order_number']] = $_params['orderInfo']['master']['grand_total'];
            $_params['ChildOrderList'] = $payment_child_order_number;
            $_params['ChildOrderPrice'] = $payment_child_order_price;

        }

        ####SC支付start################################################
        //如果是SC，将SC校验放在创建订单之前，且文案给用户提示，不用跳转至支付失败页面 20190410 tinghu.liu
        if(strtolower($_params['pay_type']) == 'sc'){
            //获取用户的SC金额
            $_params['pay_type'] = 'SC';//重置支付方式跟支付渠道
            $_params['pay_chennel'] = 'SC';
            /** 获取支付金额 **/
            $grand_total = isset($_create_order_res['data']['orderInfo']['master']['grand_total'])?$_create_order_res['data']['orderInfo']['master']['grand_total']:0;

            //调用SC验证接口,
            //Log::record('sc$grand_total'.$grand_total.$_params['sc_password'].$_customer_id.$_params['currency']);
            $_sc_res = $this->OrderService->checkSC($grand_total,$_params['sc_password'],$_customer_id,$_params['currency']);
            Log::record('$_sc_res'.json_encode($_sc_res));
            $_params['sc_price'] = $grand_total;
            //拿到结果后进行汇率转换，判断是否可以使用
            if(!isset($_sc_res['code']) || $_sc_res['code'] != 200 || !isset($_sc_res['data'])){
                //出错处理
                $ReturnData['code'] = 5;
                $ReturnData['data'] = '';
                $ReturnData['handle_flag'] =5;
                $ReturnData['msg'] = isset($_sc_res['msg'])?$_sc_res['msg']:'sc data is error!!';
                return json($ReturnData);
            }
            //币种不相同的情况处理
            if($_params['currency'] != $_sc_res['data']['CurrencyType']){
                $ReturnData['code'] = 5;
                $ReturnData['data'] = '';
                $ReturnData['handle_flag'] =5;
                $ReturnData['msg'] = 'The currency is error!!';
                return json($ReturnData);
            }
            //金额不够的情况处理
            if($_sc_res['data']['UsableAmount'] < $_params['sc_price']){
                $ReturnData['code'] = 5;
                $ReturnData['data'] = '';
                $ReturnData['handle_flag'] =5;
                $ReturnData['msg'] = 'SC balance insufficient.';
                return json($ReturnData);
            }
        }

        if(strtolower($_params['pay_type']) == 'sc') {
            $res = array();
            //使用新支付系统进行支付
            $_params['transaction_channel'] = TRANSACTION_CHANNEL_SC;
            $_params['transaction_type'] = TRANSACTION_TYPE_SC;
            $_params['currency_type'] = PAYMENT_CURRENCY_TYPE_CASH;
            Log::record('$_params_sc'.json_encode($_params));
            $res = $this->PayMent->payCommon($_params);
            Log::record('$res_sc'.json_encode($res));
            $return = $res['result'];
            //如果是 Pending也需要创建OMS订单(已和payment确认) BY tinghu.liu IN 20190215
            if (isset($return['data']['status']) && $return['data']['status'] != PAYMENT_RESULT_STATUS_FAILURE) {
                $_params['OrderTotal'] = isset($res['OrderTotal']) ? sprintf("%.2f", $res['OrderTotal']) : 0;
                //不跳状态，按正常单处理 edit by Carl 2018-08-15 11:14
                $_params['OrderStatus'] = 120;//SC支付的价格直接
                //进入风控的订单
                $_params['TransactionID'] = isset($return['data']['transaction_id']) ? $return['data']['transaction_id'] : 0;
            }
            $res = $res['result'];

        }
        ####SC支付END################################################


        #######paypal支付非快捷支付START###############################
        Log::record('PayPal$_params1'.json_encode($_params));
        if($_params['pay_type'] == 'PayPal' && $_params['sc_price'] == 0) {//paypal支付比较特殊
            if (isset($_create_order_res['data']['slave'])) {
                $this->redis->set("App_OrderMasterNumber_" . $_params['customer_id'], $_create_order_res['data']['master']['order_number']);//保存用户提交的订单编号信息
                $this->redis->set("App_OrderNumberArr_" . $_params['customer_id'], json_encode($_create_order_res['data']['slave']));//保存用户提交的订单编号信息
            } else {
                //生成订单失败操作
                $ReturnData['code'] = 2;
                $ReturnData['msg'] = 'order create is error!';
                //  return json($ReturnData);
                return apiReturn([
                    'code' => 1014,
                    'handle_flag' => self::ORDER_HANDLEFLAG_JUMP,
                    'url' => MALL_DOCUMENT . '/mpaymentError?order_number=' . $order_master_number . '&reason=' . urlencode('Order create is error!'),
                    'data' => $ReturnData,
                    'msg' => 'Order create is error!'
                ]);
            }
            $_params['transaction_channel'] = TRANSACTION_CHANNEL_PAYPAL;
            $_params['transaction_type'] = TRANSACTION_TYPE_PAYPAL;
            $_params['currency_type'] = PAYMENT_CURRENCY_TYPE_CASH;
            Log::record('PayPal$_params' . json_encode($_params));
            $res = $this->PayMent->payCommon($_params);
            Log::record('PayPal$res' . json_encode($res));
            $return = $res['result'];
            if (isset($return['data']['status']) && $return['data']['status'] != 'failure') {
                $ReturnData['code'] = 1;
                $ReturnData['OrderID'] = isset($return['data']['invoice_id']) ? (string)$return['data']['invoice_id'] : 0;
                $ReturnData['TransactionID'] = isset($return['data']['transaction_id']) ? $return['data']['transaction_id'] : 0;
                Log::record('PayPal$return' . json_encode($return));
                $_url = isset($return['data']['url']) ? $return['data']['url'] : '';
                return apiReturn([
                    'code' => self::API_SECCUSS_CODE,
                    'handle_flag' => self::ORDER_HANDLEFLAG_JUMP,
                    'url' => $_url,
                    'data' => $ReturnData,
                    'msg' => 'Success.'
                ]);
            } else {
                $ReturnData['code'] = 1;
                $ReturnData['msg'] = 'payment system is error!';//;
                $ReturnData['url'] = url('/paymentError?order_number=' . $order_master_number . '&reason=' . lang('payment_try_again'), false, false);
                return apiReturn([
                    'code' => 2016,
                    'handle_flag' => self::ORDER_HANDLEFLAG_JUMP,
                    'url' => MALL_DOCUMENT . '/mpaymentError?order_number=' . $order_master_number . '&reason=' . urlencode('payment system is error!'),
                    'data' => $ReturnData,
                    'msg' => 'payment system is error!'
                ]);
            }
        }

        #####paypal支付非快捷支付END############################################################################
        #################################################################################
        if($_params['pay_type'] == 'CreditCard' && $_params['pay_chennel'] == 'EGP' && !$_params['credit_card_token_id'] && $_params['sc_price'] == 0){
            //使用新支付系统进行支付
            Log::record('$payment_system_for_repay_update'.$payment_system_for_repay_update);
            if ($payment_system_for_repay_update == self::PAYMENT_SYSTEM_NEW){
                $_params['transaction_channel'] = TRANSACTION_CHANNEL_EGP;
                $_params['transaction_type'] = TRANSACTION_TYPE_CREDITCARD;
                $_params['currency_type'] = PAYMENT_CURRENCY_TYPE_CASH;
                Log::record('$payment_system_for_repay_update2$_params'.json_encode($_params));
                $res = $this->PayMent->payCommon($_params);//信用卡支付(非Token)
                Log::record('EGP$res'.json_encode($res));
                $return = $res['result'];
                //如果是 Pending也需要创建OMS订单(已和payment确认) BY tinghu.liu IN 20190215
                if(isset($return['data']['status']) && $return['data']['status'] != PAYMENT_RESULT_STATUS_FAILURE){
                    $_params['OrderTotal'] = isset($res['OrderTotal']) ? (string)$res['OrderTotal'] : 0;
                    if(isset($return['data']['risky_status']) && $return['data']['risky_status'] == 1){
                        $_params['risky'] = 1;
                    }//进入风控的订单
                    $_params['TransactionID'] = isset($return['data']['transaction_id']) ? $return['data']['transaction_id'] : 0;
                }
                $res = $res['result'];
            }else{
                $res = array();
                $res = $this->PayMent->doCreditCheckout($_params);//信用卡支付(非Token)
                $return = $res['result'];
                if (property_exists($return, 'DoCreditCheckoutResult')){
                    if($return->DoCreditCheckoutResult->ResponseResult == 'Success'
                        || $return->DoCreditCheckoutResult->ResponseResult == 'Pending'){
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
                        'data'=>$ReturnData,
                        'msg'=>'CreditCard PayMent is error.'
                    ]);
                }
            }
        }

        if($_params['pay_type'] == 'CreditCard' && $_params['pay_chennel'] == 'EGP' && $_params['credit_card_token_id'] && $_params['sc_price'] == 0){
            //使用新支付系统进行支付
            if ($payment_system_for_repay_update == self::PAYMENT_SYSTEM_NEW){
                $_params['transaction_channel'] = TRANSACTION_CHANNEL_EGP;
                $_params['transaction_type'] = TRANSACTION_TYPE_CREDITCARD_TOKEN;
                $_params['currency_type'] = PAYMENT_CURRENCY_TYPE_CASH;
                Log::record('EGP2$_params'.json_encode($_params));
                $res = $this->PayMent->payCommon($_params);//信用卡支付EGP(Token)
                Log::record('EGP2$res'.json_encode($res));
                $return = $res['result'];
                //如果是 Pending也需要创建OMS订单(已和payment确认) BY tinghu.liu IN 20190215
                if(isset($return['data']['status']) && $return['data']['status'] != PAYMENT_RESULT_STATUS_FAILURE){
                    $_params['OrderTotal'] = isset($res['OrderTotal'])?sprintf("%.2f",$res['OrderTotal']):0;
                    if(isset($return['data']['risky_status']) && $return['data']['risky_status'] == 1){
                        $_params['risky'] = 1;
                    }//进入风控的订单
                    $_params['TransactionID'] = isset($return['data']['transaction_id']) ? $return['data']['transaction_id'] : 0;
                }
                $res = $res['result'];
            }else {
                $res = array();
                $res = $this->PayMent->doCreditCardTokenCheckout($_params);//信用卡支付EGP(Token)
                $return = $res['result'];
                if ($return->DoCreditCardTokenCheckoutResult->ResponseResult == 'Success') {
                    $_params['OrderTotal'] = isset($res['OrderTotal']) ? sprintf("%.2f", $res['OrderTotal']) : 0;
                    $res = $res['result']->DoCreditCardTokenCheckoutResult;
                    if (isset($res->RiskControlStatus) && $res->RiskControlStatus == 'CRCRisk') {
                        $_params['risky'] = 1;
                    }//进入风控的订单
                    $_params['TransactionID'] = isset($res->ResponseTransactionInfo->TransactionID) ? $res->ResponseTransactionInfo->TransactionID : 0;
                } else {
                    $res = $res['result']->DoCreditCardTokenCheckoutResult;
                }
            }
        }

        if($_params['pay_type'] == 'WebMoney' && $_params['sc_price'] == 0){
            $res = array();
            $res = $this->PayMent->setWebMoneyCheckout($_params);//WebMoney
            $return = $res['result'];
            if($return->SetWebMoneyCheckoutResult->ResponseResult == 'Success'){
                //webmoney支付成功
                $ReturnData['code'] = 1;
                $ReturnData['msg'] = 'success';

                return apiReturn([
                    'code'=>self::API_SECCUSS_CODE,
                    'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                    'url'=>$return->SetWebMoneyCheckoutResult->Url,
                    'data'=>$ReturnData,
                    'msg'=>'Success.'
                ]);
            }else{
                $ReturnData['code'] = 1;
                $ReturnData['data'] = lang('payment_try_again');//'payment is error';
//                $ReturnData['url'] = url('/paymentError?order_number='.$order_master_number.'&reason='.lang('payment_try_again'), false, false);
                Monlog::write(LOGS_MALL_CART,'error',__METHOD__,__FUNCTION__.$order_master_number,$_params,MALL_API.'/home/order/submitOrder/setWebMoneyCheckout_res',$res);
//                return json($ReturnData);
                return apiReturn([
                    'code'=>1017,
                    'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                    'url'=>MALL_DOCUMENT.'/mpaymentError?order_number='.$order_master_number.'&reason='.urlencode('Payment is error!!'),
                    'data'=>$ReturnData,
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
        ####new Astropay####
        if($_params['pay_chennel'] == 'Astropay' && $_params['sc_price'] == 0){
            $_params['payment_method'] = $this->CheckoutService->astropayPaymentMethodTransV2($_params['pay_type']);
            $_params['transaction_channel'] = TRANSACTION_CHANNEL_ASTROPAY;
            $_params['transaction_type'] = $_params['payment_method'];
            $_params['currency_type'] = PAYMENT_CURRENCY_TYPE_CASH;
            Log::record('Astropay$_params'.json_encode($_params));
            $res = $this->PayMent->payCommon($_params);//Astropay支付
            Log::record('Astropay$res'.json_encode($res));
            $_params['TransactionID'] = isset($res['TransactionID'])?$res['TransactionID']:0;
            $this->packagingAstropayReturnParams($_params);
            $return = $res['result'];
            if(isset($return['data']['status']) && $return['data']['status'] != PAYMENT_RESULT_STATUS_FAILURE){
                //信用卡支付的不用跳转至第三方页面，直接成功或失败页面 tinghu.liu 20191018
                if ($_params['payment_method'] != TRANSACTION_TYPE_ASTROPAY_CREDIT_CARD){
                    if($_params['pay_type'] == 'Boleto-Astropay'){
                        $_params['boleto_url'] = $return['data']['url'];
                    }
                    $_params['redirect'] = 0;
                    $this->paymentSuccessProcessHeader($_params);
                    $ReturnData['code'] = 1;
                    $ReturnData['data'] = 'success';
                    $url=!empty($return['data']['url'])?$return['data']['url']:'';
                    $ReturnData['url'] = $url;
                    return apiReturn([
                        'code'=>self::API_SECCUSS_CODE,
                        'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                        'url'=>$url,
                        'data'=>$ReturnData,
                        'msg'=>'Success.'
                    ]);
                }
                //是信用卡，且有返回url则跳转，因为印度需要做3d验证 tinghu.liu 20191030
                if ($_params['payment_method'] == TRANSACTION_TYPE_ASTROPAY_CREDIT_CARD && isset($return['data']['url']) && !empty($return['data']['url'])){
                    $_params['redirect'] = 0;
                    $this->paymentSuccessProcessHeader($_params);
                    $url=!empty($return['data']['url'])?$return['data']['url']:'';
                    $ReturnData['code'] = 1;
                    $ReturnData['data'] = 'success';
                    $ReturnData['url'] = $url;
                    return apiReturn([
                        'code'=>self::API_SECCUSS_CODE,
                        'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                        'url'=>$url,
                        'data'=>$ReturnData,
                        'msg'=>'Success.'
                    ]);
                }
            }
            $res = $res['result'];
        }
        ####new  Astropay####

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
                    'data'=>$ReturnData,
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
                $ReturnData['code'] = 1;
                $ReturnData['msg'] = $resDo->DoExpressCheckoutResult->Error->LongMessage;
                //$ReturnData['url'] = url('/home/order/paymentError', '', true, true);
//                $ReturnData['url'] = url('/paymentError?order_number='.$order_master_number.'&reason='.$resDo->DoExpressCheckoutResult->Error->LongMessage, false, false);
                Monlog::write(LOGS_MALL_CART,'error',__METHOD__,__FUNCTION__.$order_master_number,$_params,MALL_API.'/home/order/submitOrder/QuickDoExpressCheckout_res',$resDo);
//                return json($ReturnData);

                return apiReturn([
                    'code'=>1018,
                    'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                    'url'=>MALL_DOCUMENT.'/mpaymentError?order_number='.$order_master_number.'&reason='.urlencode($resDo->DoExpressCheckoutResult->Error->LongMessage),
                    'data'=>$ReturnData,
                    'msg'=>$resDo->DoExpressCheckoutResult->Error->LongMessage
                ]);
            }
        }

        if($_params['pay_type'] == 'IDeal' && $_params['sc_price'] == 0){
            //Bic
            /*if (empty($_params['bic'])){
                $res['code'] = 2;
                $res['msg'] = 'Please select the bank information.';
                return json($res);
            }*/
            $_params['user_name'] = $UserName;
            $_params['transaction_channel'] = TRANSACTION_CHANNEL_IDEAL;
            $_params['transaction_type'] = TRANSACTION_TYPE_IDEAL;
            $_params['currency_type'] = PAYMENT_CURRENCY_TYPE_CASH;
            Log::record('IDeal$_params'.json_encode($_params));
            $res = $this->PayMent->payCommon($_params);
            Log::record('IDeal$res'.json_encode($res));
            //只要是获取token成功的都需要创建OMS订单 20190221 tinghu.liu
            if ($res['code'] == 4){
                $url = !empty($res['url'])?$res['url']:'';
                $_params['TransactionID'] = $res['TransactionID'];
                $this->paymentSuccessProcessHeader($_params);
                $res['code'] = 1;
                return apiReturn([
                    'code'=>self::API_SECCUSS_CODE,
                    'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                    'url'=>$url,
                    'data'=>$res
                ]);
            }else{
                $Reason = (isset($res['data']['error_info']) && !empty($res['data']['error_info']))?$res['data']['error_info']:'Payment failure please retry.';
                return apiReturn([
                    'code'=>1019,
                    'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                    'url'=>MALL_DOCUMENT.'/mpaymentError?order_number='.$order_master_number.'&reason='.urlencode($Reason),
                    'data'=>$ReturnData,
                    'msg'=>$Reason
                ]);
            }
        }

        //使用的是新支付系统
        if ($payment_system_for_repay_update == self::PAYMENT_SYSTEM_NEW){
            //如果是 Pending也需要创建OMS订单(已和payment确认) BY tinghu.liu IN 20190215
            if(isset($res['data']['status']) && $res['data']['status'] != PAYMENT_RESULT_STATUS_FAILURE){
                //成功处理机制
                $_params['redirect'] = 0;//不能直接跳转，需返回给ajax进行跳转
                $_params['payment_status'] = 'Success';
                //logService::write(LOGS_MALL_CART,'info',__METHOD__,__FUNCTION__,[2],MALL_API.'/home/order/submitOrder144','34444444');
                $_result=$this->paymentSuccesProcess($_params);
                return apiReturn([
                    'code'=>self::API_SECCUSS_CODE,
                    'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                    'url'=>MALL_DOCUMENT.'/mpaymentSuccess?order_number='.$_result['order_number'].'&order_total='.$_result['order_total'].'&payment_status='.$_result['payment_status'].'&currency_code='.$_result['currency_code'],
                    'data'=>$_result
                ]);
            }else{
                //错误处理机制
                $Reason = (isset($res['data']['error_info']) && !empty($res['data']['error_info']))?$res['data']['error_info']:'Payment failure please retry.';
                $ReturnData['code'] = 1;
                $ReturnData['reason'] = /*$res->Error->Code.' '.*/$Reason;
                $ReturnData['order_number'] = $_params['order_number'];
                //$ReturnData['url'] = '/home/order/paymentError';
                $ReturnData['url'] = url('/paymentError?order_number='.$order_master_number.'&reason='.$Reason, false, false);
                return apiReturn([
                    'code'=>1019,
                    'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                    'url'=>MALL_DOCUMENT.'/mpaymentError?order_number='.$order_master_number.'&reason='.urlencode($Reason),
                    'data'=>$ReturnData,
                    'msg'=>$Reason
                ]);
            }
        }else{
            if($res->ResponseResult == 'Success'){
                //成功处理机制
                $_params['redirect'] = 0;//不能直接跳转，需返回给ajax进行跳转
                $_params['payment_status'] = 'Success';
                //Monlog::write(LOGS_MALL_CART,'info',__METHOD__,__FUNCTION__,[2],MALL_API.'/home/order/submitOrder144','34444444');
                $_result = $this->paymentSuccesProcess($_params);
                Monlog::write(LOGS_MALL_CART,'info',__METHOD__,__FUNCTION__.$order_master_number,$_params,MALL_API.'/home/order/submitOrder/paymentSuccesProcess_res',json($_result));
                $ReturnData['currency_code']=$_result['currency_code'];
                $ReturnData['order_number']=$_result['order_number'];
                $ReturnData['order_total']=$_result['order_total'];
                $ReturnData['payment_status']=$_result['payment_status'];
                return apiReturn([
                    'code'=>self::API_SECCUSS_CODE,
                    'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                    'url'=>MALL_DOCUMENT.'/mpaymentSuccess?order_number='.$_result['order_number'].'&order_total='.$_result['order_total'].'&payment_status='.$_result['payment_status'].'&currency_code='.$_result['currency_code'],
                    'data'=>$ReturnData
                ]);
            }else{
                //错误处理机制
                $_error_reason = $res->Error->Code.' '.$res->Error->LongMessage;
                $ReturnData['code'] = 1;
                $ReturnData['msg'] = $_error_reason;
                $ReturnData['order_number'] = $_params['order_number'];
                //$ReturnData['url'] = '/home/order/paymentError';
                $ReturnData['url'] = url('/mpaymentError?order_number='.$order_master_number.'&reason='.$res->Error->Code.' '.$res->Error->LongMessage, false, false);
                $_result = json($ReturnData);
                Monlog::write(LOGS_MALL_CART,'info',__METHOD__,__FUNCTION__.$order_master_number,$_params,MALL_API.'/home/order/submitOrder11',$ReturnData);
//            return $_result;
                return apiReturn([
                    'code'=>1019,
                    'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                    'url'=>MALL_DOCUMENT.'/mpaymentError?order_number='.$order_master_number.'&reason='.urlencode($_error_reason),
                    'data'=>$ReturnData,
                    'msg'=>$_error_reason
                ]);
            }
        }
    }

    public function test(){
        //$this->redirect('/mpaymentSuccess');
        header('Location:http://www.example.com/');
        exit;
    }

    /**
     * IDeal接口认证TOKEN，payment成功返回
     * home/Order/iDealPaymentSuccess
     */
    public function iDealPaymentSuccess(){
        $_params = input();
        Log::record("iDealPaymentSuccess_params:".json_encode($_params));
        //参数校验
        $validate = $this->validate($_params,(new OrderParams())->iDealCallBackParams());
        if(true !== $validate){
            Log::record("iDealPaymentSuccess_params_error:".json_encode($_params));
        }
        $_params['type'] = 'Success';
        //写队列，异步调用
        $this->CommonService->loadRedis()->lPush("mall_order_success_ideal", json_encode($_params));
//        $this->PayMent->IDealTokenReturnUrl($_params);

        //获取订单信息
        $_token = isset($_params['cko-payment-token']) ? $_params['cko-payment-token'] : '';
        $orderInfo = $this->CommonService->loadRedis()->get("IDealSubmitInfo_".strtoupper($_token));
        Log::record("iDealPaymentSuccess$orderInfo:".json_encode($orderInfo));
        /*
         * 移至获取token成功时处理 20190221 tinghu.liu
         * if(!empty($orderInfo)){
            $this->paymentSuccessProcessHeader($orderInfo);
        }*/
        $OrderNumber = isset($orderInfo['OrderNumber']) ? $orderInfo['OrderNumber'] : $orderInfo['order_number'];


        $OrderTotal = isset($orderInfo['OrderTotal']) ? $orderInfo['OrderTotal']: 0;
        $currency =  isset($orderInfo['currency']) ? $orderInfo['currency']: DEFAULT_CURRENCY;
        //跳转成功页面
//        $this->redirect('Order/paymentSuccess',['order_number' => $OrderNumber,'order_total' => $OrderTotal,'currency_code' => $currency]);

        //跳转成功页面
        header('Location:'.MALL_URL.'mpaymentSuccess?order_number='.$OrderNumber.'&order_total='.$OrderTotal.'&currency_code='.$currency);exit();
    }

    /**
     * IDeal接口认证TOKEN，payment失败返回
     * home/Order/iDealPaymentFailure
     */
    public function iDealPaymentFailure(){
        $_params = input();
        Log::record("iDealPaymentFailure_params:".json_encode($_params));

        $_token = isset($_params['cko-payment-token']) ? $_params['cko-payment-token'] : '';
        $orderInfo = $this->CommonService->loadRedis()->get("IDealSubmitInfo_".$_token);

        $OrderNumber = isset($orderInfo['OrderNumber']) ? $orderInfo['OrderNumber'] : $orderInfo['order_number'];

        //跳转失败页面
//        $this->redirect('Order/paymentError',['order_number' => $orderInfo['order_number'],'reason' => lang('payment_try_again')]);
        //跳转失败页面
        $msg='System Error,try again later';
        if (!empty($OrderNumber)){
            header('Location:'.MALL_URL.'mpaymentError?order_number='.$OrderNumber.'&reason='.$msg);exit();
        }else{
            header('Location:'.MALL_URL.'mpaymentError?error&reason='.$msg);exit();
        }
    }

    /**
     * 支付页面获取订单详情数据功能接口【不登录也可以进行支付】
     * @return \think\response\Json
     * Home/Order/getPayOrderInfo
     * {
    "pay_token":"X1909101001I0N0WA0XDG",
    "pay_channel":"PayPal"
    "pay_type":"PayPal"
     * }
     */
    public function getPayOrderInfo(){
        $_params = input();
        if(
            !isset($_params['pay_token'])
            || !isset($_params['pay_type'])
            || empty($_params['pay_token'])
            || empty($_params['pay_type'])
        ){
            $ReturnData['code'] = 0;
            $ReturnData['msg'] = $ReturnData['tips'] = 'Invalid access.';
            return json($ReturnData);
        }
        $res = $this->OrderService->getRepetitionPayV2($_params);
        $tips = isset($res['tips'])?$res['tips']:'';
        if(isset($res['code']) && $res['code'] == 1){
            $Currency = $res['currency'];
            $CurrencyCode = $this->CommonService->getCurrencyCode($Currency);
            $ReturnData['currencyCode'] = $CurrencyCode['Code'];
            $ReturnData['code'] = 1;
            $ReturnData['has_tariff_insurance'] = $res['has_tariff_insurance'];
            $ReturnData['tariff_insurance_amount'] = $res['tariff_insurance_amount'];
            $ReturnData['data'] = $res['data'];
        }else{
            $ReturnData['code'] = 0;
            $ReturnData['msg'] = isset($res['msg'])?$res['msg']:'error';
            Log::record('getRepetitionPayV2-error:'.json_encode($res), Log::DEBUG);
        }
        /*3s、返回给前端*/
        $ReturnData['tips'] = $tips;
        return json($ReturnData);
    }

    /**
     * 组装支付数据
     */
    private function submitOrderCommonForPlaceOrder($_params){
        $_rate = 1;
        $_customer_id = $_params['customer_id'];
        //'订单来源：10-PC，20-Android，30-iOS，40-Pad，50-Mobile'
        $_order_from = 10;
        $_params['order_from'] = input("order_from")?input("order_from"):'';

        //SC支付
        $_params['sc_password'] = input("sc_password")?input("sc_password"):'';
        $_params['sc_price'] = input("sc_price")?input("sc_price"):0;
        $is_paypal_quick = input('is_paypal_quick')?input('is_paypal_quick'):0;
        $_params['is_paypal_quick'] = 0;//APP没有快捷支付
        $_is_buynow = input('IsBuyNow');//用于判断是否是buynow
        $_params['is_buynow'] = $_is_buynow?$_is_buynow:0;
        $_params['customer_id'] = $_customer_id;
        $_params['order_message'] = isset(input()['message'])?input()['message']:'';

        $_params['pay_type'] = input("pay_type")?input("pay_type"):'';//支付方式,支付方式的不同，调用不同的接口,并有不同的处理流程
        $_params['pay_chennel'] = input("pay_chennel")?input("pay_chennel"):'';
        $_params['bic'] = input("bic")?input("bic"):'';//新的payment Ideal支付专有
        $_params['cpf'] = input("CPF",'');//Astropay支付的CPF
        $_params['card_bank'] = input("CardBank",'');//Astropay
        /**需要判断当前币种是否是我们的实收币种，如果不是，则强制转成美元来收取*/

        if($is_paypal_quick == 0){
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

        //是否是affiliate订单，条件（缺一不可）：1、$this->affiliate不为空；2、产品SPU佣金比例大于0
        //这些判断逻辑均在Task项目生成订单佣金时处理了
        $_params['affiliate'] = '';
        $_params['customer_address_id'] = input("customer_address_id");
        //拿到汇率
        if($_params['currency'] != DEFAULT_CURRENCY){
            $_rate = $this->CommonService->getOneRate( DEFAULT_CURRENCY,$_params['currency']);
        }
        $_params['rate'] = $_rate;
        //如果是信用卡支付的，接收信用卡信息(或者是选择了某张信用卡的，调取该信用卡信息)
//        $_params['BillingAddress']['City'] = input('City');
//        $_params['BillingAddress']['CityCode'] = input('CityCode');
//        $_params['BillingAddress']['Country'] = input('Country');
//        $_params['BillingAddress']['CountryCode'] = trim(input('CountryCode'));
//        $_params['BillingAddress']['Email'] = trim(input('Email'));
//        $_params['BillingAddress']['FirstName'] = input('FirstName');
//        $_params['BillingAddress']['LastName'] = input('LastName');
//        $_params['BillingAddress']['Mobile'] = input('Mobile');
//        $_params['BillingAddress']['Phone'] = input('Phone');
//        $_params['BillingAddress']['PostalCode'] = input('PostalCode');
//        $_params['BillingAddress']['State'] = input('State');
//        $_params['BillingAddress']['Street1'] = input('Street1');
//        $_params['BillingAddress']['Street2'] = input('Street2');
//        $_params['CardInfo']['CVVCode'] = input('CVVCode');
//        $_params['CardInfo']['CardHolder'] = input('FirstName').' '.input('LastName');
//        $_params['CardInfo']['CardNumber'] = preg_replace('# #','',input('CardNumber'));
//        $_params['CardInfo']['ExpireMonth'] = input('ExpireMonth');
//        $_params['CardInfo']['ExpireYear'] = 2000+input('ExpireYear');
        //$_params['CardInfo']['IssuingBank'] = 'Visa';//input('IssuingBank')
        //Pagsmile专用 20190309 tinghu.liu
//        $_params['CardInfo']['psPaymentMethodId'] = input('psPaymentMethodId');//Pagsmile 卡类型
//        $_params['psToken'] = input('psToken');//Pagsmile 支付Token

//        $_params['payment_method'] = input("payment_method");//Astropay的支付方式()
//        $_params['cpf'] = input("cpf");//Astropay支付的CPF
//        $_params['card_bank'] = input("card_bank");//Astropay
//        $_params['credit_card_token_id'] = input("credit_card_token_id");//信用卡支付的tokenID
        $_params['querystring'] = input("querystring")?htmlspecialchars_decode(input("querystring")):'';//payment返回，order返回到前端，paypal支付get,do阶段使用
        //获取用户的地址信息
        $_params['order_master_number'] = input("order_number")?input("order_number"):0;
        $_params['NocNoc'] = input('NocNoc')?input('NocNoc'):0;

        $_params['CVVCode'] = input('CVVCode')?input('CVVCode'):'956';
        //测试repay
        //$_params['order_master_number'] = '180610019742134905';
        //如果是快捷支付的，没有使用客户的地址信息，而是使用了从paypal带过来的地址
        $_paypal_address = isset(input()['address'])?input()['address']:null;

        if(is_array($_paypal_address)){
            $_params['ShippingAddress']['City'] = isset($_paypal_address['city'])?$_paypal_address['city']:'';
            $_params['ShippingAddress']['CityCode'] = isset($_paypal_address['cityCode'])?$_paypal_address['cityCode']:'';
            $_params['ShippingAddress']['Country'] = isset($_paypal_address['country'])?$_paypal_address['country']:'';
            $_params['ShippingAddress']['CountryCode'] = isset($_paypal_address['countryCode'])?trim($_paypal_address['countryCode']):'';
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
            $_params['ShippingAddress']['cpf'] = isset($_paypal_address['cpf'])?$_paypal_address['cpf']:'';

        }
        if(input('saveCard') == 'true'){
            $_params['save_card'] = 1;
        }else{
            $_params['save_card'] = 0;
        }

        /**
         * 信用卡支付账单地址是否使用和收货地址一致的信息 tinghu.liu 20190723
         * 包含信息：地址1，地址2，国家，省，城市，邮编，电话号码，邮箱
         */
        if(input('UseShippingAddress') == 'true'){
            $_params['use_shipping_address'] = 1;
        }else{
            $_params['use_shipping_address'] = 0;
        }

        //$_params['save_card'] = (input('saveCard') == true)?1:0;//是否保存信用卡信息
        /** 20190108 关税保险 start 按照多个店铺来处理 **/
        /*TariffInsurance[0][StoreId]: 18
        TariffInsurance[0][IsChecked]: 1*/
        $is_tariff_insurance = 0;//是否买了关税保险,0表示没有买，1表示买了(有一个店铺选择了就算购买)
        $tariff_insurance = isset(input()['TariffInsurance'])?input()['TariffInsurance']:[];
        $_params['tariff_insurance'] = $tariff_insurance;
        if (!empty($tariff_insurance) && is_array($tariff_insurance)){
            foreach ($tariff_insurance as $k=>$v){
                if (isset($v['IsChecked']) && $v['IsChecked'] == 1){
                    $is_tariff_insurance = 1;
                    break;
                }
            }
        }
        $_params['is_tariff_insurance'] = $is_tariff_insurance;
        /** 20190108 关税保险 end 按照多个店铺来处理 **/
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
        //记录支付方式等信息,在获取支付方式的时候要带出来给前端,什么情况下需要记录支付信息???
//        $this->CommonService->recordParams($_params);
        //Cookie::set("payParams",json_encode($_params));
        $this->CommonService->loadRedis()->set("payParams_placeOrder_".$_customer_id, json_encode($_params));
    }
    /**
     * 用户下单
     * 只接收订单留言的数据，生成订单需要的数据全部读取购物车的内容
     * @param message 订单留言(17-85-233-请尽快发货;商铺ID-ProductId-SKUId-快点发货)
     * code：0-刷新页面，1-跳转，2-文案提示，3-地址数据校验不通过，需要通知前端弹出地址输入框，再次填写收货地址，4-IDeal相关， 5-SC支付验证错误，需要将提示信息展示在SC密码框附近
     * @return \think\response\Json
     */
    public function placeOrder(){
        $_params = input();
        $_customer_id = $_params['customer_id'];
        $_token = input("checkout_token")?input("checkout_token"):cookie('checkout_token');
        //支付时间超时处理，支付页面超时，直接跳到cart页面
        //调用接收参数接口
        $this->submitOrderCommonForPlaceOrder($_params);
        //$_params = json_decode(Cookie::get("payParams"),true);
        $_params = json_decode($this->CommonService->loadRedis()->get("payParams_placeOrder_".$_customer_id),true);
        //日志记录参数
        $_log_params = CommonLib::removeSensitiveInfoForLog($_params, 1);
        Log::record('placeOrder$_params2'.json_encode($_params));
        $is_paypal_quick = $_params['is_paypal_quick'];

        /*********************** end ************************/
        //20181214 获取用户邮箱
        $check_user_result = doCurl(CIC_API.'/cic/Customer/GetEmailsByCID',['id'=>$_customer_id], null, true);
        if(
            !isset($check_user_result['code'])
            || $check_user_result['code'] != 200
            || !isset($check_user_result['data'])
            || empty($check_user_result['data'])
        ){
            $_return_data['code'] = 2;
            $_return_data['data'] = 'User information error.';
            $_return_data['msg'] = 'User information error.';
            return json($_return_data);
        }
        $_params['email'] = $check_user_result['data'];

        /**
         * 增加用户状态判断，如果“已禁用”则不让下单，避免用户恶意下单禁用用户后仍能下单的问题
         * -1 匿名账户不允许激活 0激活未激活账户 1正常用户 -3账户不存在  10 未指定 20未激活禁用 21已激活禁用
         * 常用的状态：0 - 未激活,1 - 正常用户 ,20 - 未激活禁用 ,21 - 已激活禁用
         * tinghu .liu 20191028
         */
        $_user_status = isset($check_user_result['user_status'])?$check_user_result['user_status']:0;
        if (in_array($_user_status, [20,21])){
            $_return_data['code'] = 2;
            $_return_data['data'] = '';
            $_return_data['msg'] = 'The account number is abnormal and cannot be placed or paid normally. If you have any questions, please give us your feedback. Thank you.';
            return json($_return_data);
        }

        //判断是否有收货信息
        if(!$_params['customer_address_id'] && !isset($_params['ShippingAddress'])){
            $Data['code'] = 2;
            $Data['data'] = '';
            $Data['msg'] = 'the shipping address is empty!';
            return json($Data);
        }
        //如果是NOC，需要跳转到TaxId输入页面，如果是已经传了TaxId的，则不需要跳到TaxId输入页面，直接走下面的流程（因为有TaxId，说明已经询价过了，这里不需要再处理）
        $tax_id = input('NocNocTaxId');
        $_params['nocnoc_tax_id'] = $tax_id;
        if($_params['NocNoc'] == 1 && empty($tax_id) && !empty($_customer_id)){
            //Cache::set('nocSubmitOrderParams'.$_customer_id, input(), 60*30);
            //跳到输入TaxId的页面
            $ReturnData['code'] = 1;
            $ReturnData['data'] = 'nocnoc';
            $ReturnData['msg'] = 'nocnoc';
            $ReturnData['url'] = '/home/Noc/index?'.$_params['querystring'];
            return json($ReturnData);
        }
        //20181221 如果是NOC，需要判断NOC运费是否正确，不正确不让提交
        if ($_params['NocNoc'] == 1){
            if(isset($_params['is_buynow']) && $_params['is_buynow']){
                $_cart_info = $this->CommonService->loadRedis()->get("ShoppingCartBuyNow_".$_customer_id);
            }else{
                $_cart_info = $this->CommonService->loadRedis()->get("ShoppingCart_CheckOut".$_customer_id);
            }
            if (!isset($_cart_info[$_customer_id]['nocdata'])){
                $_return_data['code'] = 2;
                $_return_data['msg'] = 'Nocnoc shipping fee error.';
                $_return_data['data'] = 'Nocnoc shipping fee error.';
                Log::record('Nocnoc$_cart_info2'.json_encode($_cart_info));
                return json($_return_data);
            }
        }

        /**
         * ========================================================================
         * ========================================================================
         * 如果使用新的payment系统进行支付，则走新payment支付流程，否则走之前的支付逻辑
         * tinghu.liu 20190812
         * ========================================================================
         * ========================================================================
         * TODO 是否使用新支付系统需要在支付页面进行判断，因为在创建订单这一步没有支付渠道和支付方式，不能准确判断
         */
        //创建订单时候不指定使用的支付系统
        $payment_system = $payment_system_for_repay_update = self::PAYMENT_SYSTEM_OLD;//使用的支付系统。1-旧系统（.net）;2-新系统（php）
        $payment_system_for_repay_update = self::PAYMENT_SYSTEM_NEW;

        //入库的使用支付系统标识以$payment_system_for_repay_update为准
        $_params['payment_system'] = $payment_system_for_repay_update;
        $_params['is_cod'] =0;
        //创建订单
        $_params['is_submit_order'] = 1;
        Log::record('submitOrder'.json_encode($_params));
        $_create_order_res = $this->OrderService->submitOrder($_params);

        if(!isset($_create_order_res['code']) || $_create_order_res['code'] != 1){
            //返回信息
            if(isset($_create_order_res['data']['orderInfo']['master']['grand_total']) &&
                $_create_order_res['data']['orderInfo']['master']['grand_total'] ==0){
                //订单金额为0的，订单状态为200（在创建订单时已经将状态修改为了200）
                $_params['OrderStatus'] = 200;
                $_params['order_number'] = isset($_create_order_res['data']['orderInfo']['master']['order_number'])?$_create_order_res['data']['orderInfo']['master']['order_number']:'';
                $this->paymentSuccessProcessHeader($_params);
                $ReturnData['code'] = 1;
                $ReturnData['order_number'] = $_params['order_number'];
                $ReturnData['order_total'] = 0;
                $ReturnData['payment_status'] = 'success';
                $currency_code = $this->CommonService->getCurrencyCode($_params['currency']);
                $ReturnData['currency_code'] = $currency_code['Code'];
                $ReturnData['data'] = '/paymentSuccess';
                $ReturnData['msg'] = 'Success';
                return json($ReturnData);
            }else{
                $ReturnData['code'] = $_create_order_res['code'];
                $ReturnData['order_number'] = isset($_create_order_res['data']['orderInfo']['master']['order_number'])?$_create_order_res['data']['orderInfo']['master']['order_number']:'';
                $ReturnData['order_total'] = isset($_create_order_res['data']['orderInfo']['master']['grand_total'])?$_create_order_res['data']['orderInfo']['master']['grand_total']:0;
                $ReturnData['data'] = '/paymentSuccess'; //TODO ???
                $ReturnData['msg'] = isset($_create_order_res['msg'])?$_create_order_res['msg']:'create order is error!';
                return json($ReturnData);
            }
        }
        $_create_order_res = $_create_order_res['data'];
        $_params['orderInfo'] = $_create_order_res['orderInfo'];
        $_params['order_number'] = isset($_create_order_res['data']['master']['order_number'])?$_create_order_res['data']['master']['order_number']:'';
        $order_master_number = $_params['order_number'];
        $pay_token = $_create_order_res['data']['pay_token'];

        //获取子单/主单数组，新payment支付用 tinghu.liu 20190925
        $payment_child_order_number = [];
        $payment_child_order_price = [];
        foreach ($_params['orderInfo']['slave'] as $k100=>$v100){
            $payment_child_order_number[] = $v100['order']['order_number'];
            $payment_child_order_price[$v100['order']['order_number']] = sprintf("%.2f", $v100['order']['grand_total']);
        }

        $payment_child_order_price[$_params['orderInfo']['master']['order_number']] = sprintf("%.2f", $_params['orderInfo']['master']['grand_total']);
        $_params['ChildOrderList'] = $payment_child_order_number;
        $_params['ChildOrderPrice'] = $payment_child_order_price;
        //跳转至支付页面进行支付
        $ReturnData['code'] = 200;
        $ReturnData['data'] = $pay_token;
        $ReturnData['msg'] = '';
        return json($ReturnData);
    }

    /**
     * TODO 支付操作
     * 只接收订单留言的数据，生成订单需要的数据全部读取购物车的内容
     * message 订单留言(17-85-233-请尽快发货;商铺ID-ProductId-SKUId-快点发货)
     * code：0-刷新页面，1-跳转，2-文案提示，3-地址数据校验不通过，需要通知前端弹出地址输入框，再次填写收货地址，4-IDeal相关， 5-SC支付验证错误，需要将提示信息展示在SC密码框附近
     * @return \think\response\Json
     * home/Order/payOrder
     */
    public function payOrder(){
        $post_params = input();
        //$_log_params = CommonLib::removeSensitiveInfoForLog($post_params, 1);
        Log::record('payOrder$_params0'.json_encode($post_params));
        $post_params['pay_type']=$post_params['PayType'];
        $post_params['pay_chennel']=$post_params['PayChennel'];
        try{
            //参数必填判断，增加错误邮件提醒
            $validate = $this->validate($post_params,(new OrderParams())->submitOrderParams());
            if(true !== $validate){
                $_return_data['code'] = 2; //前端在页面提示，但是不JUMPPURL
                $_return_data['msg'] = 'token error'; //'token error';
                $_return_data['data'] = 'token error'; //'token error';
                $_return_data['debug_code'] = 100;
                logService::write(LOGS_MALL_CART,'error',__METHOD__,'submitOrder',null,null,$_return_data);
                return json($_return_data);
            }

            //需要做一个token验证处理,排除paypal，因为新版PayPal没有cookie，获取不到session
            $_pay_token = input("pay_token")?input("pay_token"):'';
            $_pay_token_info = $this->CommonService->getOrderBaseInfoByPayToken($_pay_token);
           // Log::record('$_pay_token_info'.json_encode($_pay_token_info));
            if (empty($_pay_token_info)){
                $_return_data['code'] = 2; //前端在页面提示，但是不JUMPPURL
                $_return_data['msg'] = 'token error'; //;
                $_return_data['data'] = 'token error'; //;
                $_return_data['debug_code'] = 101;
                logService::write(LOGS_MALL_CART,'error',__METHOD__,'submitOrder',null,null,$_return_data);
                return json($_return_data);
            }

            $_customer_id = $_pay_token_info['order_data']['slave'][0]['customer_id'];
            $_customer_name = $_pay_token_info['order_data']['slave'][0]['customer_name'];
            $_currency_code = $_pay_token_info['order_data']['slave'][0]['currency_code'];
            $_exchange_rate = $_pay_token_info['order_data']['slave'][0]['exchange_rate'];
            $_language_code = $_pay_token_info['order_data']['slave'][0]['language_code'];
            $_order_status = $_pay_token_info['order_data']['slave'][0]['order_status'];
            $_order_branch_status = $_pay_token_info['order_data']['slave'][0]['order_branch_status'];
            $_lock_status = $_pay_token_info['order_data']['slave'][0]['lock_status'];
            $_affiliate = $_pay_token_info['order_data']['slave'][0]['affiliate'];
            $_pay_channel = $_pay_token_info['order_data']['slave'][0]['pay_channel'];
            $_pay_type = $_pay_token_info['order_data']['slave'][0]['pay_type'];
            $order_master_number = $_pay_token_info['order_data']['master']['order_number'];
            $_country_code = $_pay_token_info['order_data']['address']['country_code'];
            $_order_from=input("order_from")?input("order_from"):'';
            $_is_buynow = input('IsBuyNow');//用于判断是否是buynow
            /********** 状态控制，已经支付成功的不能再次支付 start ***********/
            if (
                $_order_status == 100
                || ($_order_status == 120 && $_pay_channel == 'Astropay' && $_pay_type == 'CreditCard')
            ){
                //订单锁住状态(60:正常，未加锁,73:强制锁住，需手动解锁)，折扣过高、或者admin手动加锁的情况订单状态为73
                if ($_lock_status == 73){
//                $_return_data['code'] = 2; //前端在页面提示，但是不JUMPPURL
//                $_return_data['msg'] = 'The order cannot be paid under lock condition.'; //'token error';
//                $_return_data['debug_code'] = 102;
//                logService::write(LOGS_MALL_CART,'info',__METHOD__,'submitOrder',null,null,$_return_data,$_customer_id,$order_master_number);
//                return json($_return_data);
                }
            }else{
                $_return_data['code'] = 2; //前端在页面提示，但是不JUMPPURL
                $_return_data['data'] = 'Payment cannot be made under this order status.'; //;
                $_return_data['msg'] = 'Payment cannot be made under this order status.'; //'token error';
                $_return_data['debug_code'] = 103;
                Log::record($_order_status.'submitOrder'.$order_master_number,'error');
                logService::write(LOGS_MALL_CART,'error',__METHOD__,'submitOrder'.$order_master_number,null,null,$_return_data,$_customer_id,$order_master_number);
                return json($_return_data);
            }

            /********** 状态控制，已经支付成功的不能再次支付 end ***********/
            //调用接收参数接口
            $this->submitOrderCommonForPayOrder($_customer_id, $_exchange_rate,$_currency_code,$_language_code,$_country_code,$_order_from,$_is_buynow);
            $_params = json_decode($this->CommonService->loadRedis()->get("payParams_payOrder_".$_customer_id),true);
            //
            if(empty($_params['cpf'])&&!empty($_pay_token_info['order_data']['address']['cpf'])){
                $_params['cpf']=$_pay_token_info['order_data']['address']['cpf'];
            }
            //Log::record('$_paramscpf]'.json_encode($_params['cpf']));
            //如果收货国家为巴西，且支付方式是PayPal或SC、或Astropay，则cpf（税号）不能为空 tinghu.liu 20191125
            if (
                $_country_code == 'BR'
                && empty($_params['cpf'])
            ){
                $_return_data['code'] = 2; //前端在页面提示，但是不JUMPPURL
                $_return_data['data'] = [];
                $_return_data['msg'] = 'The field CPF is required.Please update APP'; //'token error';
                $_return_data['debug_code'] = 201;
                Log::record('$_pay_token_info'.json_encode($_pay_token_info),'error');
                return json($_return_data);
            }
            //20181214 获取用户邮箱
            $check_user_result = doCurl(CIC_API.'/cic/Customer/GetEmailsByCID',['id'=>$_customer_id], null, true);
            if(
                !isset($check_user_result['code'])
                || $check_user_result['code'] != 200
                || !isset($check_user_result['data'])
                || empty($check_user_result['data'])
            ){
                $_return_data['code'] = 2;
                $_return_data['data'] = 'User information error.';
                $_return_data['msg'] = 'User information error.';
                $_return_data['debug_code'] = 105;
                logService::write(LOGS_MALL_CART,'error',__METHOD__,'submitOrder'.$order_master_number,null,null,$_return_data,$_customer_id,$order_master_number);
                return json($_return_data);
            }
            $_params['email'] = $check_user_result['data'];

            /**
             * 增加用户状态判断，如果“已禁用”则不让下单，避免用户恶意下单禁用用户后仍能下单的问题
             * -1 匿名账户不允许激活 0激活未激活账户 1正常用户 -3账户不存在  10 未指定 20未激活禁用 21已激活禁用
             * 常用的状态：0 - 未激活,1 - 正常用户 ,20 - 未激活禁用 ,21 - 已激活禁用
             * tinghu .liu 20191028
             */
            $_user_status = isset($check_user_result['user_status'])?$check_user_result['user_status']:0;
            if (in_array($_user_status, [20,21])){
                $_return_data['code'] = 2;
                $_return_data['data'] = 'User information error.';
                $_return_data['msg'] = 'The account number is abnormal and cannot be placed or paid normally. If you have any questions, please give us your feedback. Thank you.';
                return json($_return_data);
            }

            $_params['pay_token'] = $_pay_token;
            $_params['customer_id'] = $_customer_id;
            $_params['order_master_number'] = $order_master_number;
//        $_params['order_message'] = isset(input()['message'])?input()['message']:'';
//        $_params['currency'] = $_currency_code;
//        $_params['lang'] = $_language_code;

            //TODO 如果订单币种和支付币种不一致，需要将订单币种转换为支付币种。使用PayPal且币种为ARS的情况下（paypal支持BRL币种） tinghu.liu 20190917
            if ($_currency_code != $_params['currency']){
                //如果修改失败，则返回用户失败，请重试的信息，不继续支付

            }
            //日志记录参数
            $_log_params = CommonLib::removeSensitiveInfoForLog($_params, 1);

            /**
             * 用户选择的币种不等于checkout页面的币种 || 实际支付渠道和方式和币种对应的支付渠道方式不匹配，则刷新checkout页面，避免不同币种
             * 【解决用户进入checkout后，在其他页面改变币种，但没刷新checkout，在checkout进行操作出现问题情况】
             * 【排除repay情况】
             * tinghu.liu 20190627
             * start
             */
            /*$_create_order_res = $this->CommonService->loadRedis()->get("repetitionPayV2_" . $order_master_number . '_' . $_customer_id);
            //超过订单信息保存时间用户没刷新页面，则自动去获取支付数据，再没有则提示用户超时，请重试
            if (!$_create_order_res){
                $_create_order_res = $this->OrderService->getRepetitionPayV2(
                        [
                            'pay_token'=>$_pay_token,
                            'pay_type'=>$_params['pay_type'],
                        ],
                        1
                );
                if (empty($_create_order_res)){
                    $_return_data['code'] = 2;
                    $_return_data['msg'] = 'Payment failed, please try again.';
                    $_return_data['debug_code'] = 104;
                    logService::write(LOGS_MALL_CART,'error',__METHOD__,'submitOrder'.$order_master_number,null,null,$_return_data,$_customer_id,$order_master_number);
                    return json($_return_data);
                }
            }*/

            $_create_order_res = $this->OrderService->getRepetitionPayV2(
                [
                    'pay_token'=>$_pay_token,
                    'pay_type'=>$_params['pay_type'],
                ],
                1
            );
            Log::record('$_create_order_res1'.json_encode($_create_order_res));
            if (!isset($_create_order_res['code']) || $_create_order_res['code'] != 1){

                $_return_data['code'] = 2;
                $_return_data['data'] = 'User information error.';
                $_return_data['msg'] = isset($_create_order_res['msg'])?$_create_order_res['msg']:'Payment failed, please try again.';
                $_return_data['debug_code'] = 104;
                logService::write(LOGS_MALL_CART,'error',__METHOD__,'submitOrder'.$order_master_number,null,null,$_return_data,$_customer_id,$order_master_number);
                return json($_return_data);
//            $_create_order_res = $this->CommonService->loadRedis()->get("repetitionPayV2_" . $order_master_number . '_' . $_customer_id);
//
//            if (empty($_create_order_res)){
//                $_return_data['code'] = 2;
//                $_return_data['msg'] = 'Payment failed, please try again.';
//                $_return_data['debug_code'] = 104;
//                logService::write(LOGS_MALL_CART,'error',__METHOD__,'submitOrder'.$order_master_number,null,null,$_return_data,$_customer_id,$order_master_number);
//                return json($_return_data);
//            }
            }

            $country_code = isset($_create_order_res['orderInfo']['slave'][0]['shipping_address']['country_code'])?$_create_order_res['orderInfo']['slave'][0]['shipping_address']['country_code']:'';

            /*********************** end ************************/

            /**
             * ========================================================================
             * ========================================================================
             * 如果使用新的payment系统进行支付，则走新payment支付流程，否则走之前的支付逻辑
             * tinghu.liu 20190812
             * ========================================================================
             * ========================================================================
             */
            $payment_system = self::PAYMENT_SYSTEM_NEW;
            $payment_system_for_repay_update = self::PAYMENT_SYSTEM_NEW;

            //入库的使用支付系统标识以$payment_system_for_repay_update为准
            $_params['payment_system'] = $payment_system_for_repay_update;

            /*********** PayPal是否使用新支付系统进行支付 end ***********/
            if($_params['pay_type'] == 'IDeal') {
                    //Bic
                    if (empty($_params['bic'])) {
                        $res['code'] = 2;
                        $res['data'] = 'Please select the bank information.';
                        $res['msg'] = 'Please select the bank information.';
                        $res['debug_code'] = 106;
                        logService::write(LOGS_MALL_CART,'error',__METHOD__,'submitOrder'.$order_master_number,null,null,$res,$_customer_id,$order_master_number);
                        return json($res);
                    }
            }

            //如果是SC，将SC校验放在创建订单之前，且文案给用户提示，不用跳转至支付失败页面 20190410 tinghu.liu
            if(strtolower($_params['pay_type']) == 'sc'){
                //获取用户的SC金额
                $_params['pay_type'] = 'SC';//重置支付方式跟支付渠道
                $_params['pay_chennel'] = 'SC';
                $_sc_amount = $grand_total = 0;
                /** 获取支付金额 **/
                $grand_total = $_create_order_res['orderInfo']['master']['grand_total'];
                //调用SC验证接口,
                $_sc_res = $this->OrderService->checkSC($grand_total,$_params['sc_password'],$_params['customer_id'],$_params['currency']);
                $_params['sc_price'] = $grand_total;
                //拿到结果后进行汇率转换，判断是否可以使用
                if(!isset($_sc_res['code']) || $_sc_res['code'] != 200 || !isset($_sc_res['data'])){
                    //出错处理
                    $ReturnData['code'] = 5;
                    $ReturnData['data'] = '';
                    $ReturnData['msg'] = isset($_sc_res['msg'])?$_sc_res['msg']:'sc data is error!!';
                    logService::write(LOGS_MALL_CART,'error',__METHOD__,'submitOrder'.$order_master_number,$_log_params,MALL_API.'/home/order/submitOrder/checkSC_res',$_sc_res, $_customer_id, $order_master_number);
                    return json($ReturnData);
                }
                //币种不相同的情况处理
                if($_params['currency'] != $_sc_res['data']['CurrencyType']){
                    $ReturnData['code'] = 5;
                    $ReturnData['data'] = '';
                    $ReturnData['msg'] = 'The currency is error!!';
                    logService::write(LOGS_MALL_CART,'error',__METHOD__,'submitOrder'.$order_master_number,$_log_params,'/home/order/submitOrder/sc','The currency is error!!', $_customer_id, $order_master_number);
                    return json($ReturnData);
                }
                //金额不够的情况处理
                if($_sc_res['data']['UsableAmount'] < $_params['sc_price']){
                    $ReturnData['code'] = 5;
                    $ReturnData['data'] = '';
                    $ReturnData['msg'] = 'SC balance insufficient.';
                    logService::write(LOGS_MALL_CART,'error',__METHOD__,'submitOrder'.$order_master_number,$_log_params,MALL_API.'/home/order/submitOrder/sc','SC Balance insufficient', $_customer_id,$order_master_number);
                    logService::write(LOGS_MALL_CART,'error',__METHOD__,'submitOrder'.$order_master_number,$_log_params,'/home/order/submitOrder/sc_res',$_sc_res, $_customer_id, $order_master_number);
                    return json($ReturnData);
                }
            }

            ######对正常支付与repay支付的区分处理__START############################
            //repay,把订单信息写入到$_params['orderInfo']，$_params['order_number'],摸拟新生成订单
            //要把新的支付方式传进去,更新原有的支付方式

            $_params['orderInfo'] = $_create_order_res['orderInfo'];
            $_create_order_res = $_create_order_res['data'];
            $_params['order_number'] = isset($_create_order_res['data']['master']['order_number'])?$_create_order_res['data']['master']['order_number']:'';
//        $order_master_number = $_params['order_number'];
            //支付币种保持和下单时一致 BY tinghu.liu IN 20190227
//        $_params['currency'] = isset($_params['orderInfo']['master']['currency_code'])?$_params['orderInfo']['master']['currency_code']:$_params['currency'];
            //修改支付方式？？？？如果是repay需要修改支付方式和渠道、使用的支付系统 tinghu.liu 20190315
            //TODO 增加用户输入的CPF保存功能
            $_update_order_params = [
                'order_master_number'=>$order_master_number,
                'pay_type'=>$_params['pay_type'],
                'pay_channel'=>$_params['pay_chennel'],
                'payment_system'=>$payment_system_for_repay_update,
                'cpf'=>$_params['cpf'],
            ];
            $_update_order_res = doCurl(
                MALL_API."/orderfrontend/order/updateOrderPaytypeAndChannel",
                $_update_order_params,
                null, true);
            Log::record('updateOrderPaytypeAndChannel, params:'.json_encode($_update_order_params).', res:'.json_encode($_update_order_res));
            if (
                !isset($_update_order_res['code'])
                || $_update_order_res['code'] != 200
            ){
                logService::write(LOGS_MALL_CART,'error',__METHOD__,'submitOrder'.$order_master_number,$_create_order_res['data'],MALL_API.'/orderfrontend/order/updateOrderPaytypeAndChannel',$_update_order_res, $_customer_id, $order_master_number);
            }

            //获取子单数组，新payment支付用 tinghu.liu 20190816
            $payment_child_order_number = [];
            $payment_child_order_price = [];
            foreach ($_create_order_res['data']['slave'] as $k100=>$v100){
                $payment_child_order_number[] = $v100['order_number'];
                $payment_child_order_price[$v100['order_number']] = sprintf("%.2f", $v100['grand_total']);
            }

            $payment_child_order_price[$_create_order_res['data']['master']['order_number']] = sprintf("%.2f", $_create_order_res['data']['master']['grand_total']);

            $_params['ChildOrderList'] = $payment_child_order_number;
            $_params['ChildOrderPrice'] = $payment_child_order_price;
            /**
             * 如果是信用卡支付，且用户选择了使用收货地址为账单地址数据，则将收货地址赋值给账单地址 start
             * tinghu.liu 20190724
             */
            if ($_params['use_shipping_address'] == 1){
                $_shipping_address = isset($_params['orderInfo']['slave'][0]['shipping_address'])?$_params['orderInfo']['slave'][0]['shipping_address']:[];
                Log::record('use_shipping_address:_$_shipping_address:'.json_encode($_shipping_address));
                if (!empty($_shipping_address)){
                    $_params['BillingAddress']['City'] = isset($_shipping_address['city'])?$_shipping_address['city']:'';
                    $_params['BillingAddress']['CityCode'] = isset($_shipping_address['city_code'])?$_shipping_address['city_code']:'';
                    $_params['BillingAddress']['Country'] = isset($_shipping_address['country'])?$_shipping_address['country']:'';
                    $_params['BillingAddress']['CountryCode'] = trim(isset($_shipping_address['country_code'])?$_shipping_address['country_code']:'');
                    $_params['BillingAddress']['Email'] = trim(isset($_shipping_address['email'])?$_shipping_address['email']:'');
                    $_params['BillingAddress']['FirstName'] = isset($_shipping_address['first_name'])?$_shipping_address['first_name']:'';
                    $_params['BillingAddress']['LastName'] = isset($_shipping_address['last_name'])?$_shipping_address['last_name']:'';
                    $_params['BillingAddress']['Mobile'] = isset($_shipping_address['mobile'])?$_shipping_address['mobile']:'';
                    $_params['BillingAddress']['Phone'] = isset($_shipping_address['phone_number'])?$_shipping_address['phone_number']:'';
                    $_params['BillingAddress']['PostalCode'] = isset($_shipping_address['postal_code'])?$_shipping_address['postal_code']:'';
                    $_params['BillingAddress']['State'] = isset($_shipping_address['state'])?$_shipping_address['state']:'';
                    $_params['BillingAddress']['Street1'] = isset($_shipping_address['street1'])?$_shipping_address['street1']:'';
                    $_params['BillingAddress']['Street2'] = isset($_shipping_address['street2'])?$_shipping_address['street2']:'';
                }
            }
            /***************** end *******************/

            ######对正常支付与repay支付的区分处理__END############################
            /*当有SC支付的时候，先调用SC支付接口，如果应该金额大于SC的金额，还需要调用相应的支付方式*/
            ###SC支付START#################################################
            //06-22定：当SC不够支付的时候，不给支付
            if(strtolower($_params['pay_type']) == 'sc'){
                $res = array();
                //使用新支付系统进行支付
                //判断SC是否足够支付订单所需支付的总金额，如果是，则跳转到支付成功处理函数
                $_params['transaction_channel'] = TRANSACTION_CHANNEL_SC;
                $_params['transaction_type'] = TRANSACTION_TYPE_SC;
                $_params['currency_type'] = PAYMENT_CURRENCY_TYPE_CASH;

                $res = $this->PayMent->payCommon($_params);

                $return = $res['result'];
                //如果是 Pending也需要创建OMS订单(已和payment确认) BY tinghu.liu IN 20190215
                if (isset($return['data']['status']) && $return['data']['status'] != PAYMENT_RESULT_STATUS_FAILURE) {
                    $_params['OrderTotal'] = isset($res['OrderTotal']) ? sprintf("%.2f",$res['OrderTotal']):0;
                    //不跳状态，按正常单处理 edit by Carl 2018-08-15 11:14
                    $_params['OrderStatus'] = 120;//SC支付的价格直接
                    //进入风控的订单
                    $_params['TransactionID'] = isset($return['data']['transaction_id']) ? $return['data']['transaction_id'] : 0;
                }
                $res = $res['result'];

            }
            ####SC支付END################################################

            #######paypal支付非快捷支付START###############################
            if($_params['pay_type'] == 'PayPal' && $_params['sc_price'] == 0) {//paypal支付比较特殊
                if(isset($_create_order_res['data']['slave'])){
                    $this->CommonService->loadRedis()->set("App_OrderMasterNumber_".$_params['customer_id'],$_create_order_res['data']['master']['order_number']);//保存用户提交的订单编号信息
                    $this->CommonService->loadRedis()->set("App_OrderNumberArr_".$_params['customer_id'],json_encode($_create_order_res['data']['slave']));//保存用户提交的订单编号信息
                }else{
                    //生成订单失败操作
                    $ReturnData['code'] = 2;
                    $ReturnData['data'] = 'order create is error!';
                    $ReturnData['msg'] = 'order create is error!';
                    return json($ReturnData);
                }

                //使用新支付系统进行支付 TODO.......... create Order 且返回第三方ID号
                    $_params['transaction_channel'] = TRANSACTION_CHANNEL_PAYPAL;
                    $_params['transaction_type'] = TRANSACTION_TYPE_PAYPAL;
                    $_params['currency_type'] = PAYMENT_CURRENCY_TYPE_CASH;
                    $res = $this->PayMent->payCommon($_params);
                    $return = $res['result'];
                    if (isset($return['data']['status']) && $return['data']['status'] != 'failure'){
                        $ReturnData['code'] = 1;
                        $ReturnData['OrderID'] = isset($return['data']['invoice_id']) ? (string)$return['data']['invoice_id'] : '';
                        $ReturnData['TransactionID'] = isset($return['data']['transaction_id']) ? $return['data']['transaction_id'] : 0;
                        Log::record('PayPal$return' . json_encode($return));
                        $_url = isset($return['data']['url']) ? $return['data']['url'] : '';
                        return apiReturn([
                            'code' => self::API_SECCUSS_CODE,
                            'handle_flag' => self::ORDER_HANDLEFLAG_JUMP,
                            'url' => $_url,
                            'data' => $ReturnData,
                            'msg' => 'Success.'
                        ]);
                    }else{
                        $Reason = (isset($return['data']['error_info']) && !empty($return['data']['error_info']))?$return['data']['error_info']:lang('payment_try_again');
                        //通知前端跳转支付失败页面
                        $ReturnData['code'] = 1;
                        $ReturnData['msg'] = $Reason;//'payment system is error!';
                        $ReturnData['url'] = url('/paymentError?order_number='.$order_master_number.'&reason='.$Reason, false, false);
                        Log::record('PayPal$error' . json_encode($return));
                        //logService::write(LOGS_MALL_CART,'error',__METHOD__,'submitOrder'.$order_master_number,$_params,MALL_API.'/home/order/payOrder/paypalNoQuick_res',$res);
                        return apiReturn([
                            'code' => 2016,
                            'handle_flag' => self::ORDER_HANDLEFLAG_JUMP,
                            'url' => MALL_DOCUMENT . '/mpaymentError?order_number=' . $order_master_number . '&reason=' . urlencode('payment system is error!'),
                            'data' => $ReturnData,
                            'msg' => 'payment system is error!'
                        ]);
                    }


            }

            #####paypal支付非快捷支付END############################################################################
            $_return_data['order_number'] = $_params['order_number'];
            #################################################################################
            //die();
            if($_params['pay_type'] == 'CreditCard' && $_params['pay_chennel'] == 'EGP' && !$_params['credit_card_token_id'] && $_params['sc_price'] == 0){
                $_params['transaction_channel'] = TRANSACTION_CHANNEL_EGP;
                $_params['transaction_type'] = TRANSACTION_TYPE_CREDITCARD;
                $_params['currency_type'] = PAYMENT_CURRENCY_TYPE_CASH;
                Log::record('EGP$_params'.json_encode($_params));
                $res = $this->PayMent->payCommon($_params);//信用卡支付(非Token)
                Log::record('EGP$res'.json_encode($res));
                $return = $res['result'];
                //如果是 Pending也需要创建OMS订单(已和payment确认) BY tinghu.liu IN 20190215
                if(isset($return['data']['status']) && $return['data']['status'] != PAYMENT_RESULT_STATUS_FAILURE){
                    $_params['OrderTotal'] = isset($res['OrderTotal']) ? (string)$res['OrderTotal'] : 0;
                    if(isset($return['data']['risky_status']) && $return['data']['risky_status'] == 1){
                        $_params['risky'] = 1;
                    }//进入风控的订单
                    $_params['TransactionID'] = isset($return['data']['transaction_id']) ? $return['data']['transaction_id'] : 0;
                }
                $res = $res['result'];
            }

            if($_params['pay_type'] == 'CreditCard' && $_params['pay_chennel'] == 'EGP' && $_params['credit_card_token_id'] && $_params['sc_price'] == 0){
                $_params['transaction_channel'] = TRANSACTION_CHANNEL_EGP;
                $_params['transaction_type'] = TRANSACTION_TYPE_CREDITCARD_TOKEN;
                $_params['currency_type'] = PAYMENT_CURRENCY_TYPE_CASH;
                Log::record('EGP$_params1'.json_encode($_params));
                $res = $this->PayMent->payCommon($_params);//信用卡支付EGP(Token)
                Log::record('EGP$res1'.json_encode($res));
                $return = $res['result'];
                //如果是 Pending也需要创建OMS订单(已和payment确认) BY tinghu.liu IN 20190215
                if(isset($return['data']['status']) && $return['data']['status'] != PAYMENT_RESULT_STATUS_FAILURE){
                    $_params['OrderTotal'] = isset($res['OrderTotal'])?sprintf("%.2f",$res['OrderTotal']):0;
                    if(isset($return['data']['risky_status']) && $return['data']['risky_status'] == 1){
                        $_params['risky'] = 1;
                    }//进入风控的订单
                    $_params['TransactionID'] = isset($return['data']['transaction_id']) ? $return['data']['transaction_id'] : 0;
                }
                $res = $res['result'];
            }

            if($_params['pay_type'] == 'CreditCard' && $_params['pay_chennel'] == 'Asiabill' && !$_params['credit_card_token_id'] && $_params['sc_price'] == 0){
                $res = array();
                $_params['transaction_channel'] = TRANSACTION_CHANNEL_ASIABILL;
                $_params['transaction_type'] = TRANSACTION_TYPE_CREDITCARD;
                $_params['currency_type'] = PAYMENT_CURRENCY_TYPE_CASH;
                Log::record('Asiabill$_params'.json_encode($_params));
                $res = $this->PayMent->payCommon($_params);//信用卡支付聚宝Asiabill(非Token)
                Log::record('Asiabill$res'.json_encode($res));
                $return = $res['result'];
                //如果是 Pending也需要创建OMS订单(已和payment确认) BY tinghu.liu IN 20190215
                if(isset($return['data']['status']) && $return['data']['status'] != PAYMENT_RESULT_STATUS_FAILURE){
                    $_params['OrderTotal'] = isset($res['OrderTotal'])?sprintf("%.2f",$res['OrderTotal']):0;
                    if(isset($return['data']['risky_status']) && $return['data']['risky_status'] == 1){
                        $_params['risky'] = 1;
                    }//进入风控的订单
                    $_params['TransactionID'] = isset($return['data']['transaction_id']) ? $return['data']['transaction_id'] : 0;
                }
                $res = $res['result'];
            }

            if($_params['pay_type'] == 'CreditCard' && $_params['pay_chennel'] == 'Asiabill' && $_params['credit_card_token_id'] && $_params['sc_price'] == 0){
                $res = array();
                $_params['transaction_channel'] = TRANSACTION_CHANNEL_ASIABILL;
                $_params['transaction_type'] = TRANSACTION_TYPE_CREDITCARD_TOKEN;
                $_params['currency_type'] = PAYMENT_CURRENCY_TYPE_CASH;
                Log::record('Asiabill$_params'.json_encode($_params));
                $res = $this->PayMent->payCommon($_params);//信用卡支付聚宝Asiabill(Token)
                Log::record('Asiabill$res'.json_encode($res));
                $return = $res['result'];
                //如果是 Pending也需要创建OMS订单(已和payment确认) BY tinghu.liu IN 20190215
                if(isset($return['data']['status']) && $return['data']['status'] != PAYMENT_RESULT_STATUS_FAILURE){
                    $_params['OrderTotal'] = isset($res['OrderTotal'])?sprintf("%.2f",$res['OrderTotal']):0;
                    if(isset($return['data']['risky_status']) && $return['data']['risky_status'] == 1){
                        $_params['risky'] = 1;
                    }//进入风控的订单
                    $_params['TransactionID'] = isset($return['data']['transaction_id']) ? $return['data']['transaction_id'] : 0;
                }
                $res = $res['result'];
            }
            if($_params['pay_chennel'] == 'Astropay' && $_params['sc_price'] == 0){
                //使用新支付系统进行支付
                $_params['payment_method'] = $this->CheckoutService->astropayPaymentMethodTransV2($_params['pay_type']);
                $_params['transaction_channel'] = TRANSACTION_CHANNEL_ASTROPAY;
                $_params['transaction_type'] = $_params['payment_method'];
                $_params['currency_type'] = PAYMENT_CURRENCY_TYPE_CASH;
                Log::record('Astropay$_params'.json_encode($_params));
                $res = $this->PayMent->payCommon($_params);//Astropay支付
                Log::record('Astropay$res'.json_encode($res));
                $_params['TransactionID'] = isset($res['TransactionID'])?$res['TransactionID']:0;
                $this->packagingAstropayReturnParams($_params);
                $return = $res['result'];
                if(isset($return['data']['status']) && $return['data']['status'] != PAYMENT_RESULT_STATUS_FAILURE){
                    //信用卡支付的不用跳转至第三方页面，直接成功或失败页面 tinghu.liu 20191018
                    if ($_params['payment_method'] != TRANSACTION_TYPE_ASTROPAY_CREDIT_CARD){
                        if($_params['pay_type'] == 'Boleto-Astropay'){
                            $_params['boleto_url'] = $return['data']['url'];
                        }
                        $_params['redirect'] = 0;
                        $this->paymentSuccessProcessHeader($_params);
                        $ReturnData['code'] = 1;
                        $ReturnData['data'] = 'success';
                        $url=!empty($return['data']['url'])?$return['data']['url']:'';
                        $ReturnData['url'] = $url;
                        return apiReturn([
                            'code'=>self::API_SECCUSS_CODE,
                            'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                            'url'=>$url,
                            'data'=>$ReturnData,
                            'msg'=>'Success.'
                        ]);
                    }
                    //是信用卡，且有返回url则跳转，因为印度需要做3d验证 tinghu.liu 20191030
                    if ($_params['payment_method'] == TRANSACTION_TYPE_ASTROPAY_CREDIT_CARD && isset($return['data']['url']) && !empty($return['data']['url'])){
                        $_params['redirect'] = 0;
                        $this->paymentSuccessProcessHeader($_params);
                        $url=!empty($return['data']['url'])?$return['data']['url']:'';
                        $ReturnData['code'] = 1;
                        $ReturnData['data'] = 'success';
                        $ReturnData['url'] = $url;
                        return apiReturn([
                            'code'=>self::API_SECCUSS_CODE,
                            'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                            'url'=>$url,
                            'data'=>$ReturnData,
                            'msg'=>'Success.'
                        ]);
                    }
                }
                $res = $res['result'];
            }

            if($_params['pay_type'] == 'IDeal'){
                //使用新支付系统进行支付
                $_params['user_name'] = $_customer_name;
                $_params['transaction_channel'] = TRANSACTION_CHANNEL_IDEAL;
                $_params['transaction_type'] = TRANSACTION_TYPE_IDEAL;
                $_params['currency_type'] = PAYMENT_CURRENCY_TYPE_CASH;
                $res = $this->PayMent->payCommon($_params);
                //只要是获取token成功的都需要创建OMS订单 20190221 tinghu.liu
                if ($res['code'] == 4){
                    $url = !empty($res['url'])?$res['url']:'';
                    $_params['TransactionID'] = isset($res['TransactionID'])?$res['TransactionID']:0;
                    $this->paymentSuccessProcessHeader($_params);
                    $res['code'] = 1;
                    return apiReturn([
                        'code'=>self::API_SECCUSS_CODE,
                        'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                        'url'=>$url,
                        'msg'=>'Success',
                        'data'=>$res
                    ]);
                }else{
                    $Reason = (isset($res['data']['error_info']) && !empty($res['data']['error_info']))?$res['data']['error_info']:'Payment failure please retry.';
                    return apiReturn([
                        'code'=>1019,
                        'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                        'url'=>MALL_DOCUMENT.'/mpaymentError?order_number='.$order_master_number.'&reason='.urlencode($Reason),
                        'data'=>'',
                        'msg'=>$Reason
                    ]);
                }
            }

            //使用的是新支付系统
            //如果是 Pending也需要创建OMS订单(已和payment确认) BY tinghu.liu IN 20190215
            if(isset($res['data']['status']) && $res['data']['status'] != PAYMENT_RESULT_STATUS_FAILURE){
                //成功处理机制
                $_params['redirect'] = 0;//不能直接跳转，需返回给ajax进行跳转
                $_params['payment_status'] = 'Success';
                //logService::write(LOGS_MALL_CART,'info',__METHOD__,__FUNCTION__,[2],MALL_API.'/home/order/submitOrder144','34444444');
                $_params['TransactionID'] = isset($res['data']['transaction_id'])?$res['data']['transaction_id']:'';
                $_result = $this->paymentSuccesProcess($_params);
                logService::write(LOGS_MALL_CART,'info',__METHOD__,'submitOrder'.$order_master_number,$_log_params,MALL_API.'/home/order/submitOrder/paymentSuccesProcess_res',$_result, $_customer_id,$order_master_number);
                return apiReturn([
                    'code'=>self::API_SECCUSS_CODE,
                    'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                    'msg'=>'Success',
                    'url'=>MALL_DOCUMENT.'/mpaymentSuccess?order_number='.$order_master_number.'&order_total='.$_result['order_total'].'&payment_status='.$_result['payment_status'].'&currency_code='.$_result['currency_code'],
                    'data'=>$_result
                ]);
            } else {
                //错误处理机制
                $Reason = (isset($res['data']['error_info']) && !empty($res['data']['error_info']))?$res['data']['error_info']:'Payment failure please retry.';
                $ReturnData['code'] = 1;
                $ReturnData['reason'] = /*$res->Error->Code.' '.*/$Reason;
                $ReturnData['order_number'] = $_params['order_number'];
                //$ReturnData['url'] = '/home/order/paymentError';
                logService::write(LOGS_MALL_CART,'error',__METHOD__,'submitOrder'.$order_master_number,$_log_params,MALL_API.'/home/order/submitOrder11',$ReturnData, $_customer_id, $order_master_number);
                if (
                    isset($res['code'])
                    && $res['code'] == self::PAYMENT_EXCEPTION_CODE_NEW
                ) {
                    //增加payment系统异常，邮件提醒机制 tinghu.liu 20190408
                    $this->CommonService->sendEmailForOrderBug([
                        'title'=>SUBMIT_ORDER_BUG_TITLE.'(master_number:'.$order_master_number.')',
                        'content'=>'Error info :'.json_encode($ReturnData)
                    ]);
                }
                return apiReturn([
                    'code'=>1029,
                    'handle_flag'=>self::ORDER_HANDLEFLAG_JUMP,
                    'url'=>MALL_DOCUMENT.'/mpaymentError?order_number='.$order_master_number.'&reason='.urlencode($Reason),
                    'data'=>'',
                    'msg'=>$Reason
                ]);
            }
        }catch (Exception $e){
            $msg = '支付异常, '.$e->getMessage().', '.$e->getFile().'['.$e->getLine().']';
            $_return_data['code'] = 2; //前端在页面提示，但是不JUMPPURL
            $_return_data['msg'] = $e->getMessage(); //;
            $_return_data['debug_code'] = 2000; //'token error';
            if (in_array(THINK_ENV, [CODE_RUNTIME_LOCAL, CODE_RUNTIME_TEST])){
                $_return_data['true_msg'] = $msg; //'token error';
            }
            //logService::write(LOGS_MALL_CART,'error',__METHOD__,'submitOrder10000',null,null,$_return_data);
            Log::record($msg, Log::ERROR);
            return json($_return_data);
        }
    }

    /**
     * 组装支付数据
     */
    private function submitOrderCommonForPayOrder($_customer_id, $_rate,$_currency_code,$_language_code,$_country_code,$_order_from,$_is_buynow){
//        $_rate = 1;
//        $_customer_id = $this->CstomerInfo['data']['ID'];

        //20181214 获取邮箱重新调用接口获取，统一在下面获取
        /*if(isset($this->CstomerInfo['data']['email']) && !empty($this->CstomerInfo['data']['email'])){
            $_params['email'] = $this->CstomerInfo['data']['email'];
        }else{
            logService::write(LOGS_MALL_CART,'info',__METHOD__,__FUNCTION__,null,null,'email is null');
            $_params['email'] = 'admin@comepro.com'; //以防万一 TODO
        }*/

        /**
         * this.$cookie.set('goToPcPaymentType','mobile', null,null,$Config.cookie_domain) //排查问题,设置cookie值，区分是移动站过去的还是pc端过去的。 tinghu.liu 20190828
         */
        //'订单来源：10-PC，20-Android，30-iOS，40-Pad，50-Mobile'
        $_params['order_from'] = $_order_from;

        //SC支付
        $_params['sc_password'] = input("sc_password")?input("sc_password"):'';
        $_params['sc_price'] = input("sc_price")?input("sc_price"):0;
        $_params['is_paypal_quick'] = input('is_paypal_quick')?input('is_paypal_quick'):0;
        $_params['is_buynow'] = $_is_buynow?$_is_buynow:0;
        $_params['customer_id'] = $_customer_id;
        $_params['order_message'] = isset(input()['Message'])?input()['Message']:'';
        $_params['currency'] = $_currency_code;
        $_params['lang'] = $_language_code;
        $_params['pay_type'] = input("PayType")?input("PayType"):'';//支付方式,支付方式的不同，调用不同的接口,并有不同的处理流程
        $_params['bic'] = input("bic")?input("bic"):'';//新的payment Ideal支付专有
        /**需要判断当前币种是否是我们的实收币种，如果不是，则强制转成美元来收取*/
        $_params['country'] = $_country_code;
        if(strtolower($_params['pay_type']) != 'paypal'){
            /**如果支付方式是非paypal的，要获取我们dx支付的币种进行比对，
             * 不在其中的，全部切换成USD
             */
            if(!in_array($_params['currency'],config('dx_support_currency'))){
                $_params['currency'] = 'USD';
                $_rate = 1;
            }
        }else{
            /**如果是paypal的支付方式的，要获取我们和paypal签订的币种进行比对，
             * 不在其中的全部切成USD
             */
            if(
                in_array($_params['currency'],config('paypal_not_support_currency'))
                || !in_array($_params['currency'],config('paypal_support_currency'))
            ){
                $_params['currency'] = 'USD';
                $_rate = 1;
            }
        }

        $_params['card_type'] = input("cardType")?input("cardType"):'';
        $_params['pay_chennel'] = input("PayChennel");
        if($_params['pay_chennel'] == 'Asiabill' && $_params['card_type'] && !in_array($_params['card_type'],config('asiabill_creditcard'))){
            $_params['pay_chennel'] = 'EGP';
        }

        //是否是affiliate订单，条件（缺一不可）：1、$this->affiliate不为空；2、产品SPU佣金比例大于0
        //这些判断逻辑均在Task项目生成订单佣金时处理了
        $_affiliate='';
        $_params['affiliate'] = $_affiliate;
        $_params['customer_address_id'] = input("CustomerAddressId");
        //拿到汇率
//        if($_params['currency'] != DEFAULT_CURRENCY){
//            $_rate = $this->CommonService->getOneRate( DEFAULT_CURRENCY,$_params['currency']);
//        }
        $_params['rate'] = $_rate;
        //如果是信用卡支付的，接收信用卡信息(或者是选择了某张信用卡的，调取该信用卡信息)
        $_params['BillingAddress']['City'] = input('City');
        $_params['BillingAddress']['CityCode'] = input('CityCode');
        $_params['BillingAddress']['Country'] = input('Country');
        $_params['BillingAddress']['CountryCode'] = trim(input('CountryCode'));
        $_params['BillingAddress']['Email'] = trim(input('Email'));
        $_params['BillingAddress']['FirstName'] = input('FirstName');
        $_params['BillingAddress']['LastName'] = input('LastName');
        $_params['BillingAddress']['Mobile'] = input('Mobile');
        $_params['BillingAddress']['Phone'] = input('Phone');
        $_params['BillingAddress']['PostalCode'] = input('PostalCode');
        $_params['BillingAddress']['State'] = input('State');
        $_params['BillingAddress']['Street1'] = input('Street1');
        $_params['BillingAddress']['Street2'] = input('Street2');
        $_params['CardInfo']['CVVCode'] = input('CVVCode');
        $_params['CardInfo']['CardHolder'] = input('FirstName').' '.input('LastName');
        $_params['CardInfo']['CardNumber'] = preg_replace('# #','',input('CardNumber'));
        $_params['CardInfo']['ExpireMonth'] = input('ExpireMonth');
        $_params['CardInfo']['ExpireYear'] = 2000+input('ExpireYear');
        //$_params['CardInfo']['IssuingBank'] = 'Visa';//input('IssuingBank')
        //Pagsmile专用 20190309 tinghu.liu
        $_params['CardInfo']['psPaymentMethodId'] = input('psPaymentMethodId');//Pagsmile 卡类型
        //Astropay信用卡分期数据 tinghu.liu 20191023
        $_params['CardInfo']['Installments'] = input('installments','');//Astropay信用卡分期数据：分期数
        $_params['CardInfo']['InstallmentsId'] = input('installments_id', '');//Astropay信用卡分期数据：分期ID

        $_params['psToken'] = input('psToken');//Pagsmile 支付Token

        $_params['payment_method'] = input("payment_method");//Astropay的支付方式()
        $_params['cpf'] = input("CPF",'');//Astropay支付的CPF
        $_params['card_bank'] = input("CardBank");//Astropay
        $_params['credit_card_token_id'] = input("CreditCardTokenId");//信用卡支付的tokenID
        $_params['querystring'] = input("querystring")?htmlspecialchars_decode(input("querystring")):'';//payment返回，order返回到前端，paypal支付get,do阶段使用
        //获取用户的地址信息
        $_params['order_master_number'] = input("order_number")?input("order_number"):0;
        $_params['NocNoc'] = input('NocNoc')?input('NocNoc'):0;

        $_params['CVVCode'] = input('CVVCode')?input('CVVCode'):'956';
        //测试repay
        //$_params['order_master_number'] = '180610019742134905';
        //如果是快捷支付的，没有使用客户的地址信息，而是使用了从paypal带过来的地址
        $_paypal_address = isset(input()['address'])?input()['address']:null;

        if(is_array($_paypal_address)){
            $_params['ShippingAddress']['City'] = isset($_paypal_address['city'])?$_paypal_address['city']:'';
            $_params['ShippingAddress']['CityCode'] = isset($_paypal_address['cityCode'])?$_paypal_address['cityCode']:'';
            $_params['ShippingAddress']['Country'] = isset($_paypal_address['country'])?$_paypal_address['country']:'';
            $_params['ShippingAddress']['CountryCode'] = isset($_paypal_address['countryCode'])?trim($_paypal_address['countryCode']):'';
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
        if(input('saveCard') == 'true'){
            $_params['save_card'] = 1;
        }else{
            $_params['save_card'] = 0;
        }

        /**
         * 信用卡支付账单地址是否使用和收货地址一致的信息 tinghu.liu 20190723
         * 包含信息：地址1，地址2，国家，省，城市，邮编，电话号码，邮箱
         */
        if(input('UseShippingAddress') == 'true'){
            $_params['use_shipping_address'] = 1;
        }else{
            $_params['use_shipping_address'] = 0;
        }

        //$_params['save_card'] = (input('saveCard') == true)?1:0;//是否保存信用卡信息
        /** 20190108 关税保险 start 按照多个店铺来处理 **/
        /*TariffInsurance[0][StoreId]: 18
        TariffInsurance[0][IsChecked]: 1*/
        $is_tariff_insurance = 0;//是否买了关税保险,0表示没有买，1表示买了(有一个店铺选择了就算购买)
        $tariff_insurance = isset(input()['TariffInsurance'])?input()['TariffInsurance']:[];
        $_params['tariff_insurance'] = $tariff_insurance;
        if (!empty($tariff_insurance) && is_array($tariff_insurance)){
            foreach ($tariff_insurance as $k=>$v){
                if (isset($v['IsChecked']) && $v['IsChecked'] == 1){
                    $is_tariff_insurance = 1;
                    break;
                }
            }
        }
        $_params['is_tariff_insurance'] = $is_tariff_insurance;
        /** 20190108 关税保险 end 按照多个店铺来处理 **/
        /*//判断购物车里的信息有没有NOCNOC的
        $_check_nocnoc = $this->Noc->checkNocNoc($_params, 2);
        if($_check_nocnoc){
            $_params['NocNoc'] = 1;
        }else{
            $_params['NocNoc'] = 0;
        }*/
        //判断购物车里的信息有没有NOCNOC的（不是repay的情况）
//        if (!$_params['order_master_number']){
//            $_check_nocnoc = $this->Noc->checkNocNoc($_params, 2);
//            if($_check_nocnoc){
//                $_params['NocNoc'] = 1;
//            }else{
//                $_params['NocNoc'] = 0;
//            }
//        }
        //记录支付方式等信息,在获取支付方式的时候要带出来给前端,什么情况下需要记录支付信息???
        $this->CommonService->recordParams($_params);
        //Cookie::set("payParams",json_encode($_params));
        $this->CommonService->loadRedis()->set("payParams_payOrder_".$_customer_id, json_encode($_params));
    }

    /**
     * 订单列表再支付展示功能
     */
    public function repetitionPay()
    {
        $_order_master_number = input('OrderNumber') ? input('OrderNumber') : 0;
        //获取主订单 临时方法,下周注销
        $SalesOrder=new SalesOrder();
        $where['order_number']=$_order_master_number;
        $order=$SalesOrder->get($where);

        if(!empty($order['order_master_number'])&&($order['order_master_number']!=$_order_master_number)){
            $_order_master_number=$order['order_master_number'];
        }
        //repay统一使用新支付结算页面进行支付 tinghu.liu 20190912
        $pay_token_info = $this->CommonService->getPayTokenInfoByOrderMasterNumber($_order_master_number);
        $pay_token = isset($pay_token_info['pay_token']) ? $pay_token_info['pay_token'] : '';
        $this->result($pay_token);
    }

}
