<?php
namespace app\app\controller;

use app\app\services\CartService;
use app\app\services\CheckoutService;
use app\app\services\CommonService;
use app\app\services\OrderService;
use app\app\services\ProductService;
use app\app\services\rateService;
use app\common\controller\AppBase;
use app\common\helpers\RedisClusterBase;
use app\common\params\app\CheckoutParams;
use app\common\services\logService;
use think\Cache;
use think\Cookie;
use think\Log;
use think\Monlog;

/**
 * 开发：tinghu.liu
 * 功能：Checkout
 * 时间：2018-10-31
 */
class Checkout extends AppBase
{
    public $CheckoutService;
    public $CommonService;
    public $redis;
    public $rateService;
    public $productService;
    public $CartService;
    public $OrderService;
    public function __construct()
    {
        parent::__construct();
        $this->CheckoutService = new CheckoutService();
        $this->CommonService = new CommonService();
        $this->redis = new RedisClusterBase();
        $this->rateService = new rateService();
        $this->productService = new ProductService();
        $this->CartService = new CartService();
        $this->OrderService = new OrderService();
    }

    /**
     * 获取checkout产品数据【必须登录】
     * @return mixed
     */
    public function get(){
        $params = request()->post();
        //用户行为分析
        Monlog::write(LOGS_MALL_CART,'info',__METHOD__,$params['CustomerId'],$params,MALL_API.'/checkout/get');

        $validate = $this->validate($params,(new CheckoutParams())->getRules());
        if (true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $Uid = $params['CustomerId'];
            $GuestId = $params['GuestId'];
            $UserName = $params['CustomerName'];
            $Country = $params['Country'];
            $Currency = $params['Currency'];
            $Lang = $params['Lang'];

            $BuyNow = isset($params['IsBuyNow'])?$params['IsBuyNow']:0;//用于判断是否是buynow
            $PayType = !empty($params['PayType'])?$params['PayType']:'paypal';//20190726因为APP初始化没有传,所以默认paypal,避免运费bug
            $IsPaypalQuick = isset($params['IsPaypalQuick'])?$params['IsPaypalQuick']:0;
            /*$cookieCountryCode = $cookieCountryCodeCart = Cookie::get('DXGlobalization_shiptocountry');
            $prevCountry = Cookie::get('prevCountry');
            if($prevCountry && $cookieCountryCodeCart && $cookieCountryCodeCart !=$prevCountry ){
                $cookieCountryCode = $prevCountry;
            }
            $Country = input("country");
            //if($cookieCountryCode !='' && $Country !='' && $cookieCountryCode != $Country){
              //  Cookie::set('DXGlobalization_shiptocountry',$Country,['domain'=>MALL_DOMAIN]);
            //}
            $Country = $Country ? $Country : $cookieCountryCode;
            if(!$Country){
                $Country = $this->country;
            }*/
            /**需要判断当前币种是否是我们的实收币种，如果不是，则强制转成美元来收取*/
            if(strtolower($PayType) != 'paypal' || $IsPaypalQuick ){
                /**如果支付方式是非paypal的(包括没有传支付方式过来的，比如刚加载的时候)，要获取我们dx支付的币种进行比对，
                 * 不在其中的，全部切换成USD
                 */
                if($IsPaypalQuick){
                    /**如果是paypal快捷支付的，验证是否在paypal不支持的数据里，也就是ARS与BRL*/
                    if(
                        in_array($Currency,config('paypal_not_support_currency'))
                        || !in_array($Currency,config('paypal_support_currency'))
                    ){
                        $Currency = 'USD';
                    }
                }else{
                    if(!in_array($Currency,config('dx_support_currency'))){
                        $Currency = 'USD';
                    }
                }
            }else{
                /**如果是paypal的支付方式的，要获取我们和paypal签订的币种进行比对，
                 * 不在其中的全部切成USD
                 */
                if(!in_array($Currency,config('paypal_support_currency'))){
                    $Currency = 'USD';
                }
            }
            //用户行为分析
            Monlog::write(LOGS_MALL_CART,'info',__METHOD__,$params['CustomerId'],$Currency,MALL_API.'/checkout/get');
            if($BuyNow){
                $res = $this->CheckoutService->getCartInfoBuyNow($Uid,$Currency,$Country,$Lang,$GuestId,$UserName,$UserName);
            }else{
                $res = $this->CheckoutService->getCartInfo($Uid,$Currency,$Country,$Lang,$GuestId,$UserName);

            }

//        $isHaveNoc = 0;
//        if(!empty($res) && is_array($res)){
//            if(isset($res['IsHasNocNoc'])){
//                $isHaveNoc = $res['IsHasNocNoc'];
//            }
//        }
            /*3s、返回给前端*/
            //$noc_res = $this->isHasNocNoc();
            //TODO 是否有NOCNOC，APP自己判断 ????
//        $tax_id = Cookie::get('nocnoc_tax_id');
//        if(!empty($tax_id) && strlen($tax_id)>0){
//            $tax_id =1;
//        }else{
//            $tax_id =0;
//        }
//        $Data['has_nocnoc'] = $tax_id;
//        $Data['tax_id'] = Cookie::get('nocnoc_tax_id');
//        $Data['IsHasNocNoc'] = $isHaveNoc;
            $Data['DXGlobalization_shiptocountry'] = $Country;
            $cart_data = isset($res[$Uid])?$this->CommonService->handlerCartOrCheckoutProductDataFowAPP($res[$Uid],2):[];
            $Data['data'] = $cart_data;
            //把汇率写进去
            $_rate = 1;
            if($Currency != DEFAULT_CURRENCY){
                $_rate = $this->CommonService->getOneRate( $Currency,DEFAULT_CURRENCY);
            }
            $Data['rate'] = $_rate;//前端计算积分的时候，用算出来的积分除以这个值
            $CurrencyCode = $this->CommonService->getCurrencyCode($Currency);
            $Data['currencyCode'] = isset($CurrencyCode['Code'])?$CurrencyCode['Code']:$CurrencyCode;
            return apiReturn(['code'=>200, 'data'=>$Data]);
        }catch (\Exception $e){
            return apiReturn(['code'=>1003, 'msg'=>'System anomaly, '.$e->getMessage()]);
        }
    }

