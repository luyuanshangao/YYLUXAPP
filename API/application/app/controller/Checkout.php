<?php
namespace app\app\controller;

use app\app\services\CartService;
use app\app\services\CheckoutService;
use app\app\services\CommonService;
use app\app\services\ProductService;
use app\app\services\rateService;
use app\common\controller\AppBase;
use app\common\helpers\RedisClusterBase;
use app\common\params\app\CheckoutParams;
use think\Cookie;
use think\Log;

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
    public function __construct()
    {
        parent::__construct();
        $this->CheckoutService = new CheckoutService();
        $this->CommonService = new CommonService();
        $this->redis = new RedisClusterBase();
        $this->rateService = new rateService();
        $this->productService = new ProductService();
        $this->CartService = new CartService();
    }

    /**
     * 获取checkout产品数据【必须登录】
     * @return mixed
     */
    public function get(){
        $params = request()->post();
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
            $PayType = isset($params['PayType'])?$params['PayType']:'';
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

            /** 接收参数 处理 start **/
            $PayType = isset($params['PayType'])?$params['PayType']:'';
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
                                        $ShippingFeeType = isset($sv[$i]['ShippingFee'])?$sv[$i]['ShippingFee']:0;
                                        if(isset($sv[$i]['ShippingService']) && strtolower($sv[$i]['ShippingService']) == 'nocnoc'){
                                            $ShippingFee = lang('nocnoc_tips');
                                            $ShippingFeeType = 3;
                                        }
                                        $ShippingMoel = isset($sv[$i]['ShippingService'])?$sv[$i]['ShippingService']:'';
                                        $OldShippingMoel = isset($sv[$i]['OldShippingService'])?$sv[$i]['OldShippingService']:'';

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
        //如果有缓存的，把缓存加进去
        $res = $this->CheckoutService->getPayType($Currency,$Country);
        if($res){
            /*$Data['code'] = 1;
            $Data['data'] = $res;*/
            return apiReturn(['code'=>200, 'data'=>$res]);
        }else{
            return apiReturn(['code'=>1003, 'msg'=>'Failed to get payment method']);
        }
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

}
