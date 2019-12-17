<?php
namespace app\app\controller;

use app\app\services\CartService;
use app\app\services\CommonService;
use app\app\services\CouponService;
use app\app\services\ProductService;
use app\app\services\rateService;
use app\common\controller\AppBase;
use app\common\helpers\RedisClusterBase;
use app\common\params\app\CartParams;
use app\mall\controller\Coupon;
use think\Cookie;

/**
 * 开发：tinghu.liu
 * 功能：cart
 * 时间：2018-09-08
 */
class Cart extends AppBase
{
    public $redis;
    public $rateService;
    public $productService;
    public $CartService;
    public $CommonService;
    public $CouponService;
    public function __construct()
    {
        parent::__construct();
        $this->redis = new RedisClusterBase();
        $this->CartService = new CartService();
        $this->CommonService = new CommonService();
        $this->rateService = new rateService();
        $this->productService = new ProductService();
        $this->CouponService = new CouponService();
    }

    /**
     * 添加购物车
     * 必穿参数：
     *
     *  ProductID: 2002972
        SkuID: 607921
        Qty: 1
        ShipTo: RU
        Currency: USD
        Lang: en
     *  CustomerId （无论是游客还是已经登录的，都需要传唯一ID）
     *  isGuest（是否是游客：1-是，2-不是）
     *
     * 非必穿参数：
     *
     * CustomerName
     *
     * @return mixed
     */
    public function add(){
        $post_params = request()->post();
        try{
            $validate = $this->validate($post_params,(new CartParams())->addRules());
            if (true !== $validate){
                return apiReturn(['code'=>1002, 'msg'=>$validate]);
            }
            $_uid = $post_params['CustomerId'];
            //判断是否已满
            $checkSkuNumsRes = $this->getCartInfoSimpleProcess($_uid);
            if(isset($checkSkuNumsRes['data']) && $checkSkuNumsRes['data'] > 999){
                return apiReturn(['code'=>1003, 'msg'=>'the shopping cart full']);
            }
            $Params['ProductID'] = $post_params['ProductID'];//产品ID
            $Params['SkuID'] = $post_params['SkuID'];//SKUID
            $_qty = $post_params['Qty'];//购买的数量
            if($_qty<=0){
                $_qty =1;
            }
            $Params['Qty'] =$_qty;
            $Params['ShipTo'] = $post_params['ShipTo'];
            $Params['Currency'] = $post_params['Currency'];
            $Params['Lang'] = $post_params['Lang'];
            $Params['CstomerID'] = $_uid;
            $Params['CstomerName'] = isset($post_params['CustomerName'])?$post_params['CustomerName']:'';
            $Params['isGuest'] = isset($post_params['isGuest'])?$post_params['isGuest']:'';
            //20190105 添加购物车保持用户选择的运输方式
            $Params['ShippingMoel'] = isset($post_params['ShippingService'])?$post_params['ShippingService']:'';
            $res = $this->realAddToCart($Params);
            $checkSkuNumsRes = $this->getCartInfoSimpleProcess($_uid);
            Cookie::set('prevCountry',$Params['ShipTo']);//用来判断是否切换了国家
            /*返回*/
            if(!isset($res['code']) || $res['code'] != 1){
                return apiReturn(['code'=>1004, 'msg'=>$res['msg']]);
            }else{
                $_nums = isset($checkSkuNumsRes['data'])?((int)$checkSkuNumsRes['data']):1;
                return apiReturn(['code'=>200, 'msg'=>'success', 'nums'=>$_nums]);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1004, 'msg'=>'程序异常 '.$e->getMessage()]);
        }
    }

    /**
     * 更新购物车
     * @return mixed
     */
    public function updateCart(){
        $params = request()->post();
        $validate = $this->validate($params,(new CartParams())->updateCartRules());
        if (true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            //游客或登录用户ID
            $customer_id = $params['CustomerId'];
            $res = $this->redis->set(SHOPPINGCART_.$customer_id, $params['cart_data']);
            if ($res){
                return apiReturn(['code'=>200]);
            }else{
                return apiReturn(['code'=>1003, 'msg'=>'更新失败']);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1004, 'msg'=>'程序异常 '.$e->getMessage()]);
        }
    }

    /**
     * 获取购物车数据
     * 必传字段：
     * GuestId
     * Currency
     * Country
     * Lang
     *
     * 非必传字段：
     * CustomerId
     * CustomerName
     *
     * 流程：
     * 1、app/Cart/getCart 接口获取数据【没有运输方式数据】
     * 2、初始化产品默认或选择的运输方式
     * 3、如果运输方式有NOCNOC，则调用NOCNOC询价接口返回NOCNOC运费信息
     * @return mixed
     */
    public function getCart(){
        $params = request()->post();
        $validate = $this->validate($params,(new CartParams())->getCartRules());
        if (true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $Uid = isset($params['CustomerId'])?$params['CustomerId']:'';//登录用户ID【可不传，登录就传】
            $UserName = isset($params['CustomerName'])?$params['CustomerName']:'';//登录用户名【可不传，登录就传】
            $GuestId = $params['GuestId'];//游客ID
            $Currency = $params['Currency'];
            $Country = $params['Country']; //国家简码
            $Lang = $params['Lang'];

            //Cookie::set('DXGlobalization_shiptocountry',$Country,['domain'=>MALL_DOMAIN]);

            $CouponCartInfo = null;
            $Coupon = $this->getStoreCoupon($Uid,$GuestId,$Lang,$Country,$Currency);
            //如果存在则缓存该用户的
            if(is_array($Coupon) && isset($Coupon['CartInfo'])){
                $CouponCartInfo = $Coupon['CartInfo'];
            }
            $res = $this->CartService->getCartInfo($Uid,$Currency,$Country,$Lang,$GuestId,$CouponCartInfo,$UserName);

            if(isset($res['code']) && $res['code'] == 1){
                if(in_array($Currency,config('paypal_not_support_currency'))){
                    //$Data['paypal_not_support'] = lang('tips_3020006');
                    $Data['paypal_not_support'] = 'You will be required to pay with US dollars if you choose paypal!';
                }
                if(!$Uid){
                    //如果用户没有登录的情况下使用游客的身份获取购物车的信息
                    $Uid = $GuestId;
                }
                /*3s、返回给前端*/
                //$Data['code'] = 1;
                if(isset($res['nocdata'])){
                    //$Data['nocdata'] = $res['nocdata'];
                }
                if(isset($res['IsHasNocNoc'])){
                    $Data['IsHasNocNoc'] = $res['IsHasNocNoc'];
                }
                //把汇率写进去
                $_rate = 1;
                if($Currency != 'USD'){
                    $_rate = $this->CommonService->getOneRate( $Currency,'USD');
                }
                $Data['rate'] = $_rate;//前端计算积分的时候，用算出来的积分除以这个值
                $Data['data'] = isset($res['data'][$Uid])?$this->CommonService->handlerCartOrCheckoutProductDataFowAPP($res['data'][$Uid], 1):array();
                return apiReturn(['code'=>200, 'data'=>$Data]);
            }else{
                /*3s、返回给前端*/
                /*$Data['code'] = $res['code'];
                $Data['msg'] = lang('tips_'.$res['code']);*/
                $msg = isset($res['msg'])?$res['msg']:'Get cart data is error.';
                return apiReturn(['code'=>$res['code'], 'msg'=>$msg]);
            }
            /*$data = $this->redis->get(SHOPPINGCART_.$Uid);
            return apiReturn(['code'=>200, 'data'=>$data]);*/
        }catch (\Exception $e){
            return apiReturn(['code'=>1004, 'msg'=>'程序异常 '.$e->getMessage()]);
        }
    }

    /**
     * 获取购物车运费初始化数据
     * 获取购物车初始化运输方式数据(购物车列表初始化使用)
     * 必传参数
     * ['Currency','require'],

        ['GuestId','require'],
        //收货国家
        ['Country','require'],
        //语种
        ['Lang','require'],
     *
     * 非必传参数
     * ['CustomerId','integer'],
     *
     */
    public function getInitShippingData(){
        $params = request()->post();
        $validate = $this->validate($params,(new CartParams())->getInitShippingDataRules());
        if (true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $currency = $params['Currency'];
            $Uid = isset($params['CustomerId'])?$params['CustomerId']:'';//登录用户ID【可不传，登录就传】
            $GuestId = $params['GuestId'];//游客ID
            $country = $params['Country']; //国家简码
            $Lang = $params['Lang'];


            //$currency = $this->currency;
            $return_data = ['code'=>200, 'data'=>[], 'msg'=>'success'];
            //$this->loadCustomerInfo();
            //$GuestId = $this->guestUniquenessIdentify;//游客的身份标识
            //$Uid = $this->CstomerInfo['data']['ID'];
            if(!$Uid){
                $Uid = $GuestId;
            }
            $CartInfo = $this->redis->get(SHOPPINGCART_.$Uid);
            if (empty($CartInfo) || !isset($CartInfo[$Uid]) || !isset($CartInfo[$Uid]['StoreData'])){
                return apiReturn(['code'=>1002, 'msg'=>'Cart data is error.']);
            }
            //是否更新购物车信息，默认为false。【20181211 修改为true，为了解决初始化运费产品数量变化但运费没计算问题】
            $isUpdateCart = true;
            //收货国家选择
            //$country = !empty($this->checkoutCountry)?$this->checkoutCountry:$this->country;
            //获取汇率参数
            $_rate = 1;
            if($currency != 'USD'){
                $startTime = microtime(true);
                $_rate = $this->rateService->getCurrentRate($currency);
                $endTime = microtime(true);
                $slowTime = config('slow_api_time')?config('slow_api_time'):100;//慢API时间(单位时间为毫秒)
                $useTime = ($endTime-$startTime)*1000;//毫秒
                if($useTime > $slowTime){
                    //记录日志(待定),格式：主调方($from)，被调方($url)，花费时间($useTime)
                    $log = '=FunctionName:Cart-getShippingInitData-_rate=UseTime:'.$useTime;
                    \think\Log::pathlog('APIRequest',$log,'FunctionRequest.log');
                }
            }
            //组装获取运输方式请求参数 start
            $tempData = [];
            $tempData2 = [];
            $getShippingParams = [];
            $getShippingParams['lang'] = $Lang;
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
                                    //cart页面去掉NOC标识，2018-08-17确定cart页面不计算noc运费
                                    /*$ShippingFee = lang('nocnoc_tips');
                                    $ShippingFeeType = 3;*/
                                }
                                $temp['SkuID'] = $sku_id;
                                $temp['ShippModelStatusType'] = $v2['ShippModelStatusType'];
                                $temp['ShippingDays'] = $v2['ShippingDays'];
                                $temp['ShippingFee'] = (string)$ShippingFee;
                                $temp['ShippingFeeType'] = $ShippingFeeType;
                                $temp['ShippingMoel'] = $v2['ShippingMoel'];
                                $temp['OldShippingMoel'] = isset($v2['OldShippingMoel'])?$v2['OldShippingMoel']:'';
                                $tempData[$sku_id] = $temp;
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
                                        //20190105 运输方式选中加入购物车时的
                                        $i = 0; //默认选中最便宜的运输方式
                                        foreach ($sv as $sk2=>$sv2){
                                            if(strtolower($sv2['ShippingService']) == strtolower($v5['ShippingMoel'])){
                                                $i = $sk2;
                                                break;
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
                                            //cart页面去掉NOC标识，2018-08-17确定cart页面不计算noc运费
                                            /*$ShippingFee = lang('nocnoc_tips');
                                            $ShippingFeeType = 3;*/
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

                                        $tempData2[$sku_id] = $temp2;
                                        break 3;
                                    }
                                }
                            }
                        }
                    }
                }
                $return_data['data'] = $tempData2;
                //更新购物车信息
                $this->redis->set(SHOPPINGCART_.$Uid, $CartInfo);
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
            return json($return_data);
        }catch (\Exception $e){
            return apiReturn(['code'=>1004, 'msg'=>'程序异常 '.$e->getMessage()]);
        }
    }

    /**
     * 清空购物车数据
     * @return mixed
     */
    public function clearCart(){
        $params = request()->post();
        $validate = $this->validate($params,(new CartParams())->clearCartRules());
        if (true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            //游客或登录用户ID
            $customer_id = $params['CustomerId'];
            $res = $this->redis->rm(SHOPPINGCART_.$customer_id);
            if ($res){
                return apiReturn(['code'=>200]);
            }else{
                return apiReturn(['code'=>1003, 'msg'=>'操作失败']);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1004, 'msg'=>'程序异常 '.$e->getMessage()]);
        }
    }

    /**
     * 获取购物车数量
     * @return mixed
     */
    public function getCartCount(){
        $params = request()->post();
        $validate = $this->validate($params,(new CartParams())->getCartCountRules());
        if (true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            //游客或登录用户ID
            $customer_id = $params['CustomerId'];
            $cart_info = $this->redis->get(SHOPPINGCART_.$customer_id);
            if (!empty($cart_info)){
                $num_counts = 0;
                if (isset($cart_info[$customer_id])){
                    $num_counts = $this->CartService->getCartInfoSimple($cart_info[$customer_id]);
                }
                return apiReturn(['code'=>200,'count'=>$num_counts]);
            }else{
                return apiReturn(['code'=>200, 'count'=>0]);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1004, 'msg'=>'程序异常 '.$e->getMessage()]);
        }
    }

    /**
     * 移除购物车产品
     * @param int ProductId
     * @param int SkuId
     * @return true/false
     */
    public function removeFromCart(){
        $params = request()->post();
        $validate = $this->validate($params,(new CartParams())->removeFromCartRules());
        if (true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        foreach ($params['SkuInfo'] as $param_info){
            $validate_info = $this->validate($param_info,(new CartParams())->removeFromCartSkuInfoRules());
            if (true !== $validate_info){
                return apiReturn(['code'=>1002, 'msg'=>$validate_info]);
            }
        }
        try{
            $Uid = $params['CustomerId'];
            $GuestId = $params['GuestId'];
            if(!$Uid){
                $Uid = $GuestId;
            }
            $res = $this->CartService->removeFromCart($Uid, $params['SkuInfo']);
            if($res){
                $ItemTotal = $this->getCartInfoSimpleProcess($Uid);
                $Data['cartNums'] = $ItemTotal['data'];
                //$Data['noc_data'] = $res['noc_data']; 购物车不做NOCNOC询价，可忽略
                return apiReturn(['code'=>200, 'data'=>$Data]);
            }else{
                return apiReturn(['code'=>1003, 'msg'=>'Remove fail.']);
            }

        }catch (\Exception $e){
            return apiReturn(['code'=>1004, 'msg'=>'程序异常 '.$e->getMessage()]);
        }
    }

    /**
     * 选中和不选中产品（包含全选）
     *
     * 必传
        ['GuestId','require'],
        //币种
        ['Currency','require'],
        //收货国家
        ['Country','require'],
        //语种
        ['Lang','require'],
     *
     * 非必传
     * ['CustomerId','integer'],
     */
    public function isCheck(){
        $params = request()->post();
        $validate = $this->validate($params,(new CartParams())->isCheckRules());
        if (true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        foreach ($params['Data'] as $info){
            $validate_data = $this->validate($info,(new CartParams())->isCheckDataRules());
            if (true !== $validate_data){
                return apiReturn(['code'=>1002, 'msg'=>$validate_data]);
            }
        }
        try{
            $Uid = $params['CustomerId'];
            $GuestId = $params['GuestId'];
            if(!$Uid){
                $Uid = $GuestId;
            }
            $Params = $params['Data'];
            $ShipTo = $params['Country'];
            $Lang = $params['Lang'];
            $Currency = $params['Currency'];
            $ResData = $this->CartService->isCheck($Uid,$Params,$ShipTo,$Lang,$Currency);

            if($ResData['code'] == 1){
                //修改了是否选中,如果有coupon,需要判断该coupon是否可用
                $CouponStatus = array();
                foreach ($Params as $k=>$v){
                    $Tmp = $this->CartService->CouponStatusProcess($v,$Uid);
                    if($Tmp){
                        $CouponStatus[] = $Tmp;
                    }
                }
                $Data = [];
                //要对coupon做去重操作
                if(count($CouponStatus) > 0){
                    $ResCoupon = array();
                    foreach ($CouponStatus as $k=>$v){
                        if(is_array($v)){
                            foreach ($v as $k1=>$v1){
//                                $ResCoupon[$v1['CouponId']] = $v1;
                                $ResCoupon[] = $v1;
                            }
                        }
                    }
                    $Data['Coupon'] = $ResCoupon;
                }
                if(isset($ResData['noc_data'])){
                    //$Data['noc_data'] = $ResData['noc_data']; cart去掉NOCNOC
                }
                return apiReturn(['code'=>200, 'data'=>$Data]);
            }else{
                return apiReturn(['code'=>1005, 'msg'=>$ResData['msg']]);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1004, 'msg'=>'程序异常 '.$e->getMessage()]);
        }
    }

    /**
     * 改变购物车产品数量
     */
    public function changeProductNums(){
        $params = request()->post();
        $validate = $this->validate($params,(new CartParams())->changeProductNumsRules());
        if (true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $Uid = $params['CustomerId'];
            $GuestId = $params['GuestId'];
            if(!$Uid){
                $Uid = $GuestId;
            }
            /*$this->loadCustomerInfo();
            //初始化错误提示
            //$exception = new BusinessException($this->lang);
            $Uid = $this->CstomerInfo['data']['ID'];
            if(!$Uid){
                $Uid = $this->guestUniquenessIdentify;
            }*/
            $ProductID = $params['ProductID'];//产品ID
            $SkuID = $params['SkuID'];//SKUID
            $Qty =  isset($params['Qty'])?$params['Qty']:1;//购买的数量
            if($Qty<=0){
                $Qty =1;
            }
            /*
            if($Qty>999){
                $Qty = 999;
            }
            */
            $Params['Qty'] = $Qty;
            $Params['ShipModel'] = $params['ShipModel'];
            $Params['SkuID'] = $SkuID;
            $Params['ProductID'] = $ProductID;
            $Params['StoreID'] = $params['StoreID']?$params['StoreID']:0;

            $Params['Lang'] = $params['Lang'];
            $Params['Currency'] = $params['Currency'];
            $Params['ShipTo'] = $params['Country'];
            $Params['Uid'] = $Uid;

            $res = $this->CartService->changeProductNums($Params);
            if($res['flag'] == 1){
                //$ReturnData['code'] = 1;
                //$ReturnData['data'] = $res;
                return apiReturn(['code'=>200, 'data'=>$res]);
            }else{
                $ReturnData['code'] = 0;
                $ReturnData['product_nums'] = isset($res['product_nums'])?$res['product_nums']:$Params['Qty'];
                //$ReturnData['msg'] = $exception->getErrorMessage($res['flag']);
                $_msg = $res['msg'];
                /*if(empty($_msg) || strlen($_msg)<1)
                    $_msg =lang('tips_'.$res['flag']);*/
                //$ReturnData['msg'] = $_msg; //lang('tips_'.$res['flag']);
                return apiReturn(['code'=>1005, 'msg'=>$_msg]);
            }
            //return json($ReturnData);
        }catch (\Exception $e){
            return apiReturn(['code'=>1004, 'msg'=>'程序异常 '.$e->getMessage()]);
        }
    }

    /**
     * 获取运输方式
     */
    public function getShipModel(){
        $params = request()->post();
        $validate = $this->validate($params,(new CartParams())->getShipModelRules());
        if (true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            //初始化错误提示
            //$exception = new BusinessException($this->lang);
            $ProductID = (int)$params['ProductID'];//产品ID
            $SkuID = $params['SkuID'];
            $ShipTo = $params['ShipTo'];//SKUID
            $Count = $params['Qty'];
            $Currency = $params['Currency'];
            $Lang = $params['Lang'];

            //$PayType = input('pay_type')?input('pay_type'):'';
            //$IsPaypalQuick = input('is_paypal_quick')?input('is_paypal_quick'):0;
            $PayType = isset($params['PayType'])?$params['PayType']:'';
            $IsPaypalQuick = isset($params['IsPaypalQuick'])?$params['IsPaypalQuick']:'';

            //$PayType不为空，则表示来至checkout，checkout需要对币种进行处理，cart不需要
            if (!empty($PayType) || $IsPaypalQuick){
                if(strtolower($PayType) != 'paypal' || $IsPaypalQuick){
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
            }
            $params['spu'] = $ProductID;
            $params['skuid'] = $SkuID;
            $params['count'] = $Count;
            $params += [
                'lang' => $Lang ,//当前语种
                'currency' => $Currency,//当前币种
                'country' => $ShipTo
            ];
            $checkParams['ShipTo'] = $ShipTo;
            $checkParams['ProductID'] = $ProductID;
            $resData = $this->productService->countProductShipping($params);
            if($resData){
                $Data['code'] = 200;
                $Data['data'] = $resData;
            }else{
                $Data['code'] = 3010007;
                //$Data['msg'] = $exception->getErrorMessage(3010007);
                //$Data['msg'] = lang('tips_3010007');
                $Data['msg'] = 'Failed to get shipping template';
            }
            /*返回给客户端*/
            return apiReturn($Data);
        }catch (\Exception $e){
            return apiReturn(['code'=>1004, 'msg'=>'程序异常 '.$e->getMessage()]);
        }

    }

    /**
     * 改变运输方式
     * TODO【buy now的判断，传参？？？？或将buy now标识写进redis（通过用户ID来区分key），之后判断？？？】
     */
    public function changeShipModel(){
        $params = request()->post();
        $validate = $this->validate($params,(new CartParams())->changeShipModelRules());
        if (true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        $Uid = $params['CustomerId'];
        $GuestId = $params['GuestId'];
        if(!$Uid){
            $Uid = $GuestId;
        }
        $Currency = $params['Currency'];
        $ShipTo = $params['ShipTo'];
        $ShipModel = $params['ShipModel'];
        $StoreId = $params['StoreId'];
        $ProductID = $params['ProductID'];//产品ID
        $SkuId = $params['SkuID'];//产品ID
        $Qty = $params['Qty'];
        $From = isset($params['From'])?$params['From']:1;//来源：1-cart，2-checkout
        $Lang = $params['Lang'];//来源：1-cart，2-checkout
        $IsPaypalQuick = isset($params['IsPaypalQuick'])?$params['IsPaypalQuick']:0;
        $PayType = isset($params['PayType'])?$params['PayType']:0;
        $Params['StoreId'] = $StoreId;
        $Params['SkuId'] = $SkuId;
        $Params['ProductID'] = $ProductID;
        $Params['ShipModel'] = $ShipModel;
        $Params['ShipTo'] = $ShipTo;
        $Params['Qty'] = $Qty;
        $Params['From'] = $From;
        //如果来至checkout，需要转化币种
        if ($From == 2){
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
        }
        $Params['Lang'] = $Lang;
        $Params['Currency'] = $Currency;
        $Params['Uid'] = $Uid;
        $res = $this->CartService->changeShipModel($Params);
        if (isset($res['code']) && $res['code'] == 1){
            return apiReturn(['code'=>200, 'data'=>$res['data']]);
        }else{
            return apiReturn(['code'=>1004, 'msg'=>$res['msg']]);
        }
    }

    /**
     * 使用或取消使用coupon
     */
    public function useCoupon(){
        /*$this->loadCustomerInfo();
        $Uid = $this->CstomerInfo['data']['ID'];
        if(!$Uid){
            $Uid = $this->guestUniquenessIdentify;
        }*/
        $_params = request()->post();
        $validate = $this->validate($_params,(new CartParams())->useCouponForSellerRules());
        if (true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        //如果为2则表示是seller级别的coupon,如果为1则表示为sku级别的coupon
        if (isset($_params['DiscountLevel']) && $_params['DiscountLevel'] == 1){
            $validate = $this->validate($_params,(new CartParams())->useCouponRules());
            if (true !== $validate){
                return apiReturn(['code'=>1005, 'msg'=>$validate]);
            }
        }
        $Uid = $_params['CustomerId'];
        $GuestId = $_params['GuestId'];
        if(!$Uid){
            $Uid = $GuestId;
        }
        $Params = array();
        $Params['StoreId'] = $_params['StoreId'];
        $Params['productId'] = $_params['ProductID'];
        $Params['SkuId'] = $_params['SkuID'];
        $Params['Qty'] = $_params['Qty'];
        $Params['DiscountLevel'] = $_params['DiscountLevel'];//如果为2则表示是seller级别的coupon,如果为1则表示为sku级别的coupon
        $Params['CouponId'] = isset($_params['CouponId'])?$_params['CouponId']:0;
        $Params['CouponCode'] = isset($_params['CouponCode'])?$_params['CouponCode']:0;
        if(strlen($Params['CouponCode']) >10){
            /*$ResData['IsError'] = 1;
            $ResData['DiscountPrice'] = 0;*/
            return apiReturn(['code'=>1003, 'msg'=>'CouponCode is error.']);
        }
        //验证
        $Params['customer_id'] = $Uid;//用来获取coupon
        $Params['type'] = 1;//表示获取店铺级别的优惠券//用来获取coupon
        $Params['CouponRuleType'] = array(1,2);//用来获取coupon
        $Params['country_code'] = $_params['ShipTo'];//用来获取coupon
        $Params['Lang'] = $_params['Lang'];
        $Params['Currency'] = $_params['Currency'];
        if($Params['CouponId']){
            $ResData = $this->CouponService->useCoupon($Uid,$Params);
            if ($ResData !== false){
                return apiReturn(['code'=>200, 'data'=>$ResData]);
            }else{
                return apiReturn(['code'=>1004, 'msg'=>'Failure to use coupon.']);
            }
        }else{
            $ResData = $this->CouponService->cancelCoupon($Uid,$Params);
            if ($ResData['code'] == 1){
                return apiReturn(['code'=>200]);
            }else{
                return apiReturn(['code'=>1004, 'msg'=>'Failure to cancel coupon.']);
            }
        }
    }

    /**
     * 从购物车进入checkout
     */
    public function goToCheckOut(){
        $params = request()->post();
        $validate = $this->validate($params,(new CartParams())->goToCheckOutRules());
        if (true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        if (empty($params['Skus']) || !is_array($params['Skus'])){
            return apiReturn(['code'=>1003, 'msg'=>'Skus is error.']);
        }
        foreach ($params['Skus'] as $param_sku){
            $validate = $this->validate($param_sku,(new CartParams())->goToCheckOutSkusRules());
            if (true !== $validate){
                return apiReturn(['code'=>1002, 'msg'=>$validate]);
            }
        }
        $Uid = $params['CustomerId'];
        $GuestId = $params['GuestId'];
        if(!$Uid){
            $Uid = $GuestId;
        }
        $_params = $params['Skus'];
        $res = $this->CartService->goToCheckOut($Uid,$_params);
        Cookie::delete('buynow');//从购物车进checkout页面的要清除buynow标识，TODO APP是否是BUY NOW，让客户端传过来？？？
        if($res){
            return apiReturn(['code'=>200]);
        }else{
            return apiReturn(['code'=>1003, 'msg'=>'Go To CheckOut fail.']);
        }
    }


    /**
     * 处理获取迷你购物车的方法
     * @param $Uid 用户ID
     * @return mixed
     */
    public function getCartInfoSimpleProcess($Uid){
        /*$this->loadCustomerInfo();
        $GuestId = $this->guestUniquenessIdentify;//游客的身份标识
        $Uid = $this->CstomerInfo['data']['ID'];
        if(!$Uid){
            $Uid = $GuestId;
        }*/
        /*1、读取redis里的购物车信息*/
        $CartInfo = $this->redis->get(SHOPPINGCART_.$Uid);
        if(isset($CartInfo[$Uid])){
            $res = $this->CartService->getCartInfoSimple($CartInfo[$Uid]);
            $Data['code'] = 1;
            $Data['data'] = $res;
        }else{
            $Data['code'] = 0;
            $Data['data'] = '';
        }
        return $Data;
    }

    /**
     * 真正处理加入购物车的方法
     * @param $Params
     * @return mixed
     */
    private function realAddToCart($Params){
        //用户ID
        /*if($Params['CstomerID'] && $Params['CstomerName']){
            $Params['Uid'] = $Params['CstomerID'];
            $Params['UserName'] = $Params['CstomerName'];
        }else{
            $Params['Uid'] = isset($Params['Uid'])?$Params['Uid']:$this->CstomerInfo['data']['ID'];
            $Params['UserName'] = isset($Params['UserName'])?$Params['UserName']:$this->CstomerInfo['data']['UserName'];
            if(!$this->CstomerInfo['data']['ID']){
                //用户没有的情况下，把数据写入到键为游客唯一标识符上
                $Params['Uid'] = $this->guestUniquenessIdentify;
                $isGuest = 1;
            }
        }*/

        $Params['Uid'] = $Params['CstomerID'];
        $Params['UserName'] = $Params['CstomerName'];
        $Params['lang'] = $Params['Lang'];
        $Params['isGuest'] = $Params['isGuest'];//是否是游客

        //计算快捷加入购物车的产品信息和相关的物流信息
        $ResData = $this->CartService->addToCartGetInfo($Params);
        if(!isset($ResData['code']) || $ResData['code'] != 1){
            //$Return['msg'] = $exception->getErrorMessage($ResData['code']);
            //$Return['msg'] = lang('tips_'.$ResData['code']);
            $Return['msg'] = $ResData['msg'];
            return $Return;
        }

        $Params['ShippingMoel'] = isset($ResData['data']['ShippingMoel'])?$ResData['data']['ShippingMoel']:'';
        $Params['OldShippingMoel'] = isset($ResData['data']['OldShippingMoel'])?$ResData['data']['OldShippingMoel']:'';
        $Params['ShippingFee'] = isset($ResData['data']['ShippingFee'])?$ResData['data']['ShippingFee']:0;
        $Params['ShippingFeeType'] = isset($ResData['data']['ShippingFeeType'])?$ResData['data']['ShippingFeeType']:0;
        $Params['ShippingDays'] = isset($ResData['data']['ShippingDays'])?$ResData['data']['ShippingDays']:'';
        $Params['ProductUnit'] = isset($ResData['data']['ProductUnit'])?$ResData['data']['ProductUnit']:'';
        $Params['ShippModelStatusType'] = 1;
        $ResData = $this->CartService->addToCart($Params);
        if(!isset($ResData['code']) || $ResData['code'] != 1){
            //$Return['msg'] = $exception->getErrorMessage($ResData['code']);
            //$Return['msg'] = lang('tips_'.$ResData['code']);
            $Return['msg'] = isset($ResData['msg'])?$ResData['msg']:'Add to cart error.';
            return $Return;
        }else{
            $Return['code'] = 1;
            $Return['data'] = 'success';
            return $Return;
        }
    }

    /**
     * 获取店铺优惠券信息
     * is_sku:如果为1则表示是获取sku的优惠券,2表示获取订单级别的
     */
    public function getStoreCoupon($Uid, $GuestId, $Lang, $Country, $Currency){
        //coupon处理，不使用coupon（判断是否使用coupon）
        if (!config('use_coupon_switch_on')){
            return [];
        }
        if(empty($Uid)){
            $Uid = $GuestId;
        }
        //获取购物车信息，将优惠券与购物车相关联
        $CartInfo = $this->redis->get(SHOPPINGCART_.$Uid);
        if(!isset($CartInfo[$Uid])){
            //如果用户购物车里都没有东西，则直接返回
            $ReturnData['code'] = 0;
            $ReturnData['data'] = array();
            return json($ReturnData);
        }
        //根据购物车信息，匹配现在缓存的sku,store的优惠券，如果完全匹配，则直接返回，如果存在差集，则重新请求
        $TmpCoupon = array();
        //$AnewFlag = $this->CommonService->checkCouponCache($CartInfo,$Uid,$TmpCoupon);
        $Params['customer_id'] = $Uid;
        $Params['type'] = 1;//表示获取店铺级别的优惠券
        $Params['country_code'] = $Country;
        $Params['CouponRuleType'] = array(1,2);//
        $Params['Lang'] = $Lang;
        //if(!$AnewFlag){
        //如果缓存的不匹配，则重新请求
        $ReturnData = $this->CartService->getCoupon($Params);

        //}else{
        //请求匹配，使用缓存
        //	$ReturnData['data'] = $TmpCoupon;
        //}
        $cartUsableCoupon = $this->CommonService->filtrationCouponByCart($CartInfo[$Uid],$ReturnData['data'],$Params['country_code'], $Currency);
        //针对sku,store缓存对应的优惠券
        if(isset($cartUsableCoupon['SkuCanUseCoupon'])){
            foreach ($cartUsableCoupon['SkuCanUseCoupon'] as $k=>$v){
                $this->redis->set("SkuCoupon_".$k,$v);
            }
        }
        if(isset($cartUsableCoupon['SellerCanUseCoupon'])){
            foreach ($cartUsableCoupon['SellerCanUseCoupon'] as $k=>$v){
                $this->redis->set("SellerCoupon_".$k,$v);
            }
        }
        return $cartUsableCoupon;
    }

}
