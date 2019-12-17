<?php
namespace app\app\services;

use app\app\model\AdvertisingModel;
use app\common\helpers\CommonLib;
use think\Cache;
use app\app\model\CommonModel;
use think\Cookie;
use think\Log;
use think\Monlog;
use think\Session;

/**
 * 基础配置数据
 */
class CommonService extends  BaseService
{
    private static $enableSelectActive = array('1'=>'使用活动价','10'=>'使用优惠券','20'=>'使用批发价','0'=>'不使用优惠');

    const PRODUCT_STATUS_REVIEWING = 0;  //待审核（草稿）
    const PRODUCT_STATUS_SUCCESS = 1;  //已开通（正常销售）
    const PRODUCT_STATUS_PRESALE = 2;  //预售
    const PRODUCT_STATUS_STOP_PRESALE = 3;  //暂时停售
    const PRODUCT_STATUS_DOWN = 4;  //已下架
    const PRODUCT_STATUS_SUCCESS_UPDATE = 5;  //正常销售，编辑状态
    const PRODUCT_STATUS_DELETE = 10;  //已删除
    const PRODUCT_STATUS_REJECT = 12;    //审核失败

    /**
     * @param $params
     * @return \app\app\model\data
     */
    public function saveCustomerFilter($params){
       $ids  = $this-> gtCategoryArray($params['BlackCategoryIds']);
       if(!$ids){
           return false;
       }
       unset($params['BlackCategoryIds']);//移除字符字段
       $params[]['BlackCategoryIds'] = $ids;//加入数组字段
       return (new CommonModel())->saveCustomerFilter($params);
    }

    /**
     * 把字符串用-号隔开的一键过滤品类数据拆分数组
     * @param $blackCategoryIds
     * @return array
     */
    private function gtCategoryArray($blackCategoryIds){
        $result = array();
        if(empty($blackCategoryIds))
            return $result;
        $ids = explode('-',$blackCategoryIds); // explode(',', $str);
        for ($i =0;$i<count($ids);$i++){
            $tempID = (int)$ids[$i];
            if($tempID >0){
                $result[] = $tempID;
            }
        }
       return $result;
    }

    /**
     * 查询用户一键过滤数据
     * @param $customerID CIC ID
     * @return 数据
     */
    public function getCustomerFilter($customerID){
        return (new CommonModel())->getCustomerFilter($customerID);
    }

    /**
     * 根据产品ID获取产品信息
     * @param int productID
     * @return array
     */
    public function productInfoByID($ProductId,$SkuId,$Lang='',$Currency = null,$IsCache = true){
        /*首选判断缓存里是否有数据，如果没有则请求接口*/
        if($IsCache){
            $ProductInfo = $this->redis->get("Cart_Checkout_Product_".$ProductId.$SkuId.$Lang);
            /**清除相关key的方法,
             * $keys = Redis::keys('Cart_Checkout_Product_*')
             * Redis::del($keys);
             */
        }else{
            $ProductInfo = null;
        }
        if(!$ProductInfo){
            $Data['product_id'] = $ProductId;
            $Data['sku_id'] = $SkuId;
            if($Lang){
                $Data['lang'] = $Lang;
            }
            $Url = MALL_API."/mall/product/getProduct";
            /*请求接口写日志*/
            $ProductInfo = doCurl($Url,$Data,null,true);
            if(isset($ProductInfo['code']) && $ProductInfo['code'] == 200 && count($ProductInfo['data']) > 0){
                /**如果该SKU是处于活动状态的，拿到活动的到期时间，并以此时间作为SKU的缓存时间*/
                $expire_time = 360;
                if(isset($data['IsActivity']) && !empty($data['IsActivity'])){
                    $expire_time = $data['ActivityEndTime'] -$data['ActivityStartTime'];
                }
                $this->redis->set("Cart_Checkout_Product_".$ProductId.$SkuId.$Lang,json_encode($ProductInfo),$expire_time);
            }
        }else{
            $ProductInfo = json_decode($ProductInfo,true);
        }
        return $ProductInfo;
    }

    /**
     * 处理SKU信息
     * @param unknown $_sku_info
     * @param unknown $sku_id
     * @return
     */
    public function processSkuInfo($_sku_info,$sku_id){
        $_return_data = array();
        $_attr_desc = array();
        if(is_array($_sku_info)){
            foreach ($_sku_info as $k=>$v){
                if(isset($v['_id']) && $v['_id'] == $sku_id){
                    if(isset($v['SalesAttrs'])){
                        foreach ($v['SalesAttrs'] as $k1=>$v1){
                            if(isset($v1['Image']) && $v1['Image']){
                                $_attr_desc[] = $v1['Name'].':<img src='.IMG_DXCDN.$v1['Image'].'>';
                            }else{
                                if(isset($v1['CustomValue']) && $v1['CustomValue']){
                                    $_attr_desc[] = $v1['Name'].':'.$v1['CustomValue'];
                                }else{
                                    if(isset($v1['DefaultValue']) && $v1['DefaultValue']){
                                        $_attr_desc[] = $v1['Name'].':'.$v1['DefaultValue'];
                                    }else{
                                        $_attr_desc[] = $v1['Name'].':'.$v1['Value'];
                                    }

                                }
                            }

                        }
                    }

                }

            }
        }
        //$_attr_desc = mb_substr($_attr_desc,0,-1,'utf8');
        $_return_data['attr_desc'] = $_attr_desc;

        return $_return_data;
    }

    /**
     * 计算价格(是自动计算最低价还是固定计算哪种价格，还是什么都不计算，让用户自己选？)
     * (批发价，活动价，coupon价，原价)
     * 2018-05-12:1.有活动用活动;2.没活动，如果有Coupon且多个，取优先级最高的应用，其他的在列表选择;3如果SKU购买量大于等于起批量,则选择批发价
     * 缓存SKU的活动信息，缓存SKU的counpon信息,起批量信息在产品信息缓存里找?
     * @param array $_sku_info
     * @param int $sku_id
     * @param int $_nums
     * @return skuprice,sku 选中的优惠方式
     * 注：sku具体可选的优惠方式，前端点击的时候异步加载
     */
    public function getProductPrice($_product_info,$sku_id,$_nums){
        $_product_price = 0;
        $_return_data['code'] = 1;
        $_enable_select_active['0'] = self::$enableSelectActive['0'];
        $_type = 0;
        $_type_text = '';
        $_type_id = 0;
        /**取消对活动判断，只认IsActivity字段
        isset($_product_info['Skus'][0]['ActivityInfo']['ActivityStartTime']) &&
        isset($_product_info['Skus'][0]['ActivityInfo']['ActivityEndTime']) &&
        $_product_info['Skus'][0]['ActivityInfo']['ActivityStartTime'] < time() &&
        $_product_info['Skus'][0]['ActivityInfo']['ActivityEndTime'] > time()
         */
        if(isset($_product_info['IsActivity']) && $_product_info['IsActivity']){
            sort($_product_info['Skus']);
            //这是有参与活动的
            //先判断活动的SKU是否已用完,如果在活动有效期内，但活动库存不足的，提示库存不足
            if(!isset($_product_info['Skus'][0]['ActivityInfo']['SalesLimit']) || $_product_info['Skus'][0]['ActivityInfo']['SalesLimit'] < $_nums){
                $_return_data['sku_id'] = $sku_id;
                $_return_data['code'] = 3060001;
                //$_msg = lang('tips_3060001').' '.$_product_info['Skus'][0]['ActivityInfo']['SalesLimit'].' '.$_product_info['SalesUnitType'];
                if(isset($_product_info['Skus'][0]['ActivityInfo']['SalesLimit'])){
                    $_msg = 'Purchases are limited to '.$_product_info['Skus'][0]['ActivityInfo']['SalesLimit'].' '.$_product_info['SalesUnitType'];
                }else{
                    $_msg = 'Activity data error!';
                }
                $_return_data['msg'] = $_msg;  //"Active Limit!"; 提示格式："Purchases are limited to 177 pieces"
                return $_return_data;
            }
            if(isset($_product_info['Skus'][0]['ActivityInfo']['DiscountPrice'])){
                //这里获取的是活动价
                $_product_price = $_product_info['Skus'][0]['ActivityInfo']['DiscountPrice'];
                $_enable_select_active['1'] = self::$enableSelectActive['1'];
                $_type_text = 'active';//活动价
                $_type = 2;
                $_type_id = isset($_product_info['Skus'][0]['ActivityInfo']['ActivityId'])?$_product_info['Skus'][0]['ActivityInfo']['ActivityId']:0;
            }else{
                //数据出错
                $_return_data['sku_id'] = $sku_id;
                $_return_data['code'] = 3060002;
                //$_return_data['msg'] = lang('tips_3060002');
                $_return_data['msg'] = 'Sorry,activity data error!';
                return $_return_data;
            }
        }else{
            //这是没有参与活动的,则计算阶梯价
            $_sku_info = isset($_product_info['Skus'])?$_product_info['Skus']:'';
            if(is_array($_sku_info)){
                foreach ($_sku_info as $k=>$v){
                    if(isset($v['_id']) && $v['_id'] == $sku_id){
                        //库存判断，如果库存不足，需提示库存不足
                        if($v['Inventory'] < $_nums){
                            $_return_data['sku_id'] = $sku_id;
                            $_return_data['code'] = 3060003;
                            //$_return_data['msg'] = lang('tips_3060003'); //"Inventory Limit!";
                            $_return_data['msg'] = 'Sorry,the quantity you purchased exceeds the stock!'; //"Inventory Limit!";
                            return $_return_data;
                            break;
                        }else{
                            $_product_price = $v['SalesPrice'];
                            $_enable_select_active['20'] = '';
                            $_type_text = 'nomarl';
                            $_type = 1;
                        }
                        if(isset($v['BulkRateSet']['Batches']) && $_nums >= $v['BulkRateSet']['Batches']){
                            //如果购买的数量大于或等于了起批量，则使用批发价
                            $_product_price = $v['BulkRateSet']['SalesPrice']?$v['BulkRateSet']['SalesPrice']:0;
                            $_enable_select_active['20'] = self::$enableSelectActive['20'];
                            $_type_text = 'bulkrate';//批发价
                            $_type = 1;
                        }
                    }
                }
            }
        }
        $_return_data = array();
        $_return_data['code'] = 1;
        $_return_data['product_price'] = $_product_price;
        $_return_data['enable_select_active'] = $_enable_select_active;
        $_return_data['type_text'] = $_type_text;
        $_return_data['type'] = $_type;
        $_return_data['type_id'] = $_type_id;

        return $_return_data;
    }

