<?php
namespace app\app\services;

use app\app\model\AdvertisingModel;
use app\common\helpers\CommonLib;
use app\common\helpers\RedisClusterBase;
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
     * @param string $country 区域定价 added by wangyj in 20190220
     * @return array
     */
    public function productInfoByID($ProductId,$SkuId,$Lang='en',$Currency = null,$country = null,$IsCache = false){
        /*首选判断缓存里是否有数据，如果没有则请求接口*/
        if($IsCache){
            $ProductInfo = $this->redis->get("Cart_Checkout_Product_".$ProductId.$SkuId.$Lang.$country);
            /**清除相关key的方法,
             * $keys = Redis::keys('Cart_Checkout_Product_*')
             * Redis::del($keys);
             */
        }else{
            $ProductInfo = null;
        }
        if(!$ProductInfo){
            $Data['product_id'] = (int)$ProductId;
            $Data['sku_id'] = $SkuId;
            if($Lang){
                $Data['lang'] = $Lang;
            }
            if($country){
                $Data['country'] = $country;
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
                $this->redis->set("Cart_Checkout_Product_".$ProductId.$SkuId.$Lang.$country,json_encode($ProductInfo),$expire_time);
            }
        }else{
            $ProductInfo = json_decode($ProductInfo,true);
        }
        //对SKU进行排序，解决部分获取产品信息失败问题 tinghu.liu 20191118
        if (isset($ProductInfo['data']['Skus'])) sort($ProductInfo['data']['Skus']);
        return $ProductInfo;
    }

    /**
     * 根据产品ID获取产品信息【用sku code判断】
     * @param int productID
     * @param string $country 区域定价 added by wangyj in 20190220
     * @return array
     */
    public function productInfoByIDAndCode($ProductId,$SkuCode,$Lang='en',$Currency = null,$country = null,$IsCache = false){
        /*首选判断缓存里是否有数据，如果没有则请求接口*/
        if($IsCache){
            $ProductInfo = $this->redis->get("Cart_Checkout_Product_".$ProductId.$SkuCode.$Lang);
            /**清除相关key的方法,
             * $keys = Redis::keys('Cart_Checkout_Product_*')
             * Redis::del($keys);
             */
        }else{
            $ProductInfo = null;
        }
        if(!$ProductInfo){
            $Data['product_id'] = (int)$ProductId;
            $Data['sku_code'] = $SkuCode;
            if($Lang){
                $Data['lang'] = $Lang;
            }
            if($country){
                $Data['country'] = $country;
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
                $this->redis->set("Cart_Checkout_Product_".$ProductId.$SkuCode.$Lang,json_encode($ProductInfo),$expire_time);
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
        //增加一个spu有多个sku但只有部分sku参加活动报错情况 tinghu.liu 20190808
        if (isset($_product_info['Skus'])) sort($_product_info['Skus']);
        if(isset($_product_info['IsActivity']) && $_product_info['IsActivity']
            && isset($_product_info['Skus'][0]['ActivityInfo']['SalesLimit'])
            && isset($_product_info['Skus'][0]['ActivityInfo']['DiscountPrice'])
        ){
            //这是有参与活动的
            //先判断活动的SKU是否已用完,如果在活动有效期内，但活动库存不足的，提示库存不足
            if(!isset($_product_info['Skus'][0]['ActivityInfo']['SalesLimit']) || $_product_info['Skus'][0]['ActivityInfo']['SalesLimit'] < $_nums){
                $_return_data['sku_id'] = $sku_id;
                $_return_data['code'] = 3060001;
                //$_msg = lang('tips_3060001').' '.$_product_info['Skus'][0]['ActivityInfo']['SalesLimit'].' '.$_product_info['SalesUnitType'];
                if(isset($_product_info['Skus'][0]['ActivityInfo']['SalesLimit'])){
                    $_msg = 'Purchases are limited to '.$_product_info['Skus'][0]['ActivityInfo']['SalesLimit'].' '.$_product_info['SalesUnitType'];
                }else{
                    Log::record('Activity data error!'.json_encode($_product_info),'error');
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
                Log::record('Sorry,activity data errorr!'.json_encode($_product_info),'error');
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
     * @param $CartInfo 购物车数据
     * @param $Coupon Coupon列表（包含自动和手动[用户领取的]）
     * @param $CountryCode
     * @param $Currency
     * @return null
     */
    public function filtrationCouponByCart($CartInfo,$Coupon,$CountryCode,$Currency,$lang=''){
        $SkuIdArr = array(); //购物车所有产品总和
        $CartInfoStoreIdArr = array(); //购物车所有店铺总和
        $SkuCateArr = array();//需要另开一个可以同时查询多个SKU信息的接口
        $SkuBrandArr = array();//需要另开一个可以同时查询多个SKU信息的接口
        $RequestParams = array(); //初始值和$SkuIdArr一致
        if(!isset($CartInfo['StoreData'])){
            return null;
        }
        //获取系统汇率数据源
        $rate_source = [];
        if(strtoupper($Currency) != DEFAULT_CURRENCY){
            $rate_source = $this->getRateDataSource();
        }
        foreach ($CartInfo['StoreData'] as $k=>$v){
            $CartInfoStoreIdArr[] = $k;
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

                            //获取产品SKU code start
                            $_sku_code = '';
                            //$v2['ShipTo'] 区域定价 added by wangyj in 20190220
                            $_ProductInfo = $this->ProductInfoByID($v2['ProductID'],$v2['SkuID'],$lang,$Currency,$CountryCode);
                            if(
                                isset($_ProductInfo['data']['Skus'])
                                && is_array($_ProductInfo['data']['Skus'])
                            ){
                                foreach ($_ProductInfo['data']['Skus'] as $k20=>$v20){
                                    if ($v20['_id'] == $v2['SkuID']){
                                        $_sku_code = $v20['Code'];
                                        $TmpArr['product_price'] = $v20['SalesPrice'];//区域定价 added by wangyj in 20190227
                                        break;
                                    }
                                }
                            }
                            //TODO 非正常产品不处理 tinghu.liu 20191101
                            $ProductStatus = isset($_ProductInfo['data']['ProductStatus'])?$_ProductInfo['data']['ProductStatus']:-1;
                            if (!in_array($ProductStatus, [self::PRODUCT_STATUS_SUCCESS, self::PRODUCT_STATUS_SUCCESS_UPDATE])){
                                continue;
                            }
                            $TmpArr['sku_code'] = $_sku_code;
                            //获取产品SKU code end

                            $RequestParams[] = $TmpArr;
                            $SkuIdArr[] = $TmpArr;

                            //coupon初始化，需要清空之前保存的单品级别的coupon数据
                            if(isset($v2['coupon'])){
                                foreach ($v2['coupon'] as $k98=>$v98){
                                    unset($CartInfo['StoreData'][$k]['ProductInfo'][$k1][$k2]['coupon'][$k98]);
                                }
                            }
                        }
                    }
                }
            }

            //coupon初始化，需要清空之前保存的seller级别的coupon数据
            if(isset($v['coupon'])){
                foreach ($v['coupon'] as $k99=>$v99){
                    unset($CartInfo['StoreData'][$k]['coupon'][$k99]);
                }
            }
        }
        $OrderCanUseCoupon = array();
        $SkuCanUseCoupon = array();
        foreach ($Coupon as $k=>$v){
            $DiscountLevel = $v['DiscountLevel'];
            //Coupon支持的所有店铺
            $CouponStoreIdArr = (isset($v['DesignatedStore']) && !empty($v['DesignatedStore']))?$v['DesignatedStore']:[];
            $CouponStoreIdArr[] = $v['SellerId'];
            $CouponStoreIdArr = array_unique($CouponStoreIdArr);

//            print_r($CartInfoStoreIdArr);
//            print_r($CouponStoreIdArr);
//            print_r(array_intersect($CartInfoStoreIdArr, $CouponStoreIdArr));
            $CouponStoreIdArr = array_intersect($CartInfoStoreIdArr, $CouponStoreIdArr);

            $sellerAllData = [];
            //初始化Coupon每个店铺符合条件的总数量和总价格 tinghu.liu 20191104
            foreach ($CouponStoreIdArr as $k101=>$v101){
                $sellerAllData[$v101]['all_count'] = 0;
                $sellerAllData[$v101]['all_price'] = 0;
                $sellerAllData[$v101]['all_sku_id'] = '';
                //增加验证，如果指定了规则，是否有符合规则的产品，没有则不显示Coupon tinghu.liu 20191104
                $sellerAllData[$v101]['coupon_rule_flag'] = 0;
            }

            //如果是有规则的(制定限制规则)，要把他们过滤出来 //优惠券规则：1-全店铺使用，2-制定限制规则，3-全站使用
            if(isset($v['CouponRuleSetting']['CouponRuleType']) && $v['CouponRuleSetting']['CouponRuleType'] == 2){
                //获取SKU信息,与规则相匹配
                if(isset($v['CouponRuleSetting']['LimitData']['Data'])){
//    				$LimitData = explode(",",$v['CouponRuleSetting']['LimitData']['Data']);
                    if(strpos($v['CouponRuleSetting']['LimitData']['Data'],"\n") != false){
                        $LimitData = explode("\n",$v['CouponRuleSetting']['LimitData']['Data']);
                    }else{
                        $LimitData = explode(",",$v['CouponRuleSetting']['LimitData']['Data']);
                    }
                    if(isset($v['CouponRuleSetting']['LimitData']['LimitType'])){
                        switch ($v['CouponRuleSetting']['LimitData']['LimitType']){
                            case 1:
                                //指定商品
                                $IsReverse = isset($v['CouponRuleSetting']['LimitData']['IsReverse'])?$v['CouponRuleSetting']['LimitData']['IsReverse']:0;
                                //优惠级别：1-单品级别优惠，2-订单级别优惠
                                //限制总金额和总数量不对，下面是根据单个产品来算的，应该算符合条件的所有产品总合（单品级别没问题，但是seller级别有问题）
                                if($DiscountLevel == 1){ //单品级别优惠，可以直接拿产品价格和数量进行判断
                                    if($IsReverse == 1){
                                        //取反
                                        $couponMatchRes = array();
                                        foreach ($SkuIdArr as $k1 => $v1){
                                            if(!in_array($v1['sku_code'],$LimitData)){
                                                $couponMatchRes = $this->couponMatch($v,$v1,$CartInfo,$OrderCanUseCoupon,$SkuCanUseCoupon);
                                            }
                                        }
                                    }else{
                                        //不取反
                                        $couponMatchRes = array();
                                        foreach ($SkuIdArr as $k1 => $v1){
                                            if(in_array($v1['sku_code'],$LimitData)){
                                                $couponMatchRes = $this->couponMatch($v,$v1,$CartInfo,$OrderCanUseCoupon,$SkuCanUseCoupon);
                                            }
                                        }
                                    }
                                }elseif($DiscountLevel == 2){ //订单级别优惠
                                    //初始化Coupon每个店铺符合条件的总数量和总价格 tinghu.liu 20191104
                                    if($IsReverse == 1){
                                        //取反
                                        //获取总数量和价格，以此来判断是否符合使用条件
                                        //根据$CouponStoreIdArr支持的店铺统计相关数量，之后再去判断
                                        foreach ($SkuIdArr as $k1 => $v1){
                                            if (!in_array($v1['sku_code'],$LimitData)){
                                                $sellerAllData[$v1['store_id']]['coupon_rule_flag'] =1;
                                            }
                                            if(
                                                !in_array($v1['sku_code'],$LimitData)
                                                && in_array($v1['store_id'], $CouponStoreIdArr)
                                                && $v1['shipp_model_status_type'] != 3
                                                && $v1['is_checked'] != 0
                                            ){
                                                $sellerAllData[$v1['store_id']]['all_count'] += $v1['qty'];
                                                $sellerAllData[$v1['store_id']]['all_price'] += ($v1['qty']*$v1['product_price']);
                                                $sellerAllData[$v1['store_id']]['all_sku_id'] .= $v1['sku_id'].',';
                                            }
                                        }
                                    }else {
                                        //不取反
                                        foreach ($SkuIdArr as $k1 => $v1){
                                            if (in_array($v1['sku_code'],$LimitData)){
                                                $sellerAllData[$v1['store_id']]['coupon_rule_flag'] = 1;
                                            }
                                            if(
                                                in_array($v1['sku_code'],$LimitData)
                                                && in_array($v1['store_id'], $CouponStoreIdArr)
                                                && $v1['shipp_model_status_type'] != 3
                                                && $v1['is_checked'] != 0
                                            ){
                                                $sellerAllData[$v1['store_id']]['all_count'] += $v1['qty'];
                                                $sellerAllData[$v1['store_id']]['all_price'] += ($v1['qty']*$v1['product_price']);
                                                $sellerAllData[$v1['store_id']]['all_sku_id'] .= $v1['sku_id'].',';
                                            }
                                        }
                                    }
                                    //取反和不取反、一个Coupon指定多店铺、总数量。这里传总数量
                                    $this->couponMatchForSellerLevel($v,$CartInfo,$OrderCanUseCoupon,$SkuCanUseCoupon, $sellerAllData, $Currency, $rate_source);
                                }
                                break;
                            case 2:
                                //指定分类
                                $IsReverse = isset($v['CouponRuleSetting']['LimitData']['IsReverse'])?$v['CouponRuleSetting']['LimitData']['IsReverse']:0;
                                if($DiscountLevel == 1) { //单品级别优惠，可以直接拿产品价格和数量进行判断
                                    if($IsReverse == 1){
                                        //取反
                                        $couponMatchRes = array();
                                        foreach ($SkuIdArr as $k1 => $v1){
                                            //指定分类使用修改 tinghu.liu 20190402
                                            //获取一级分类对应的ERP类别ID
                                            $first_map = $this->getErpCategoryMapById($v1['first_category']);
                                            $rules_res = array_intersect($first_map,$LimitData);
                                            if (empty($rules_res)){
                                                $couponMatchRes = $this->couponMatch($v,$v1,$CartInfo,$OrderCanUseCoupon,$SkuCanUseCoupon);
                                            }
                                        }
                                    }else{
                                        //不取反
                                        $couponMatchRes = array();
                                        foreach ($SkuIdArr as $k1 => $v1){
                                            //获取一级分类对应的ERP类别ID
                                            //指定分类使用修改 tinghu.liu 20190402
                                            $first_map = $this->getErpCategoryMapById($v1['first_category']);
                                            $rules_res = array_intersect($first_map,$LimitData);
                                            if (!empty($rules_res)){
                                                $couponMatchRes = $this->couponMatch($v,$v1,$CartInfo,$OrderCanUseCoupon,$SkuCanUseCoupon);
                                            }
                                        }
                                    }
                                }elseif($DiscountLevel == 2) { //订单级别优惠
                                    //初始化Coupon每个店铺符合条件的总数量和总价格 tinghu.liu 20191104
                                    if($IsReverse == 1){
                                        //获取总数量和价格，以此来判断是否符合使用条件
                                        //根据$CouponStoreIdArr支持的店铺统计相关数量，之后再去判断
                                        foreach ($SkuIdArr as $k1 => $v1){
                                            //获取一级分类对应的ERP类别ID
                                            $first_map = $this->getErpCategoryMapById($v1['first_category']);
                                            $rules_res = array_intersect($first_map,$LimitData);
                                            if (empty($rules_res)){
                                                $sellerAllData[$v1['store_id']]['coupon_rule_flag'] = 1;
                                            }
                                            if(
                                                empty($rules_res)
                                                && in_array($v1['store_id'], $CouponStoreIdArr)
                                                && $v1['shipp_model_status_type'] != 3
                                                && $v1['is_checked'] != 0
                                            ){
                                                $sellerAllData[$v1['store_id']]['all_count'] += $v1['qty'];
                                                $sellerAllData[$v1['store_id']]['all_price'] += ($v1['qty']*$v1['product_price']);
                                                $sellerAllData[$v1['store_id']]['all_sku_id'] .= $v1['sku_id'].',';
                                            }
                                        }
                                    }else{
                                        //不取反
                                        foreach ($SkuIdArr as $k1 => $v1){

                                            //获取一级分类对应的ERP类别ID
                                            $first_map = $this->getErpCategoryMapById($v1['first_category']);
                                            $rules_res = array_intersect($first_map,$LimitData);
                                            if (!empty($rules_res)){
                                                $sellerAllData[$v1['store_id']]['coupon_rule_flag'] = 1;
                                            }
                                            if(
                                                !empty($rules_res)
                                                && in_array($v1['store_id'], $CouponStoreIdArr)
                                                && $v1['shipp_model_status_type'] != 3
                                                && $v1['is_checked'] != 0
                                            ){
                                                $sellerAllData[$v1['store_id']]['all_count'] += $v1['qty'];
                                                $sellerAllData[$v1['store_id']]['all_price'] += ($v1['qty']*$v1['product_price']);
                                                $sellerAllData[$v1['store_id']]['all_sku_id'] .= $v1['sku_id'].',';
                                            }
                                        }

                                    }
                                    //取反和不取反、一个Coupon指定多店铺、总数量。这里传总数量
                                    $this->couponMatchForSellerLevel($v,$CartInfo,$OrderCanUseCoupon,$SkuCanUseCoupon, $sellerAllData, $Currency, $rate_source);
                                }

                                break;
                            case 3:
                                //指定品牌
                                $IsReverse = isset($v['CouponRuleSetting']['LimitData']['IsReverse'])?$v['CouponRuleSetting']['LimitData']['IsReverse']:0;
                                if($DiscountLevel == 1) { //单品级别优惠，可以直接拿产品价格和数量进行判断
                                    if($IsReverse == 1){
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
                                }elseif($DiscountLevel == 2) { //订单级别优惠
                                    //初始化Coupon每个店铺符合条件的总数量和总价格 tinghu.liu 20191104
                                    if($IsReverse == 1){
                                        //获取总数量和价格，以此来判断是否符合使用条件
                                        //根据$CouponStoreIdArr支持的店铺统计相关数量，之后再去判断
                                        foreach ($SkuIdArr as $k1 => $v1){
                                            if (!in_array($v1['brand_id'],$LimitData)){
                                                $sellerAllData[$v1['store_id']]['coupon_rule_flag'] = 1;
                                            }
                                            if(
                                                !in_array($v1['brand_id'],$LimitData)
                                                && in_array($v1['store_id'], $CouponStoreIdArr)
                                                && $v1['shipp_model_status_type'] != 3
                                                && $v1['is_checked'] != 0
                                            ){
                                                $sellerAllData[$v1['store_id']]['all_count'] += $v1['qty'];
                                                $sellerAllData[$v1['store_id']]['all_price'] += ($v1['qty']*$v1['product_price']);
                                                $sellerAllData[$v1['store_id']]['all_sku_id'] .= $v1['sku_id'].',';
                                            }
                                        }
                                    }else{
                                        //不取反
                                        foreach ($SkuIdArr as $k1 => $v1){
                                            if (in_array($v1['brand_id'],$LimitData)){
                                                $sellerAllData[$v1['store_id']]['coupon_rule_flag'] = 1;
                                            }
                                            if(
                                                in_array($v1['brand_id'],$LimitData)
                                                && in_array($v1['store_id'], $CouponStoreIdArr)
                                                && $v1['shipp_model_status_type'] != 3
                                                && $v1['is_checked'] != 0
                                            ){
                                                $sellerAllData[$v1['store_id']]['all_count'] += $v1['qty'];
                                                $sellerAllData[$v1['store_id']]['all_price'] += ($v1['qty']*$v1['product_price']);
                                                $sellerAllData[$v1['store_id']]['all_sku_id'] .= $v1['sku_id'].',';
                                            }
                                        }

                                    }
                                    //取反和不取反、一个Coupon指定多店铺、总数量。这里传总数量
                                    $this->couponMatchForSellerLevel($v,$CartInfo,$OrderCanUseCoupon,$SkuCanUseCoupon, $sellerAllData, $Currency, $rate_source);
                                }

                                break;
                            case 4:
                                //指定产品类型
                                $IsReverse = isset($v['CouponRuleSetting']['LimitData']['IsReverse'])?$v['CouponRuleSetting']['LimitData']['IsReverse']:0;

                                if($DiscountLevel == 1) { //单品级别优惠，可以直接拿产品价格和数量进行判断
                                    if($IsReverse == 1){
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

                                }elseif($DiscountLevel == 2) { //订单级别优惠
                                    //初始化Coupon每个店铺符合条件的总数量和总价格 tinghu.liu 20191104
                                    if($IsReverse == 1){
                                        //获取总数量和价格，以此来判断是否符合使用条件
                                        //根据$CouponStoreIdArr支持的店铺统计相关数量，之后再去判断
                                        foreach ($SkuIdArr as $k1 => $v1){
                                            if (!in_array($v1['product_type'],$LimitData)){
                                                $sellerAllData[$v1['store_id']]['coupon_rule_flag'] = 1;
                                            }
                                            if(
                                                !in_array($v1['product_type'],$LimitData)
                                                && in_array($v1['store_id'], $CouponStoreIdArr)
                                                && $v1['shipp_model_status_type'] != 3
                                                && $v1['is_checked'] != 0
                                            ){
                                                $sellerAllData[$v1['store_id']]['all_count'] += $v1['qty'];
                                                $sellerAllData[$v1['store_id']]['all_price'] += ($v1['qty']*$v1['product_price']);
                                                $sellerAllData[$v1['store_id']]['all_sku_id'] .= $v1['sku_id'].',';
                                            }
                                        }
                                    }else{
                                        //不取反
                                        foreach ($SkuIdArr as $k1 => $v1){
                                            if (in_array($v1['product_type'],$LimitData)){
                                                $sellerAllData[$v1['store_id']]['coupon_rule_flag'] = 1;
                                            }
                                            if(
                                                in_array($v1['product_type'],$LimitData)
                                                && in_array($v1['store_id'], $CouponStoreIdArr)
                                                && $v1['shipp_model_status_type'] != 3
                                                && $v1['is_checked'] != 0
                                            ){
                                                $sellerAllData[$v1['store_id']]['all_count'] += $v1['qty'];
                                                $sellerAllData[$v1['store_id']]['all_price'] += ($v1['qty']*$v1['product_price']);
                                                $sellerAllData[$v1['store_id']]['all_sku_id'] .= $v1['sku_id'].',';
                                            }
                                        }

                                    }
                                    //取反和不取反、一个Coupon指定多店铺、总数量。这里传总数量
                                    $this->couponMatchForSellerLevel($v,$CartInfo,$OrderCanUseCoupon,$SkuCanUseCoupon, $sellerAllData, $Currency, $rate_source);
                                }
                                break;
                            case 5:
                                //指定国家
                                $IsReverse = isset($v['CouponRuleSetting']['LimitData']['IsReverse'])?$v['CouponRuleSetting']['LimitData']['IsReverse']:0;

                                if($DiscountLevel == 1) { //单品级别优惠，可以直接拿产品价格和数量进行判断

                                    if($IsReverse == 1){
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
                                }elseif($DiscountLevel == 2) { //订单级别优惠
                                    //初始化Coupon每个店铺符合条件的总数量和总价格 tinghu.liu 20191104
                                    if($IsReverse == 1){
                                        //获取总数量和价格，以此来判断是否符合使用条件
                                        //根据$CouponStoreIdArr支持的店铺统计相关数量，之后再去判断
                                        foreach ($SkuIdArr as $k1 => $v1){
                                            if (!in_array($CountryCode,$LimitData)){
                                                $sellerAllData[$v1['store_id']]['coupon_rule_flag'] = 1;
                                            }
                                            if(
                                                !in_array($CountryCode,$LimitData)
                                                && in_array($v1['store_id'], $CouponStoreIdArr)
                                                && $v1['shipp_model_status_type'] != 3
                                                && $v1['is_checked'] != 0
                                            ){
                                                $sellerAllData[$v1['store_id']]['all_count'] += $v1['qty'];
                                                $sellerAllData[$v1['store_id']]['all_price'] += ($v1['qty']*$v1['product_price']);
                                                $sellerAllData[$v1['store_id']]['all_sku_id'] .= $v1['sku_id'].',';
                                            }
                                        }
                                    }else{
                                        //不取反
                                        foreach ($SkuIdArr as $k1 => $v1){
                                            if (in_array($CountryCode,$LimitData)){
                                                $sellerAllData[$v1['store_id']]['coupon_rule_flag'] = 1;
                                            }
                                            if(
                                                in_array($CountryCode,$LimitData)
                                                && in_array($v1['store_id'], $CouponStoreIdArr)
                                                && $v1['shipp_model_status_type'] != 3
                                                && $v1['is_checked'] != 0
                                            ){
                                                $sellerAllData[$v1['store_id']]['all_count'] += $v1['qty'];
                                                $sellerAllData[$v1['store_id']]['all_price'] += ($v1['qty']*$v1['product_price']);
                                                $sellerAllData[$v1['store_id']]['all_sku_id'] .= $v1['sku_id'].',';
                                            }
                                        }

                                    }
                                    //取反和不取反、一个Coupon指定多店铺、总数量。这里传总数量
                                    $this->couponMatchForSellerLevel($v,$CartInfo,$OrderCanUseCoupon,$SkuCanUseCoupon, $sellerAllData, $Currency, $rate_source);
                                }
                                break;
                        }
                    }
                }
            }else if(isset($v['CouponRuleSetting']['CouponRuleType']) && $v['CouponRuleSetting']['CouponRuleType'] == 1)
            {

                //这些是没有规则的(但也需要过滤金额与sku,qty等信息)(全店铺使用)
//                $TmpArrSeller = array();
                //初始化
                foreach ($SkuIdArr as $kk1 => $vv1){
//                    $StoreId = $vv1['store_id'];
//                    $TmpArrSeller[$StoreId]['AllQty'] = 0;
//                    $TmpArrSeller[$StoreId]['AllPrice'] = 0;
                    //初始化店铺级别需要数据 tinghu.liu 20191104
                    if(
                        in_array($vv1['store_id'], $CouponStoreIdArr)
                        && $vv1['shipp_model_status_type'] != 3
                        && $vv1['is_checked'] != 0
                    ){
                        $sellerAllData[$vv1['store_id']]['all_count'] += $vv1['qty'];
                        $sellerAllData[$vv1['store_id']]['all_price'] += ($vv1['qty']*$vv1['product_price']);
                        $sellerAllData[$vv1['store_id']]['all_sku_id'] .= $vv1['sku_id'].',';
                    }
                }
//                foreach ($SkuIdArr as $kk => $vv){
//                    $StoreId = $vv['store_id'];
//                    //去掉没有选中的、没有运输方式的数据
//                    if ($vv['is_checked'] != 0 && $vv['shipp_model_status_type'] != 3){
//                        $TmpArrSeller[$StoreId]['AllQty'] = (int)$TmpArrSeller[$StoreId]['AllQty'] + $vv['qty'];
//                        $TmpArrSeller[$StoreId]['AllPrice'] = $TmpArrSeller[$StoreId]['AllPrice']+ ($vv['qty']*$vv['product_price']);//去掉Int转换 added by wangyj in 20190227
//                    }
//                }
                //优惠级别：1-单品级别优惠，2-订单级别优惠
                if ($DiscountLevel == 1){
                    foreach ($SkuIdArr as $k1 => $v1){
                        //把coupon_id关联到sku上面
                        //添加多seller判断 字段：DesignatedStore 。。。。。。。。。。BY tinghu.liu IN 20190128
                        if(
                            $v1['store_id'] == $v['SellerId']
                            || (isset($v['DesignatedStore']) && in_array($v1['store_id'], $v['DesignatedStore']))
                        ){
                            $v['isUsable'] = 1;
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
                                $ProductPrice = $v1['product_price'];//区域定价 added by wangyj in 20190227
                                $TmpPrice = $TmpQty*$ProductPrice;
                                //汇率转换
                                $TmpPrice = $this->calculateRate($Currency,DEFAULT_CURRENCY,$TmpPrice,$rate_source);//汇率转换
                                //限量区间处理，为了避免只输入一个区间的情况
                                $TemStartPrice = is_numeric($v['PurchaseAmountLimit']['StartPrice']) && !empty($v['PurchaseAmountLimit']['StartPrice'])?$v['PurchaseAmountLimit']['StartPrice']:0;
                                $TemEndPrice = is_numeric($v['PurchaseAmountLimit']['EndPrice']) && !empty($v['PurchaseAmountLimit']['EndPrice'])?$v['PurchaseAmountLimit']['EndPrice']:999999999;
                                if( $TmpPrice < $TemStartPrice || $TmpPrice > $TemEndPrice){
                                    $v['isUsable'] = 0;
                                }
                            }
                            //去掉没有选中的、没有运输方式的数据
                            //不需要去掉“没有选中的”，因为单品级别不受是否选中影响 tinghu.liu 20190402
//                            if ($v1['is_checked'] == 0 || $v1['shipp_model_status_type'] == 3){
                            if ($v1['shipp_model_status_type'] == 3){
                                $v['isUsable'] = 0;
                            }
                            //把符合条件的sku都加进来
                            $v['UsableSku'] = $v1['sku_id'].',';
                            //$v['UsableSku'] = substr($v['UsableSku'],0,-1);

                            $SkuCanUseCouponTmp[$v1['sku_id']][$v['CouponId']] = $v;
                            $CartInfo['StoreData'][$v1['store_id']]['ProductInfo'][$v1['product_id']][$v1['sku_id']]['coupon'][$v['CouponId']] = $v;
                        }
                    }
                }elseif($DiscountLevel == 2){ //订单级别
//                    Log::record('$v:'.print_r($v, true));
                    //数量限制
                    $TempStartNum = !empty($v['BuyGoodsNumLimit']['StartNum']) && is_numeric($v['BuyGoodsNumLimit']['StartNum'])?$v['BuyGoodsNumLimit']['StartNum']:0;
                    $TempEndNum =  !empty($v['BuyGoodsNumLimit']['EndNum']) && is_numeric($v['BuyGoodsNumLimit']['EndNum'])?$v['BuyGoodsNumLimit']['EndNum']:999999999;
                    //价格限制
                    $TemStartPrice = !empty($v['PurchaseAmountLimit']['StartPrice']) && is_numeric($v['PurchaseAmountLimit']['StartPrice'])?$v['PurchaseAmountLimit']['StartPrice']:0;
                    $TemEndPrice = !empty($v['PurchaseAmountLimit']['EndPrice']) && is_numeric($v['PurchaseAmountLimit']['EndPrice']) ?$v['PurchaseAmountLimit']['EndPrice']:999999999;
                    foreach ($sellerAllData as $k200=>$v200){
                        $isUsable = 1;
                        $storeId = $k200;
                        $sellerAllCount = $v200['all_count'];
                        $sellerAllPrice = $v200['all_price'];
                        //汇率转换，不用转换，因为这个价格上面拿的是产品数据库的价格（美元）
//                        $sellerAllPrice = $this->calculateRate($Currency,DEFAULT_CURRENCY,$sellerAllPrice,$rate_source);
                        $sellerAllSkuId = $v200['all_sku_id'];

                        //订单级别的
                        if(isset($v['BuyGoodsNumLimit']['Type']) && $v['BuyGoodsNumLimit']['Type'] == 2){
                            if( $sellerAllCount < $TempStartNum || $sellerAllCount > $TempEndNum){
                                $isUsable = 0;
//                                Log::record('$sellerAllCount-True('.$storeId.'):'.$sellerAllCount.', $sellerAllData($v200):'.json_encode($v200));
                            }
                        }
                        if(isset($v['PurchaseAmountLimit']['Type']) && $v['PurchaseAmountLimit']['Type'] == 2){
                            if( $sellerAllPrice < $TemStartPrice || $sellerAllPrice > $TemEndPrice){
                                $isUsable = 0;
//                                Log::record('$sellerAllPrice-True('.$storeId.'):'.$sellerAllPrice.', $sellerAllData($v200):'.json_encode($v200));
                            }
                        }

                        //TODO 计算Coupon折扣总金额，为了自动使用Coupon拿最大的Coupon折扣进行使用？？？为了提升自动使用Coupon接口的效率问题

                        $v['isUsable'] = $isUsable;
                        //把符合条件的sku都加进来
                        $v['UsableSku'] = $sellerAllSkuId;
                        //$v['UsableSku'] = substr($v['UsableSku'],0,-1);
                        $OrderCanUseCouponTmp[$storeId][$v['CouponId']] = $v;
                        $CartInfo['StoreData'][$storeId]['coupon'][$v['CouponId']] = $v;
                    }
//                    foreach ($SkuIdArr as $k1 => $v1){
//                        //把coupon_id关联到sku上面
//                        //添加多seller判断 字段：DesignatedStore 。。。。。。。。。。BY tinghu.liu IN 20190128
//                        if(
//                            $v1['store_id'] == $v['SellerId']
//                            || (isset($v['DesignatedStore']) && in_array($v1['store_id'], $v['DesignatedStore']))
//                        ){
//                            $v['isUsable'] = 1;
//                            //优惠级别：1-单品级别优惠，2-订单级别优惠
//                            if($v['DiscountLevel'] == 1){
//                                //单品级别优惠
//                                //对金额和数量的判断
//                                if(isset($v['BuyGoodsNumLimit']['Type']) && $v['BuyGoodsNumLimit']['Type'] == 2){
//                                    //商品数量限制
//                                    if(isset($CartInfo['StoreData'][$v1['store_id']]['ProductInfo'][$v1['product_id']][$v1['sku_id']]['Qty'])){
//                                        $TmpQty = $CartInfo['StoreData'][$v1['store_id']]['ProductInfo'][$v1['product_id']][$v1['sku_id']]['Qty'];
//                                        $TempStartNum = is_numeric($v['BuyGoodsNumLimit']['StartNum']) && !empty($v['BuyGoodsNumLimit']['StartNum'])?$v['BuyGoodsNumLimit']['StartNum']:0;
//                                        $TempEndNum = is_numeric($v['BuyGoodsNumLimit']['EndNum']) && !empty($v['BuyGoodsNumLimit']['EndNum'])?$v['BuyGoodsNumLimit']['EndNum']:999999999;
//                                        if( $TmpQty < $TempStartNum || $TmpQty > $TempEndNum){
//                                            $v['isUsable'] = 0;
//                                        }
//                                    }
//                                }
//                                if(isset($v['PurchaseAmountLimit']['Type']) && $v['PurchaseAmountLimit']['Type'] == 2){
//                                    //金额的限制
//                                    $TmpQty = $CartInfo['StoreData'][$v1['store_id']]['ProductInfo'][$v1['product_id']][$v1['sku_id']]['Qty'];
//                                    $ProductPrice = $v1['product_price'];//区域定价 added by wangyj in 20190227
//                                    $TmpPrice = $TmpQty*$ProductPrice;
//                                    //汇率转换
//                                    $TmpPrice = $this->calculateRate($Currency,DEFAULT_CURRENCY,$TmpPrice,$rate_source);//汇率转换
//                                    /*if( $TmpPrice < $v['PurchaseAmountLimit']['StartPrice'] || $TmpPrice > $v['PurchaseAmountLimit']['EndPrice']){
//                                        $v['isUsable'] = 0;
//                                    }*/
//                                    //限量区间处理，为了避免只输入一个区间的情况
//                                    $TemStartPrice = is_numeric($v['PurchaseAmountLimit']['StartPrice']) && !empty($v['PurchaseAmountLimit']['StartPrice'])?$v['PurchaseAmountLimit']['StartPrice']:0;
//                                    $TemEndPrice = is_numeric($v['PurchaseAmountLimit']['EndPrice']) && !empty($v['PurchaseAmountLimit']['EndPrice'])?$v['PurchaseAmountLimit']['EndPrice']:999999999;
//                                    if( $TmpPrice < $TemStartPrice || $TmpPrice > $TemEndPrice){
//                                        $v['isUsable'] = 0;
//                                    }
//                                }
//                                //去掉没有选中的、没有运输方式的数据
//                                //不需要去掉“没有选中的”，因为单品级别不受是否选中影响 tinghu.liu 20190402
////                            if ($v1['is_checked'] == 0 || $v1['shipp_model_status_type'] == 3){
//                                if ($v1['shipp_model_status_type'] == 3){
//                                    $v['isUsable'] = 0;
//                                }
//                                //把符合条件的sku都加进来
//                                $v['UsableSku'] = $v1['sku_id'].',';
//                                //$v['UsableSku'] = substr($v['UsableSku'],0,-1);
//
//                                $SkuCanUseCouponTmp[$v1['sku_id']][$v['CouponId']] = $v;
//                                $CartInfo['StoreData'][$v1['store_id']]['ProductInfo'][$v1['product_id']][$v1['sku_id']]['coupon'][$v['CouponId']] = $v;
//                            }else{
//                                //订单级别的TODO
//                                if(isset($v['BuyGoodsNumLimit']['Type']) && $v['BuyGoodsNumLimit']['Type'] == 2){
//                                    $TempStartNum = is_numeric($v['BuyGoodsNumLimit']['StartNum']) && !empty($v['BuyGoodsNumLimit']['StartNum'])?$v['BuyGoodsNumLimit']['StartNum']:0;
//                                    $TempEndNum = is_numeric($v['BuyGoodsNumLimit']['EndNum']) && !empty($v['BuyGoodsNumLimit']['EndNum'])?$v['BuyGoodsNumLimit']['EndNum']:999999999;
//                                    if( $TmpArrSeller[$v1['store_id']]['AllQty'] < $TempStartNum || $TmpArrSeller[$v1['store_id']]['AllQty'] > $TempEndNum){
//                                        $v['isUsable'] = 0;
//                                    }
//                                }
//                                if(isset($v['PurchaseAmountLimit']['Type']) && $v['PurchaseAmountLimit']['Type'] == 2){
//                                    //汇率转换
//                                    $TmpPrice = $this->calculateRate($Currency,DEFAULT_CURRENCY,$TmpArrSeller[$v1['store_id']]['AllPrice'],$rate_source);//汇率转换
//                                    //限量区间处理，为了避免只输入一个区间的情况
//                                    $TemStartPrice = is_numeric($v['PurchaseAmountLimit']['StartPrice']) && !empty($v['PurchaseAmountLimit']['StartPrice'])?$v['PurchaseAmountLimit']['StartPrice']:0;
//                                    $TemEndPrice = is_numeric($v['PurchaseAmountLimit']['EndPrice']) && !empty($v['PurchaseAmountLimit']['EndPrice'])?$v['PurchaseAmountLimit']['EndPrice']:999999999;
//                                    if( $TmpPrice < $TemStartPrice || $TmpPrice > $TemEndPrice){
//                                        $v['isUsable'] = 0;
//                                    }
//                                }
//                                //把符合条件的sku都加进来
//                                if (isset($v['UsableSku'])){
//                                    $v['UsableSku'] .= $v1['sku_id'].',';
//                                }else{
//                                    $v['UsableSku'] = $v1['sku_id'].',';
//                                }
//                                //$v['UsableSku'] = substr($v['UsableSku'],0,-1);
//
//                                $OrderCanUseCouponTmp[$v1['store_id']][$v['CouponId']] = $v;
//                                $CartInfo['StoreData'][$v1['store_id']]['coupon'][$v['CouponId']] = $v;
//                            }
//                        }
//                    }
                }
                if(isset($OrderCanUseCouponTmp)){
                    //如果是订单级别的coupon，则加到订单级别里去
                    $OrderCanUseCoupon = $OrderCanUseCouponTmp;
                }
                if(isset($SkuCanUseCouponTmp)){
                    //如果是sku级别的coupon,则加到sku里去
                    $SkuCanUseCoupon = $SkuCanUseCouponTmp;
                }

                /**
                 * 以下为修改前备份 tinghu.liu 20191104
                 */
//                //这些是没有规则的(但也需要过滤金额与sku,qty等信息)(全店铺使用)
//                $TmpArrSeller = array();
//                //初始化
//                foreach ($SkuIdArr as $kk1 => $vv1){
//                    $StoreId = $vv1['store_id'];
//                    $TmpArrSeller[$StoreId]['AllQty'] = 0;
//                    $TmpArrSeller[$StoreId]['AllPrice'] = 0;
//                }
//                foreach ($SkuIdArr as $kk => $vv){
//                    $StoreId = $vv['store_id'];
//                    //去掉没有选中的、没有运输方式的数据
//                    if ($vv['is_checked'] != 0 && $vv['shipp_model_status_type'] != 3){
//                        $TmpArrSeller[$StoreId]['AllQty'] = (int)$TmpArrSeller[$StoreId]['AllQty'] + $vv['qty'];
//                        $TmpArrSeller[$StoreId]['AllPrice'] = $TmpArrSeller[$StoreId]['AllPrice']+ ($vv['qty']*$vv['product_price']);//去掉Int转换 added by wangyj in 20190227
//                    }
//                }
//                foreach ($SkuIdArr as $k1 => $v1){
//                    //把coupon_id关联到sku上面
//                    //添加多seller判断 字段：DesignatedStore 。。。。。。。。。。BY tinghu.liu IN 20190128
//                    if(
//                        $v1['store_id'] == $v['SellerId']
//                        || (isset($v['DesignatedStore']) && in_array($v1['store_id'], $v['DesignatedStore']))
//                    ){
//                        $v['isUsable'] = 1;
//                        //优惠级别：1-单品级别优惠，2-订单级别优惠
//                        if($v['DiscountLevel'] == 1){
//                            //单品级别优惠
//                            //对金额和数量的判断
//                            if(isset($v['BuyGoodsNumLimit']['Type']) && $v['BuyGoodsNumLimit']['Type'] == 2){
//                                //商品数量限制
//                                if(isset($CartInfo['StoreData'][$v1['store_id']]['ProductInfo'][$v1['product_id']][$v1['sku_id']]['Qty'])){
//                                    $TmpQty = $CartInfo['StoreData'][$v1['store_id']]['ProductInfo'][$v1['product_id']][$v1['sku_id']]['Qty'];
//                                    $TempStartNum = is_numeric($v['BuyGoodsNumLimit']['StartNum']) && !empty($v['BuyGoodsNumLimit']['StartNum'])?$v['BuyGoodsNumLimit']['StartNum']:0;
//                                    $TempEndNum = is_numeric($v['BuyGoodsNumLimit']['EndNum']) && !empty($v['BuyGoodsNumLimit']['EndNum'])?$v['BuyGoodsNumLimit']['EndNum']:999999999;
//                                    if( $TmpQty < $TempStartNum || $TmpQty > $TempEndNum){
//                                        $v['isUsable'] = 0;
//                                    }
//                                }
//                            }
//                            if(isset($v['PurchaseAmountLimit']['Type']) && $v['PurchaseAmountLimit']['Type'] == 2){
//                                //金额的限制
//                                $TmpQty = $CartInfo['StoreData'][$v1['store_id']]['ProductInfo'][$v1['product_id']][$v1['sku_id']]['Qty'];
//                                $ProductPrice = $v1['product_price'];//区域定价 added by wangyj in 20190227
//                                $TmpPrice = $TmpQty*$ProductPrice;
//                                //汇率转换
//                                $TmpPrice = $this->calculateRate($Currency,DEFAULT_CURRENCY,$TmpPrice,$rate_source);//汇率转换
//                                /*if( $TmpPrice < $v['PurchaseAmountLimit']['StartPrice'] || $TmpPrice > $v['PurchaseAmountLimit']['EndPrice']){
//                                    $v['isUsable'] = 0;
//                                }*/
//                                //限量区间处理，为了避免只输入一个区间的情况
//                                $TemStartPrice = is_numeric($v['PurchaseAmountLimit']['StartPrice']) && !empty($v['PurchaseAmountLimit']['StartPrice'])?$v['PurchaseAmountLimit']['StartPrice']:0;
//                                $TemEndPrice = is_numeric($v['PurchaseAmountLimit']['EndPrice']) && !empty($v['PurchaseAmountLimit']['EndPrice'])?$v['PurchaseAmountLimit']['EndPrice']:999999999;
//                                if( $TmpPrice < $TemStartPrice || $TmpPrice > $TemEndPrice){
//                                    $v['isUsable'] = 0;
//                                }
//                            }
//                            //去掉没有选中的、没有运输方式的数据
//                            //不需要去掉“没有选中的”，因为单品级别不受是否选中影响 tinghu.liu 20190402
////                            if ($v1['is_checked'] == 0 || $v1['shipp_model_status_type'] == 3){
//                            if ($v1['shipp_model_status_type'] == 3){
//                                $v['isUsable'] = 0;
//                            }
//                            //把符合条件的sku都加进来
//                            $v['UsableSku'] = $v1['sku_id'].',';
//                            //$v['UsableSku'] = substr($v['UsableSku'],0,-1);
//
//                            $SkuCanUseCouponTmp[$v1['sku_id']][$v['CouponId']] = $v;
//                            $CartInfo['StoreData'][$v1['store_id']]['ProductInfo'][$v1['product_id']][$v1['sku_id']]['coupon'][$v['CouponId']] = $v;
//                        }else{
//                            //订单级别的TODO
//                            if(isset($v['BuyGoodsNumLimit']['Type']) && $v['BuyGoodsNumLimit']['Type'] == 2){
//                                $TempStartNum = is_numeric($v['BuyGoodsNumLimit']['StartNum']) && !empty($v['BuyGoodsNumLimit']['StartNum'])?$v['BuyGoodsNumLimit']['StartNum']:0;
//                                $TempEndNum = is_numeric($v['BuyGoodsNumLimit']['EndNum']) && !empty($v['BuyGoodsNumLimit']['EndNum'])?$v['BuyGoodsNumLimit']['EndNum']:999999999;
//                                if( $TmpArrSeller[$v1['store_id']]['AllQty'] < $TempStartNum || $TmpArrSeller[$v1['store_id']]['AllQty'] > $TempEndNum){
//                                    $v['isUsable'] = 0;
//                                }
//                            }
//                            if(isset($v['PurchaseAmountLimit']['Type']) && $v['PurchaseAmountLimit']['Type'] == 2){
//                                //汇率转换
//                                $TmpPrice = $this->calculateRate($Currency,DEFAULT_CURRENCY,$TmpArrSeller[$v1['store_id']]['AllPrice'],$rate_source);//汇率转换
//                                //限量区间处理，为了避免只输入一个区间的情况
//                                $TemStartPrice = is_numeric($v['PurchaseAmountLimit']['StartPrice']) && !empty($v['PurchaseAmountLimit']['StartPrice'])?$v['PurchaseAmountLimit']['StartPrice']:0;
//                                $TemEndPrice = is_numeric($v['PurchaseAmountLimit']['EndPrice']) && !empty($v['PurchaseAmountLimit']['EndPrice'])?$v['PurchaseAmountLimit']['EndPrice']:999999999;
//                                if( $TmpPrice < $TemStartPrice || $TmpPrice > $TemEndPrice){
//                                    $v['isUsable'] = 0;
//                                }
//                            }
//                            //把符合条件的sku都加进来
//                            if (isset($v['UsableSku'])){
//                                $v['UsableSku'] .= $v1['sku_id'].',';
//                            }else{
//                                $v['UsableSku'] = $v1['sku_id'].',';
//                            }
//                            //$v['UsableSku'] = substr($v['UsableSku'],0,-1);
//
//                            $OrderCanUseCouponTmp[$v1['store_id']][$v['CouponId']] = $v;
//                            $CartInfo['StoreData'][$v1['store_id']]['coupon'][$v['CouponId']] = $v;
//                        }
//                    }
//                }
//                if(isset($OrderCanUseCouponTmp)){
//                    //如果是订单级别的coupon，则加到订单级别里去
//                    $OrderCanUseCoupon = $OrderCanUseCouponTmp;
//                }
//                if(isset($SkuCanUseCouponTmp)){
//                    //如果是sku级别的coupon,则加到sku里去
//                    $SkuCanUseCoupon = $SkuCanUseCouponTmp;
//                }
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
     * @param array $rate
     * @return number
     */
    public function calculateRate($From,$To,$money,$rate=[]){
        $result = $money;//转换失败或者没有对应的币种，则原值返回
        if(empty($From) || empty($To) || empty($money)){
            return $result;
        }
        if($From == $To){
            return $result;
        }
        if (empty($rate)){
            $rate = $this->getRateDataSource(false);
        }
        if(!empty($rate)){
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
        }else{
            Log::record('$rate'.json_encode($rate),'error');
        }
        return $result;
    }

    /**
     * 匹配coupon
     * @param unknown $v - coupon
     * @param unknown $v1 - 产品数据
     * @param unknown $CartInfo - 购物车数据
     * @param unknown $OrderCanUseCoupon - 订单级别coupon使用
     * @param unknown $SkuCanUseCoupon - 产品级别coupon使用
     */
    public function couponMatch($v,$v1,&$CartInfo,&$OrderCanUseCoupon,&$SkuCanUseCoupon){
        //根据Qty,Price判断该coupon是否可用
        $v['isUsable'] = 1; //默认coupon可用 tinghu.liu 20190402
//    	if($v1['is_checked'] && $v1['shipp_model_status_type'] != 3){
        if($v1['shipp_model_status_type'] != 3){
            //如果是选中的，需要对数量进行判断
//    		$v['isUsable'] = 1;
            if(isset($v['BuyGoodsNumLimit']['Type']) && $v['BuyGoodsNumLimit']['Type'] == 2){
                //对于购买数量的判断

                //商品数量限制
                $TempStartNum = isset($v['BuyGoodsNumLimit']['StartNum']) && is_numeric($v['BuyGoodsNumLimit']['StartNum'])?$v['BuyGoodsNumLimit']['StartNum']:0;
                $TempEndNum = isset($v['BuyGoodsNumLimit']['EndNum']) && is_numeric($v['BuyGoodsNumLimit']['EndNum'])?$v['BuyGoodsNumLimit']['EndNum']:9999999999;

//                if(isset($v['BuyGoodsNumLimit']['StartNum']) && $v['BuyGoodsNumLimit']['StartNum'] > 0 && $v['BuyGoodsNumLimit']['StartNum'] < $v1['qty']){

                //$TempStartNum:2, $TempEndNum:9999999999, $qty:2
                Log::record('$TempStartNum:'.$TempStartNum.', $TempEndNum:'.$TempEndNum.', $qty:'.$v1['qty']);
                if($TempStartNum > $v1['qty']){
                    //不能用
                    $v['isUsable'] = 0;
                }
//    			if(isset($v['BuyGoodsNumLimit']['EndNum']) && $v['BuyGoodsNumLimit']['EndNum'] > 0 && $v['BuyGoodsNumLimit']['EndNum'] > $v1['qty']){
                if($TempEndNum < $v1['qty']){
                    //不能用
                    $v['isUsable'] = 0;
                }
            }
            if(isset($v['PurchaseAmountLimit']['Type']) && $v['PurchaseAmountLimit']['Type'] == 2){

                $TempStartPrice = isset($v['PurchaseAmountLimit']['StartPrice']) && is_numeric($v['PurchaseAmountLimit']['StartPrice'])?$v['PurchaseAmountLimit']['StartPrice']:0;
                $TempEndPrice = isset($v['PurchaseAmountLimit']['EndPrice']) && is_numeric($v['PurchaseAmountLimit']['EndPrice'])?$v['PurchaseAmountLimit']['EndPrice']:9999999999;

                //TODO 金额要转换为当前币种来进行判断


                //对于购买金额的判断
//    			if(isset($v['PurchaseAmountLimit']['StartPrice']) && $v['PurchaseAmountLimit']['StartPrice'] > 0 && $v['PurchaseAmountLimit']['StartPrice'] < ($v1['qty']*$v1['product_price'])){
                if($TempStartPrice > ($v1['qty']*$v1['product_price'])){
                    //不能用
                    $v['isUsable'] = 0;
                }
//    			if(isset($v['PurchaseAmountLimit']['EndPrice']) && $v['PurchaseAmountLimit']['EndPrice'] > 0 && $v['PurchaseAmountLimit']['EndPrice'] > ($v1['qty']*$v1['product_price'])){
                if($TempEndPrice < ($v1['qty']*$v1['product_price'])){
                    //不能用
                    $v['isUsable'] = 0;
                }
            }
        }/*else{
    		//如果没有选中，直接置为不可用状态
    		$v['isUsable'] = 0;
    	}*/

        //单品级别优惠
        if($v['DiscountLevel'] == 1){
            //单品级别优惠,把coupon_id关联到sku上面
            $SkuCanUseCoupon[$v1['sku_id']][$v['CouponId']] = $v;
            $CartInfo['StoreData'][$v1['store_id']]['ProductInfo'][$v1['product_id']][$v1['sku_id']]['coupon'][$v['CouponId']] = $v;
        }else{
            //订单级别的
            //添加多seller判断 字段：DesignatedStore 。。。。。。。。。。BY tinghu.liu IN 20190128
            if(
                $v1['store_id'] == $v['SellerId']
                || (isset($v['DesignatedStore']) && in_array($v1['store_id'], $v['DesignatedStore']))
            ){
                $OrderCanUseCoupon[$v1['store_id']][$v['CouponId']] = $v;
                //把符合条件的sku都加进来
                $v['UsableSku'] = $v1['sku_id'].',';
                //$v['UsableSku'] = substr($v['UsableSku'],0,-1);
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
     * @param $From 来源：2-checkout（需要处理币种改变时coupon价格问题）， 默认1
     * @param $isDifferentStoreID 是否存在不同的店铺ID情况
     * 区分出cart与checkout所带出来的商品是有区别的
     * cart:没有任何条件
     * checkout:IsBuy=1,IsCheck=1,ShippModelStatusType=3
     */
    /**
     * 处理购物车里的信息
     * @param $CartInfo
     * @param $Currency
     * @param $Country
     * @param $Lang
     * @param $Uid
     * @param $type
     * @param $GlobalShipTo
     * @param $From 来源：2-checkout（需要处理币种改变时coupon价格问题）， 默认1
     * @param $isDifferentStoreID 是否存在不同的店铺ID情况
     * 区分出cart与checkout所带出来的商品是有区别的
     * cart:没有任何条件
     * checkout:IsBuy=1,IsCheck=1,ShippModelStatusType=3
     */
    public function processCartProduct(&$CartInfo,$Currency,$Country,$Lang,$Uid,$type,&$GlobalShipTo,&$IsHasNocNoc,$UserName, $From=1, &$isDifferentStoreID=0){
        $prevCountry = Cookie::get('prevCountry');//用来判断是否切换了国家
        $prevCurrency = Cookie::get('prevCurrency');//用来判断是否切换了币种
        $shiptoCountry = Cookie::get('DXGlobalization_shiptocountry');
        Cookie::set('prevCurrency',$Currency);
        $this->productService = new ProductService();
        if(!isset($CartInfo[$Uid]['StoreData']) || !is_array($CartInfo[$Uid]['StoreData'])){
            $Return['code'] = 3010005;
            return $Return;
        }
        //获取系统汇率数据源
        $rate_source = $this->getRateDataSource();
        $IsHasNocNoc = 0;
        //20190110 上一次的币种
        $preCurrency = $Currency;

        foreach($CartInfo[$Uid]['StoreData'] as $k=>$v){
            $CartInfo[$Uid]['StoreData'][$k]['StoreInfo']['CustomerName'] = $UserName;
            $storeFlag = 0;
            if(isset($v['ProductInfo'])){
                foreach ($v['ProductInfo'] as $k2 =>$v2){
                    foreach ($v2 as $kk=>$vv){
                        //20190110 初始化上一次的币种
                        $preCurrency = isset($vv['Currency']) ? $vv['Currency'] : $Currency;

                        //如果有优惠券，需要把优惠的价格转换成当前汇率
                        $ProductID = $k2;
                        $SkuID = $kk;
                        $Qty = isset($vv['Qty'])?$vv['Qty']:0;
                        //$Country 区域定价 added by wangyj in 20190220
                        $ProductInfo = $this->ProductInfoByID($ProductID,$SkuID,$Lang,$Currency,$Country); //["IsActivity"] => int(289)
                        //dump($ProductInfo);
                        $ProductInfo = $ProductInfo['data'];
                        //重新赋值产品真实店铺ID，如果不一致，需要在下一步进行重新组装数据 tinghu.liu 20190507
                        if ($From == 2 && isset($ProductInfo['StoreID']) && isset($ProductInfo['StoreName']) && $k != $ProductInfo['StoreID']){
                            $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['StoreID'] = $ProductInfo['StoreID'];
                            $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['StoreName'] = $ProductInfo['StoreName'];
                            $isDifferentStoreID = 1;
                            Log::record('$isDifferentStoreID: Store SellerID is different. $ProductID:'.$ProductID.', $SkuID'.$SkuID.', StoreID[a] '.$k.', StoreID[b]'.$ProductInfo['StoreID']);
                            Log::record('$ProductInfo:'.json_encode($ProductInfo));
                        }

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

                            //来至checkout的需要判断是否使用了coupon，需要和批发价互斥 tinghu.liu 20190718
                            $PriceFlag = 0;
                            if ($From == 2){
                                //如果存在单品级别coupon使用，则互斥批发价
                                if (
                                    isset($CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['isUsedCoupon']['CouponCode'])
                                    || isset($CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['isUsedCoupon']['DiscountInfo']['Type'])

                                ){
                                    $PriceFlag = 1;
                                }
                                //如果存在seller级别coupon使用，则互斥批发价
                                if (
                                    isset($CartInfo[$Uid]['StoreData'][$k]['isUsedCoupon']['CouponCode'])
                                    || isset($CartInfo[$Uid]['StoreData'][$k]['isUsedCoupon']['DiscountInfo']['Type'])

                                ){
                                    $PriceFlag = 1;
                                }
                            }

                            $_product_price_info = $this->getProductPrice($ProductInfo,$SkuID,$Qty,$PriceFlag);
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
                                //有市场价，则展示市场价 tinghu.liu 20190511
                                if (isset($ProductInfo['Skus'][0]['ListPrice'])){
                                    $money = max($money,$ProductInfo['Skus'][0]['ListPrice']);
                                }
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
                        }

//                        else{
//                            $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['IsHasInventory'] = 1;
//                            $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['ErrMessage'] = '';
//                        }

                        //20190110 来至chechout，需要对产品级别的coupon价格进行转换，因为支付方式的变化会引起币种的变化
                        if ($From == 2) {
                            if (isset($vv['ShippModelStatusType']) && isset($vv['IsBuy']) && $vv['IsBuy'] == 1) {
                                if (isset($vv['isUsedCoupon']['DiscountInfo']['DiscountPrice'])) {
                                    $TmpPrice = $vv['isUsedCoupon']['DiscountInfo']['DiscountPrice'];//coupon的价格汇率计算
                                    if ($Currency != $preCurrency) {
                                        $TmpPrice = $this->calculateRate($preCurrency, $Currency, $TmpPrice, $rate_source);//汇率转换
                                        $TmpPrice = sprintf("%.2f", $TmpPrice);
                                    }
                                    //保存coupon因币种变化而改变的价格
                                    $CartInfo[$Uid]['StoreData'][$k]['ProductInfo'][$k2][$kk]['isUsedCoupon']['DiscountInfo']['DiscountPrice'] = $TmpPrice;
                                }
                            }
                        }
                    }
                }
//                if(!$storeFlag){
//                    unset($CartInfo[$Uid]['StoreData'][$k]);
//                }
            }

            //20190110 来至chechout，需要对订单级别的coupon价格进行转换，因为支付方式的变化会引起币种的变化
            if ($From == 2){
                if(isset($v['isUsedCouponDX'])){
                    if(isset($v['isUsedCouponDX']['DiscountInfo']['DiscountPrice'])){
                        $TmpPrice = $v['isUsedCouponDX']['DiscountInfo']['DiscountPrice'];
                        if ($Currency != $preCurrency){
                            $TmpPrice = $this->calculateRate($preCurrency,$Currency,$TmpPrice,$rate_source);//汇率转换
                            $TmpPrice = sprintf("%.2f", $TmpPrice);
                        }
                        //保存coupon因币种变化而改变的价格
                        $CartInfo[$Uid]['StoreData'][$k]['isUsedCouponDX']['DiscountInfo']['DiscountPrice'] = $TmpPrice;
                    }
                }
                if(isset($v['isUsedCoupon'])){
                    if(isset($v['isUsedCoupon']['DiscountInfo']['DiscountPrice'])){
                        $TmpPrice = $v['isUsedCoupon']['DiscountInfo']['DiscountPrice'];
                        //20190110 checkout优惠券已经是当前币种不用再转换，但在支付方式不支持选择币种导致币种改变时（变为默认币种USD）需要将不支持的币种转换为默认支持的币种USD
                        if ($Currency != $preCurrency){
                            $TmpPrice = $this->calculateRate($preCurrency,$Currency,$TmpPrice,$rate_source);//汇率转换
                            $TmpPrice = sprintf("%.2f", $TmpPrice);
                        }
                        ////保存coupon因币种变化而改变的价格
                        $CartInfo[$Uid]['StoreData'][$k]['isUsedCoupon']['DiscountInfo']['DiscountPrice'] = $TmpPrice;
                    }
                }
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
                $this->redis->set(ADVERTISING_INFO_BY_.$key,$result,CACHE_HOUR);
            }
        }
        $data  = $this->getBannerInfos($result,$lang);
        if(!empty($data) && is_array($data)){
            foreach($data as $key => $val){
                //赋值是为了跟原来的格式相同
                $banner[$key]['ActiveID'] = trim($val['MainText']);
                $banner[$key]['PhoneImg'] = $val['ImageUrl'];
                $banner[$key]['LinkUrl'] = $val['LinkUrl'];
                $banner[$key]['Name'] = trim($val['MainText']);
                $banner[$key]['Sort'] = $key;
                $banner[$key]['PadImg'] = '';
                $banner[$key]['Sku'] = 0;
                $banner[$key]['LinkType'] = trim($val['SubText']);
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
     * 处理cart&checkout初始化运费数据【解决APP不支持888类似的key问题】
     * @param array $data
     * @return array
     */
    public function handlerCartOrCheckoutInitShippingDataFowAPP(array $data){
        return $this->transformToIndexArray($data);
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
     * @param string $pay_type 支付方式：当支付方式为信用卡（CreditCard）时，相关字段需要按照以下规则来判断
     * @return array
     *
     * 地址相关校验（Payment）限制
    BLACK_LIST_CHARS = { '<', '>', '(', ')', '{', '}', '[', ']', '?', ';', '&', '*' };
    INVALID_ADDRESS_CHARS = { '~', '`', '!', '@', '$', '%', '^', '_', '=', '+', '|', '\\', ':', '\"', '/', ';' };
    INVALID_TEXT_CHARS = { '~', '`', '!', '@', '#', '$', '%', '^', '_', '=', '+', '|', '\\', ':', '\'', '\"', '.', '/', ';', ',' };
    INVALID_NAME_CHARS = { '~', '`', '!', '@', '#', '$', '%', '^', '_', '=', '+', '|', '\\', ':', '\"', ',', '/', ';' };

    State和City使用的校验规则：
    BLACK_LIST_CHARS
    INVALID_TEXT_CHARS

    FirstName/LastName使用的校验规则：
    BLACK_LIST_CHARS
    INVALID_NAME_CHARS

    street1/street2的校验规则：
    BLACK_LIST_CHARS
    INVALID_ADDRESS_CHARS
     */
    public function verifyOrderPayAddressParams(array $params, $pay_type=''){
        $credit_card_flag = 'CreditCard';
        foreach ($params as $k=>$v){
            $params[$k] = htmlspecialchars_decode(htmlspecialchars_decode(htmlspecialchars_decode($v)));
        }
        $data = ['code'=>100, 'msg'=>''];
        //Contact Name校验
        if (
            !$this->isEnName($params['FirstName'], $pay_type)
            ||!$this->isEnName($params['LastName'], $pay_type)
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
    //        || empty($params['ProvinceCode'])
        ){
            $data['msg'] = 'Province is error.';
            return $data;
        }
        if ($pay_type == $credit_card_flag){ //如果是信用卡支付，需要对省增加校验 tinghu.liu 20190709
            $province_res = $this->isProvinceCityForCreditCard($params['Province'], 'The field State/Province/Region contains illegal character: ');
            if ($province_res !== true){
                $data['msg'] = $province_res;
                return $data;
            }
        }

        //City
        if (
            empty($params['City'])
           // || !$this->isCity($params['City'])
        ){
            $data['msg'] = 'City is error.';
            return $data;
        }
        if ($pay_type == $credit_card_flag) { //如果是信用卡支付，需要对城市增加校验 tinghu.liu 20190709
            $city_res = $this->isProvinceCityForCreditCard($params['City'], 'The field City contains illegal character: ');
            if ($city_res !== true){
                $data['msg'] = $city_res;
                return $data;
            }
        }

        //Street校验
        if (
            !$this->isAddress($params['Street1'], $params['CountryCode'], $pay_type)
            || ( !empty($params['Street2']) && !$this->isAddress($params['Street2'], $params['CountryCode'], $pay_type) )
        ){
            $data['msg'] = 'Street is error.';
            return $data;
        }

        //PostalCode
        if (
            empty($params['PostalCode'])
            || !$this->isPostalCode($params['PostalCode'], $params['CountryCode'], $pay_type)
        ){
            $data['msg'] = 'PostalCode is error.';
            return $data;
        }
        //Phone number Mobile必填，Phone非必填
        if (
            !$this->isPhoneNum($params['Mobile'], $pay_type)
            || (!empty($params['Phone']) && !$this->isPhoneNum($params['Phone'], $pay_type))
        ){
            $data['msg'] = 'Phone number is error.';
            return $data;
        }
        $data['code'] = 200;
        return $data;
    }

    /**
     * 省和城市校验【信用卡支付方式专用】TODO 。。。。信用卡增加这里即可
     * @param $str
     * @param $tip_prefix 提示前缀
     * @return bool|string
     *
     * 不能输入的特殊字符
     *
     *  BLACK_LIST_CHARS = { '<', '>', '(', ')', '{', '}', '[', ']', '?', ';', '&', '*' };
    INVALID_TEXT_CHARS = { '~', '`', '!', '@', '#', '$', '%', '^', '_', '=', '+', '|', '\\', ':', '\'', '\"', '.', '/', ';', ',' };

    State和City使用的校验规则：
    BLACK_LIST_CHARS
    INVALID_TEXT_CHARS
     */
    public function isProvinceCityForCreditCard($str, $tip_prefix){
        $pattern = "/[<|>|\(|\)|\{|\}|\[|\]|\?|;|&|\*|\~|\`|\!|\@|\#|\\$|\%|\^|\_|\=|\+|\||\\\|:|\'|\"|\.|\/|\,]+/iu";//State和City使用的校验规则： BLACK_LIST_CHARS && INVALID_TEXT_CHARS
        //$pattern = "/(\.)+/";//State和City使用的校验规则： BLACK_LIST_CHARS && INVALID_TEXT_CHARS
        $res = preg_match_all($pattern, $str, $matches);
        if ($res){
            $msg = $tip_prefix.implode(' ', $matches[0]);
            return $msg;
        }else{
            return true;
        }
    }

    /**
     * 支付地址联系人名称校验
     * 只能是英文、数字、-、空格
     * /^(\d|[a-zA-Z]|\-|^\s+|\s+$|\s+)+$/
     * @param $str
     * @param  string $pay_type
     * @return bool
     *
     * 信用卡相关校验（Payment）不能输入限制【已包含】
    BLACK_LIST_CHARS = { '<', '>', '(', ')', '{', '}', '[', ']', '?', ';', '&', '*' };
    INVALID_NAME_CHARS = { '~', '`', '!', '@', '#', '$', '%', '^', '_', '=', '+', '|', '\\', ':', '\"', ',', '/', ';' };

    FirstName/LastName使用的校验规则：
    BLACK_LIST_CHARS
    INVALID_NAME_CHARS
     *
     */
    public function isEnName($str, $pay_type=''){
        if (preg_match('/^(\d|[a-zA-Z]|\-|^\s+|\s+$|\s+)+$/', $str)) {
            return true;
        }else{
            return false;
        }
    }

    /**
     * 支付地址-收货地址校验
     * 只能输入数字、空格、"单引号"、"/"、","、".","#","-"、英文字符
     * /^(\d|[a-zA-Z]|\'|\/|\.|\,|\#|\-|^\s+|\s+$|\s+)+$/
     * BR：/^(\d|[a-zA-Z]|^\s+|\s+$|\s+)+$/  巴西只能输入数字、空格、英文字符
     * @param $str
     * @param $country_code
     * @param $pay_type
     * @return bool
     *
     *  支付方式为信用卡时验证规则【已包含】：
     *  BLACK_LIST_CHARS = { '<', '>', '(', ')', '{', '}', '[', ']', '?', ';', '&', '*' };
    INVALID_ADDRESS_CHARS = { '~', '`', '!', '@', '$', '%', '^', '_', '=', '+', '|', '\\', ':', '\"', '/', ';' };

    street1/street2的校验规则：
    BLACK_LIST_CHARS
    INVALID_ADDRESS_CHARS
     *
     */
    public function isAddress($str, $country_code, $pay_type=''){
        $result = false;
        $country_code = strtoupper($country_code);
        /**
         * 巴西收货地址去掉校验，使用统一校验规则（恒总定） tinghu.liu 20190714
         *
         * switch ($country_code)
        {
        case 'BR':
        //巴西只能输入数字、空格、英文字符
        if (preg_match("/^(\d|[a-zA-Z]|^\s+|\s+$|\s+)+$/", $str)){
        $result = true;
        }
        break;
        default:
        //只能输入数字、空格、"单引号"、"/"、","、".","#","-"、英文字符
        //20190625不能输入"/" tinghu.liu
        if (preg_match("/^(\d|[a-zA-Z]|\'|\.|\,|\#|\-|^\s+|\s+$|\s+)+$/", $str)){
        $result = true;
        }
        break;
        }*/
        //只能输入数字、空格、"单引号"、"/"、","、".","#","-"、英文字符
        if (preg_match("/^(\d|[a-zA-Z]|\'|\.|\,|\#|\-|^\s+|\s+$|\s+)+$/", $str)){
            $result = true;
        }
        return $result;
    }

    /**
     * 支付地址-手机号码验证
     * /^1[23456789]\d{9}$/
     * /^1[23456789]\d{9}$/
     *
     *  输入数字、-、空格、+，必须有一位数字
     * @param $str
     * @param $pay_type
     * @return bool
     */
    public function isPhoneNum($str, $pay_type=''){
//        if (preg_match("/^(\d)(\d|\-|\s){4,15}$/u", $str)) {
        //电话号码验证修改 tinghu.liu 20190916
        if (preg_match("/^(\d+|([\d\+\-\s]*\d+[\d\+\-\s]*))$/u", $str)) {
            return true;
        }else{
            return false;
        }
    }

    /**
     * 支付地址-城市验证
     * @param $str
     * @return bool
     */
    public function isCity($str){
        if (preg_match("/(.[a-zA-Z].?){1,}|(.?[a-zA-Z].){1,}/", $str)) {
            return true;
        }else{
            return false;
        }
    }

    /**
     * 支付收货地址邮编校验
     * 同步邮编规则校验 tinghu.liu 20190527
     * @param $str
     * @param $country_code
     * @param $pay_type
     * @return bool
     */
    public function isPostalCode($str, $country_code, $pay_type=''){
        $result = false;
        $country_code = strtoupper($country_code);
        switch ($country_code)
        {
            case 'BR':
                //输入8位数字，原：/^\d{8}$/ ，原：/^(\d{5}|\d{8}|(\d{5}-\d{3}))$/，与前端同步 20190606 tinghu.liu
                if (preg_match("/^(\d{5}|(\d{5}-\d{3}))$/", $str)){
                    $result = true;
                }
                break;
            case 'US':
                //输入5位数字
                if (preg_match("/^\d{5}$/", $str)){
                    $result = true;
                }
                break;
            case 'GB':
                //输入3位数字或者字母+空格+三位数字或者空格 tinghu.liu 20190718
                if (preg_match("/^[0-9a-zA-Z]+\s?[0-9a-zA-Z]+$/", $str)){
                    $result = true;
                }
                break;
            case 'JP':
                //输入数字+数字
                if (preg_match("/^\d{3}-\d{4}$/", $str)){
                    $result = true;
                }
                break;
            default:
                //只能输入数字，字母，-，且长度不能大于15 tinghu.liu 20190718
                if (
                    preg_match("/^[0-9a-zA-Z]+(\s|-)?[0-9a-zA-Z]+$/", $str)
                    && mb_strlen($str,'utf-8') <= 15
                ){
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
                //对接EGP
                $_item_tmp['ProductId'] = isset($v1['product_id'])?$v1['product_id']:'';
                $_item_tmp['UnitPrice'] = $v1['product_price'];
                $_item_tmp['SkuId'] = isset($v1['sku_id'])?$v1['sku_id']:'';
                $_item_tmp['SkuCode'] = isset($v1['sku_num'])?$v1['sku_num']:'';
                $_item_tmp['Count'] = isset($v1['product_nums'])?$v1['product_nums']:1;
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
        $_return_data['discount_total'] = sprintf("%.2f", $_params['master']['discount_total']);
        $_return_data['handling_total'] = sprintf("%.2f", $_handling_total);
        $_return_data['items_totals'] = sprintf("%.2f", $_params['master']['goods_total']);//$_params['master']['goods_total']-$_params['master']['discount_total']
        $_return_data['order_total'] = sprintf("%.2f", $_params['master']['grand_total']);//注意:这里应该用total_amount还是grand_total
        $_return_data['shipping_total'] = sprintf("%.2f", $_params['master']['shipping_fee']);
        $_return_data['order_total_usd'] = sprintf("%.2f",$_params['master']['captured_amount_usd']);//注意:这里应该用total_amount还是grand_total
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
        $paymentInfo['data']['data']['master']['grand_total'] = $OrderInfo['master_order']['grand_total'];
        return $paymentInfo;

    }

    /**
     * 根据分类ID获取对应的ERP分类ID【coupon规则校验用】
     * @param $category_id
     * @return array
     */
    public function getErpCategoryMapById($category_id, $is_cache = true){
        $cache_key = "Cart_Product_Erp_Category_Map".$category_id;
        $return = [];
        if($is_cache){
            $return = $this->redis->get($cache_key);
        }
        if (empty($return)){
            //doCurl($Url,$Data,null,true);
            $res = doCurl(MALL_API."mall/ProductClass/getErpClassIdByClassId",['class_id'=>$category_id],null,true);
            Log::record('getErpCategoryMapById-res:'.json_encode($res));
            if($res['code'] == 200 && isset($res['data'])){
                $return = $res['data'];
                $this->redis->set($cache_key, json_encode($return), 86400);
            }
        }else{
            $return = json_decode($return, true);
        }
        return !empty($return)?$return:[];
    }

    /**
     * APP支付成功或失败获取 svn
     * @return array
     */
    public function getRecentHistoryForMobile(){
        $return = [];
        $res = doCurl(MALL_API."app/productExtension/getRecentHistory",null,null,true);
        if($res['code'] == 200 && isset($res['data'])){
            $return = $res['data'];
        }
        if (!empty($return)){
            //处理图片地址
            foreach ($return as $k=>$v){
                $return[$k]['FirstProductImage'] = IMG_DXCDN.'/'.$v['FirstProductImage'];
            }
        }
        return $return;
    }

    public function loadRedis(){
        return new RedisClusterBase();
    }
    /**
    * 获取用户收货地址信息
    * @param $customer_id 用户ID
    * @param $customer_address_id 收货地址ID
    * @return array
    */
    public function getUserAddressData($customer_id, $customer_address_id){
        $data = [];
        $_get_address_params['CustomerID'] = $customer_id;
        $_get_address_params['AddressID'] = $customer_address_id;

        //使用用户地址ID的处理
        $Url = CIC_API."/cic/address/getAddress";
        $_get_address_res = doCurl($Url,$_get_address_params,null,true);
        Log::record('getUserAddressData:'.json_encode($_get_address_res));

        if($_get_address_res['code'] == 200){
            $data = isset($_get_address_res['data'])?$_get_address_res['data']:array();
        }else{
            Log::record('getUserAddressData the address data is error, params:'.json_encode($_get_address_params).',res:'.json_encode($_get_address_res), Log::ERROR);
        }
        return $data;
    }

    /**
     * 【NOC询价】地址校验
     * @param array $address
     * {"country":"","zipcode":"","recipient_name":" ","phone":"","street":"","street_2":"","region":"","city":""}
     * @return array
     */
    public function verifyAddressForNocQuote(array $address){
        $rtn = ['code'=>200, 'msg'=>'Success'];
        /*if (
            empty($address['country'])
            || empty($address['zipcode'])
            || empty($address['recipient_name'])
            || empty($address['phone'])
            || empty($address['street'])
            || empty($address['region'])
            || empty($address['city'])
        ){
            $rtn['code'] = 1001;
            $rtn['msg'] = 'Address error.';
        }*/
        if (empty($address['country'])){
            $rtn['code'] = 1001;
            $rtn['msg'] = 'Shipping address error, the country is empty.';

        }elseif (empty($address['zipcode'])){
            $rtn['code'] = 1001;
            $rtn['msg'] = 'Shipping address error, the PostalCode is empty.';

        }elseif (empty($address['recipient_name'])){
            $rtn['code'] = 1001;
            $rtn['msg'] = 'Shipping address error, the FirstName or LastName is empty.';
        }elseif (empty($address['phone'])){
            $rtn['code'] = 1001;
            $rtn['msg'] = 'Shipping address error, the phone is empty.';
        }elseif (empty($address['street'])){
            $rtn['code'] = 1001;
            $rtn['msg'] = 'Shipping address error, the Street1 is empty.';
        }elseif (empty($address['region'])){
            $rtn['code'] = 1001;
            $rtn['msg'] = 'Shipping address error, the region is empty.';
        }elseif (empty($address['city'])){
            $rtn['code'] = 1001;
            $rtn['msg'] = 'Shipping address error, the City is empty.';
        }
        return $rtn;
    }

    /**
     * 获取用户可用的SC
     * @param unknown $customer_id
     */
    public function getCustomerSC($customer_id,$currency){
        $Params['CustomerID'] = $customer_id;
        $Params['Currency'] = $currency;
        $Url = CIC_API."/cic/StoreCredit/getStoreCarditBasicInfo";
        $Data = doCurl($Url,$Params,null,true);

        if(isset($Data['code']) && $Data['code'] == 200 && isset($Data['data'])){
//            if($currency != $Data['data']['CurrencyType']){
//                //计算汇率
//                $currencyMoney = $this->calculateRate($Data['data']['CurrencyType'],$currency,$Data['data']['UsableAmount']);
//                $currencyMoney = sprintf("%.2f", $currencyMoney);
//                return $currencyMoney;
            //}else{
            return $Data;
            //}
        }else{
            return 0;
        }
    }

    /**
     * 快捷支付计算
     * @param $_cart_info
     * @param $user_id
     * @param null $Lang
     * @param $Currency
     * @return array
     */
    public function ExpressCheckoutToShortcutcalCartInfo($_cart_info,$user_id,$Lang = null,$_currency){
        //计算各种价格($DiscountTotal = 优惠总额,$HandlingTotal = 手续价总额,$ItemsTotals = 订单的商品总额,$OrderTotal = 订单总额（包括运费等）,$ShippingTotal运费总额)
        //组装用户收货地址信息
        //组装item简单信息
        $_return_data = array();
        $_return_data['item'] = array();
        $_return_data['shiping_model'] = '';
        $_return_data['code'] = 0;
        $_return_data['msg'] = 'The operation failed. Please try again.';
        $_cart_info = isset($_cart_info[$user_id])?$_cart_info[$user_id]:array();
        $_items_totals = 0;//订单的商品总额s
        $_discount_total = 0;//优惠总额
        $_handling_total = 0;//手续价总额
        $_order_total = 0;//订单总额（包括运费等）
        $_shipping_total = 0;//运费总额
        $_check_product_info = 1;
        if($_cart_info){
            foreach ($_cart_info['StoreData'] as $k => $v){
                foreach ($v['ProductInfo'] as $k1=>$v1){
                    foreach ($v1 as $k2=>$v2){
                        $_cart_info['StoreData'][$k]['ProductInfo'][$k1][$k2]['IsBuy'] = 0;
                    }
                }
            }
        }
        //获取系统汇率数据源--支付页面不可使用缓存
        $rate_source = $this->getRateDataSource(false);
        foreach ($_cart_info['StoreData'] as $k=>$v){
            if(isset($v['ProductInfo'])){
                foreach ($v['ProductInfo'] as $k2=>$v2){
                    if(isset($v2)){
                        foreach ($v2 as $k3=>$v3){
                            if($v3['IsChecked'] && (isset($v3['ShippModelStatusType']) && $v3['ShippModelStatusType'] < 3)){
                                $_cart_info['StoreData'][$v3['StoreID']]['ProductInfo'][$v3['ProductID']][$v3['SkuID']]['IsBuy'] = 1;
                                $_coupon_discount = 0;
                                $_item_tmp = array();
                                //根据产品ID与SKUID获取商品信息
                                $ProductID = isset($v3['ProductID'])?$v3['ProductID']:0;
                                $SkuID = isset($v3['SkuID'])?$v3['SkuID']:0;
                                //$v3['ShipTo'] 区域定价 added by wangyj in 20190220
                                $ProductInfo = $this->ProductInfoByID($ProductID,$SkuID,$Lang,$_currency,$v3['ShipTo']);
                                if(isset($ProductInfo['data']['Skus'])){
                                    $ProductInfo = $ProductInfo['data'];
                                    sort($ProductInfo['Skus']);
                                }else{
                                    $_return_data['code'] = 0;
                                    $_return_data['msg'] = 'product info is error:PRODUCTID:'.$ProductID.',SKUID:'.$SkuID;
                                    return $_return_data;
                                    break;
                                }
                                //组装item信息
                                $_item_tmp['Name'] = isset($ProductInfo['Title'])?$ProductInfo['Title']:'';
                                $_tmp_price = isset($ProductInfo['Skus'][0]['SalesPrice'])?$ProductInfo['Skus'][0]['SalesPrice']:0;
                                $_tmp_price = $this->calculateRate(DEFAULT_CURRENCY,$_currency,$_tmp_price,$rate_source);//汇率转
                                $_tmp_price = sprintf("%.2f", $_tmp_price);
                                $_item_tmp['Price'] = $_tmp_price;
                                $_item_tmp['Quantity'] = isset($v3['Qty'])?$v3['Qty']:0;
                                $_item_tmp['SKU'] = isset($v3['SkuID'])?$v3['SkuID']:0;

                                //对接新payment PayPal支付 tinghu.liu 20190925
                                $_item_tmp['ProductId'] = $ProductID;
                                $_item_tmp['UnitPrice'] = $_tmp_price;
                                $_item_tmp['SkuId'] = isset($v3['SkuID'])?$v3['SkuID']:0;
                                $_item_tmp['SkuCode'] = isset($v3['SkuCode'])?$v3['SkuCode']:0;
                                $_item_tmp['Count'] = isset($v3['Qty'])?$v3['Qty']:0;

                                $_return_data['item'][] = $_item_tmp;
                                //组装物流发货信息
                                $_return_data['shiping_model'] = isset($v3['ShippingMoel'])?$v3['ShippingMoel']:'';
                                $_handling_total = 0;
                                $_items_totals += ($_item_tmp['Price']*$v3['Qty']);
                                //根据产品ID获取运费信息
                                if(is_numeric($v3['ShippingFee'])){
                                    $_shipping_total += $v3['ShippingFee'];
                                }
                                ###SKU_conpon START#############################################################
                                if(isset($v3['isUsedCoupon']['DiscountInfo']['Type']) && isset($v3['isUsedCoupon']['DiscountInfo']['Type'])){
                                    if($v3['isUsedCoupon']['DiscountInfo']['Type'] == 2){
                                        //赠品
                                        if(isset($v['isUsedCoupon']['DiscountInfo']['SkuInfo']) && count($v['isUsedCoupon']['DiscountInfo']['SkuInfo']) > 0){
                                            //不处理
                                        }
                                    }else{
                                        //折扣
                                        $tmp_price = $v3['isUsedCoupon']['DiscountInfo']['DiscountPrice'];
                                        $_coupon_discount = $this->calculateRate(DEFAULT_CURRENCY,$_currency,$tmp_price,$rate_source);
                                        $CouponTmp ['coupon_id'] = $v3['isUsedCoupon']['CouponId'];
                                        $CouponTmp ['captured_discount'] = $_coupon_discount;
                                        //$CouponTmp ['StoreId'] = $k;
                                        $CouponTmp ['sku_id'] = $v3['SkuID'];
                                        $CouponTmp ['create_on'] = time();
                                        //如果当前贷币不是美元，则需要用当前币种转换成美元
                                        $CouponTmp ['USD_discount'] = $tmp_price;//以美元为单位的优惠额度
                                        $_data['slave'][$k]['coupon'][] = $CouponTmp;

                                    }
                                }
                                //获取可供选择的优惠信息和计算价格
                                $_product_price_info = $this->getProductPrice($ProductInfo,$SkuID,$v3['Qty']);
                                $_product_price = $this->calculateRate(DEFAULT_CURRENCY,$_currency,$ProductInfo['Skus'][0]['SalesPrice'],$rate_source);
                                $_product_price = sprintf("%.2f", $_product_price);
                                //标记该sku使用了批发价还是活动价
                                $_active_price = $_product_price;
                                //产品的售价(不折扣不优惠)
                                $_goods_count = $v3['Qty'];//SKU里的SKU个数
                                if(isset($v3['active_type'])){//如果有活动价,或者批发价
                                    //标记该sku使用了批发价还是活动价
                                    if(isset($_product_price_info['code']) && $_product_price_info['code']){
                                        $_active_price = $_product_price_info['product_price'];
                                        //转换汇率
                                        $_active_price = $this->calculateRate(DEFAULT_CURRENCY,$_currency,$_active_price,$rate_source);
                                        $_active_price = sprintf("%.2f", $_active_price);
                                    }
                                    $_discount_total_tmp = $_product_price - $_active_price;//原价减掉活动价，等于折扣的价钱
                                    $_discount_total += ($_discount_total_tmp*$v3['Qty']);
                                }
                                if($_coupon_discount){
                                    $_discount_total += $_coupon_discount;//总的折扣(包括起批，活动，coupon)
                                }
                                $_items_totals = sprintf("%.2f", $_items_totals);
                                $_shipping_total = sprintf("%.2f", $_shipping_total);
                                $_discount_total = sprintf("%.2f", $_discount_total);
                            }
                        }
                    }
                }
                $_order_total = $_items_totals + $_shipping_total - $_discount_total;
                $_return_data['code'] = 1;
                $_return_data['discount_total'] = $_discount_total;
                $_return_data['handling_total'] = sprintf("%.2f", $_handling_total);
                $_return_data['items_totals'] = $_items_totals;
                $_return_data['order_total'] = sprintf("%.2f", $_order_total);
                $_return_data['shipping_total'] = $_shipping_total;
            }
        }
        $_cart_info_new[$user_id] = $_cart_info;
        $this->loadRedis()->set("ShoppingCart_".$user_id,$_cart_info_new);
        return $_return_data;
    }

    /**
     * 订单产品描述特殊处理
     * @param $product_attr_desc
     * @return string
     * ram:8GB,color:Grey green|//photo.dxinterns.com/productimages/20180719/2332a466cea9bb36ec6569c675671797.png
     */
    public function handleOrderProductaAttrDesc($product_attr_desc){
        $new_arr = [];
        $product_attr_desc_arr = explode(',', $product_attr_desc);
        foreach ($product_attr_desc_arr as $attr_info){
            $arr = explode('|', $attr_info);
            if (count($arr) >= 2){
                $arr[1] = '<img src="'.$arr[1].'">';
                $new_arr[] = implode('',$arr);
            }else{
                $new_arr[] = $attr_info;
            }
        }
        return implode(',',$new_arr);
    }

    /**
     * 根据pay_token获取订单信息
     * @param $pay_token
     * @return array
     */
    public function getOrderBaseInfoByPayToken($pay_token){
        $data = [];
        if (!empty($pay_token)){
            $_params['pay_token'] = $pay_token;
            //使用用户地址ID的处理
            $Url = MALL_API.'/orderfrontend/Order/getOrderBaseInfoByPayToken';
            $res = doCurl($Url,$_params,null,true);
            if($res['code'] == 200){
                $data = (isset($res['data']) && !empty($res['data']))?$res['data']:[];
            }else{
                Log::record('getOrderBaseInfoByPayToken is error, params:'.json_encode($_params).',res:'.json_encode($res), Log::ERROR);
            }
        }
        return $data;
    }

    /**
     * 发送邮件【订单相关错误】
     * @param $_params
     * @author tinghu.liu 20190408
     * @return bool
     */
    public function sendEmailForOrderBug($_params){
        if (!isset($_params['title']) || !isset($_params['content'])){
            Log::record('sendEmailForOrderBug - params is error, params:'.json_encode($_params));
            return true;
        }
        $params['to_email'] = $this->getSendEmailDataForOrderBug();
        $params['title'] = $_params['title'];
        $params['content'] = $_params['content'];
        $res = doCurl(SHARE_API.'share/EmailHandle/sendEmail',$params,null,true);
        if(!isset($res['code']) || $res['code'] != 200){
            Log::record('sendEmailForOrderBug - is error, params:'.json_encode($params).', res:'.json_encode($res), 'error');
        }
    }

    /**
     * 根据主单号获取paytoken信息
     * @param $order_master_number
     * @return array
     */
    public function getPayTokenInfoByOrderMasterNumber($order_master_number){
        $data = [];
        if (!empty($order_master_number)){
            $_params['order_master_number'] = $order_master_number;
            //使用用户地址ID的处理
            $Url = MALL_API.'/orderfrontend/Order/getPayTokenInfoByOrderMasterNumber';
            $res = doCurl($Url,$_params,null,true);
            Log::record('getPayTokenInfoByOrderMasterNumber:'.json_encode($res));
            if($res['code'] == 200){
                $data = isset($res['data'])?$res['data']:[];
            }else{
                Log::record('getPayTokenInfoByOrderMasterNumber is error, params:'.json_encode($_params).',res:'.json_encode($res), Log::ERROR);
            }
        }
        return $data;
    }

    /**
     * 购物车自动使用coupon
     * @param $customer_id 用户ID（也有可能是游客ID）
     * @param $store_id 店铺ID，没有则传 0
     * @param $country
     * @param $lang
     * @param $currency
     * @param CouponService $coupon_service
     * @return array
     */
    public function autoUseCouponForCart($customer_id, $store_id, $country, $lang, $currency, CouponService $coupon_service ){
        $rtn_data = [];
        //设置自动使用coupon缓存，在购物车：数量变化、选中和非选中、删除，添加购物车时候删除缓存
        //不使用缓存，因为会存在限制coupon重复使用的情况
//        $cache_key = $this->getAutoUseCouponForCartCacheKey($customer_id, $store_id, $country, $currency);
        try{
//            $rtn_data = $this->loadRedis()->get($cache_key);
//            if (!empty($rtn_data)){
//                return $rtn_data;
//            }
            $cart_info = $this->loadRedis()->get("ShoppingCart_".$customer_id);
            $cart_info = isset($cart_info[$customer_id]['StoreData'])?$cart_info[$customer_id]['StoreData']:[];
            if (empty($cart_info)){
                return $rtn_data;
            }
            /******** 获取最大使用coupon折扣且自动使用coupon start *********/
            $temp_use_coupon_data = [];
            /**
             * 一个Coupon可以多个店铺使用，但规则限制是“每人一次、每人每天一次”，则只能自动使用成功一次
             * ReceiveLimit 领取限制：1-不限、2-每人一次、3-每人每天一次
             * tinghu.liu 20191101
             */
            //多个店铺互斥使用，已自动使用的CouponId
            $tmep_limit_single_use = [];
            /**
             * 如果是指定店铺自动使用coupon，需要将其他店铺已经使用的coupon（是“每人一次、每人每天一次”的情况）和指定店铺计算的Coupon对比，如果指定店铺计算的Coupon存在其他店铺已经使用的情况，需要去除掉指定店铺计算的Coupon，跳往下一个Coupon进行计算
             * tinghu.liu 20191101
             */
            $tmep_other_limit_single_use = [];
            if (!empty($store_id)){
                //获取其他店铺已经使用的Coupon（是“每人一次、每人每天一次”的情况）
                foreach ($cart_info as $k200=>$v200){
                    if (
                        $k200 != $store_id
                        && isset($v200['isUsedCoupon']['CouponId'])
                        && !empty($v200['isUsedCoupon']['CouponId'])
                        && isset($v200['isUsedCoupon']['DiscountInfo']['ReceiveLimit'])
                        && in_array($v200['isUsedCoupon']['DiscountInfo']['ReceiveLimit'], [2,3])
                    ){
                        $tmep_other_limit_single_use[] = $v200['isUsedCoupon']['CouponId'];
                    }
                }
            }
            foreach ($cart_info as $k=>$v){
                $_store_id = $k;
                //如果传了$store_id，则只处理指定的店铺数据
                if (!empty($store_id)){
                    if ($store_id != $_store_id){
                        continue;
                    }
                }
                if (isset($v['coupon']) && !empty($v['coupon'])){
                    $_coupon_data = $v['coupon'];
                    foreach ($_coupon_data as $k1=>$v1){
                        $_coupon_info = $v1;
                        $_coupon_id = $_coupon_info['CouponId'];
                        //领取限制：1-不限、2-每人一次、3-每人每天一次
                        $_receive_limit = isset($_coupon_info['ReceiveLimit'])?$_coupon_info['ReceiveLimit']:1;
                        if (isset($_coupon_info['isUsable']) && $_coupon_info['isUsable'] == 1){
                            //如果Coupon只能使用一次且已经计算使用过的情况（即互斥情况）时，不能再次使用，避免重复使用Coupon
                            if (in_array($_coupon_id, $tmep_limit_single_use)){
                                continue;
                            }
                            //单店铺自动使用，且改Coupon规则为“每人一次、每人每天一次”且其他店铺已经使用，则不自动计算 tinghu.liu 20191101
                            if (!empty($store_id)){
                                if (in_array($_coupon_id, $tmep_other_limit_single_use)){
                                    continue;
                                }
                            }
                            //判断是否符合使用条件，符合则记录下来，最后返回折扣最大的用于自动使用
                            $_use_params['StoreId']         =  $_store_id;
                            $_use_params['DiscountLevel']   =  2;
                            $_use_params['CouponId']        =  $_coupon_id;

                            $_use_params['type']            =  1;
                            $_use_params['CouponCode']      =  '';
                            $_use_params['customer_id']     =  $customer_id;
                            $_use_params['CouponRuleType']  =  [1,2];
                            $_use_params['country_code']    =  $country;
                            $_use_params['Lang']            =  $lang;
                            $_use_params['Currency']        =  $currency;
                            $_use_params['FromFlag']        =  1;

                            $_use_res = $coupon_service->useCoupon($customer_id, $_use_params);
                            //配出赠送券
                            if (!empty($_use_res) && isset($_use_res['code']) && isset($_use_res['Type']) && $_use_res['Type'] != 2 && isset($_use_res['DiscountPrice']) && $_use_res['code'] == 1){
                                //后面直接使用且更新购物车
                                unset($_use_params['FromFlag']);
                                $_use_res['use_params'] = $_use_params;
                                $temp_use_coupon_data[$_store_id][] = $_use_res;
                                //记录互斥使用Coupon
                                if (in_array($_receive_limit, [2,3])){
                                    $tmep_limit_single_use[] = $_coupon_id;
                                }
                            }
                        }
                    }
                }
            }
            //排序，获取最大coupon优惠用于自动使用
            if (!empty($temp_use_coupon_data)){
                foreach ($temp_use_coupon_data as $k100=>$v100){
                    $store_id_100 = $k100;
                    array_multisort($temp_use_coupon_data[$k100],SORT_DESC, array_column($temp_use_coupon_data[$k100],'DiscountPrice'));
                    //最大优惠折扣自动使用
                    if (isset($temp_use_coupon_data[$store_id_100][0]) && isset($temp_use_coupon_data[$k100][0]['use_params'])){
                        $use_params100 = $temp_use_coupon_data[$k100][0]['use_params'];
                        $tem100 = [];
                        $tem100['use_params'] = [
                            'store_id'           =>$use_params100['StoreId'],
                            'coupon_id'          =>$use_params100['CouponId'],
                            'discount_level'     =>$use_params100['DiscountLevel'],
                        ];
                        $use_data=$tem100['use_data'] = $coupon_service->useCoupon($customer_id, $use_params100);
                        //$rtn_data[$store_id_100] = $tem100;
                        $tem99 = [
                            'store_id'           =>$use_params100['StoreId'],
                            'coupon_id'          =>$use_params100['CouponId'],
                            'DiscountPrice'     =>!empty($use_data['DiscountPrice'])?$use_data['DiscountPrice']:'',
                            'Name'     =>!empty($use_data['Name'])?$use_data['Name']:'',
                        ];
                        $rtn_data[] = $tem99;
                    }
                }
            }
//            if (!empty($rtn_data)){
//                //自动使用coupon缓存
//                $this->loadRedis()->set($cache_key, $rtn_data, CACHE_HOUR*2);
//            }
            /******** 获取最大使用coupon折扣且自动使用coupon end *********/
        }catch (Exception $e){
            $err_msg = '购物车自动使用coupon异常，异常信息：'.$e->getMessage().', 基本信息：'.$e->getFile().'['.$e->getLine().']';
            logService::write(LOGS_MALL_CART, 'error', __METHOD__, 'autoUseCoupon',[
                'customer_id'   =>$customer_id,
                'store_id'      =>$store_id,
                'country'       =>$country,
                'lang'          =>$lang,
                'currency'      =>$currency,
            ],null, $err_msg,$customer_id);
        }
        return $rtn_data;
    }

    /**
     * 获取购物车自动使用coupon缓存key
     * @param $customer_id
     * @param $store_id
     * @param $country
     * @param $currency
     * @return string
     */
    protected function getAutoUseCouponForCartCacheKey($customer_id, $store_id, $country, $currency){
        return 'AUC_'.md5('autoUseCouponForCart'.$customer_id.$store_id.$country.$currency);
    }

    /**
     * 清除购物车自动使用coupon缓存
     * 以下情况触发清除缓存操作：
     *      1、购物车：数量变化、选中非选中、删除商品；
     *      2、产品详情页添加购物车。
     * @param $customer_id
     * @param $store_id
     * @param $country
     * @param $currency
     * @return string
     */
    public function clearAutoUseCouponForCartCache($customer_id, $store_id, $country, $currency){
        //不使用缓存，因为会存在限制coupon重复使用的情况（比如一个coupon使用次数为10次，但由于缓存就有可能会存在使用次数超过10次的情况）
        return true;
        $cache_key = $this->getAutoUseCouponForCartCacheKey($customer_id, $store_id, $country, $currency);
        //每次清除都需要将初始化的自动使用coupon清除掉，因为每一个变化都会影响初始化自动coupon使用数据
        $cache_key0 = '';
        if ($customer_id != 0){
            $cache_key0 = $this->getAutoUseCouponForCartCacheKey($customer_id, 0, $country, $currency);
        }
        if (!empty($cache_key0)){
            return ($this->loadRedis()->rm($cache_key)) && ($this->loadRedis()->rm($cache_key0));
        }else{
            return $this->loadRedis()->rm($cache_key);
        }
    }

    /**
     * 获取产品价格 - 【使用coupon用，为了解决coupon和批发价互斥问题】
     * @param $ProductID
     * @param $SkuID
     * @param $Qty
     * @param $Lang
     * @param $Currency
     * @param $ShipTo
     * @param $Flag  标识：0-正常，1-来至使用coupon获取价格（排除批发价）
     * @param array $ProductInfo  产品信息
     * @param boolean $IsCache
     * @return bool|int
     */
    public function getProductPriceForCoupon($ProductID, $SkuID, $Qty, $Lang, $Currency, $ShipTo, $Flag, $ProductInfo=[],$IsCache = false){
        //获取产品数据
        if (empty($ProductInfo)){
            $ProductInfo = $this->ProductInfoByID($ProductID, $SkuID, $Lang, $Currency,$ShipTo,$IsCache);//false不使用缓存
            if(!isset($ProductInfo['code']) || $ProductInfo['code'] != 200 || !isset($ProductInfo['data']) || count($ProductInfo['data']) < 1){
                //没有找到数据
                Log::record('coupon使用获取产品数据失败：'.$ProductID.'-'.$SkuID.'-'.$Lang.'-'.$Currency.'-'.$ShipTo.', res'.json_encode($ProductInfo), Log::NOTICE);
                return false;
            }
        }
        $ProductInfo = $ProductInfo['data'];
        sort($ProductInfo['Skus']);

        //获取产品价格（排斥批发价）
        $_product_price_info = $this->getProductPrice($ProductInfo, $SkuID,$Qty , $Flag);

        if(!isset($_product_price_info['code']) || $_product_price_info['code'] != 1){
            Log::record('coupon使用获取产品价格（排斥批发价）失败：'.$ProductID.'-'.$SkuID.'-'.$Lang.'-'.$Currency.'-'.$ShipTo.', res'.json_encode($_product_price_info), Log::NOTICE);
            return false;
        }
        return $_product_price_info;
    }

    /**
     * 处理运输方式，主要作用是加上NOCNOC
     * @param $params
     * @param $handler
     * @param int $rate 汇率
     * @return mixed
     */
    public function countProductShipping($params,$handler,$rate = 0){
        if ($rate != 0){
            $ShippingInfo = $handler->countProductShipping($params, $rate);
        }else{
            $ShippingInfo = $handler->countProductShipping($params);
        }
        $Len = count($ShippingInfo);
//        if(isset($params['country']) && in_array($params['country'],config('nocnoc_country'))){
//            $ShippingInfo[$Len]['Cost'] = 0;
//            $ShippingInfo[$Len]['TrackingInformation'] = 'Available';
//            $ShippingInfo[$Len]['ShippingFee'] = 0;
//            $ShippingInfo[$Len]['ShippingService'] = 'NOCNOC';
//            $ShippingInfo[$Len]['EstimatedDeliveryTime'] = '';
//        }
        return $ShippingInfo;
    }
}