    /**
     * 初始化checkou产品运费数据
     * @return mixed
     */
    public function getInitShippingData(){
        $params = request()->post();
        $validate = $this->validate($params,(new CheckoutParams())->getInitShippingDataRules());
        if (true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $return_data = [];
            $Uid = $params['CustomerId'];
            $GuestId = $params['GuestId'];
            if(!$Uid){
                $Uid = $GuestId;
            }
            $BuyNow = isset($params['IsBuyNow'])?$params['IsBuyNow']:0;//用于判断是否是buynow
            //语种
            $lang = $params['Lang'];
            //币种
            $currency = $params['Currency'];
            //收货国家选择
            $country = $params['Country'];
            $address_province = !empty($params["province"])?$params["province"]:'';
            /** 接收参数 处理 start **/
            $PayType = !empty($params['PayType'])?$params['PayType']:'paypal';//20190726因为APP初始化没有传,所以默认paypal,避免运费bug
            $IsPaypalQuick = isset($params['IsPaypalQuick'])?$params['IsPaypalQuick']:0;

            //来源标识：1-来至checkout改变支付方式，如果币种或地址发生变化，只需要更新价格，不需要更新运输方式，运输方式需要保持用户之前选中的
            $from_flag = isset($params['FromFlag'])?$params['FromFlag']:0;
            if(strtolower($PayType) != 'paypal' || $IsPaypalQuick ){
                /**如果支付方式是非paypal的(包括没有传支付方式过来的，比如刚加载的时候)，要获取我们dx支付的币种进行比对，
                 * 不在其中的，全部切换成USD
                 */
                if($IsPaypalQuick){
                    /**如果是paypal快捷支付的，验证是否在paypal不支持的数据里，也就是ARS与BRL*/
                    if(
                        in_array($currency,config('paypal_not_support_currency'))
                        || !in_array($currency,config('paypal_support_currency'))
                    ){
                        $currency = 'USD';
                    }
                }else{
                    if(!in_array($currency,config('dx_support_currency'))){
                        $currency = 'USD';
                    }
                }
            }else{
                /**如果是paypal的支付方式的，要获取我们和paypal签订的币种进行比对，
                 * 不在其中的全部切成USD
                 */
                if(!in_array($currency,config('paypal_support_currency'))){
                    $currency = 'USD';
                }
            }
            /** 接收参数 处理 end **/
            /*$this->loadCustomerInfo();
            $GuestId = $this->guestUniquenessIdentify;//游客的身份标识
            $Uid = $this->CstomerInfo['data']['ID'];
            if(!$Uid){
                $Uid = $GuestId;
            }*/

            if($BuyNow){
                $CartInfo = $this->redis->get(SHOPPINGCART_BUYNOW_.$Uid);
            }else{
                $CartInfo = $this->redis->get(SHOPPINGCART_CHECKOUT_.$Uid);
            }
            //是否更新购物车信息，默认为false。【20181211 修改为true，为了解决初始化运费产品数量变化但运费没计算问题】
            $isUpdateCart = true;
            //获取汇率参数
            $_rate = 1;
            if($currency != 'USD'){
                $startTime = microtime(true);
                $_rate = $this->rateService->getCurrentRate($currency);
                $endTime = microtime(true);
                $slowTime = config('slow_api_time')?config('slow_api_time'):100;//慢API时间(单位时间为毫秒)
                $useTime = ($endTime-$startTime)*1000;//毫秒
                //dump('$useTime');
                if($useTime > $slowTime){
                    //dump('$slowTime--------------');
                    //记录日志(待定),格式：主调方($from)，被调方($url)，花费时间($useTime)
                    //$log = '=FunctionName:Cart-getShippingInitData-_rate=UseTime:'.$useTime;
                    //\think\Log::pathlog('APIRequest',$log,'FunctionRequest.log');
                }
            }
            //组装获取运输方式请求参数 start
            $tempData = [];
            $tempData2 = [];
            $getShippingParams = [];
            $getShippingParams['lang'] = $lang;
            $getShippingParams['currency'] = $currency;
            $getShippingParams['spus'] = [];
            if (isset($CartInfo[$Uid]['StoreData'])) {
                foreach ($CartInfo[$Uid]['StoreData'] as $k => $v) {
                    foreach ($v['ProductInfo'] as $k1 => $v1) {
                        foreach ($v1 as $k2 => $v2) {
                            if (!isset($v2['Qty'])
                                || !isset($v2['ShippingMoel'])
                                || !isset($v2['ShipTo'])
                                || !isset($v2['Currency'])
                                || !isset($v2['ShippModelStatusType'])
                                || !isset($v2['ShippingDays'])
                                || !isset($v2['ShippingFee'])
                                || !isset($v2['ShippingFeeType'])
                                || !isset($v2['ShippingMoel'])
                            ){
                                continue;
                            }
                            $sku_id = $k2;
                            $temp = [];
                            $tempParams = [];
                            $tempParams['country'] = $country;
                            $tempParams['province'] = $address_province;
                            $tempParams['spu'] = $k1;
                            $tempParams['skuid'] = $sku_id;
                            $tempParams['count'] = $v2['Qty'];
                            $getShippingParams['spus'][] = $tempParams;
                            if (
                                empty($v2['ShippingMoel'])
                                || strtolower($v2['ShipTo']) != strtolower($country)
                                || strtolower($v2['Currency']) != strtolower($currency)
                            ){
                                $isUpdateCart = true;
                            }else{
                                $ShippingFee = $v2['ShippingFee'];
                                $ShippingFeeType = $v2['ShippingFeeType'];
                                if(isset($v2['ShippingMoel']) && strtolower($v2['ShippingMoel']) == 'nocnoc'){
                                    //$ShippingFee = lang('nocnoc_tips');
                                    $ShippingFee = 'Unique door to door service with taxes and customs clearence included. Total 15-20 days';
                                    $ShippingFeeType = 3;
                                }
                                $temp['SkuID'] = $sku_id;
                                $temp['ShippModelStatusType'] = $v2['ShippModelStatusType'];
                                $temp['ShippingDays'] = $v2['ShippingDays'];
                                $temp['ShippingFee'] = (string)$ShippingFee;
                                $temp['ShippingFeeType'] = $ShippingFeeType;
                                $temp['ShippingMoel'] = $v2['ShippingMoel'];
                                $temp['OldShippingMoel'] = isset($v2['OldShippingMoel'])?$v2['OldShippingMoel']:'';
                                //APP不支持这种格式
                                //$tempData[$sku_id] = $temp;
                                $tempData[] = $temp;
                            }
                        }
                    }
                }
            }
            //组装获取运输方式请求参数 end
            if ($isUpdateCart){
                //获取运费信息
                $shippingData = $this->productService->getBatchShippingCost($getShippingParams, $_rate);
                /** 重新组装返回给前端 **/
                if (!empty($shippingData)){
                    foreach ($shippingData as $sk=>$sv){
                        $s_skuid = $sk;
                        //$shippingFirstData = $sv[0];
                        foreach ($CartInfo[$Uid]['StoreData'] as $k3 => $v3) {
                            foreach ($v3['ProductInfo'] as $k4 => $v4) {
                                foreach ($v4 as $k5 => $v5) {
                                    $sku_id = $k5;
                                    if ($s_skuid == $sku_id){
                                        $temp2 = [];
                                        //来源标识：1-来至checkout改变支付方式，如果币种或地址发生变化，只需要更新价格，不需要更新运输方式，运输方式需要保持用户之前选中的
                                        $i = 0;//默认取运输方式的第一条数据（即最便宜的一条）
                                        if ($from_flag == 1){
                                            foreach ($sv as $sk2=>$sv2){
                                                if(strtolower($sv2['ShippingService']) == strtolower($v5['ShippingMoel'])){
                                                    $i = $sk2;
                                                    break;
                                                }
                                            }
                                        }
                                        //运输方式类型：1-有运输方式，2-有运输方式但已经切换了，3-没有运输方式
                                        if (isset($sv[$i]) && !empty($sv[$i])){
                                            $ShippModelStatusType = 1;
                                            if($sv[$i]['ShippingService'] != $v5['ShippingMoel']){
                                                $ShippModelStatusType = 2;
                                            }
                                        }else{
                                            $ShippModelStatusType = 3;
                                        }
                                        $ShippingDays = isset($sv[$i]['EstimatedDeliveryTime'])?$sv[$i]['EstimatedDeliveryTime']:'';
                                        $ShippingFee = isset($sv[$i]['Cost'])?$sv[$i]['Cost']:0;
                                        //免邮状态： 0-免邮， 1-MVP 24小时到货提示， 2-不免邮
                                        $ShippingFeeType = isset($sv[$i]['ShippingFee'])?$sv[$i]['ShippingFee']:0;
                                        if(isset($sv[$i]['ShippingService']) && strtolower($sv[$i]['ShippingService']) == 'nocnoc'){
                                            $ShippingFee = lang('nocnoc_tips');
                                            $ShippingFeeType = 3;
                                        }
                                        $ShippingMoel = isset($sv[$i]['ShippingService'])?$sv[$i]['ShippingService']:'';
                                        $OldShippingMoel = isset($sv[$i]['OldShippingService'])?$sv[$i]['OldShippingService']:'';
                                        if ($ShippingFeeType == 0){
                                            $ShippingFee = FREE_SHIPPING;
                                        }elseif ($ShippingFeeType == 1){
                                            $ShippingFee = FREE_SHIPPING_IN_ONEDAY;
                                        }

                                        //默认获取第一个运输方式数据【因为返回来的第一个是最便宜的】
                                        $temp2['SkuID'] = $sku_id;
                                        $temp2['ShippModelStatusType'] = $ShippModelStatusType;
                                        $temp2['ShippingDays'] = $ShippingDays;
                                        $temp2['ShippingFee'] = (string)$ShippingFee;
                                        $temp2['ShippingFeeType'] = $ShippingFeeType;
                                        $temp2['ShippingMoel'] = $ShippingMoel;
                                        $temp2['OldShippingMoel'] = $OldShippingMoel;
                                        //更新购物车运输方式信息，为了将运输方式给checkout页面（在cart没有改变运输方式的情况下）
                                        $CartInfo[$Uid]['StoreData'][$k3]['ProductInfo'][$k4][$k5]['ShippingMoel'] = $ShippingMoel;
                                        $CartInfo[$Uid]['StoreData'][$k3]['ProductInfo'][$k4][$k5]['OldShippingMoel'] = $OldShippingMoel;
                                        $CartInfo[$Uid]['StoreData'][$k3]['ProductInfo'][$k4][$k5]['ShippingFee'] = $ShippingFee;
                                        $CartInfo[$Uid]['StoreData'][$k3]['ProductInfo'][$k4][$k5]['ShippingFeeType'] = $ShippingFeeType;
                                        $CartInfo[$Uid]['StoreData'][$k3]['ProductInfo'][$k4][$k5]['ShippingDays'] = $ShippingDays;
                                        $CartInfo[$Uid]['StoreData'][$k3]['ProductInfo'][$k4][$k5]['ShippModelStatusType'] = $ShippModelStatusType;
                                        //其他信息
                                        $CartInfo[$Uid]['StoreData'][$k3]['ProductInfo'][$k4][$k5]['ShipTo'] = $country;
                                        $CartInfo[$Uid]['StoreData'][$k3]['ProductInfo'][$k4][$k5]['Currency'] = $currency;

                                        //APP不支持这种格式
                                        //$tempData2[$sku_id] = $temp2;
                                        $tempData2[] = $temp2;
                                        break 3;
                                    }
                                }
                            }
                        }
                    }
                }
                $return_data['data'] = $tempData2;
                //更新购物车信息
                if($BuyNow){
                    $this->redis->set(SHOPPINGCART_BUYNOW_.$Uid, $CartInfo);
                }else{
                    $this->redis->set(SHOPPINGCART_CHECKOUT_.$Uid, $CartInfo);
                }
            }else{
                $return_data['data'] = $tempData;
            }
            //判断是否有NOC start
            $isHaveNoc = 0;
            if (isset($return_data['data']) && !empty($return_data['data'])){
                foreach ($return_data['data'] as $k=>$v){
                    if (strtolower($v['ShippingMoel']) == 'nocnoc'){
                        $isHaveNoc = 1;
                    }
                }
            }
            $return_data['IsHasNocNoc'] = $isHaveNoc;
            //判断是否有NOC end
            $return_data['code'] = 200;
            return json($return_data);
        }catch (\Exception $e){
            return apiReturn(['code'=>1003, 'msg'=>'System anomaly, '.$e->getMessage()]);
        }
    }