    /**
     * 根据购物车信息过滤coupon
     * @param unknown $CartInfo
     * @param unknown $Coupon
     */
    public function filtrationCouponByCart($CartInfo,$Coupon,$CountryCode,$Currency){
        $SkuIdArr = array();
        $SkuCateArr = array();//需要另开一个可以同时查询多个SKU信息的接口
        $SkuBrandArr = array();//需要另开一个可以同时查询多个SKU信息的接口
        $RequestParams = array();
        if(!isset($CartInfo['StoreData'])){
            return null;
        }
        //获取系统汇率数据源
        $rate_source = [];
        if(strtoupper($Currency) != DEFAULT_CURRENCY){
            $rate_source = $this->getRateDataSource();
        }
        foreach ($CartInfo['StoreData'] as $k=>$v){
            if(isset($v['ProductInfo'])){
                foreach ($v['ProductInfo'] as $k1=>$v1){
                    if(is_array($v1)){
                        foreach ($v1 as $k2=>$v2){
                            //去掉没有产品的数据
                            if(
                            !isset($v2['ProductID'])
                            ){
                                continue;
                            }
                            $TmpArr['product_id'] = $v2['ProductID'];
                            $TmpArr['sku_id'] = $v2['SkuID'];
                            $TmpArr['store_id'] = $v2['StoreID'];
                            //把购买数量，购买金额(购物车的金额也是当前币种)加到数组里去，作为该coupon是否能用的依据之一
                            $TmpArr['qty'] = $v2['Qty'];
                            $TmpArr['product_price'] = $v2['ProductPrice'];
                            $TmpArr['first_category'] = isset($v2['FirstCategory'])?$v2['FirstCategory']:0;
                            $TmpArr['second_category'] = isset($v2['SecondCategory'])?$v2['SecondCategory']:0;
                            $TmpArr['third_category'] = isset($v2['ThirdCategory'])?$v2['ThirdCategory']:0;
                            $TmpArr['brand_id'] = isset($v2['BrandId'])?$v2['BrandId']:0;
                            $TmpArr['product_type'] = isset($v2['ProductType'])?$v2['ProductType']:0;
                            $TmpArr['is_checked'] = isset($v2['IsChecked'])?$v2['IsChecked']:0;
                            $TmpArr['shipp_model_status_type'] = isset($v2['ShippModelStatusType'])?$v2['ShippModelStatusType']:3;

                            $RequestParams[] = $TmpArr;
                            $SkuIdArr[] = $TmpArr;
                        }
                    }
                }
            }
        }
        $OrderCanUseCoupon = array();
        $SkuCanUseCoupon = array();
        foreach ($Coupon as $k=>$v){
            //如果是有规则的(制定限制规则)，要把他们过滤出来
            if(isset($v['CouponRuleSetting']['CouponRuleType']) && $v['CouponRuleSetting']['CouponRuleType'] == 2){
                //获取SKU信息,与规则相匹配
                if(isset($v['CouponRuleSetting']['LimitData']['Data'])){
                    $LimitData = explode(",",$v['CouponRuleSetting']['LimitData']['Data']);
                    if(isset($v['CouponRuleSetting']['LimitData']['LimitType'])){
                        switch ($v['CouponRuleSetting']['LimitData']['LimitType']){
                            case 1:
                                //指定商品
                                if(isset($v['CouponRuleSetting']['LimitData']['IsReverse']) && $v['CouponRuleSetting']['LimitData']['IsReverse'] == 1){
                                    //取反
                                    $couponMatchRes = array();
                                    foreach ($SkuIdArr as $k1 => $v1){
                                        if(!in_array($v1['sku_id'],$LimitData)){
                                            $couponMatchRes = $this->couponMatch($v,$v1,$CartInfo,$OrderCanUseCoupon,$SkuCanUseCoupon);
                                        }
                                    }
                                }else{
                                    //不取反
                                    $couponMatchRes = array();
                                    foreach ($SkuIdArr as $k1 => $v1){
                                        if(in_array($v1['sku_id'],$LimitData)){
                                            $couponMatchRes = $this->couponMatch($v,$v1,$CartInfo,$OrderCanUseCoupon,$SkuCanUseCoupon);
                                        }
                                    }
                                }
                                break;
                            case 2:
                                //指定分类
                                if(isset($v['CouponRuleSetting']['LimitData']['IsReverse']) && $v['CouponRuleSetting']['LimitData']['IsReverse'] == 2){
                                    //取反
                                    $couponMatchRes = array();
                                    foreach ($SkuIdArr as $k1 => $v1){
                                        //if(!in_array($v1,$LimitData)){
                                        $couponMatchRes = $this->couponMatch($v,$v1,$CartInfo,$OrderCanUseCoupon,$SkuCanUseCoupon);
                                        //}
                                    }
                                }else{
                                    //不取反
                                    $couponMatchRes = array();
                                    foreach ($SkuIdArr as $k1 => $v1){
                                        //if(in_array($v1,$LimitData)){
                                        $couponMatchRes = $this->couponMatch($v,$v1,$CartInfo,$OrderCanUseCoupon,$SkuCanUseCoupon);
                                        //}
                                    }
                                }
                                break;
                            case 3:
                                //指定品牌
                                if(isset($v['CouponRuleSetting']['LimitData']['IsReverse']) && $v['CouponRuleSetting']['LimitData']['IsReverse'] == 3){
                                    //取反
                                    $couponMatchRes = array();
                                    foreach ($SkuIdArr as $k1 => $v1){
                                        if(!in_array($v1['brand_id'],$LimitData)){
                                            $couponMatchRes = $this->couponMatch($v,$v1,$CartInfo,$OrderCanUseCoupon,$SkuCanUseCoupon);
                                        }
                                    }
                                }else{
                                    //不取反
                                    $couponMatchRes = array();
                                    foreach ($SkuIdArr as $k1 => $v1){
                                        if(in_array($v1['brand_id'],$LimitData)){
                                            $couponMatchRes = $this->couponMatch($v,$v1,$CartInfo,$OrderCanUseCoupon,$SkuCanUseCoupon);
                                        }
                                    }
                                }
                                break;
                            case 4:
                                //指定产品类型
                                if(isset($v['CouponRuleSetting']['LimitData']['IsReverse']) && $v['CouponRuleSetting']['LimitData']['IsReverse'] == 4){
                                    //取反
                                    $couponMatchRes = array();
                                    foreach ($SkuIdArr as $k1 => $v1){
                                        if(!in_array($v1['product_type'],$LimitData)){
                                            $couponMatchRes = $this->couponMatch($v,$v1,$CartInfo,$OrderCanUseCoupon,$SkuCanUseCoupon);
                                        }
                                    }
                                }else{
                                    //不取反
                                    $couponMatchRes = array();
                                    foreach ($SkuIdArr as $k1 => $v1){
                                        if(in_array($v1['product_type'],$LimitData)){
                                            $couponMatchRes = $this->couponMatch($v,$v1,$CartInfo,$OrderCanUseCoupon,$SkuCanUseCoupon);
                                        }
                                    }
                                }
                                break;
                            case 5:
                                //指定国家
                                if(isset($v['CouponRuleSetting']['LimitData']['IsReverse']) && $v['CouponRuleSetting']['LimitData']['IsReverse'] == 5){
                                    //取反
                                    $couponMatchRes = array();
                                    foreach ($SkuIdArr as $k1 => $v1){
                                        if(!in_array($CountryCode,$LimitData)){
                                            $couponMatchRes = $this->couponMatch($v,$v1,$CartInfo,$OrderCanUseCoupon,$SkuCanUseCoupon);
                                        }
                                    }
                                }else{
                                    //不取反
                                    $couponMatchRes = array();
                                    foreach ($SkuIdArr as $k1 => $v1){
                                        if(in_array($CountryCode,$LimitData)){
                                            $couponMatchRes = $this->couponMatch($v,$v1,$CartInfo,$OrderCanUseCoupon,$SkuCanUseCoupon);
                                        }
                                    }
                                }
                                break;
                        }
                    }
                }
            }else if(isset($v['CouponRuleSetting']['CouponRuleType']) && $v['CouponRuleSetting']['CouponRuleType'] == 1){
                //这些是没有规则的(但也需要过滤金额与sku,qty等信息)(全店铺使用)
                $TmpArrSeller = array();
                //初始化
                foreach ($SkuIdArr as $kk1 => $vv1){
                    $StoreId = $vv1['store_id'];
                    $TmpArrSeller[$StoreId]['AllQty'] = 0;
                    $TmpArrSeller[$StoreId]['AllPrice'] = 0;
                }
                foreach ($SkuIdArr as $kk => $vv){
                    $StoreId = $vv['store_id'];
                    //去掉没有选中的、没有运输方式的数据
                    if ($vv['is_checked'] != 0 && $vv['shipp_model_status_type'] != 3){
                        $TmpArrSeller[$StoreId]['AllQty'] = (int)$TmpArrSeller[$StoreId]['AllQty'] + $vv['qty'];
                        $TmpArrSeller[$StoreId]['AllPrice'] = (int)$TmpArrSeller[$StoreId]['AllPrice']+ ($vv['qty']*$vv['product_price']);
                    }
                }
                foreach ($SkuIdArr as $k1 => $v1){
                    //把coupon_id关联到sku上面
                    if($v1['store_id'] == $v['SellerId']){
                        $v['isUsable'] = 1;
                        if($v['DiscountLevel'] == 1){
                            //单品级别优惠
                            //对金额和数量的判断
                            if(isset($v['BuyGoodsNumLimit']['Type']) && $v['BuyGoodsNumLimit']['Type'] == 2){
                                //商品数量限制
                                if(isset($CartInfo['StoreData'][$v1['store_id']]['ProductInfo'][$v1['product_id']][$v1['sku_id']]['Qty'])){
                                    $TmpQty = $CartInfo['StoreData'][$v1['store_id']]['ProductInfo'][$v1['product_id']][$v1['sku_id']]['Qty'];
                                    $TempStartNum = is_numeric($v['BuyGoodsNumLimit']['StartNum']) && !empty($v['BuyGoodsNumLimit']['StartNum'])?$v['BuyGoodsNumLimit']['StartNum']:0;
                                    $TempEndNum = is_numeric($v['BuyGoodsNumLimit']['EndNum']) && !empty($v['BuyGoodsNumLimit']['EndNum'])?$v['BuyGoodsNumLimit']['EndNum']:999999999;
                                    if( $TmpQty < $TempStartNum || $TmpQty > $TempEndNum){
                                        $v['isUsable'] = 0;
                                    }
                                }
                            }
                            if(isset($v['PurchaseAmountLimit']['Type']) && $v['PurchaseAmountLimit']['Type'] == 2){
                                //金额的限制
                                $TmpQty = $CartInfo['StoreData'][$v1['store_id']]['ProductInfo'][$v1['product_id']][$v1['sku_id']]['Qty'];
                                $ProductPrice = $CartInfo['StoreData'][$v1['store_id']]['ProductInfo'][$v1['product_id']][$v1['sku_id']]['ProductPrice'];
                                $TmpPrice = $TmpQty*$ProductPrice;
                                //汇率转换
                                $TmpPrice = $this->calculateRate($Currency,DEFAULT_CURRENCY,$TmpPrice,$rate_source);//汇率转换
                                /*if( $TmpPrice < $v['PurchaseAmountLimit']['StartPrice'] || $TmpPrice > $v['PurchaseAmountLimit']['EndPrice']){
                                    $v['isUsable'] = 0;
                                }*/
                                //限量区间处理，为了避免只输入一个区间的情况
                                $TemStartPrice = is_numeric($v['PurchaseAmountLimit']['StartPrice']) && !empty($v['PurchaseAmountLimit']['StartPrice'])?$v['PurchaseAmountLimit']['StartPrice']:0;
                                $TemEndPrice = is_numeric($v['PurchaseAmountLimit']['EndPrice']) && !empty($v['PurchaseAmountLimit']['EndPrice'])?$v['PurchaseAmountLimit']['EndPrice']:999999999;
                                if( $TmpPrice < $TemStartPrice || $TmpPrice > $TemEndPrice){
                                    $v['isUsable'] = 0;
                                }
                            }
                            //去掉没有选中的、没有运输方式的数据
                            if ($v1['is_checked'] == 0 || $v1['shipp_model_status_type'] == 3){
                                $v['isUsable'] = 0;
                            }
                            //把符合条件的sku都加进来
                            $v['UsableSku'] = $v1['sku_id'].',';
                            $v['UsableSku'] = substr($v['UsableSku'],0,-1);

                            $SkuCanUseCouponTmp[$v1['sku_id']][$v['CouponId']] = $v;
                            $CartInfo['StoreData'][$v1['store_id']]['ProductInfo'][$v1['product_id']][$v1['sku_id']]['coupon'][$v['CouponId']] = $v;
                        }else{
                            //订单级别的TODO
                            if(isset($v['BuyGoodsNumLimit']['Type']) && $v['BuyGoodsNumLimit']['Type'] == 2){
                                $TempStartNum = is_numeric($v['BuyGoodsNumLimit']['StartNum']) && !empty($v['BuyGoodsNumLimit']['StartNum'])?$v['BuyGoodsNumLimit']['StartNum']:0;
                                $TempEndNum = is_numeric($v['BuyGoodsNumLimit']['EndNum']) && !empty($v['BuyGoodsNumLimit']['EndNum'])?$v['BuyGoodsNumLimit']['EndNum']:999999999;
                                if( $TmpArrSeller[$v1['store_id']]['AllQty'] < $TempStartNum || $TmpArrSeller[$v1['store_id']]['AllQty'] > $TempEndNum){
                                    $v['isUsable'] = 0;
                                }
                            }
                            if(isset($v['PurchaseAmountLimit']['Type']) && $v['PurchaseAmountLimit']['Type'] == 2){
                                //汇率转换
                                $TmpPrice = $this->calculateRate($Currency,DEFAULT_CURRENCY,$TmpArrSeller[$v1['store_id']]['AllPrice'],$rate_source);//汇率转换
                                //限量区间处理，为了避免只输入一个区间的情况
                                $TemStartPrice = is_numeric($v['PurchaseAmountLimit']['StartPrice']) && !empty($v['PurchaseAmountLimit']['StartPrice'])?$v['PurchaseAmountLimit']['StartPrice']:0;
                                $TemEndPrice = is_numeric($v['PurchaseAmountLimit']['EndPrice']) && !empty($v['PurchaseAmountLimit']['EndPrice'])?$v['PurchaseAmountLimit']['EndPrice']:999999999;
                                if( $TmpPrice < $TemStartPrice || $TmpPrice > $TemEndPrice){
                                    $v['isUsable'] = 0;
                                }
                            }
                            //把符合条件的sku都加进来
                            if (isset($v['UsableSku'])){
                                $v['UsableSku'] .= $v1['sku_id'].',';
                            }else{
                                $v['UsableSku'] = $v1['sku_id'].',';
                            }
                            //$v['UsableSku'] = substr($v['UsableSku'],0,-1);

                            $OrderCanUseCouponTmp[$v1['store_id']][$v['CouponId']] = $v;
                            $CartInfo['StoreData'][$v1['store_id']]['coupon'][$v['CouponId']] = $v;
                        }
                    }
                }
                if(isset($OrderCanUseCouponTmp)){
                    //如果是订单级别的coupon，则加到订单级别里去
                    $OrderCanUseCoupon = $OrderCanUseCouponTmp;
                }
                if(isset($SkuCanUseCouponTmp)){
                    //如果是sku级别的coupon,则加到sku里去
                    $SkuCanUseCoupon = $SkuCanUseCouponTmp;
                }
            }
        }
        $ReturnData['SkuCanUseCoupon'] = $SkuCanUseCoupon;
        $ReturnData['SellerCanUseCoupon'] = $OrderCanUseCoupon;
        $ReturnData['CartInfo'] = $CartInfo;

        if(isset($SkuCanUseCoupon) || isset($OrderCanUseCoupon) || isset($CartInfo)){
            return $ReturnData;
        }else {
            return null;
        }
    }


