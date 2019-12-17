<?php
namespace app\app\services;

use app\common\helpers\CommonLib;
use think\Cache;
use app\app\model\NoticesModel;
use think\Cookie;

/**
 * 开发：tinghu.liu
 * 功能：CartService
 * 时间：2018-09-19
 */
class CartService extends BaseService
{
    public $CommonService;
    public $CouponService;
    public $ProductService;
    public $NocService;
    public function __construct()
    {
        parent::__construct();
        $this->CommonService = new CommonService();
        $this->ProductService = new ProductService();
        $this->CouponService = new CouponService();
        $this->NocService = new NocService();
    }

    /**
     * 获取迷你购物车
     * @param $CartInfo
     * @return int
     */
    public function getCartInfoSimple($CartInfo){
        $nums = 0;
        if(!isset($CartInfo['StoreData'])){
            return 0;
        }
        foreach ($CartInfo['StoreData'] as $k=>$v){
            if(is_array($v) && isset($v['ProductInfo'])){
                foreach ($v['ProductInfo'] as $k2=>$v2){
                    foreach ($v2 as $k3 => $v3){
                        if(count($v3) > 0){
                            $nums += 1;
                        }
                    }
                }
            }
        }
        return $nums;
    }

    /**
     * 加入购物车的，获得购物车所需的信息
     * @param $_params
     * @return mixed
     */
    public function addToCartGetInfo($_params){
        $_return_data = array();
        //获取运输方式信息相关(分为有shippingModel和没有shippingModel)
        $params['spu'] = $_params['ProductID'];
        $params['count'] = $_params['Qty'];
        $params += [
            'lang' => $_params['lang'] ,//当前语种
            'currency' => $_params['Currency'],//当前币种
            'country' => $_params['ShipTo']
        ];
        $resData = $this->ProductService->countProductShipping($params);
        if(is_array($resData) && count($resData) > 0){
            $_low_free = isset($resData[0]['Cost'])?$resData[0]['Cost']:array();//最低运费，如果没有选择shippingModel的按最低运费的算
            $_low_free_index = 0;
            foreach ($resData as $k=>$v){
                if(isset($_params['ShippingMoel'])){
                    if($_params['ShippingMoel'] == $v['ShippingService']){
                        $_return_data['ShippingMoel'] = $v['ShippingService'];
                        $_return_data['OldShippingMoel'] = isset($v['OldShippingService'])?$v['OldShippingService']:'';
                        $_return_data['ShippingFee'] = $v['Cost'];
                        $_return_data['ShippingFeeType'] = $v['ShippingFee'];
                        $_return_data['ShippingDays'] = $v['EstimatedDeliveryTime'];
                    }
                }else{
                    if($v['Cost'] < $_low_free){
                        $_low_free = $v['Cost'];
                        $_low_free_index = $k;
                    }
                }
            }
            if(!isset($_params['ShippingMoel'])){
                //没有选择运送方式的情况
                $_return_data['ShippingMoel'] = $resData[0]['ShippingService'];
                $_return_data['OldShippingMoel'] = isset($resData[0]['OldShippingService'])?$resData[0]['OldShippingService']:'';
                $_return_data['ShippingFee'] = $resData[0]['Cost'];
                $_return_data['ShippingFeeType'] = $resData[0]['ShippingFee'];
                $_return_data['ShippingDays'] = $resData[0]['EstimatedDeliveryTime'];
            }
        }else{
            $_return['code'] = 2010001;
            $_return['msg'] = 'shipping data is error!';
            return $_return;
        }
        $_return['code'] = 1;
        $_return['data'] = $_return_data;
        return $_return;
    }