    /**
     * 获取支付方式
     * @return mixed
     */
    public function getPayType(){
        $params = request()->post();
        $validate = $this->validate($params,(new CheckoutParams())->getPayTypeRules());
        if (true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        $Currency = $params['Currency'];//用户使用的当前币种
        if(!in_array($Currency,config('dx_support_currency'))){
            $Currency = 'USD';
        }
        $Country = $params['Country'];//这里是用户所在国家

        //20190710 如果是来至repay，币种、国家要和生成订单时候的信息一致 tinghu.liu
        $order_master_number = input("OrderNumber")?input("OrderNumber"):'';
        if (!empty($order_master_number)){
            $order_res = $this->OrderService->getOrderInfoByOrderMasterNumber($order_master_number);
            if (isset($order_res['code']) && $order_res['code'] == 200){
                $Currency = isset($order_res['data']['currency_code'])?$order_res['data']['currency_code']:$Currency;
                $Country = isset($order_res['data']['country_code'])?$order_res['data']['country_code']:$Country;
            }
        }
        $version=!empty($params['version'])?$params['version']:0;
        //如果有缓存的，把缓存加进去
        $res = $this->CheckoutService->getPayType($Currency,$Country,$version);
        if($res){
            /*$Data['code'] = 1;
            $Data['data'] = $res;*/
            //将PayPal支付渠道移至第一位并选中 tinghu.liu 20190801
           foreach ($res as $k10=>$v10){
                if (strtoupper($v10['payname']) == 'PAYPAL'){
                    if ($k10 !== 0){
                        $temp_val = $res[0];
                        $res[0] = $v10;
                        $res[$k10] = $temp_val;
                    }
                    $res[0]['isSelect'] = 1;
                }else{
                    $res[$k10]['isSelect'] = 0;
                }
            }

            //20190710，如果是repay，当订单币种为ARS，BRL时，支付方式不要Paypal tinghu.liu
            if (!empty($order_master_number)){
                if(in_array(strtoupper($Currency), ['ARS','BRL'])){
                    foreach ($res as $k=>$v){
                        if (strtoupper($v['payname']) == 'PAYPAL'){
                            unset($res[$k]);
                        }
                    }
                    $res=array_values($res);
                }
            }

            if(!empty($params['CustomerID'])){
                $customer_id=$params['CustomerID'];
                $ScRes = $this->getCustomerSC($customer_id,$Currency);
                if(isset($ScRes['code']) && $ScRes['code'] == 1 && !empty($ScRes['data']['data']['UsableAmount'])) {
                    $ScData=    array (
                        'name' => 'sc',
                        'payname' => 'Remaining Store Credit',
                        'channel' =>
                            array (
                                'channelName' => 'sc',
                                'channelId' => 5,
                            ),
                        'Addtime' => 1531290639,
                        'add_person' => 'admin',
                        'isSelect' => 0,
                    );
                    $res[]=$ScData;
                }
            }

            return apiReturn(['code'=>200, 'data'=>$res]);
        }else{
            return apiReturn(['code'=>1003, 'msg'=>'Failed to get payment method']);
        }
    }

    /**
     * 获取支付列表信息（三步走时通）
     * @param string $paramsCountry
     * @param int $flag 标识：0-默认，1-来至内部，不是post请求
     * @return \think\response\Json
     */
    public function getPayTypes($paramsCountry='', $flag=0){
        $log_key = 'getPayTypes';
        $_pay_token = input("pay_token")?input("pay_token"):'';
        if(empty($_pay_token)){
            $Data['code'] = 101;
            $Data['msg'] = 'Illegal access.';
            return json($Data);
        }
        $_pay_token_info = $this->CommonService->getOrderBaseInfoByPayToken($_pay_token);
        if (empty($_pay_token_info)){
            $Data['code'] = 102;
            $Data['msg'] = 'Illegal access.';
            $Data['pay_token'] = $_pay_token;
            logService::write(LOGS_MALL_CART,'debug',__METHOD__,$log_key,null,null,$Data);
            return json($Data);
        }
        $Currency = isset($_pay_token_info['order_data']['slave'][0]['currency_code'])?$_pay_token_info['order_data']['slave'][0]['currency_code']:'';
        $Country = isset($_pay_token_info['order_data']['address']['country_code'])?$_pay_token_info['order_data']['address']['country_code']:'';
        //支付方式，用于默认选中
        $PayType = isset($_pay_token_info['order_data']['slave'][0]['pay_type'])?$_pay_token_info['order_data']['slave'][0]['pay_type']:'';
        if(empty($Currency) || empty($Country)){
            $Data['code'] = 102;
            $Data['msg'] = 'Invalid data.';
            $Data['pay_token'] = $_pay_token;
            logService::write(LOGS_MALL_CART,'debug',__METHOD__,$log_key,null,null,$Data);
            return json($Data);
        }
        if(!in_array($Currency,config('dx_support_currency'))){
            $Currency = 'USD';
        }
        $Country = $this->CommonService->verifyCountry($Country);
        //如果有缓存的，把缓存加进去
        $res = $this->CheckoutService->getPayType($Currency,$Country);

//		echo json_encode($_pay_token_info);die;
        if($res){
                //将PayPal支付渠道移至第一位并选中 tinghu.liu 20190801
                foreach ($res as $k10=>$v10){
                    if (strtoupper($v10['payname']) == 'PAYPAL'){
                        if ($k10 !== 0){
                            $temp_val = $res[0];
                            $res[0] = $v10;
                            $res[$k10] = $temp_val;
                        }
                        $res[0]['isSelect'] = 1;
                    }else{
                        $res[$k10]['isSelect'] = 0;
                    }
                }
                //将信用卡移至第二位
                if (count($res) >= 3){
                    foreach ($res as $k11=>$v11){
                        if (strtolower($v11['payname']) == 'creditcard'){
                            if ($k11 !== 1){
                                $temp_val = $res[1];
                                $res[1] = $v11;
                                $res[$k11] = $temp_val;
                            }
                        }
                    }
                }
                if (!empty($PayType)){
                    $selectKey = -1;
                    foreach ($res as $k12=>$v12){
                        if (strtolower($v12['payname']) == strtolower($PayType)){
                            $res[$k12]['isSelect'] = 1;
                            $selectKey = $k12;
                        }
                    }
                    if ($selectKey !== -1){
                        foreach ($res as $k13=>$v13){
                            if ($k13 != $selectKey){
                                $res[$k13]['isSelect'] = 0;
                            }
                        }
                    }
                }
            //20181122，如果是repay，当订单币种为ARS，BRL时，支付方式不要Paypal
            if (!empty($order_master_number)){
                if(in_array(strtoupper($Currency), ['ARS','BRL'])){
                    //如果删除的Paypal是选中的，需要重新设置选中，避免repay页面没有选中出现问题
                    $is_delete_select = false;
                    foreach ($res as $k=>$v){
                        if (strtoupper($v['payname']) == 'PAYPAL'){
                            if (isset($v['isSelect']) && $v['isSelect'] == 1){
                                $is_delete_select = true;
                            }
                            unset($res[$k]);
                        }
                    }
                    sort($res);
                    if ($is_delete_select){
                        if (isset($res[0]['isSelect']))
                            $res[0]['isSelect'] = 1;
                    }
                }
            }
            //如果没有选中的支付方式，默认第一个为选中状态
            $isselect_arr = array_unique(array_column($res,'isSelect'));
            if (!in_array('1', $isselect_arr)){
                if (isset($res[0]['isSelect'])) $res[0]['isSelect'] = 1;
            }
            $Data['code'] = 1;
            $Data['data'] = $res;

            /**
             * TODO 离线环境开启，上线时去掉，再配置admin中的ARS和MXN这两个币种的配置。为了避免发布离线影响线上 tinghu.liu 20191101
             *  start
             */
            /*$dataJson = json_encode($Data);
			if (strtoupper($Currency) == 'MXN'){
                $dataJson = '{"code":1,"data":[{"payname":"PayPal","defaultImg":"https:\/\/checkout.dx.com\/Content\/img\/checkout\/payment_methods.png","selectedImg":"https:\/\/checkout.dx.com\/Content\/img\/checkout\/payment_methods.png","channel":{"channelName":"Paypal","channelId":1},"IconImg":"https:\/\/e.dx.com\/Pattaya\/publicImg\/VISA_72x36.jpg\r\nhttps:\/\/e.dx.com\/Pattaya\/publicImg\/MasterCard_72x36.jpg\r\nhttps:\/\/e.dx.com\/Pattaya\/publicImg\/AmericanExpress_72x36.jpg","status":1,"Addtime":1531290639,"add_person":"admin","isSelect":1},{"payname":"CreditCard","defaultImg":"https:\/\/e.dx.com\/Pattaya\/publicImg\/VISA_72x36.jpg","selectedImg":"https:\/\/e.dx.com\/Pattaya\/publicImg\/MasterCard_72x36.jpg","channel":{"channelName":"Astropay","channelId":4},"IconImg":"https:\/\/c.dx.com\/Pattaya\/publicImg\/astropay_72x36.jpg\r\nhttps:\/\/c.dx.com\/Pattaya\/publicImg\/VISA_72x36.jpg\r\nhttps:\/\/c.dx.com\/Pattaya\/publicImg\/MasterCard_72x36.jpg","status":1,"BankList":["VI","MC"],"isSelect":0},{"payname":"Transfer-Astropay","paynameAlias":"Bank Transfer","defaultImg":"https:\/\/checkout.dx.com\/Content\/img\/checkout\/brz_astropay_boleto.jpg","selectedImg":"https:\/\/checkout.dx.com\/Content\/img\/checkout\/brz_astropay_boleto.jpg","introduction":"阿根廷、巴西、墨西哥","channel":{"channelName":"Astropay","channelId":4},"IconImg":"https:\/\/checkout.dx.com\/Content\/img\/checkout\/brz_astropay_boleto.jpg","status":1,"edittime":1572423841,"edit_person":"admin","BankList":["SE","BV","BQ","SM"],"isSelect":0},{"payname":"Boleto-Astropay","paynameAlias":"OXXO","defaultImg":"https:\/\/checkout.dx.com\/Content\/img\/checkout\/brz_astropay_boleto.jpg","selectedImg":"https:\/\/checkout.dx.com\/Content\/img\/checkout\/brz_astropay_boleto.jpg","introduction":"阿根廷、墨西哥、巴西！","channel":{"channelName":"Astropay","channelId":4},"IconImg":"https:\/\/checkout.dx.com\/Content\/img\/checkout\/brz_astropay_boleto.jpg","status":1,"edittime":1572426052,"edit_person":"admin","BankList":["OX"],"isSelect":0}]}';
            }
            if (strtoupper($Currency) == 'ARS'){
                $dataJson = '{"code":1,"data":[{"payname":"PayPal","defaultImg":"https:\/\/checkout.dx.com\/Content\/img\/checkout\/payment_methods.png","selectedImg":"https:\/\/checkout.dx.com\/Content\/img\/checkout\/payment_methods.png","channel":{"channelName":"Paypal","channelId":1},"IconImg":"https:\/\/e.dx.com\/Pattaya\/publicImg\/VISA_72x36.jpg\r\nhttps:\/\/e.dx.com\/Pattaya\/publicImg\/MasterCard_72x36.jpg\r\nhttps:\/\/e.dx.com\/Pattaya\/publicImg\/AmericanExpress_72x36.jpg","status":1,"change_currency_msg":"You will be required to pay with US dollars if you choose paypal!","isSelect":0},{"payname":"CreditCard","defaultImg":"https:\/\/e.dx.com\/Pattaya\/publicImg\/VISA_72x36.jpg","selectedImg":"https:\/\/e.dx.com\/Pattaya\/publicImg\/MasterCard_72x36.jpg","channel":{"channelName":"Astropay","channelId":4},"IconImg":"https:\/\/c.dx.com\/Pattaya\/publicImg\/astropay_72x36.jpg\r\nhttps:\/\/c.dx.com\/Pattaya\/publicImg\/VISA_72x36.jpg\r\nhttps:\/\/c.dx.com\/Pattaya\/publicImg\/MasterCard_72x36.jpg\r\nhttps:\/\/c.dx.com\/Pattaya\/publicImg\/americanexpress_72x36.png\r\nhttps:\/\/c.dx.com\/Pattaya\/publicImg\/argencard_72x36.png\r\nhttps:\/\/c.dx.com\/Pattaya\/publicImg\/cabal_72x36.png\r\nhttps:\/\/c.dx.com\/Pattaya\/publicImg\/cencosud_72x36.png\r\nhttps:\/\/c.dx.com\/Pattaya\/publicImg\/naranja_72x36.png\r\nhttps:\/\/c.dx.com\/Pattaya\/publicImg\/nativa_72x36.png\r\nhttps:\/\/c.dx.com\/Pattaya\/publicImg\/shopping_72x36.png","status":1,"BankList":["VI","MC","AE","CL","NJ","TS","NT","CS","AG"],"isSelect":0},{"payname":"Transfer-Astropay","paynameAlias":"Santander Rio","defaultImg":"https:\/\/checkout.dx.com\/Content\/img\/checkout\/brz_astropay_boleto.jpg","selectedImg":"https:\/\/checkout.dx.com\/Content\/img\/checkout\/brz_astropay_boleto.jpg","introduction":"阿根廷、巴西、墨西哥","channel":{"channelName":"Astropay","channelId":4},"IconImg":"https:\/\/checkout.dx.com\/Content\/img\/checkout\/brz_astropay_boleto.jpg","status":1,"edittime":1572427554,"edit_person":"admin","BankList":["SI"],"isSelect":0},{"payname":"Boleto-Astropay","paynameAlias":"Cash Payment","defaultImg":"https:\/\/checkout.dx.com\/Content\/img\/checkout\/brz_astropay_boleto.jpg","selectedImg":"https:\/\/checkout.dx.com\/Content\/img\/checkout\/brz_astropay_boleto.jpg","introduction":"阿根廷、墨西哥、巴西！","channel":{"channelName":"Astropay","channelId":4},"IconImg":"https:\/\/checkout.dx.com\/Content\/img\/checkout\/brz_astropay_boleto.jpg","status":1,"edittime":1572430458,"edit_person":"admin","BankList":["PF","RP"],"isSelect":1}]}';
            }
            if (strtoupper($Currency) == 'INR'){
			    $dataJson = '{"code":1,"data":[{"payname":"CreditCard","defaultImg":"https:\/\/e.dx.com\/Pattaya\/publicImg\/VISA_72x36.jpg","selectedImg":"https:\/\/e.dx.com\/Pattaya\/publicImg\/MasterCard_72x36.jpg","channel":{"channelName":"Astropay","channelId":4},"IconImg":"https:\/\/e.dx.com\/Pattaya\/publicImg\/VISA_72x36.jpg\r\nhttps:\/\/e.dx.com\/Pattaya\/publicImg\/MasterCard_72x36.jpg\r\nhttps:\/\/e.dx.com\/Pattaya\/publicImg\/AmericanExpress_72x36.jpg\r\nhttps:\/\/e.dx.com\/Pattaya\/publicImg\/Discover_72x36.jpg\r\nhttps:\/\/e.dx.com\/Pattaya\/publicImg\/DinersClub_72x36.jpg\r\nhttps:\/\/e.dx.com\/Pattaya\/publicImg\/JCB_72x36.jpg","status":1,"BankList":["VI","MD","AE","RU"],"isSelect":1},{"payname":"Transfer-Astropay","paynameAlias":"Bank Transfer","defaultImg":"https:\/\/pay.dlocal.com\/views\/2.0\/images\/payments\/UI.png","selectedImg":"https:\/\/pay.dlocal.com\/views\/2.0\/images\/payments\/NB.png","introduction":"https:\/\/pay.dlocal.com\/views\/2.0\/images\/payments\/UI.png","channel":{"channelName":"Astropay","channelId":4},"IconImg":"https:\/\/pay.dlocal.com\/views\/2.0\/images\/payments\/UI.png\r\nhttps:\/\/pay.dlocal.com\/views\/2.0\/images\/payments\/NB.png","status":1,"edittime":1572419243,"edit_person":"admin","BankList":["PW","NB","UI"],"isSelect":0}]}';
            }

            $Data = json_decode($dataJson, true);*/
            /****** end *******/
        }else{
            $Data['code'] = 2010005;
            $Data['msg'] = lang('tips_2010005');
            logService::write(LOGS_MALL_CART,'debug',__METHOD__,$log_key,null,null,$Data);
        }
        if (!empty($paramsCountry) || $flag == 1){
            return $Data;
        }else{
            return json($Data);
        }
    }

    /**
     * 获取用户的SC
     * @param string $currency
     * @return mixed
     */
    private function getCustomerSC($customer_id,$currency=''){
        $res = $this->CommonService->getCustomerSC($customer_id,$currency);
        if($res){
            $returnData['code'] = 1;
            $returnData['data'] = $res;
        }else{
            $returnData['code'] = 0;
            $returnData['msg'] = lang('tips_3010045');
        }
        return $returnData;
    }
    /**
     * Astropay：根据支付方式获取支付渠道
     * @return mixed
     */
    public function getAstroPayCardInfo(){
        //参数校验
        $params = request()->post();
        $validate = $this->validate($params, (new CheckoutParams())->getAstroPayCardInfoRules());
        if (true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        $Currency = $params['Currency'];//用户使用的当前币种
        $Country = $params['Country'];//这里是用户所在国家
        $PayType = $params['PayType'];//支付方式
        $res = $this->CheckoutService->getAstroPayCardInfo($Currency,$Country,$PayType);
        if($res){
            return apiReturn(['code'=>200, 'data'=>$res]);
        }else{
            return apiReturn(['code'=>1003, 'msg'=>'Failed to get payment method']);
        }
    }

    /**
     * 产品详情页BuyNow
     * @return Ambigous <\think\response\Json, \think\Response, \think\response\View, \think\response\Xml, \think\response\Redirect, \think\response\Jsonp, unknown, \think\Response>
     */
    public function addToCartBuyNow(){
        //参数校验
        $params = request()->post();
        $validate = $this->validate($params, (new CheckoutParams())->addToCartBuyNowRules());
        if (true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        //用户ID
        $Params['Uid'] = isset($params['CustomerId'])?$params['CustomerId']:0;
        $Params['UserName'] = isset($params['CustomerName'])?$params['CustomerName']:'';

        $Params['ProductID'] = $params['ProductID'];//产品ID
        $Params['SkuID'] = $params['SkuID'];//SKUID
        $Params['Qty'] = $params['Qty'];//购买的数量
        $Params['ShipTo'] = $params['Country'];
        $Params['Currency'] = $params['Currency'];//从cookies里获取语种
        $Params['lang'] = $params['Lang'];

        //20190105 初始化运输方式 buy now选中的为准
        $Params['ShippingMoel'] = $params['ShippingService'];

        if(!$Params['Uid']){
            //用户没有的情况下，把数据写入到键为游客唯一标识符上
            $Params['Uid'] = $params['GuestId'];
        }
        //计算快捷加入购物车的产品信息和相关的物流信息
        
        $ResData = $this->CartService->addToCartGetInfo($Params);

        if(!isset($ResData['code']) || $ResData['code'] != 1){
            //$ResData['msg'] = $exception->getErrorMessage($ResData['code']);
//            $ResData['msg'] = lang('tips_'.$ResData['code']);
            return apiReturn(['code'=>1003, 'msg'=>$ResData['msg']]);
        }
        $Params['ShippingMoel'] = isset($ResData['data']['ShippingMoel'])?$ResData['data']['ShippingMoel']:'';
        $Params['OldShippingMoel'] = isset($ResData['data']['OldShippingMoel'])?$ResData['data']['OldShippingMoel']:'';
        $Params['ShippingFee'] = isset($ResData['data']['ShippingFee'])?$ResData['data']['ShippingFee']:0;
        $Params['ShippingFeeType'] = isset($ResData['data']['ShippingFeeType'])?$ResData['data']['ShippingFeeType']:0;
        $Params['ShippingDays'] = isset($ResData['data']['ShippingDays'])?$ResData['data']['ShippingDays']:'';
        $Params['ProductUnit'] = isset($ResData['data']['ProductUnit'])?$ResData['data']['ProductUnit']:'';
        $Params['ShippModelStatusType'] = 1;
        $res = $this->CheckoutService->addToCartBuyNow($Params);

        Cookie::set('buynow','1');
        Cookie::set('prevCountry',$Params['ShipTo']);//用来判断是否切换了国家
        /*返回*/
        if(!isset($res['code']) || $res['code'] != 1){
//            $Data['code'] = $res['code'];
//            $Data['msg'] = $res['msg'];
            return apiReturn(['code'=>1004, 'msg'=>$res['msg']]);
        }else{
            $Data['code'] = 1;
            $Data['msg'] = 'success';
            return apiReturn(['code'=>200]);
        }
    }

    /**
     * 获取NOCNOC填写taxid后回来初始化checkout页面的数据
     * @return mixed
     */
    public function getNocSubmitOrderParams($customer_id){
        return Cache::get('nocSubmitOrderParams'.$customer_id);
    }

}