    /**
     * @param bool $is_Cache 获取汇率系统的数据
     * @return bool|mixed|null
     */
    public function getRateDataSource($is_Cache = true){
        if($is_Cache){
            $RateInfo = $this->redis->get("DX_CURRENCY");
        }else{
            $RateInfo = null;
        }
        if($RateInfo){
            $RateInfo = json_decode($RateInfo,true);
        }
        if(!$RateInfo){
            /*去接口获取汇率*/
            $Url = MALL_API."/share/currency/getExchangeRate";
            $RateInfo = doCurl($Url,'',null,true);
            if(isset($RateInfo['code']) && $RateInfo['code'] == 200 && isset($RateInfo['data']) && count($RateInfo['data']) > 0){
                $this->redis->set("DX_CURRENCY",json_encode($RateInfo['data']),config('rate_expire_time'));
                $RateInfo = $RateInfo['data'];
            }else{
                //获取出错了，
                Log::record('the rate system is error!'.json_encode($RateInfo),'error');
                return false;
            }
        }
        return $RateInfo;
    }

    /**
     * 计算一种货币切换成另一种货币的金额
     * @param string $From
     * @param string $To
     * @param number $money
     * @param number $rate
     * @return number
     */
    public function calculateRate($From,$To,$money,$rate){
        $result = $money;//转换失败或者没有对应的币种，则原值返回
        if(empty($From) || empty($To) || empty($money) || empty($rate)){
            return $result;
        }
        if($From == $To){
            return $result;
        }
        foreach ($rate as $k=>$v){
            if(isset($v['From'])){
                if ($v['From'] == $From && $v['To'] == $To) {
                    if(strtoupper($From) === DEFAULT_CURRENCY){
                        $result = $money * $v['Rate'];
                    }else{
                        $result = $money / $v['Rate'];
                    }
                }elseif ($v['From'] == $To && $v['To'] == $From){
                    if(strtoupper($To) === DEFAULT_CURRENCY){
                        $result = $money / $v['Rate'];
                    }
                }
            }
        }
        return $result;
    }