    /**
     * 加入购物车
     * @param array $Params
     * @return boolean
     */
    public function addToCart($Params){
        $Uid = $Params['Uid'];
        $UserName = $Params['UserName'];
        $ProductID = $Params['ProductID'];
        $SkuID = $Params['SkuID'];
        $Qty = $Params['Qty'];
        $ShippingMoel = $Params['ShippingMoel'];
        $OldShippingMoel = $Params['OldShippingMoel'];
        $ShippingFee = $Params['ShippingFee'];
        $ShippingDays = $Params['ShippingDays'];
        $ShippModelStatusType = $Params['ShippModelStatusType'];
        $ShipTo = $Params['ShipTo'];
        $Currency = $Params['Currency'];
        $ShippingFeeType = $Params['ShippingFeeType'];
        $Lang = $Params['lang'];
        $isGuest = $Params['isGuest'];
        $Type = isset($Params['Type'])?$Params['Type']:0;//用于区分是叠加还是覆盖
        /*判断缓存里有没有产品信息，如果没有，则去接口请求调出来*/
        $ProductInfo = $this->CommonService->ProductInfoByID($ProductID,$SkuID,$Lang,$Currency);//false不使用缓存

        if(!isset($ProductInfo['code']) || $ProductInfo['code'] != 200 || !isset($ProductInfo['data']) || count($ProductInfo['data']) < 1){
            //没有找到数据
            $Return['code'] = 2010002;
            $Return['msg'] = 'product data is error!';
            return $Return;
        }
        $ProductInfo = $ProductInfo['data'];
        sort($ProductInfo['Skus']);
        $_product_price = $ProductInfo['Skus'][0]['SalesPrice'];
        $StoreID = $ProductInfo['StoreID'];//
        $ProductUnit = isset($ProductInfo['SalesUnitType'])?$ProductInfo['SalesUnitType']:'';
        if(isset($ProductInfo['Skus'])){
            $_sku_info = $this->CommonService->processSkuInfo($ProductInfo['Skus'],$SkuID);
        }else{
            $Return['code'] = 2010003;
            $Return['msg'] = 'sku data is error!';
            return $Return;
        }
        $AttrsDesc = isset($_sku_info['attr_desc'])?$_sku_info['attr_desc']:'';
        $CartInfo = $this->redis->get(SHOPPINGCART_.$Uid);
        if(!$CartInfo){
            $CartInfo = array();
        }
        if(isset($CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID]) && !$Type){
            /*如果购物车里存在该产品，则在该产品的基础上加上新购买的数量*/
            $NewQty = $CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID]['Qty']+$Qty;
        }else{
            $NewQty = $Qty;
        }
        //获取阶梯价的单价(区别活动价与普通价)
        if($ProductInfo){
            $_product_price_info = $this->CommonService->getProductPrice($ProductInfo,$SkuID,$NewQty);
            if(isset($_product_price_info['code']) && $_product_price_info['code'] == 1){
                $_product_price = $_product_price_info['product_price'];
            }else{
                return $_product_price_info;
            }
        }

        if(isset($CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID]) && !$Type){
            $CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID]['IsMvp'] = isset($ProductInfo['IsMVP'])?$ProductInfo['IsMVP']:0;
            //店铺名称
            $CartInfo[$Uid]['StoreData'][$StoreID]['StoreInfo']['StoreName'] = isset($ProductInfo['StoreName'])?$ProductInfo['StoreName']:'';
            /*计算所购买的商品量是否大于库存和最大购买量*/
            $CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID]['Currency'] = $Currency;//更新当前币种
            $CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID]['Qty'] = $NewQty;
            $CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID]['ProductPrice'] = $_product_price;
            $CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID]['enable_select_active'] = isset($_product_price_info['enable_select_active'])?$_product_price_info['enable_select_active']:array();
            //20190105 加入购物车初始化运输方式以产品详情页选择的为准
            $CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID]['ShippingMoel'] = $ShippingMoel;
            $CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID]['OldShippingMoel'] = $OldShippingMoel;
        }else{
            /*如果购物车里不存该产品的信息，则把这些新信息添加进购物车里去*/
            $NewGoodsData['StoreID'] = isset($ProductInfo['StoreID'])?$ProductInfo['StoreID']:0;
            $NewGoodsData['StoreName'] = '';
            $NewGoodsData['ProductID'] = $ProductID;
            $NewGoodsData['SkuID'] = $SkuID;
            $NewGoodsData['ProductTitle'] = $ProductInfo['Title'];
            $NewGoodsData['ProductImg'] = isset($ProductInfo['ImageSet']['ProductImg'][0])?$ProductInfo['ImageSet']['ProductImg'][0]:'';
            $NewGoodsData['Qty'] = $Qty;
            $NewGoodsData['Currency'] = $Currency;
            $NewGoodsData['ShippingMoel'] = $ShippingMoel;
            $NewGoodsData['OldShippingMoel'] = $OldShippingMoel;
            $NewGoodsData['ShippingFee'] = $ShippingFee;
            $NewGoodsData['ShippingFeeType'] = $ShippingFeeType;
            $NewGoodsData['ShippingDays'] = $ShippingDays;
            $NewGoodsData['ShippModelStatusType'] = $ShippModelStatusType;
            $NewGoodsData['ShipTo'] = $ShipTo;
            $NewGoodsData['ProductUnit'] = $ProductUnit?$ProductUnit:'piece';
            $NewGoodsData['IsChecked'] = 1;
            $NewGoodsData['CreateOn'] = time();
            $NewGoodsData['AttrsDesc'] = $AttrsDesc;
            $NewGoodsData['IsPutInStorage'] = 0;//是否已入库,0代表未入库,1代表已入库
            $NewGoodsData['Weight'] = isset($ProductInfo['PackingList']['Weight'])?$ProductInfo['PackingList']['Weight']:'';//单个重量
            $NewGoodsData['FirstCategory'] = isset($ProductInfo['FirstCategory'])?$ProductInfo['FirstCategory']:0;
            $NewGoodsData['SecondCategory'] = isset($ProductInfo['SecondCategory'])?$ProductInfo['SecondCategory']:0;
            $NewGoodsData['ThirdCategory'] = isset($ProductInfo['ThirdCategory'])?$ProductInfo['ThirdCategory']:0;
            $NewGoodsData['BrandId'] = isset($ProductInfo['BrandId'])?$ProductInfo['BrandId']:0;
            $NewGoodsData['ProductType'] = isset($ProductInfo['ProductType'])?$ProductInfo['ProductType']:0;
            $NewGoodsData['IsMvp'] = isset($ProductInfo['IsMVP'])?$ProductInfo['IsMVP']:0;
            $NewGoodsData['LinkUrl'] = MALLDOMAIN.'/p/'.$ProductID;
            $NewGoodsData['BrandName'] = $ProductInfo['BrandName'];
            $NewGoodsData['HsCode'] = $ProductInfo['HSCode'];
            $NewGoodsData['IsHasInventory'] = 1;//初始化加入购物车的都是有库存的
            $TmpLWH = isset($ProductInfo['PackingList']['Dimensions'])?$ProductInfo['PackingList']['Dimensions']:null;
            if($TmpLWH){
                //长宽高
                $TmpLWHArr = explode('-',$TmpLWH);
            }
            $NewGoodsData['Length'] = isset($TmpLWHArr[0])?$TmpLWHArr[0]:0;
            $NewGoodsData['Width'] = isset($TmpLWHArr[1])?$TmpLWHArr[1]:0;
            $NewGoodsData['Height'] = isset($TmpLWHArr[2])?$TmpLWHArr[2]:0;
            //获取阶梯价的单价
            if($ProductInfo){
                $_product_price_info = $this->CommonService->getProductPrice($ProductInfo,$SkuID,$Qty);
                if(isset($_product_price_info['code']) && $_product_price_info['code']){
                    $_product_price = $_product_price_info['product_price'];
                }
            }

            $NewGoodsData['ProductPrice'] = $_product_price;
            $NewGoodsData['enable_select_active'] = isset($_product_price_info['enable_select_active'])?$_product_price_info['enable_select_active']:array();;
            $CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID] = $NewGoodsData;
            $CartInfo[$Uid]['StoreData'][$StoreID]['StoreInfo']['StoreName'] = isset($ProductInfo['StoreName'])?$ProductInfo['StoreName']:'';
            $CartInfo[$Uid]['StoreData'][$StoreID]['StoreInfo']['CustomerName'] = $UserName;
            $CartInfo[$Uid]['StoreData'][$StoreID]['StoreInfo']['StoreUrl'] = MYDXINTERNS.'/message/sendMessageSeller/seller_id/'.$ProductInfo['StoreID'];
        }

        /*写入到redis中的购物车*/
        //如果是游客，需要设置购物车过期时间，和设置游客PHPSESSID一致
        if ($isGuest == 1){
            $this->redis->set(SHOPPINGCART_.$Uid,$CartInfo, CACHE_DAY);
        }else{
            $this->redis->set(SHOPPINGCART_.$Uid,$CartInfo);
        }
        $Return['code'] = 1;
        $Return['data'] = 'success';
        return $Return;
    }

    /**
     * 获取coupon
     * @param $Params
     * @return mixed
     */
    public function getCoupon($Params){
        $ReturnData = $this->CouponService->getCoupon($Params);
        return $ReturnData;
    }

    /**
     * 获取购物车信息
     * @param int $Uid 用户ID
     * @param string $Currency 当前币种
     * @return array|boolean
     */
    public function getCartInfo($Uid,$Currency,$Country,$Lang,$GuestId,$CouponCartInfo = null,$UserName){
        //$this->redis->rm(SHOPPINGCART_.$Uid);
        /*1、读取redis里的购物车信息,合并购物车*/
        if(!$Uid){
            //如果用户没有登录的情况下使用游客的身份获取购物车的信息
            if(!$CouponCartInfo){
                $CartInfo = $this->redis->get(SHOPPINGCART_.$GuestId);
            }else{
                $CartInfo[$GuestId] = $CouponCartInfo;
            }
            $Uid = $GuestId;
        }else{
            //在用户已登录的情况下,需合并购物车
            if(!$CouponCartInfo){
                $CartInfo = $this->redis->get(SHOPPINGCART_.$Uid);
            }else{
                $CartInfo[$Uid] = $CouponCartInfo;
            }
            $GuesCartInfo = $this->redis->get(SHOPPINGCART_.$GuestId);
            $CartInfo = $this->CommonService->combineCart($CartInfo,$GuesCartInfo,$Uid,$GuestId);
            $this->redis->rm(SHOPPINGCART_.$GuestId);
        }
//dump($CartInfo);
//die();
        if (empty($CartInfo) || !isset($CartInfo[$Uid]) || !isset($CartInfo[$Uid]['StoreData'])){
            //return ['code'=>3010005, 'msg'=>'Failed to get shopping cart information'];
            return ['code'=>200, 'msg'=>'Shopping cart data is empty.'];
        }
        /*2、遍历购物车里的信息，*/
        $GlobalShipTo = '';
        $IsHasNocNoc = 0;
        if($CartInfo){
            $res = $this->CommonService->processCartProduct($CartInfo,$Currency,$Country,$Lang,$Uid,'cart',$GlobalShipTo,$IsHasNocNoc,$UserName);
            if(isset($res['code']) && $res['code'] != 1){
                return $res;
            }
        }
        Cookie::set('prevCountry',$Country);//用来判断是否切换了国家

        /**
         * 自动使用coupon,2018-07-25先关闭自动应用，后续调好之后再上线
         * $this->CouponService->autoUseCoupon($Uid,$Country,$CartInfo,$Lang,$Currency);
         */
        //取消自动coupon 每次获取购物车数据时都要清空选中的coupon信息
        if(isset($CartInfo[$Uid]['StoreData'])) {
            foreach ($CartInfo[$Uid]['StoreData'] as $k => $v) {
                unset($CartInfo[$Uid]['StoreData'][$k]['isUsedCoupon']);
                foreach ($v['ProductInfo'] as $k1 => $v1){
                    foreach ($v1 as $k2 => $v2){
                        unset($CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k1][$k2]['isUsedCoupon']);
                        //coupon处理，不使用coupon（判断是否使用coupon）
                        if (!config('use_coupon_switch_on')){
                            unset($CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k1][$k2]['coupon']);
                        }
                    }
                }
            }
        }
        //判断有没有NOCNOC的SKU
        $this->redis->set(SHOPPINGCART_.$Uid,$CartInfo);
        //获取系统汇率数据源
        $rate_source = [];
        if(strtoupper($Currency) != DEFAULT_CURRENCY){
            $rate_source = $this->CommonService->getRateDataSource();
        }
        /**
         * 重新组合数据返回给前端
         * 因为前端需要的数据结构跟后端不一样
         * 因此需要在这里将格式转换一下
         */
        $flag = 0;
        $returnData = array();
        $returnData['IsHasNocNoc'] = $IsHasNocNoc;

        if(isset($CartInfo[$Uid]['StoreData'])){
            foreach ($CartInfo[$Uid]['StoreData'] as $k=>$v){
                if(!empty($v['ProductInfo']) && isset($v['ProductInfo'])){
                    foreach ($v['ProductInfo'] as $K2=>$v2){
                        foreach ($v2 as $k3=>$v3){
                            if(isset($v3['isUsedCoupon']['DiscountInfo']['DiscountPrice'])){
                                $TmpPrice = $v3['isUsedCoupon']['DiscountInfo']['DiscountPrice'];
                                $TmpPrice = $this->CommonService->calculateRate(DEFAULT_CURRENCY,$Currency,$TmpPrice,$rate_source);//汇率转换
                                $TmpPrice = sprintf("%.2f", $TmpPrice);
                                $v3['isUsedCoupon']['DiscountInfo']['DiscountPrice'] = $TmpPrice;
                            }
                            if(count($v3) > 0){
                                $flag = 1;
                                $returnData[$Uid][$k]['ProductInfo'][] = $v3;
                            }
                        }
                    }
                }else{
                    unset($CartInfo[$Uid]['StoreData']);
                }
                if($flag){
                    $returnData[$Uid][$k]['Coupon'] = isset($v['coupon'])?$v['coupon']:array();
                    $returnData[$Uid][$k]['StoreInfo'] = $v['StoreInfo'];
                    if(isset($v['isUsedCouponDX'])){
                        if(isset($v['isUsedCouponDX']['DiscountInfo']['DiscountPrice'])){
                            $TmpPrice = $v['isUsedCouponDX']['DiscountInfo']['DiscountPrice'];
                            $TmpPrice = $this->CommonService->calculateRate(DEFAULT_CURRENCY,$Currency,$TmpPrice,$rate_source);//汇率转换
                            $TmpPrice = sprintf("%.2f", $TmpPrice);
                            $v['isUsedCouponDX']['DiscountInfo']['DiscountPrice'] = $TmpPrice;
                        }
                        $returnData[$Uid][$k]['isUsedCouponDX'] = $v['isUsedCouponDX'];
                        $returnData[$Uid][$k]['isUsedCouponDX'] = $v['isUsedCouponDX'];
                    }
                    if(isset($v['isUsedCoupon'])){
                        if(isset($v['isUsedCoupon']['DiscountInfo']['DiscountPrice'])){
                            $TmpPrice = $v['isUsedCoupon']['DiscountInfo']['DiscountPrice'];
                            $TmpPrice = $this->CommonService->calculateRate(DEFAULT_CURRENCY,$Currency,$TmpPrice,$rate_source);//汇率转换
                            $TmpPrice = sprintf("%.2f", $TmpPrice);
                            $v['isUsedCoupon']['DiscountInfo']['DiscountPrice'] = $TmpPrice;
                        }
                        $returnData[$Uid][$k]['isUsedCoupon'] = $v['isUsedCoupon'];
                    }
                }else{
                    unset($returnData[$Uid][$k]);
                }
            }
            if(isset($returnData[$Uid])){
                $returnData['code'] = 1;
                $returnData['data'] = $returnData;
                if(isset($CartInfo[$Uid]['nocdata'])){
                    $returnData['nocdata'] = $CartInfo[$Uid]['nocdata'];
                }
                return $returnData;
            }else{
                /*$returnData['code'] = 3010005;
                $returnData['msg'] = 'Failed to get shopping cart information';
                return $returnData;*/
                return ['code'=>200, 'msg'=>'Shopping cart data is empty.'];
            }
        }else{
            /*$returnData['code'] = 3010005;
            $returnData['msg'] = 'Failed to get shopping cart information';
            return $returnData;*/
            return ['code'=>200, 'msg'=>'Shopping cart data is empty.'];
        }
    }

    /**
     * 从购物车里移除相应的商品
     * @param int $Uid 用户ID
     * @param string $Data 删除的SKU信息组合
     * @return Ambigous <\think\mixed, \think\cache\mixed>|boolean
     */
    public function removeFromCart($Uid,$Data){
        $NocRes = null;
        /*$tmpArr = explode(",",$SkuInfo);
        foreach ($tmpArr as $k=>$v){
            $tmpArr2 = explode("-",$v);
            if(isset($tmpArr2[0])){
                $Data[$k]['ProductId'] = $tmpArr2[0];
            }
            if(isset($tmpArr2[1])){
                $Data[$k]['SkuId'] = $tmpArr2[1];
            }
        }*/
        $flag = 0;
        if(isset($Data)){
            /*1、读取redis里的购物车信息*/
            $CartInfo = $this->redis->get(SHOPPINGCART_.$Uid);
            foreach ($CartInfo[$Uid]['StoreData'] as $Ck=>$Cv){
                foreach ($Cv['ProductInfo'] as $Ck2=>$Cv2){
                    foreach ($Cv2 as $Ck3 => $Cv3){
                        //删除的信息
                        foreach ($Data as $k=>$v){
                            /*从购物车里移除相应的信息*/
                            if($Cv3['ProductID'] == $v['ProductId'] && $Cv3['SkuID'] == $v['SkuId']){
                                $flag = 1;
                                unset($CartInfo[$Uid]['StoreData'][$Ck]['ProductInfo'][$v['ProductId']][$v['SkuId']]);
                                if(count($CartInfo[$Uid]['StoreData'][$Ck]['ProductInfo'][$v['ProductId']]) < 1){
                                    unset($CartInfo[$Uid]['StoreData'][$Ck]['ProductInfo'][$v['ProductId']]);
                                }
                            }
                        }
                    }
                }
                if(isset($CartInfo[$Uid]['StoreData'][$Ck]['ProductInfo']) && count($CartInfo[$Uid]['StoreData'][$Ck]['ProductInfo']) < 1){
                    unset($CartInfo[$Uid]['StoreData'][$Ck]);
                }
            }
            if(count($CartInfo[$Uid]) > 0){
                $this->redis->set(SHOPPINGCART_.$Uid,$CartInfo);

                /** 如果存在noc数据，则需要重新询价，解决删除noc产品，还有其他noc产品时noc运费错误问题  start **/
                $is_have_noc = false;
                //收货国家选择
                $ship_to = cookie('prevCountry');
                foreach ($CartInfo[$Uid]['StoreData'] as $Ck4=>$Cv4) {
                    foreach ($Cv4['ProductInfo'] as $Ck5 => $Cv5) {
                        foreach ($Cv5 as $Ck6 => $Cv6) {
                            if (strtolower($Cv6['ShippingMoel']) == 'nocnoc'){
                                $is_have_noc = true;
                                $ship_to = $Cv6['ShipTo'];
                                break 3;
                            }
                        }
                    }
                }
                if ($is_have_noc){
                    $NocParams['customer_id'] = $Uid;
                    //cart页面不校验taxID
                    //$NocParams['tax_id'] = Cookie::get("nocnoc_tax_id");
                    $NocParams['country'] = $ship_to;
                    $NocRes = $this->NocService->claNocNocData($NocParams,$CartInfo,1);
                    if(!$NocRes){
                        $NocRes = 'noc data is error!';
                    }
                }
                /** 如果存在noc数据，则需要重新询价，解决删除noc产品，还有其他noc产品时noc运费错误问题  end **/
            }else{
                $this->redis->rm(SHOPPINGCART_.$Uid);
            }
        }
        if($flag){
            return ['cart_info'=>$CartInfo, 'noc_data'=>$NocRes];
        }else{
            return false;
        }
    }

    /**
     * 改变购物车中的产品是否去支付,
     * @param int $Uid 用户ID
     * @param array $Params (17|85|233|5|SuperSaver|AF|1,......)
     */
    public function isCheck($Uid,$Params,$ShipTo,$Lang,$Currency){
        $CartInfo = $this->redis->get(SHOPPINGCART_.$Uid);
        if(isset($Params)){
            $flag = false;
            foreach ($Params as $k=>$v){
                if(is_array($v)){
                    if(!isset($v['StoreID'])){
                        $v['StoreID'] = $v['store_id'];
                        $v['ProductID'] = $v['product_id'];
                        $v['SkuID'] = $v['sku_id'];
                        $isCheck = 1;
                        if($v['is_check'] == 2){
                            $isCheck = 0;
                        }
                    }
                    if(isset($CartInfo[$Uid]['StoreData'][$v['StoreID']]['ProductInfo'][$v['ProductID']][$v['SkuID']])){
                        /**如果是选中状态的需要对库存进行判断，如果库存不足，则给前端返回失败，*/
                        if($isCheck){
                            //判断库存是否充足
                            $ProductInfo = $this->CommonService->ProductInfoByID($v['ProductID'],$v['SkuID'],$Lang,$Currency,false);
                            if(isset($ProductInfo['data'])){
                                $ProductInfo = $ProductInfo['data'];
                                if(isset($ProductInfo['Skus'])){
                                    sort($ProductInfo['Skus']);
                                }
                                if(!$ProductInfo){
                                    //如果没有相关的产品信息
                                    $returnData['code'] = 0;
                                    $returnData['msg'] = 'productID:'.$v['ProductID'].' skuID:'.$v['SkuID'].' data is error';
                                    return $returnData;
                                }
                            }else{
                                //如果没有相关的产品信息
                                $returnData['code'] = 0;
                                $returnData['msg'] = 'productID:'.$v['ProductID'].' skuID:'.$v['SkuID'].' data is error';
                                return $returnData;
                            }
                            //获取可供选择的优惠信息和计算价格
                            $_product_price_info = $this->CommonService->getProductPrice($ProductInfo,$v['SkuID'],$v['qty']);
                            if(isset($_product_price_info['code']) && $_product_price_info['code'] && $_product_price_info['code'] == 1){
                            }else{
                                $returnData['code'] = 0;
                                $returnData['msg'] = 'productID:'.$v['ProductID'].' skuID:'.$v['SkuID'].' '.$_product_price_info['msg'];
                                return $returnData;
                            }
                        }


                        $CartInfo[$Uid]['StoreData'][$v['StoreID']]['ProductInfo'][$v['ProductID']][$v['SkuID']]['IsChecked'] = $isCheck;
                        if(isset($CartInfo[$Uid]['StoreData'][$v['StoreID']]['ProductInfo'][$v['ProductID']][$v['SkuID']]['ShippingMoel'])){
                            $ShippingModel = $CartInfo[$Uid]['StoreData'][$v['StoreID']]['ProductInfo'][$v['ProductID']][$v['SkuID']]['ShippingMoel'];
                            if(strtolower($ShippingModel) == 'nocnoc'){
                                $flag = true;
                            }
                        }
                    }
                }
            }
            $ReturnData['code'] = 1;
            //如果选中或者取消选中的SKU中存在NOCNOC的，需要调用NOCNOC处理方法进行处理
            //$flag = 1;//前端需要每一次的点击是否选中都需要带出noc的数据
            if($flag){
                $NocParams['customer_id'] = $Uid;
                //cart页面不校验taxID
                //$NocParams['tax_id'] = Cookie::get("nocnoc_tax_id");
                $NocParams['country'] = $ShipTo;
                $NocRes = $this->NocService->claNocNocData($NocParams,$CartInfo,1);
                if($NocRes){
                    //有NOC数据返回,对NOC数据的处理方法,返回NOCNOC的费用，写进redis的cart
                    //记录下用户的NOC信息
                    //重新写回到redis里,格式为getCartInfo返回的数据格式与code,data平级,命名为nocdata
                    $CartInfo[$Uid]['nocdata'] = $NocRes;
                    $ReturnData['noc_data'] = $NocRes;
                    $ReturnData['code'] = 1;
                }else{
                    //没有NOC数据返回
                    $ReturnData['msg'] = 'noc data is error!';
                    if(!$isCheck){
                        //如果是取消选中的，直接返回给前端1，表示可以取消
                        $ReturnData['code'] = 1;
                    }else{
                        $ReturnData['code'] = 0;
                    }

                }
            }
        }
        $this->redis->set(SHOPPINGCART_.$Uid,$CartInfo);
        return $ReturnData;
    }

    /**
     * 改变ischeck判断coupon是否可用
     * @param $Params
     * @param $Uid
     * @return bool
     */
    public function CouponStatusProcess($Params,$Uid){
        $Currency = cookie('DXGlobalization_currency');
        $StoreId = $Params['store_id'];
        $productId = $Params['product_id'];
        $SkuId = $Params['sku_id'];
        $Qty = $Params['qty'];
        $IsCheck = $Params['is_check'];
        $CartInfo = $this->redis->get(SHOPPINGCART_.$Uid);
        if(isset($CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$productId][$SkuId])){
            $ProductPrice = $CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$productId][$SkuId]['ProductPrice'];
            $ShippModelStatusType = $CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$productId][$SkuId]['ShippModelStatusType'];
        }else{
            //不存在此商品
            return false;
        }
        //订单级别的coupon处理
        if(isset($CartInfo[$Uid]['StoreData'][$StoreId]['coupon'])){
            $rate_source = [];
            if(strtoupper($Currency) != DEFAULT_CURRENCY){
                $rate_source = $this->CommonService->getRateDataSource();
            }
            //需要获取seller下的所有产品总数量和总价格，之后再判断相应规则
            $seller_coupon_all_nums = 0;
            $seller_coupon_all_prices = 0;
            foreach ($CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'] as $k10=>$v10){
                foreach ($v10 as $k11=>$v11){
                    //去除没选中或没有运输方式的数据
                    if ($v11['IsChecked'] != 0 && $v11['ShippModelStatusType'] != 3){
                        $seller_coupon_all_nums += $v11['Qty'];
                        $seller_coupon_all_prices += ($v11['ProductPrice']*$v11['Qty']);
                    }
                }
            }
            $TmpCoupon = $CartInfo[$Uid]['StoreData'][$StoreId]['coupon'];
            //循环这个store的coupon
            foreach ($TmpCoupon as $k=>$v){
                $isUsable = 1;
                if(isset($v['isUsable']) && isset($v['UsableSku'])){
                    $UsableSku = explode(",",$v['UsableSku']);//拿到可以使该coupon的skuid
                    if(in_array($SkuId,$UsableSku)){
                        if(isset($v['PurchaseAmountLimit']['Type']) && $v['PurchaseAmountLimit']['Type'] == 2){
                            //查看金额是否在范围
                            $TempStartPrice = isset($v['PurchaseAmountLimit']['StartPrice']) && is_numeric($v['PurchaseAmountLimit']['StartPrice'])?$v['PurchaseAmountLimit']['StartPrice']:0;
                            $TempEndPrice = isset($v['PurchaseAmountLimit']['EndPrice']) && is_numeric($v['PurchaseAmountLimit']['EndPrice'])?$v['PurchaseAmountLimit']['EndPrice']:9999999999;
                            $TempPrice = $this->CommonService->calculateRate($Currency,DEFAULT_CURRENCY,$seller_coupon_all_prices,$rate_source);
                            if(
                                $TempPrice < $TempStartPrice
                                || $TempPrice > $TempEndPrice
                            ){
                                $isUsable = 0;
                            }
                        }
                        if(isset($v['BuyGoodsNumLimit']['Type']) && $v['BuyGoodsNumLimit']['Type'] == 2){
                            //查看数量是否在范围
                            $TempStartNum = isset($v['BuyGoodsNumLimit']['StartNum']) && is_numeric($v['BuyGoodsNumLimit']['StartNum'])?$v['BuyGoodsNumLimit']['StartNum']:0;
                            $TempEndNum = isset($v['BuyGoodsNumLimit']['EndNum']) && is_numeric($v['BuyGoodsNumLimit']['EndNum'])?$v['BuyGoodsNumLimit']['EndNum']:9999999999;
                            if(
                                $seller_coupon_all_nums < $TempStartNum
                                || $seller_coupon_all_nums > $TempEndNum
                            ){
                                $isUsable = 0;
                            }
                        }
                    }
                }
                $TmpCoupon[$k]['isUsable'] = $isUsable;
            }
            sort($TmpCoupon);
            /** 【为了解决cart页面选择了coupon而后又去掉之后进去checkout页面仍然使用了coupon的问题】如果coupon数据isUsable==0，如果cart存在使用该coupon的情况，需要将cart使用的coupon数据删除. start **/
            $is_update_cart = false;
            foreach ($TmpCoupon as $k1=>$v1){
                if ($v1['isUsable'] == 0){
                    if (isset($CartInfo[$Uid]['StoreData'][$StoreId]['isUsedCoupon'])){
                        $is_update_cart = true;
                        unset($CartInfo[$Uid]['StoreData'][$StoreId]['isUsedCoupon']);
                    }
                }
            }
            if ($is_update_cart){
                $this->redis->set(SHOPPINGCART_.$Uid, $CartInfo);
            }
            /** 【为了解决cart页面选择了coupon而后又去掉之后进去checkout页面仍然使用了coupon的问题】如果coupon数据isUsable==0，如果cart存在使用该coupon的情况，需要将cart使用的coupon数据删除. end **/
            return $TmpCoupon;
        }else{
            //出错
            return false;
        }
    }

    /**
     * 修改购物车里产品的数量
     * @param int $ProductID 产品ID
     * @param int $SkuID
     * @param int $Qty 购买数量
     * @return boolean
     * (待处理：数量变化达到阶梯价时的处理，数量变化对运费的影响)
     */
    public function changeProductNums($Params){
        $Qty = $Params['Qty'];
        $SkuID = $Params['SkuID'];
        $ProductID = $Params['ProductID'];
        $StoreID = $Params['StoreID'];
        $Lang = $Params['Lang'];
        $Currency = $Params['Currency'];
        $ShipTo = $Params['ShipTo'];
        $ShipModel = $Params['ShipModel'];
        $Uid = $Params['Uid'];
        $CartInfo = $this->redis->get(SHOPPINGCART_.$Uid);
        $ProductPrice = 0;
        $BuyNow = Cookie::get('buynow');//用于判断是否是buynow
        /*1,获取该产品的信息*/
        $Data = $this->CommonService->ProductInfoByID($ProductID,$SkuID,$Lang,$Currency,false);
        //dump($Data);
        /*2、判断库存，最大购买数量*/
        $CheckFlag = 0;
        $flag = 1;
        $msg = '';
        //获取系统汇率数据源
        $rate_source = [];
        if(strtoupper($Currency) != DEFAULT_CURRENCY){
            $rate_source = $this->CommonService->getRateDataSource();
        }
        if($Data['code'] == 200){
            $ProductInfo = $Data['data'];
            $_product_price_res = $this->CommonService->getProductPrice($ProductInfo,$SkuID,$Qty);
            if(!isset($_product_price_res['code']) || $_product_price_res['code'] != 1){
                //错误直接返回
                $_product_price_res['flag'] = 4020002;
                $_prev_product_nums = isset($CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID]['Qty'])?$CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID]['Qty']:1;
                $_product_price_res['product_nums'] = $_prev_product_nums;
                $_product_price_res['msg'] = 'Sorry, the quantity you purchased exceeds the stock!';
                return $_product_price_res;
            }
            $_product_price = $_product_price_res['product_price'];//现价
            //转换汇率
            $_product_price = $this->CommonService->calculateRate(DEFAULT_CURRENCY,$Currency,$_product_price,$rate_source);
            $_product_price = sprintf("%.2f",$_product_price);
        }else{
            $_return_data['sku_id'] = $SkuID;
            $_return_data['flag'] =10003; //3010002;
            //$_return_data['msg'] = lang('tips_3010002'); //"product data is error!";
            $_return_data['msg'] = 'This item does not exist'; //"product data is error!";
            $_return_data;
        }
        $OldNums = $CartInfo;
        $shipping_free = 0;
        $ShippingFee = 0;//0代表free shipping 1代表free shipping 24H 2代表正常收费()
        $params['spu'] = $ProductID;
        $params['count'] = $Qty;
        $params += [
            'lang' => $Lang,//当前语种
            'currency' => $Currency,//当前币种
            'country' => $ShipTo
        ];
        //$ShippingInfo = $this->CommonService->countProductShipping($params,$this->productService);
        $ShippingInfo = $this->ProductService->countProductShipping($params);

        //$ShippInfo = $this->CommonService->getInexpensiveShip($ShippingInfo);
        $ShippInfo = null;
        if(!empty($ShippingInfo)){
            //产品数量增加，如果选中的运输方式依然存在则继续使用选择的，不存在则默认使用其他运输方式
            $ShippingChoseKey = 0;
            foreach ($ShippingInfo as $spk=>$spv){
                if (strtolower($spv['ShippingService']) == strtolower($Params['ShipModel'])){
                    $ShippingChoseKey = $spk;
                    break;
                }
            }
            $ShippInfo = $ShippingInfo[$ShippingChoseKey];
        }
        $ShippingDays = '';
        $NocData = null;
        $ShippModelStatusType = 1; //运输方式状态：1-正常的运输方式，2-运输方式发生了改变，3-运输费方式是NOCNOC且发生了错误
        $ShippModelChangeMsg = '';
        if(isset($ShippInfo) && !empty($ShippInfo)){
//			foreach ($ShippInfo as $k=>$v){
//				if($ShipModel == $v['ShippingService']){
            $v = $ShippInfo;
            $shipping_free = $v['Cost'];
            $ShippingFee = $v['ShippingFee'];
            $ShippingDays = $v['EstimatedDeliveryTime'];
            $ShipModel = $v['ShippingService'];
            if($Params['ShipModel'] != $ShipModel){
                $ShippModelStatusType = 2;
                //$ShippModelChangeMsg = lang('tips_3060004');
                $ShippModelChangeMsg = 'The shipping method has been changed due to the change of your shipping address.';
            }
            $CheckFlag = 1;
            if(isset($CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID])){
                //修改产品数量正确，更新redis里的购物车信息(数量，运费，运输时间)
                $CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID]['Qty'] = $Qty;
                $CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID]['ShippingDays'] = $v['EstimatedDeliveryTime'];
                $CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID]['ShippingFee'] = $shipping_free;
                $CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID]['ShippingMoel'] = isset($v['ShippingService'])?$v['ShippingService']:'';

                //如果存在NOCNOC的，需要处理
                if(strtolower($ShipModel) == 'nocnoc'){
                    //cart页面去掉NOC标识，2018-08-17确定cart页面不计算noc运费
                    /*$ShippingFee = 3;
                    $shipping_free = lang('nocnoc_tips');*/
                    // Cookie::get("nocnoc_tax_id"),怎么判断带不带taxId去获取NOCNOC的数据
                    $NocParams['customer_id'] = $Uid;
                    $params['tax_id'] = Cookie::get("nocnoc_tax_id");
                    $NocParams['country'] = $ShipTo;
                    $NocRes = $this->NocService->claNocNocData($NocParams,$CartInfo,1);
                    if($NocRes){
                        //有NOC数据返回,对NOC数据的处理方法,返回NOCNOC的费用，写进redis的cart
                        //记录下用户的NOC信息
                        //重新写回到redis里,格式为getCartInfo返回的数据格式与code,data平级,命名为nocdata
                        $CartInfo[$Uid]['nocdata'] = $NocRes;
                        $NocData = $NocRes;
                    }else{
                        $CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$Params['ProductID']][$SkuID]['ShippModelStatusType'] = 3;//选择了NOCNOC,但NOCNOC返回了错误
                        //没有NOC数据返回

                        Monlog::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,$params,'https://sandbox.nocnocgroup.com/api/order/quote',$NocParams);
                        //$msg = lang('payment_try_again'); //"NOCNOC IS ERROR!";
                        $msg = 'System Error,try again later'; //"NOCNOC IS ERROR!";
                    }
                }

                if(
                    isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'],'checkout')
                    && $BuyNow
                ){
                    $this->redis->set(SHOPPINGCART_BUYNOW_.$Uid,$CartInfo);
                }else{
                    $this->redis->set(SHOPPINGCART_.$Uid,$CartInfo);
                }
            }else{
                $flag = 10003; //3010002;//产品信息出错
                $msg = 'This item does not exist';
            }
//					break;
//				}
//			}
            /**
            if(!$CheckFlag){
            //自动寻找一种最便宜的运输方式给这个sku
            $InexpensiveShip = $this->CommonService->getInexpensiveShip($ShippInfo);
            //如果是NOCNOC的需要特殊处理
            if(strtolower($InexpensiveShip['ShippingService']) == 'nocnoc'){
            $IsHasNocNoc = 1;
            }
            if(isset($InexpensiveShip['ShippingService']) && strtolower($InexpensiveShip['ShippingService']) == 'nocnoc'){
            $CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID]['ShippingFee'] = lang('nocnoc_tips');
            }else{
            $CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID]['ShippingFee'] = isset($InexpensiveShip['Cost'])?$InexpensiveShip['Cost']:0;
            }
            $CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID]['Qty'] = $Qty;
            $CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID]['ShippModelStatusType'] = 2;//表示选中的物流方式不可以用，但可以选择其它的
            $CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID]['ShippingFeeType'] = isset($InexpensiveShip['ShippingFee'])?$InexpensiveShip['ShippingFee']:0;
            $CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID]['ShippingDays'] = isset($InexpensiveShip['EstimatedDeliveryTime'])?$InexpensiveShip['EstimatedDeliveryTime'].' days':'shipping error';
            $CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID]['ShippingMoel'] = isset($InexpensiveShip['ShippingService'])?$InexpensiveShip['ShippingService']:'';
            $shipping_free = $shipping_free;
            $ShippingFee = isset($InexpensiveShip['ShippingFee'])?$InexpensiveShip['ShippingFee']:0;
            $ShipModel = isset($InexpensiveShip['ShippingService'])?$InexpensiveShip['ShippingService']:'';
            $ShippingDays = isset($InexpensiveShip['EstimatedDeliveryTime'])?$InexpensiveShip['EstimatedDeliveryTime'].' days':'shipping error';
            if(strpos($_SERVER['HTTP_REFERER'],'checkout') && $BuyNow){
            $this->redis->set(SHOPPINGCART_BUYNOW_.$Uid,$CartInfo);
            }else{
            $this->redis->set(SHOPPINGCART_.$Uid,$CartInfo);
            }
            }
             */
        }else{
            //数据返回出错
            $flag = 4020001; //TODO 这里要给出明确的业务提示编码 2010001
            $msg = 'Sorry, the logistics method you selected is not available.';
        }
        //以下是对coupon的重新计算
        //对coupon的重新过滤(从购物车里获取coupon,根据传过来的条件一一比对)
        //拿到价格
        if(isset($CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID])){
            $ProductPrice = $CartInfo[$Uid]['StoreData'][$StoreID]['ProductInfo'][$ProductID][$SkuID]['ProductPrice'];
        }else{
            //商品不存在
            $flag = 3010002;
            $msg = 'This item does not exist';
        }
        //coupon处理
        $TmpCoupon = array();
        $this->CouponService->changeProductNumsUseCoupon($TmpCoupon,$CartInfo,$StoreID,$ProductID,$SkuID,$Uid,$Currency);

        //根据购物车的信息，编历所有信息，把有NOC信息的seller都要组装好发送给NOC API接口
        $ReturnData = array(
            'flag' => $flag,
            'product_price' => $_product_price,
            'enable_select_active' => $_product_price_res['enable_select_active'],
            'type' => $_product_price_res['type'],
            'type_id' => $_product_price_res['type_id'],
            'shipping_free' => $shipping_free,
            'ShippingFee' => $ShippingFee,
            'shipping_model' => $ShipModel,
            'ShippingDays' => $ShippingDays,
            'ShippModelStatusType' => $ShippModelStatusType,
            'ShippModelChangeMsg' => $ShippModelChangeMsg,
            'coupon' => $TmpCoupon,
            'msg' => $msg,
            'noc_data' => $NocData
        );
        return $ReturnData;
    }

    /**
     * 切换运送方式()
     * @param string $ShipTo 发往地
     * @param string $ShipModel 运送方式
     * @param int $StoreID 商家ID
     * @return multitype:number unknown |boolean
     */
    public function changeShipModel($Params){
        $StoreId = $Params['StoreId'];
        $ShipModel = $Params['ShipModel'];
        $SkuId = $Params['SkuId'];
        $Uid = $Params['Uid'];
        $From = $Params['From']; //来源：1-cart，2-checkout
        $ReturnData = array();
        $params['spu'] = $Params['ProductID'];
        $params['count'] = $Params['Qty'];
        $params += [
            'lang' => $Params['Lang'] ,//当前语种
            'currency' => $Params['Currency'],//当前币种
            'country' => $Params['ShipTo']
        ];
        $BuyNow = Cookie::get('buynow');//用于判断是否是buynow
        if(
            isset($_SERVER['HTTP_REFERER'])
            && strpos($_SERVER['HTTP_REFERER'],'checkout')
            && $BuyNow
        ){
            $CartInfo = $this->redis->get(SHOPPINGCART_BUYNOW_.$Uid);
        }else{
            if ($From == 1){
                $CartInfo = $this->redis->get(SHOPPINGCART_.$Uid);
            }else{
                $CartInfo = $this->redis->get(SHOPPINGCART_CHECKOUT_.$Uid);
            }
        }
        if(!isset($CartInfo[$Uid]['StoreData'][$StoreId])){
            //出错返回
            $ReturnData['code'] = 0;
            $ReturnData['msg'] = "Cart Data Is Error";
            return $ReturnData;
        }
        $OldShipModel = $CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$Params['ProductID']][$SkuId]['ShippingMoel'];
        //$ShippInfo = $this->CommonService->countProductShipping($params,$this->productService);
        $ShippInfo = $this->ProductService->countProductShipping($params);
        if(is_array($ShippInfo)){
            foreach ($ShippInfo as $k=>$v){
                if($ShipModel == $v['ShippingService']){
                    //把运输方式更新进redis的购物车里去
                    if(isset($CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$Params['ProductID']][$SkuId])){
                        $CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$Params['ProductID']][$SkuId]['ShippingMoel'] = $v['ShippingService'];
                        $CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$Params['ProductID']][$SkuId]['OldShippingMoel'] = isset($v['OldShippingService'])?$v['OldShippingService']:'';
                        $CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$Params['ProductID']][$SkuId]['ShippingFee'] = is_numeric($v['Cost'])?$v['Cost']:0;
                        if(strtolower($ShipModel) == 'nocnoc'){
                            //cart页面去掉NOC标识，2018-08-17确定cart页面不计算noc运费
                            //如果是NOCNOC，把ShippingFeeType置为3告诉前端
                            /*$CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$Params['ProductID']][$SkuId]['ShippingFeeType'] = 3;
                            $CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$Params['ProductID']][$SkuId]['ShippingFee'] = lang('nocnoc_tips');*/
                        }else{
                            //$CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$Params['ProductID']][$SkuId]['ShippingFeeType'] = $v['ShippingFee'];
                        }

                        $CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$Params['ProductID']][$SkuId]['ShippingFeeType'] = $v['ShippingFee'];

                        $CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$Params['ProductID']][$SkuId]['ShippingDays'] = $v['EstimatedDeliveryTime'];
                        if(!is_numeric($v['Cost'])){
                            $v['Cost'] = 0;
                        }
                        //重新放入redis
                        if(
                            isset($_SERVER['HTTP_REFERER'])
                            && strpos($_SERVER['HTTP_REFERER'],'checkout')
                            && $BuyNow
                        ){
                            $this->redis->set(SHOPPINGCART_BUYNOW_.$Uid,$CartInfo);
                        }else{
                            if ($From == 1){
                                $this->redis->set(SHOPPINGCART_.$Uid,$CartInfo);
                            }else{
                                $this->redis->set(SHOPPINGCART_CHECKOUT_.$Uid,$CartInfo);
                            }
                        }
                    }else{
                        //购物车信息出错
                        $ReturnData = array();
                        $ReturnData['code'] = 0;
                        $ReturnData['msg'] = "CART DATA ERROR!";
                        return $ReturnData;
                    }
                    $ReturnData = array();
                    $ReturnData['code'] = 1;
                    $ReturnData['data'] = $v;
                    break;
                    return $ReturnData;
                }else{
                    //没有这种配送方式，
//                    $ReturnData = array();
//					$ReturnData['code'] = 0;
//					$ReturnData['msg'] = "This ShipModel is Error!!";
//                    return $ReturnData;
                }
            }

            #######NOCNOC处理_START########################################################################
            ////cart页面去掉NOC标识，2018-08-17确定cart页面不计算noc运费
            //通过seller下的所有sku 来判断是否有nocnoc来判断，如果有一个，则是nocnoc
            if ($From != 1){
                $is_have_nocnoc = false;
                /*foreach ($CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'] as $pk=>$pv){
                    foreach ($pv as $k10=>$v10){
                        if (strtolower($v10['ShippingMoel']) == 'nocnoc'){
                            $is_have_nocnoc = true;
                            break;
                        }
                    }
                }*/
                //因为可能初始化non询价会出错，且有多个noc情况下，切换其中一个成功，但实际上另外一个nocnoc仍然是失败，所以判断是否是nocnoc，需要判断素有的checkout数据
                foreach ($CartInfo[$Uid]['StoreData'] as $k10=>$v10){
                    foreach ($v10['ProductInfo'] as $k11=>$v11){
                        foreach ($v11 as $k12=>$v12){
                            if (strtolower($v12['ShippingMoel']) == 'nocnoc'){
                                $is_have_nocnoc = true;
                                break;
                            }
                        }
                    }
                }
                if(
                    strtolower($ShipModel) == 'nocnoc'
                    //|| strtolower($OldShipModel) == 'nocnoc'
                    || $is_have_nocnoc
                ){
                    //调用Noc处理方法,需要处理(处理流程？？？)
                    // Cookie::get("nocnoc_tax_id"),怎么判断带不带taxId去获取NOCNOC的数据
                    if(strpos($_SERVER['HTTP_REFERER'],'checkout') && $BuyNow){
                        $CartInfo = $this->redis->get(SHOPPINGCART_BUYNOW_.$Uid);
                    }else{
                        if ($From == 1){
                            $CartInfo = $this->redis->get(SHOPPINGCART_.$Uid);
                        }else{
                            $CartInfo = $this->redis->get(SHOPPINGCART_CHECKOUT_.$Uid);
                        }
                    }
                    $NocParams['customer_id'] = $Uid;
                    $NocParams['tax_id'] = 0;
                    if ($From == 2){ ////来源：1-cart，2-checkout（需要传入taxid）
                        $NocParams['tax_id'] = cookie('nocnoc_tax_id')?cookie('nocnoc_tax_id'):0;
                    }
                    $NocParams['store_id'] = $StoreId;
                    $NocParams['country'] = $Params['ShipTo'];
                    $NocRes = $this->NocService->claNocNocData($NocParams,$CartInfo, $From);
                    if($NocRes){
                        //有NOC数据返回,对NOC数据的处理方法,返回NOCNOC的费用，写进redis的cart
                        //记录下用户的NOC信息
                        //重新写回到redis里,格式为getCartInfo返回的数据格式与code,data平级,命名为nocdata
                        $CartInfo[$Uid]['nocdata'] = $NocRes;
                        if(strpos($_SERVER['HTTP_REFERER'],'checkout') && $BuyNow){
                            $this->redis->set(SHOPPINGCART_BUYNOW_.$Uid,$CartInfo);
                        }else{
                            if ($From == 1){
                                $this->redis->set(SHOPPINGCART_.$Uid,$CartInfo);
                            }else{
                                $this->redis->set(SHOPPINGCART_CHECKOUT_.$Uid,$CartInfo);
                            }
                        }
                        $ReturnData['nocdata'] = $NocRes;
                        if(strtolower($ShipModel) == 'nocnoc'){
                            $ReturnData['data']['Cost'] = 0;
                            $ReturnData['data']['ShippingFee'] = 3;//告诉前端这是NOCNOC
                        }
                    }else{
                        if(strtolower($ShipModel) == 'nocnoc'){
                            $ReturnData['code'] = 0;
                            $ReturnData['data']['Cost'] = 0;
                            $ReturnData['data']['ShippModelStatusType'] = 3;//选择了NOCNOC,但NOCNOC返回了错误
                            $ReturnData['msg'] = "NOCNOC IS ERROR!";
                            $CartInfo[$Uid]['StoreData'][$StoreId]['ProductInfo'][$Params['ProductID']][$SkuId]['ShippModelStatusType'] = 3;//选择了NOCNOC,但NOCNOC返回了错误
                            if(strpos($_SERVER['HTTP_REFERER'],'checkout') && $BuyNow){
                                $this->redis->set(SHOPPINGCART_BUYNOW_.$Uid,$CartInfo);
                            }else{
                                if ($From == 1){
                                    $this->redis->set(SHOPPINGCART_.$Uid,$CartInfo);
                                }else{
                                    $this->redis->set(SHOPPINGCART_CHECKOUT_.$Uid,$CartInfo);
                                }
                            }
                        }else{
                            //没有NOC数据返回
                            $ReturnData['code'] = 0;
                            $ReturnData['msg'] = "NOCNOC IS ERROR!";
                            return $ReturnData;
                        }

                    }
                }
            }
            #######NOCNOC处理_END#######################################################################################
        }else{
            //数据返回出错
            $ReturnData = array();
            $ReturnData['code'] = 0;
            $ReturnData['msg'] = "API RETURN ERROR!";
            return $ReturnData;
        }
        return $ReturnData;
    }

    /**
     * @param $Uid
     * @param $Params
     * @return bool
     */
    public function goToCheckOut($Uid,$Params){
        $CartInfo = $this->redis->get(SHOPPINGCART_.$Uid);
        if($CartInfo[$Uid]){
            foreach ($CartInfo[$Uid]['StoreData'] as $k => $v){
                foreach ($v['ProductInfo'] as $k1=>$v1){
                    foreach ($v1 as $k2=>$v2){
                        $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k1][$k2]['IsBuy'] = 0;
                    }
                }
            }
        }
        //暂时不更新购物车，因为下单成功后会移除购物车数据
        if(isset($Params)){
            $shipTo = isset($Params['ShippToCountry'])?$Params['ShippToCountry']:'';
            foreach ($Params as $k=>$v){
                if(is_array($v)){
                    if(!isset($v['StoreID'])){
                        $v['StoreID'] = $v['store_id'];
                        $v['ProductID'] = $v['product_id'];
                        $v['SkuID'] = $v['sku_id'];
                        $v['IsChecked'] = $v['is_check'];
                    }
                    if(isset($CartInfo[$Uid]['StoreData'][$v['StoreID']]['ProductInfo'][$v['ProductID']][$v['SkuID']])){
                        //判断库存
                        $CartInfo[$Uid]['StoreData'][$v['StoreID']]['ProductInfo'][$v['ProductID']][$v['SkuID']]['IsChecked'] = 1;
                        $CartInfo[$Uid]['StoreData'][$v['StoreID']]['ProductInfo'][$v['ProductID']][$v['SkuID']]['IsBuy'] = 1;
                        if($shipTo){
                            $CartInfo[$Uid]['StoreData'][$v['StoreID']]['ProductInfo'][$v['ProductID']][$v['SkuID']]['ShipTo'] = $shipTo;
                        }
                    }
                }
            }
        }
        if($CartInfo[$Uid]){
            foreach ($CartInfo[$Uid]['StoreData'] as $k => $v){
                if(empty($v['ProductInfo']) || !isset($v['ProductInfo'])){
                    unset($CartInfo[$Uid]['StoreData'][$k]);
                }else{
                    foreach ($v['ProductInfo'] as $k1=>$v1){
                        foreach ($v1 as $k2=>$v2){
                            if($CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k1][$k2]['IsBuy'] == 0){
                                unset($CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k1][$k2]);
                            }
                            //如果SKU全部移除，那SPU也得删掉
                            if(empty($CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k1])){
                                unset($CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k1]);
                            }
                        }
                    }
                }
            }
            foreach ($CartInfo[$Uid]['StoreData'] as $k => $v){
                if(empty($v['ProductInfo']) || !isset($v['ProductInfo'])){
                    unset($CartInfo[$Uid]['StoreData'][$k]);
                }
            }
        }
        $this->redis->set(SHOPPINGCART_CHECKOUT_.$Uid,$CartInfo);
        return true;
    }
}