    /**
     * 匹配coupon
     * @param unknown $v
     * @param unknown $v1
     * @param unknown $CartInfo
     * @param unknown $OrderCanUseCoupon
     * @param unknown $SkuCanUseCoupon
     */
    public function couponMatch($v,$v1,&$CartInfo,&$OrderCanUseCoupon,&$SkuCanUseCoupon){
        //根据Qty,Price判断该coupon是否可用
        if($v1['is_checked'] && $v1['shipp_model_status_type'] != 3){
            //如果是选中的，需要对数量进行判断
            $v['isUsable'] = 1;
            if(isset($v['BuyGoodsNumLimit']['Type']) && $v['BuyGoodsNumLimit']['Type'] == 2){
                //对于购买数量的判断
                if(isset($v['BuyGoodsNumLimit']['StartNum']) && $v['BuyGoodsNumLimit']['StartNum'] > 0 && $v['BuyGoodsNumLimit']['StartNum'] < $v1['qty']){
                    //不能用
                    $v['isUsable'] = 0;
                }
                if(isset($v['BuyGoodsNumLimit']['EndNum']) && $v['BuyGoodsNumLimit']['EndNum'] > 0 && $v['BuyGoodsNumLimit']['EndNum'] > $v1['qty']){
                    //不能用
                    $v['isUsable'] = 0;
                }
            }
            if(isset($v['PurchaseAmountLimit']['Type']) && $v['PurchaseAmountLimit']['Type'] == 2){
                //对于购买金额的判断
                if(isset($v['PurchaseAmountLimit']['StartPrice']) && $v['PurchaseAmountLimit']['StartPrice'] > 0 && $v['PurchaseAmountLimit']['StartPrice'] < ($v1['qty']*$v1['product_price'])){
                    //不能用
                    $v['isUsable'] = 0;
                }
                if(isset($v['PurchaseAmountLimit']['EndPrice']) && $v['PurchaseAmountLimit']['EndPrice'] > 0 && $v['PurchaseAmountLimit']['EndPrice'] > ($v1['qty']*$v1['product_price'])){
                    //不能用
                    $v['isUsable'] = 0;
                }
            }
        }else{
            //如果没有选中，直接置为不可用状态
            $v['isUsable'] = 0;
        }
        if($v['DiscountLevel'] == 1){
            //单品级别优惠,把coupon_id关联到sku上面
            $SkuCanUseCoupon[$v1['sku_id']][$v['CouponId']] = $v;
            $CartInfo['StoreData'][$v1['store_id']]['ProductInfo'][$v1['product_id']][$v1['sku_id']]['coupon'][$v['CouponId']] = $v;
        }else{
            //订单级别的
            if($v1['store_id'] == $v['SellerId']){
                $OrderCanUseCoupon[$v1['store_id']][$v['CouponId']] = $v;
                //把符合条件的sku都加进来
                $v['UsableSku'] = $v1['sku_id'].',';
                $v['UsableSku'] = substr($v['UsableSku'],0,-1);
                //订单级别的需要计算哪个是最优惠的
                $CartInfo['StoreData'][$v1['store_id']]['coupon'][$v['CouponId']] = $v;
            }
        }
    }

    /**
     * 合并购物车
     * @param unknown $CartInfo
     * @param unknown $GuesCartInfo
     */
    public function combineCart($CartInfo,$GuesCartInfo,$Uid,$GuestId,$type='cart'){
        if(is_array($GuesCartInfo)){
            if(isset($GuesCartInfo[$GuestId]['StoreData'])){
                foreach ($GuesCartInfo[$GuestId]['StoreData'] as $k=>$v){
                    if(is_array($v)){
                        foreach ($v['ProductInfo'] as $k1=>$v1){
                            if(is_array($v1)){
                                foreach ($v1 as $k2=>$v2){
                                    if(isset($CartInfo[$Uid]['StoreData'][$v2['StoreID']]['ProductInfo'][$v2['ProductID']][$v2['SkuID']])){
                                        //如果在登录用户里有该SKU信息
                                        if(isset($CartInfo[$Uid]['StoreData'][$v2['StoreID']]['ProductInfo'][$v2['ProductID']][$v2['SkuID']]['Qty'])){
                                            $OldQty = $CartInfo[$Uid]['StoreData'][$v2['StoreID']]['ProductInfo'][$v2['ProductID']][$v2['SkuID']]['Qty'];
                                            $CartInfo[$Uid]['StoreData'][$v2['StoreID']]['ProductInfo'][$v2['ProductID']][$v2['SkuID']]['Qty'] = $OldQty+$v2['Qty'];
                                            $CartInfo[$Uid]['StoreData'][$v2['StoreID']]['ProductInfo'][$v2['ProductID']][$v2['SkuID']]['IsChecked'] = 1;
                                            if($type == 'checkout'){
                                                $CartInfo[$Uid]['StoreData'][$v2['StoreID']]['ProductInfo'][$v2['ProductID']][$v2['SkuID']]['IsBuy'] = 1;
                                            }
                                        }
                                    }else{
                                        if(isset($CartInfo[$Uid]['StoreData'][$v2['StoreID']]['ProductInfo'][$v2['ProductID']])){
                                            //如果在登录用户里有该productId信息的
                                            $CartInfo[$Uid]['StoreData'][$v2['StoreID']]['ProductInfo'][$v2['ProductID']][$v2['SkuID']] = $v2;
                                        }else{
                                            if(isset($CartInfo[$Uid]['StoreData'][$v2['StoreID']]['ProductInfo'])){
                                                //如果在登录用户里有该店铺的商品
                                                $CartInfo[$Uid]['StoreData'][$v2['StoreID']]['ProductInfo'][$v2['ProductID']] = $v1;
                                            }else{
                                                //对登录用户来说是新的店铺，新的productId,新的skuId
                                                $CartInfo[$Uid]['StoreData'][$v2['StoreID']] = $v;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }

                }
            }
        }
        return $CartInfo;
    }

    /**
     * 处理购物车里的信息
     * @param $CartInfo
     * @param $Currency
     * @param $Country
     * @param $Lang
     * @param $Uid
     * @param $type
     * @param $GlobalShipTo
     * 区分出cart与checkout所带出来的商品是有区别的
     * cart:没有任何条件
     * checkout:IsBuy=1,IsCheck=1,ShippModelStatusType=3
     */
    public function processCartProduct(&$CartInfo,$Currency,$Country,$Lang,$Uid,$type,&$GlobalShipTo,&$IsHasNocNoc,$UserName){
        $prevCountry = Cookie::get('prevCountry');//用来判断是否切换了国家
        $prevCurrency = Cookie::get('prevCurrency');//用来判断是否切换了币种
        $shiptoCountry = Cookie::get('DXGlobalization_shiptocountry');
        Cookie::set('prevCurrency',$Currency);
        if(!isset($CartInfo[$Uid]['StoreData']) || !is_array($CartInfo[$Uid]['StoreData'])){
            $Return['code'] = 3010005;
            $Return['msg'] = 'Failed to get shopping cart information';
            return $Return;
        }
        //获取系统汇率数据源
        $rate_source = $this->getRateDataSource();
        $IsHasNocNoc = 0;
        foreach($CartInfo[$Uid]['StoreData'] as $k=>$v){
            $CartInfo[$Uid]['StoreData'][$k]['StoreInfo']['CustomerName'] = $UserName;
            $storeFlag = 0;
            if(isset($v['ProductInfo'])){
                foreach ($v['ProductInfo'] as $k2 =>$v2){
                    foreach ($v2 as $kk=>$vv){
                        //如果有优惠券，需要把优惠的价格转换成当前汇率
                        $ProductID = $k2;
                        $SkuID = $kk;
                        $Qty = isset($vv['Qty'])?$vv['Qty']:0;
                        $ProductInfo = $this->ProductInfoByID($ProductID,$SkuID,$Lang,$Currency); //["IsActivity"] => int(289)
                        //dump($ProductInfo);
                        //die();
                        //20190103 更新产品平级的币种
                        $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['Currency'] = $Currency;
                        $ProductInfo = $ProductInfo['data'];
                        /**把SKU信息出错的的置为无库存*/
                        if(!isset($ProductInfo['Skus'])){
                            $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['IsChecked'] = 0;
                            $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['IsHasInventory'] = 0;
                            $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['ErrMessage'] = 'Goods is error';
                            continue;
                        }else{
                            $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['IsHasInventory'] = 1;
                            $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['ErrMessage'] = '';
                        }
                        $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['ProductTitle'] = isset($ProductInfo['Title'])?$ProductInfo['Title']:$CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['ProductTitle'];
                        /** 把状态不在销售状态的置为无库存（因为会存在价格不显示问题，所以将这个逻辑移到下面处理）*/
                        /*if(
                            !isset($ProductInfo['ProductStatus'])
                            || ($ProductInfo['ProductStatus'] != self::PRODUCT_STATUS_SUCCESS && $ProductInfo['ProductStatus'] != self::PRODUCT_STATUS_SUCCESS_UPDATE)
                        ){
                            $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['IsChecked'] = 0;
                            $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['IsHasInventory'] = 0;
                            $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['ErrMessage'] = 'Goods have been removed';
                            continue;
                        }else{
                            $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['IsHasInventory'] = 1;
                            $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['ErrMessage'] = '';
                        }*/
                        sort($ProductInfo['Skus']);
                        $_product_price = $ProductInfo['Skus'][0]['SalesPrice'];
                        $GlobalShipTo = isset($vv['ShipTo'])?$vv['ShipTo']:$Country;
                        $params['spu'] = $ProductID;
                        $params['count'] = $Qty;
                        $params += [
                            'lang' => $Lang ,//当前语种
                            'currency' => $Currency,//当前币种
                            'country' => $Country
                        ];
                        //获取运费信息
                        $startTime = microtime(true);
                        /**
                         * 如果shipTo或currency切换了，需要重新计算运费
                         * 因为运费只返回当前币种的金额，没有返回美元币种的金额，
                         * 而我们的汇率系统里并没有$currency向USD切换的条件
                         */
                        /** ========== 去掉运输方式计算，用异步加载的形式 ========== **/
                        /*if($prevCountry != $Country || $prevCurrency != $Currency || $shiptoCountry != $Country){
                            $ShippingInfo = $this->countProductShipping($params,new ProductService());
                        }*/
                        $endTime = microtime(true);

                        $slowTime = config('slow_api_time')?config('slow_api_time'):100;//慢API时间(单位时间为毫秒)
                        $useTime = ($endTime-$startTime)*1000;//毫秒
                        if($useTime > $slowTime){
                            //记录日志(待定),格式：主调方($from)，被调方($url)，花费时间($useTime)
                            $log = '=FunctionName:countProductShipping=UseTime:'.$useTime;
                            \think\Log::pathlog('APIRequest',$log,'FunctionRequest.log');
                        }
                        //判断该SKU是使用了活动价还是批发价
                        if($ProductInfo){
                            //获取可供选择的优惠信息和计算价格
                            $_product_price_info = $this->getProductPrice($ProductInfo,$SkuID,$Qty);
                            //print_r($_product_price_info);
                            //标记该sku使用了批发价还是活动价还是coupon
                            if(isset($_product_price_info['code']) && $_product_price_info['code'] == 1){
                                $_product_price = $_product_price_info['product_price'];//现价
                                //转换汇率
                                $_product_price = $this->calculateRate(DEFAULT_CURRENCY,$Currency,$_product_price,$rate_source);
                                if($_product_price !== 0 && !$_product_price){
                                    $Return['code'] = 3020005;
                                    $Return['msg'] = 'the rate is error!';
                                    return $Return;
                                }
                                $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['active_type'] = $_product_price_info['type'];//active或bulkrate
                                $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['active_type_text'] = $_product_price_info['type_text'];//active或bulkrate
                                $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['type_id'] = $_product_price_info['type_id'];//如果是活动的话，这个是活动ID
                            }else{
                                //库存不足的处理
                                $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['IsChecked'] = 0;
                                $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['IsHasInventory'] = 0;//库存不足
                                $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['ErrMessage'] = $_product_price_info['msg'];
                            }
                        }
                        /*3、替换掉购物车里原来相对应的值(产品价格，运费，配送时间)*/
                        if(isset($ProductInfo['Skus'])){
                            sort($ProductInfo['Skus']);
                            $storeFlag = 1;
                            //获取的可供用户选择的优惠选项
                            $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['enable_select_active'] = isset($_product_price_info['enable_select_active'])?$_product_price_info['enable_select_active']:array();;
                            /*拿到价格，以美元兑换当前cookies里币种*/
                            $money = 0;
                            if(isset($ProductInfo['Skus'][0]['SalesPrice'])){
                                $money = $ProductInfo['Skus'][0]['SalesPrice'];//原价
                            }else{
                                //价格出错，把该商品的IsChecked置为0，并给出提示语？
                                $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['IsChecked'] = 0;//库存不足
                                $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['ErrMessage'] = 'the price is error ';
                            }
                            $CurrencyMoney = $this->calculateRate(DEFAULT_CURRENCY,$Currency,$money,$rate_source);
                            if($_product_price !== 0 && !$CurrencyMoney){
                                $Return['code'] = 3020005;
                                $Return['msg'] = 'the rate is error!';
                                return $Return;
                            }
                            //原价
                            $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['OldProductPrice'] = sprintf("%.2f",$CurrencyMoney);
                            //更新价格和加入购物车的时候
                            //计算后的价格,如果有活动价或者起批价的就用活动价或者起批价，否则用产品正常售价
                            $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['ProductPrice'] = $_product_price?sprintf("%.2f",$_product_price):sprintf("%.2f",$CurrencyMoney);
                            $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['CreateOn'] = time();
                            //如果这个产品有发往这个国家的物流方式
                            /**
                             * 如果shipTo或currency切换了，需要重新计算运费
                             * 因为运费只返回当前币种的金额，没有返回美元币种的金额，
                             * 而我们的汇率系统里并没有$currency向USD切换的条件
                             */
                            if($prevCountry != $Country || $prevCurrency != $Currency || $shiptoCountry != $Country){
                                //if($vv['IsChecked'] == 1 || strtolower($vv['ShippingMoel']) == 'nocnoc'){
                                /** ========== 去掉运输方式计算，用异步加载的形式 ========== **/
                                /*if(isset($ShippingInfo) && count($ShippingInfo) > 0 ){
                                    foreach ($ShippingInfo as $k3=>$v3){
                                        //更新运送方式和运费信息
//                                            if($v3['ShippingService'] == $vv['ShippingMoel']){
//                                                //如果有用户选中的运输方式
//                                                if(isset($v3['ShippingService']) && strtolower($v3['ShippingService']) == 'nocnoc'){
//                                                    if($vv['IsChecked'] == 1){
//                                                        $IsHasNocNoc = 1;
//                                                    }
//                                                    $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['ShippingFeeType'] = 3;
//                                                    $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['ShippingFee'] = lang('nocnoc_tips');
//                                                }else{
//                                                    $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['ShippingFee'] = isset($v3['Cost'])?$v3['Cost']:0;
//                                                    $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['ShippingFeeType'] = $vv['ShippingFeeType'];
//                                                }
//                                                $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['ShippingMoel'] = $v3['ShippingService'];
//                                                $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['ShippingDays'] = $v3['EstimatedDeliveryTime'];
//                                                $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['ShippModelStatusType'] = 1;//表示选中的物流方式是可以用的
//                                                break;
                                        //}else{
                                            //如果没有选中的运输方式
                                            //自动寻找一种最便宜的运输方式给这个sku
                                            $InexpensiveShip = $this->getInexpensiveShip($ShippingInfo);
                                            //如果是NOCNOC的需要特殊处理
                                            if(strtolower($InexpensiveShip['ShippingService']) == 'nocnoc'){
                                                $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['ShippingFeeType'] = 3;
                                                if($vv['IsChecked'] == 1){
                                                    $IsHasNocNoc = 1;
                                                }
                                            }
                                            if(isset($InexpensiveShip['ShippingService']) && strtolower($InexpensiveShip['ShippingService']) == 'nocnoc'){
                                                $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['ShippingFeeType'] = 3;
                                                $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['ShippingFee'] = lang('nocnoc_tips');
                                            }else{
                                                $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['ShippingFee'] = isset($InexpensiveShip['Cost'])?$InexpensiveShip['Cost']:0;
                                                $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['ShippingFeeType'] = isset($InexpensiveShip['ShippingFee'])?$InexpensiveShip['ShippingFee']:0;
                                            }
                                            if($InexpensiveShip['ShippingService'] == $vv['ShippingMoel']){
                                                $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['ShippModelStatusType'] = 1;
                                            }else{
                                                $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['ShippModelStatusType'] = 2;//表示选中的物流方式不可以用，但可以选择其它的
                                            }
                                            $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['ShippingDays'] = isset($InexpensiveShip['EstimatedDeliveryTime'])?$InexpensiveShip['EstimatedDeliveryTime']:'shipping error';
                                            $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['ShippingMoel'] = isset($InexpensiveShip['ShippingService'])?$InexpensiveShip['ShippingService']:'';
                                        //}
                                    }
                                }else{
                                    //如果没有这个产品有发往这个国家的物流方式
                                    $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['ShippModelStatusType'] = 3;//表示没有物流方式支付到达这个国家
                                    $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['ShippingMoel'] = '';
                                }*/
                                //}
                            }else{
                                if(strtolower($vv['ShippingMoel']) == 'nocnoc'){
                                    $IsHasNocNoc = 1;
                                }
                                if($CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['ShippModelStatusType'] == 2){
                                    $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['ShippModelStatusType'] = 1;
                                }
                                /**
                                 * if($prevCurrency != $Currency && $vv['ShippingFee'] > 0 && $Currency != 'USD'){
                                //币种切换了，重新计算运费
                                $CurrencyShippingFee = $this->calculateRate($prevCurrency,$Currency,$vv['ShippingFee']);
                                //原价
                                $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['ShippingFee'] = sprintf("%.2f",$CurrencyShippingFee);
                                }
                                 */
                            }
                        }else{
                            continue;
                        }

                        /**把状态不在销售状态的置为无库存*/
                        if(
                            !isset($ProductInfo['ProductStatus'])
                            || ($ProductInfo['ProductStatus'] != self::PRODUCT_STATUS_SUCCESS && $ProductInfo['ProductStatus'] != self::PRODUCT_STATUS_SUCCESS_UPDATE)
                        ){
                            $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['IsChecked'] = 0;
                            $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['IsHasInventory'] = 0;
                            $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['ErrMessage'] = 'Goods have been removed';
                            continue;
                        }else{
                            $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['IsHasInventory'] = 1;
                            $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['ErrMessage'] = '';
                        }
                    }
                }
//                if(!$storeFlag){
//                    unset($CartInfo[$Uid]['StoreData'][$k]);
//                }
            }
        }

    }

    /**
     * 获取货币转换的汇率
     * @param string $From
     * @param string $To
     */
    public function getOneRate($From,$To){
        $RateInfo = $this->redis->get("DX_CURRENCY");
        if($RateInfo){
            $RateInfo = json_decode($RateInfo,true);
        }

        if(!$RateInfo){
            /*去接口获取汇率*/
            $Url = MALL_API."/share/currency/getExchangeRate";
            $RateInfo = doCurl($Url,'',null,true);
            if(isset($RateInfo['code']) && $RateInfo['code'] == 200 && isset($RateInfo['data'])){
                $this->redis->set("DX_CURRENCY",json_encode($RateInfo['data']),config('rate_expire_time'));
                $RateInfo = $RateInfo['data'];
            }else{
                //获取出错了，
            }
        }
        if($RateInfo){
            foreach ($RateInfo as $k=>$v){
                if(!isset($v['From'] ) || !isset($v['To'] )){
                    return 0;
                    break;
                }
                if($v['From'] == $From && $v['To'] == $To){
                    return $v['Rate'];
                }
                if($v['From'] == $To && $v['To'] == $From){
                    return sprintf("%.5f", 1/$v['Rate']);
                }
            }
        }
    }

    /**
     * 获取单个广告详情
     * @param $params
     * @return array|bool|false|mixed|\PDOStatement|string|\think\Model
     */
    public function getAppBanner($params){
        $lang = isset($params['lang']) ? $params['lang'] : 'en';
        $result = $banner = array();
        $key = $params['key'];
        if(config('cache_switch_on')){
            $result = $this->redis->get(ADVERTISING_INFO_BY_.$key);
        }
        if(empty($result)){
            $result = (new AdvertisingModel())->find($params);
            if(!empty($result)){
                $this->redis->set(ADVERTISING_INFO_BY_.$key,$result,CACHE_DAY);
            }
        }
        $data  = $this->getBannerInfos($result,$lang);
        if(!empty($data) && is_array($data)){
            foreach($data as $key => $val){
                //赋值是为了跟原来的格式相同
                $banner[$key]['ActiveID'] = '';
                $banner[$key]['PhoneImg'] = $val['ImageUrl'];
                $banner[$key]['LinkUrl'] = $val['LinkUrl'];
                $banner[$key]['Name'] = trim($val['MainText']);
                $banner[$key]['Sort'] = $key;
                $banner[$key]['PadImg'] = '';
                $banner[$key]['Sku'] = 0;
                $banner[$key]['LinkType'] = 0;
                $banner[$key]['InterfaceSuffix'] = '';
            }
        }
        return $banner;
    }


    public function getAppVersion(){
        return (new CommonModel())->getAppVersion();
    }

    /**
     * 处理购物车或checkout数据【解决APP不支持888类似的key问题】
     * @param array $data
     * @param int $from 来源：1-购物车，2-checkout||BUY NOW
     * @return array
     */
    public function handlerCartOrCheckoutProductDataFowAPP(array $data, $from){
        $data = $this->transformToIndexArray($data);
        foreach ($data as $k1=>$v1){
            $StoreId = 0;
            if (isset($v1['ProductInfo'])){
                //处理产品列表问题
                $data[$k1]['ProductInfo'] = $this->transformToIndexArray($v1['ProductInfo']);
                //处理订单级别coupon列表问题
                if (isset($data[$k1]['Coupon'])){
                    $data[$k1]['Coupon'] = $this->transformToIndexArray($v1['Coupon']);
                }
                foreach ($v1['ProductInfo'] as $k2=>$v2){
                    $StoreId = $v2['StoreID'];
                    //处理单品级别coupon列表问题处理
                    if (isset($v2['coupon'])){
                        if ($from == 2){
                            //“checkout||BUY NOW”这种情况不需要返回coupon数据，只需要返回使用coupon的数据即可
                            unset($data[$k1]['ProductInfo'][$k2]['coupon']);
                        }else{
                            $data[$k1]['ProductInfo'][$k2]['coupon'] = $this->transformToIndexArray($v2['coupon']);
                        }
                    }
                }
            }
            //增加店铺ID
            $data[$k1]['StoreInfo']['StoreId'] = $StoreId;
        }
        return $data;
    }

    /**
     * 将关联数组转换为索引数组
     * @param array $data 要转换的关联数组
     * @return array
     */
    public function transformToIndexArray(array $data){
        $result = [];
        foreach ($data as $v){
            $result[] = $v;
        }
        return $result;
    }

    /**
     * 记录支付方式等信息
     * 以cookie的方式记录
     * 在checkout获取支付方式的时候需要标识出已选过的支付方式
     * 在checkout获取支付方式的时候需要标识出已填写的信用卡信息或是astorypay的CPF等信息
     * 订提交成功之后，把这些cookies清除掉
     * @param $Params
     */
    public function recordParams($Params){
        $payParams = array();
        $payParams['pay_type'] = $Params['pay_type'];
        $payParams['pay_chennel'] = $Params['pay_chennel'];
        $payParams['cpf'] = $Params['cpf'];//
        $payParams['card_bank'] = $Params['card_bank'];//
        $payParams['credit_card_token_id'] = $Params['credit_card_token_id'];
        $payParams['customer_address_id'] = $Params['customer_address_id'];

        $payParams['BillingAddress']['City'] = isset($Params['City'])?$Params['City']:'';
        $payParams['BillingAddress']['CityCode'] = isset($Params['CityCode'])?$Params['CityCode']:'';
        $payParams['BillingAddress']['Country'] = isset($Params['Country'])?$Params['Country']:'';
        $payParams['BillingAddress']['CountryCode'] = isset($Params['CountryCode'])?$Params['CountryCode']:'';
        $payParams['BillingAddress']['Email'] = isset($Params['Email'])?$Params['Email']:'';
        $payParams['BillingAddress']['FirstName'] = isset($Params['FirstName'])?$Params['FirstName']:'';
        $payParams['BillingAddress']['LastName'] = isset($Params['LastName'])?$Params['LastName']:'';
        $payParams['BillingAddress']['Mobile'] = isset($Params['Mobile'])?$Params['Mobile']:'';
        $payParams['BillingAddress']['Phone'] = isset($Params['Phone'])?$Params['Phone']:'';
        $payParams['BillingAddress']['PostalCode'] = isset($Params['PostalCode'])?$Params['PostalCode']:'';
        $payParams['BillingAddress']['State'] = isset($Params['State'])?$Params['State']:'';
        $payParams['BillingAddress']['Street1'] = isset($Params['Street1'])?$Params['Street1']:'';
        $payParams['BillingAddress']['Street2'] = isset($Params['Street2'])?$Params['Street2']:'';

        $payParams['CardInfo']['CVVCode'] = isset($Params['CVVCode'])?$Params['CVVCode']:'';
        $payParams['CardInfo']['CardHolder'] = isset($Params['CardHolder'])?$Params['CardHolder']:'';
        $payParams['CardInfo']['CardNumber'] = isset($Params['CardNumber'])?$Params['CardNumber']:'';
        $payParams['CardInfo']['ExpireMonth'] = isset($Params['ExpireMonth'])?$Params['ExpireMonth']:'';
        $payParams['CardInfo']['ExpireYear'] = isset($Params['ExpireYear'])?$Params['ExpireYear']:'';

        //Cookie::set("nocnoc_payinfo",json_encode($payParams));
        Session::set("nocnoc_payinfo",json_encode($payParams));
//        $payParams['order_master_number'] = $Params['order_master_number'];//这个不用记录，repay的时候不存在切换物流方式

    }

    /**
     * 验证订单支付地址参数
     * @param array $params
     * @return array
     */
    public function verifyOrderPayAddressParams(array $params){
        $data = ['code'=>100, 'msg'=>''];
        //Contact Name校验
        if (
            !$this->isEnName($params['FirstName'])
            ||!$this->isEnName($params['LastName'])
        ){
            $data['msg'] = 'Name is error.';
            return $data;
        }

        //Country
        if (
            empty($params['Country'])
            || empty($params['CountryCode'])
        ){
            $data['msg'] = 'Country is error.';
            return $data;
        }
        //Province
        if (
            empty($params['Province'])
            || empty($params['ProvinceCode'])
        ){
            $data['msg'] = 'Province is error.';
            return $data;
        }
        //City
        if (
        empty($params['City'])
            //|| empty($params['CityCode'])
        ){
            $data['msg'] = 'City is error.';
            return $data;
        }

        //Street校验
        if (
            !$this->isAddress($params['Street1'], $params['CountryCode'])
            || ( !empty($params['Street2']) && !$this->isAddress($params['Street2'], $params['CountryCode']) )
        ){
            $data['msg'] = 'Street is error.';
            return $data;
        }

        //PostalCode
        if (
            empty($params['PostalCode'])
            || !$this->isPostalCode($params['PostalCode'], $params['CountryCode'])
        ){
            $data['msg'] = 'PostalCode is error.';
            return $data;
        }
        //Phone number Mobile必填，Phone非必填
        if (
            !$this->isPhoneNum($params['Mobile'])
            || (!empty($params['Phone']) && !$this->isPhoneNum($params['Phone']))
        ){
            $data['msg'] = 'Phone number is error.';
            return $data;
        }
        $data['code'] = 200;
        return $data;
    }

    /**
     * 支付地址联系人名称校验
     * 只能是英文、数字、-、空格
     * @param $str
     * @return bool
     */
    public function isEnName($str){
        if (preg_match('/^(\d|[a-zA-Z]|\-|^\s+|\s+$|\s+)+$/', $str)) {
            return true;
        }else{
            return false;
        }
    }

    /**
     * 支付地址-收货地址校验
     * 只能输入数字、空格、"单引号"、"/"、","、".","#","-"、英文字符
     * @param $str
     * @param $country_code
     * @return bool
     */
    public function isAddress($str, $country_code){
        $result = false;
        $country_code = strtolower($country_code);
        switch ($country_code)
        {
            case 'BR':
                //巴西只能输入数字、空格、英文字符
                if (preg_match("/^(\d|[a-zA-Z]|^\s+|\s+$|\s+)+$/", $str)){
                    $result = true;
                }
                break;
            default:
                //只能输入数字、空格、"单引号"、"/"、","、".","#","-"、英文字符
                if (preg_match("/^(\d|[a-zA-Z]|\\'|\/|\.|\,|\#|\-|^\s+|\s+$|\s+)+$/", $str)){
                    $result = true;
                }
                break;
        }
        return $result;
    }

    /**
     * 支付地址-手机号码验证
     * @param $str
     * @return bool
     */
    public function isPhoneNum($str){
        if (preg_match("/^(\d)(\d|\-|\s){4,15}$/", $str)) {
            return true;
        }else{
            return false;
        }
    }

    /**
     * 支付收货地址邮编校验
     * @param $str
     * @param $country_code
     * @return bool
     */
    public function isPostalCode($str, $country_code){
        $result = false;
        $country_code = strtolower($country_code);
        switch ($country_code)
        {
            case 'BR':
                //输入8位数字
                if (preg_match("/^\d{8}$/", $str)){
                    $result = true;
                }
                break;
            case 'US':
                //输入5位数字
                if (preg_match("/^\d{5}$/", $str)){
                    $result = true;
                }
                break;
            case 'AU':
                //输入4位数字
                if (preg_match("/^\d{4}$/", $str)){
                    $result = true;
                }
                break;
            default:
                //只能输入数字字母
                if (preg_match("/^[0-9a-zA-Z]+$/", $str)){
                    $result = true;
                }
                break;
        }
        return $result;
    }

    /**
     * 计算与组装数据给payment
     * @param unknown $_cart_info
     * @param unknown $user_id
     * @param unknown $lang
     * @return boolean|multitype:multitype: string number Ambigous <number, unknown> Ambigous <string, unknown>
     */
    public function calPayInfo($_params){
        //计算各种价格($DiscountTotal = 优惠总额,$HandlingTotal = 手续价总额,$ItemsTotals = 订单的商品总额,$OrderTotal = 订单总额（包括运费等）,$ShippingTotal运费总额)
        //组装用户收货地址信息
        //组装item简单信息
        $_params = $_params['orderInfo'];
        if(isset($_params['slave']) && count($_params['slave']) < 1){
            return false;
        }
        $_return_data = array();
        $_return_data['item'] = array();
        $_return_data['shiping_model'] = '';
        $_discount_total = 0;//优惠总额
        $_handling_total = 0;//手续价总额
        $_order_total = 0;//订单总额（包括运费等）
        $_shipping_total = 0;//运费总额
        $_items_totals = 0;
        foreach ($_params['slave'] as $k=>$v){
            foreach ($v['order_item'] as $k1=>$v1){
                $_item_tmp['Name'] = isset($v1['product_name'])?$v1['product_name']:'';
                //if(isset($v1['active_price']) && $v1['active_price'] > 0){
                //$_item_tmp['Price'] = $v1['active_price'];//活动价格,captured_price
                //}else{
                $_item_tmp['Price'] = $v1['product_price'];
                //}
                $_item_tmp['Quantity'] = isset($v1['product_nums'])?$v1['product_nums']:1;
                //$_item_tmp['SKU'] = isset($v1['sku_id'])?$v1['sku_id']:0;
                $_item_tmp['SKU'] = isset($v1['sku_num'])?$v1['sku_num']:'';
                $_return_data['item'][] = $_item_tmp;
            }
            //组装物流发货信息
            $_return_data['shiping_address']['City'] = isset($_params['slave'][$k]['shipping_address']['city'])?$_params['slave'][$k]['shipping_address']['city']:'';
            $_return_data['shiping_address']['CityCode'] = isset($_params['slave'][$k]['shipping_address']['CityCode'])?$_params['slave'][$k]['shipping_address']['CityCode']:'';
            $_return_data['shiping_address']['Country'] = isset($_params['slave'][$k]['shipping_address']['country'])?$_params['slave'][$k]['shipping_address']['country']:'';
            $_return_data['shiping_address']['CountryCode'] = isset($_params['slave'][$k]['shipping_address']['country_code'])?$_params['slave'][$k]['shipping_address']['country_code']:'';
            $_return_data['shiping_address']['Email'] =  isset($_params['slave'][$k]['shipping_address']['email'])?$_params['slave'][$k]['shipping_address']['email']:'';
            $_return_data['shiping_address']['FirstName'] = isset($_params['slave'][$k]['shipping_address']['first_name'])?$_params['slave'][$k]['shipping_address']['first_name']:'';
            $_return_data['shiping_address']['LastName'] = isset($_params['slave'][$k]['shipping_address']['last_name'])?$_params['slave'][$k]['shipping_address']['last_name']:'';
            $_return_data['shiping_address']['Mobile'] = isset($_params['slave'][$k]['shipping_address']['mobile'])?$_params['slave'][$k]['shipping_address']['mobile']:'';
            $_return_data['shiping_address']['Phone'] = isset($_params['slave'][$k]['shipping_address']['phone_number'])?$_params['slave'][$k]['shipping_address']['phone_number']:'';
            $_return_data['shiping_address']['PostalCode'] = isset($_params['slave'][$k]['shipping_address']['postal_code'])?$_params['slave'][$k]['shipping_address']['postal_code']:'';
            $_return_data['shiping_address']['Street1'] = isset($_params['slave'][$k]['shipping_address']['street1'])?$_params['slave'][$k]['shipping_address']['street1']:'';
            $_return_data['shiping_address']['Street2'] = isset($_params['slave'][$k]['shipping_address']['street2'])?$_params['slave'][$k]['shipping_address']['street2']:'';
            $_return_data['shiping_address']['State'] = isset($_params['slave'][$k]['shipping_address']['state'])?$_params['slave'][$k]['shipping_address']['state']:'';
            $_return_data['shiping_address']['StateCode'] = isset($_params['slave'][$k]['shipping_address']['state_code'])?$_params['slave'][$k]['shipping_address']['state_code']:'';
        }
        $_return_data['discount_total'] = $_params['master']['discount_total'];
        $_return_data['handling_total'] = $_handling_total;
        $_return_data['items_totals'] = $_params['master']['goods_total'];//$_params['master']['goods_total']-$_params['master']['discount_total']
        $_return_data['order_total'] = $_params['master']['grand_total'];//注意:这里应该用total_amount还是grand_total
        $_return_data['shipping_total'] = $_params['master']['shipping_fee'];
        //日志记录
        $order_master_number = isset($_params['order_number'])?$_params['order_number']:'';
        Monlog::write(LOGS_MALL_CART,'info',__METHOD__,'submitOrder'.$order_master_number,$_params,MALL_API.'/home/order/submitOrder/calPayInfo_res', $_return_data);
        return $_return_data;
    }

    /*
     * 获取用户真实的IP地址
     * */
    function getIp() {
        if (getenv("HTTP_CLIENT_IP") && strcasecmp(getenv("HTTP_CLIENT_IP"), "unknown"))
            $ip = getenv("HTTP_CLIENT_IP");
        else
            if (getenv("HTTP_X_FORWARDED_FOR") && strcasecmp(getenv("HTTP_X_FORWARDED_FOR"), "unknown"))
                $ip = getenv("HTTP_X_FORWARDED_FOR");
            else
                if (getenv("REMOTE_ADDR") && strcasecmp(getenv("REMOTE_ADDR"), "unknown"))
                    $ip = getenv("REMOTE_ADDR");
                else
                    if (isset ($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], "unknown"))
                        $ip = $_SERVER['REMOTE_ADDR'];
                    else
                        $ip = "unknown";
        //20181214 解决使用代理出现多IP情况
        $ip_arr = explode(',', str_replace('，', ',', $ip));
        $ip = isset($ip_arr[0])?$ip_arr[0]:'0.0.0.0';
        return ($ip);
    }

    /**
     * soap调用payment wcf方法
     * @param $_soap_function
     * @param $_params
     * @return \Exception|mixed
     */
    public function dxSoap($_soap_function,$_params){
        $wsdl_url_config = config('payment_wsdl_url');
        $wsdl = $wsdl_url_config['lis_service_wsdl']['url'];
        $opts = $wsdl_url_config['lis_service_wsdl']['options'];
        $user_name = $wsdl_url_config['lis_service_wsdl']['user_name'];
        $password = $wsdl_url_config['lis_service_wsdl']['password'];
        Log::record($_soap_function.':PAYMENT_PARAMS:'.json_encode($_params));
        try {
            libxml_disable_entity_loader(false);
            $streamContext = stream_context_create($opts);
            $options['stream_context'] = $streamContext;
            $xml = '
                <wsse:Security xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">
                    <wsse:UsernameToken>
                        <wsse:Username>'.$user_name.'</wsse:Username>
                        <wsse:Password>'.$password.'</wsse:Password>
                    </wsse:UsernameToken>
                </wsse:Security>';
            $client = new \SoapClient($wsdl, $options);
            $header = new \SoapHeader($wsdl, 'CallbackHandler', new \SoapVar($xml, XSD_ANYXML), TRUE);
            $client->__setSoapHeaders(array($header));
            $result = $client->__soapCall($_soap_function, $_params);
            return $result;
        }catch (\Exception $e){
            return $e;
        }
    }

    /**
     * 验证语种有效性
     * @param $lang
     * @param $default
     * @return string
     */
    public function verifyLang($lang, $default=''){
        if (empty($lang)){
            if (!empty($default)){
                $lang = $default;
            }else{
                $lang = DEFAULT_LANG;
            }
        }else{
            $indexService = new IndexService();
            //当前设置的语种
            $langMenu = $indexService->getLangs();
            $arr = CommonLib::filterArrayByKey($langMenu,'Code',$lang);
            if(empty($arr)){
                //不属于正常语种，设置默认语种
                if (!empty($default)){
                    $lang = $default;
                }else{
                    $lang = DEFAULT_LANG;
                }
            }
        }
        return $lang;
    }

    /**
     * 验证币种有效性
     * @param $currency
     * @param $default
     * @return string
     */
    public function verifyCurrency($currency, $default=''){
        if (empty($currency)){
            if (!empty($default)){
                $currency = $default;
            }else{
                $currency = DEFAULT_CURRENCY;
            }
        }else{
            $indexService = new IndexService();
            //当前设置的币种
            $currencyMenu = $indexService->getCurrencyList();
            $arr = CommonLib::filterArrayByKey($currencyMenu,'Name',$currency);
            if(empty($arr)){
                //不属于正常币种，设置默认币种
                if (!empty($default)){
                    $currency = $default;
                }else{
                    $currency = DEFAULT_CURRENCY;
                }
            }
        }
        return $currency;
    }

    /**
     * 验证国家有效性
     * @param $country
     * @param $default
     * @return string
     */
    public function verifyCountry($country, $default=''){
        if (empty($country)){
            if (!empty($default)){
                $country = $default;
            }else{
                $country = 'US';
            }
        }else{
            $indexService = new IndexService();
            //当前设置的国家
            $arr = $indexService->getCountryInfo(['Code'=>$country]);
            if(empty($arr)){
                //不属于正常国家，设置默认国家
                if (!empty($default)){
                    $country = $default;
                }else{
                    $country = 'US';
                }
            }
        }
        return $country;
    }

    /**
     * 把订单信息转成payment调用的参数格式
     */
    public function orderInfoToPaymentInfo($OrderInfo){
        $paymentInfo['orderInfo']['master'] = $OrderInfo['master_order'];
        $paymentInfo['orderInfo']['slave'] = array();
        $TmpArr['order_item'] = array();
        foreach ($OrderInfo['item'] as $k1=>$v1){
            $TmpItemArr['product_id'] = $v1['product_id'];
            $TmpItemArr['sku_id'] = $v1['sku_id'];
            $TmpItemArr['sku_num'] = $v1['sku_num'];
            $TmpItemArr['discount_total'] = $v1['discount_total'];
            $TmpItemArr['product_price'] = $v1['product_price'];
//     			$TmpItemArr['active_price'] = $v1[''];
//     			$TmpItemArr['coupon_price'] = $v1[''];
            $TmpItemArr['product_name'] = $v1['product_name'];
            $TmpItemArr['product_img'] = $v1['product_img'];
//     			$TmpItemArr['product_attr_ids'] = $v1['product_attr_ids'];
            $TmpItemArr['product_attr_desc'] = $v1['product_attr_desc'];
            $TmpItemArr['product_nums'] = $v1['product_nums'];
            $TmpItemArr['product_unit'] = $v1['product_unit'];
            $TmpItemArr['shipping_model'] = $v1['shipping_model'];
            $TmpItemArr['shipping_fee'] = $v1['shipping_fee'];
            $TmpItemArr['delivery_time'] = $v1['delivery_time'];
            $TmpArr['order_item'][$v1['sku_id']] = $TmpItemArr;
        }
        $TmpArr['shipping_address'] = $OrderInfo['shipping_address'][0];
        $TmpArr['order'] = array();//这个没有用到，可以不处理
        $DataSlave = array();
        foreach ($OrderInfo['order'] as $k=>$v){
            //过滤掉“取消订单状态”的子单数据
            if ($v['order_status'] != 1400){
                $TmpDataSlave = [];
                $TmpDataSlave['order_number'] = $v['order_number'];
                $TmpDataSlave['order_id'] = $v['order_id'];
                $TmpDataSlave['grand_total'] = $v['grand_total'];
                $TmpDataSlave['order_status'] = $v['order_status'];
                //coupon数据
                $TmpDataSlave['coupon_id'] = array();
                $DataSlave[] = $TmpDataSlave;
            }
        }
        $paymentInfo['orderInfo']['slave'][] = $TmpArr;
        $paymentInfo['data']['data']['slave'] =$DataSlave;
        $paymentInfo['data']['data']['master']['order_id'] = $OrderInfo['master_order']['order_id'];
        $paymentInfo['data']['data']['master']['order_number'] = $OrderInfo['master_order']['order_number'];
        return $paymentInfo;

    }

}
