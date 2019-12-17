<?php
namespace app\app\services;

use app\common\helpers\CommonLib;
use app\app\model\ProductActivityModel;
use app\app\model\ProductClassModel;
use app\app\model\ProductModel;
use think\Cache;
use think\Exception;
use think\Request;


/**
 * 活动产品数据
 */
class ProductActivityService extends BaseService
{
    const RESULT_COUNT = 20;//取数缓存

    /**
     * 活动进行中的产品数据
     * @param $params
     * @return array|false|null|\PDOStatement|string|\think\Model
     */
    public function getOnSaleList($params){
        $params['lang'] = isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;
        $params['currency'] = isset($params['currency']) ? $params['currency'] : DEFAULT_CURRENCY;

        $result = array();
        //获取活动产品ID
        $activity = (new ProductActivityModel())->getActivity(['current_time'=>time(),'type'=>'5','status'=>3]);
        if(!empty($activity)){
            $params['activity_id'] = $activity['_id'];
            //找出活动销售第一的产品
            $salsFirst = (new ProductModel())->findProduct(['activity_id'=>$activity['_id'],'activitySalse'=>true]);
            $result = (new ProductModel())->selectActivityProduct($params);
            if(!empty($result['data'])){
                if(!empty($salsFirst)){
                    foreach($result['data'] as $key => $v){
                        if($v['_id'] == $salsFirst['_id']){
                            unset($result['data'][$key]);
                        }
                    }
                    array_unshift($result['data'],$salsFirst);
                    array_values($result['data']);
                }
                $result['data'] = $this->getFlashData($result['data'],$params);
                //币种切换
                if(!empty($result['data']) && is_array($result['data'])){
                    if($params['currency'] != DEFAULT_CURRENCY){
                        $currentRate =  $this->getCurrencyRate($params['currency']);
                        if(!empty($currentRate)){
                            foreach($result['data'] as $key => $val){
                                if(isset($val['OriginalPrice']) && !empty($val['OriginalPrice']) && $val['OriginalPrice'] != '0.00'){
                                    $result['data'][$key]['OriginalPrice'] = sprintf("%01.2f",(double)$val['OriginalPrice'] * $currentRate);
                                }
                                if(isset($val['SalesPrice']) && !empty($val['SalesPrice']) && $val['SalesPrice'] != '0.00'){
                                    $result['data'][$key]['SalesPrice'] = sprintf("%01.2f",(double)$val['SalesPrice'] * $currentRate);
                                }
                            }
                        }
                    }
                }
                //当前活动剩余时间
                $result['remainingTime'] = $activity['activity_end_time'] - time();
            }
        }
        return $result;
    }


    /**
     * 下一场活动开始的产品数据
     * @param $params
     * @return array|false|null|\PDOStatement|string|\think\Model
     */
    public function getComingSoonLists($params){
        $params['lang'] = isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;
        $params['currency'] = isset($params['currency']) ? $params['currency'] : DEFAULT_CURRENCY;
	    $country = isset($params['country']) ? $params['country'] : null;//国家区域售价
        $result = array();
        //获取活动产品ID
        $activity = (new ProductActivityModel())->getActivity(['soon_time'=>time(),'type'=>'5','status'=>5]);
        if(!empty($activity)){
            $currentRate = '';
            if($params['currency'] != DEFAULT_CURRENCY){
                $currentRate =  $this->getCurrencyRate($params['currency']);
            }

            $activityResult = (new ProductActivityModel())->getActivityProduct(['activity_id'=>$activity['_id']]);
            if($activityResult){
                $flashData = array();
                $spu = CommonLib::getColumn('SPU',$activityResult);
                $params['product_id'] = CommonLib::supportArray($spu);
                $result = (new ProductModel())->selectActivityProduct($params);
                if(!empty($result['data'])){
                    $lang = $params['lang'];
                    foreach($result['data'] as $k => $product){
                        $activityProduct = CommonLib::filterArrayByKey($activityResult,'SPU',$product['_id']);
                        //产品id
                        $flashData[$k]['id'] = isset($product['_id']) ? $product['_id'] : '';
                        //首图
                        $flashData[$k]['FirstProductImage'] = isset($product['FirstProductImage']) ? $product['FirstProductImage'] : '';
                        if(empty($flashData[$k]['FirstProductImage'])){
                            $flashData[$k]['FirstProductImage'] = isset($product['ImageSet']['ProductImg'][0]) ? $product['ImageSet']['ProductImg'][0] : '';
                        }
                        //链接地址组合
                        $flashData[$k]['LinkUrl'] ='/p/'.$product['RewrittenUrl'].'-'.$product['_id'];//链接地址
                        //标题
                        $flashData[$k]['Title'] = isset($product['Title']) ? $product['Title'] : '';
                        //语言切换 --公共方法
                        if(self::DEFAULT_LANG != $lang){
                            $productMultiLang = $this->getProductMultiLang($product['_id'],$lang);
                            $flashData[$k]['Title'] = isset($productMultiLang['Title'][$params['lang']]) ? $productMultiLang['Title'][$params['lang']] : $product['Title'];//默认英语
                        }

                        //国家区域价格
                        if(!empty($country)){
                            $regionPrice = $this->getProductRegionPrice($product['_id'],$country);
                            //这个产品有国家区域价格
                            if(!empty($regionPrice)){
                                $this->handleProductRegionPrice($product,$regionPrice);
                            }
                        }

                        //原价的价格区间
                        $originalLowPrice = !empty($product['LowPrice']) ? (string)$product['LowPrice'] : '';//最低价格
                        $originalHightPrice = !empty($product['HightPrice']) ? (string)$product['HightPrice'] : '';//最高价

                        //折扣后的价格区间
                        $discountLowPrice = !empty($activityProduct['DiscountLowPrice']) ? (string)$activityProduct['DiscountLowPrice'] : '';//最低价格
                        $discountHightPrice = !empty($activityProduct['DiscountHightPrice']) ? (string)$activityProduct['DiscountHightPrice'] : '';//最高价

                        //价格逻辑处理
                        $priceArray = $this->commonProductPrice($originalLowPrice,$originalHightPrice,$discountLowPrice,$discountHightPrice);
                        //商品展示的销售价格
                        $flashData[$k]['SalesPrice'] = $priceArray['LowPrice'];
                        //原价
                        $flashData[$k]['OriginalPrice'] = $priceArray['OriginalLowPrice'];

                        if($params['currency'] != DEFAULT_CURRENCY){
                            if(!empty($currentRate)){
                                //商品展示的销售价格
                                $flashData[$k]['SalesPrice'] = sprintf("%01.2f",(double)$flashData[$k]['SalesPrice'] * $currentRate);
                                //原价
                                $flashData[$k]['OriginalPrice'] = sprintf("%01.2f",(double)$flashData[$k]['SalesPrice'] * $currentRate);
                            }
                        }
                        //折扣
                        $flashData[$k]['Discount'] = isset($activityProduct['HightDiscount']) ? (string)$activityProduct['HightDiscount'] : '';
                        //折扣按市场价格算
                        if(!empty($activityProduct['LowListPrice'])){
                            $result[$k]['Discount'] = (string)round($priceArray['LowPrice']/$activityProduct['LowListPrice'],2);
                        }
                        $flashData[$k]['firstClassId'] = (int)$product['FirstCategory'];
                        $flashData[$k]['isActivity'] = isset($product['IsActivity']) ? (int)$product['IsActivity'] : 0;
                        $flashData[$k]['isMvp'] = (int)$product['IsMVP'];
                        //币种符号
                        $flashData[$k]['currencyCode'] = DEFAULT_CURRENCY;
                        $flashData[$k]['currencyCodeSymbol'] = DEFAULT_CURRENCY_CODE;
                        if(self::DEFAULT_CURRENCY != $params['currency']) {
                            $flashData[$k]['currencyCode'] = $params['currency'];
                            $flashData[$k]['currencyCodeSymbol'] = $this->getCurrencyCode($params['currency']);
                        }
                        //flashDeals产品肯定是折扣产品，展示折扣图标
                        $flashData[$k]['tagName'] = 'tag-discount';
                        //下一场活动未开始，进度条为0
                        $flashData[$k]['TimeGone'] = 0;
                    }
                }
                $result['data'] = $flashData;
                $result['soonTime'] = isset($activity['activity_start_time']) ? $activity['activity_start_time'] - time() : '';
            }
        }
        return $result;
    }

    /**
     * 首页活动进行中的产品数据
     * @param $params
     * @return array|false|null|\PDOStatement|string|\think\Model
     */
    public function getHomeFlash($params){
        $country = isset($params['country']) ? $params['country'] : '';
        $params['lang'] = isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;
        $params['currency'] = isset($params['currency']) ?  $params['currency'] : DEFAULT_CURRENCY;
        $time = time();
        $product = array();
        if(config('cache_switch_on')) {
            $product = $this->redis->get(HOME_FLASH_DATA.'_'.$params['lang'].'_'.$country);
        }
        if(empty($product)){
            //当前时间是否有活动
            $activity = (new ProductActivityModel())->getActivity(['current_time'=>$time,'type'=>'5','status'=>3]);
            if(!empty($activity)){
                //格式化产品ID
                $params['activity_id'] = $activity['_id'];
                $params['page_size'] = self::RESULT_COUNT;
                $params['salesCounts'] = true;
                $params['salesRank'] = true;
                $result = (new ProductModel())->selectActivityProduct($params);
                if(isset($result['data']) && !empty($result['data'])){
                    $product['product'] = $this->getFlashData($result['data'],$params);
                    //活动剩余的时间
                    $product['time'] = $activity['activity_end_time'] - $time;

                    $this->redis->set(HOME_FLASH_DATA.'_'.$params['lang'].'_'.$country,$product,$product['time']);
                }
            }
        }

        //币种切换
        if(isset($product['product']) && !empty($product['product'])){
            if($params['currency'] != DEFAULT_CURRENCY){
                //币种切换费率
                $currentRate =  $this->getCurrencyRate($params['currency']);
                if(!empty($currentRate)){
                    foreach($product['product'] as $key => $val){
                        if(isset($val['OriginalPrice']) && !empty($val['OriginalPrice']) && $val['OriginalPrice'] != '0.00'){
                            $product['product'][$key]['OriginalPrice'] = sprintf("%01.2f",(double)$val['OriginalPrice'] * $currentRate);
                        }
                        if(isset($val['SalesPrice']) && !empty($val['SalesPrice']) && $val['SalesPrice'] != '0.00'){
                            $product['product'][$key]['SalesPrice'] = sprintf("%01.2f",(double)$val['SalesPrice'] * $currentRate);
                        }
                    }
                }
            }
        }

        return apiReturn(['code'=>200, 'data'=>$product]);

    }

    /**
     * 第一场次的剩余时间
     * 第二场次的开始时间
     * @return mixed
     */
    public function getActivityTime($params){

        $time = time();
        //当前时间是否有活动
        $sale = (new ProductActivityModel())->getActivity(['current_time'=>$time,'type'=>'5','status'=>3]);

        $result['saleTime'] = isset($sale['activity_end_time']) ? $sale['activity_end_time'] - $time : '';
//        if(!empty($sale)){
//            $params['activity_id'] = $sale['_id'];
//            $product = (new ProductModel())->selectActivityProduct($params);
//            if(empty($product['data'])){
//                $result['saleTime'] = '';
//            }
//        }
        //一级分类，产品数量列表
//        if(!empty($result['saleTime'])){
//            $result['saleClass'] = $this->getActivityCategoryCount($sale,$params['lang']);
//        }
        $soon = (new ProductActivityModel())->getActivity(['soon_time'=>$time,'type'=>'5','status'=>5]);
        $result['soonTime'] = isset($soon['activity_start_time']) ? $soon['activity_start_time'] - $time : '';
//        if(!empty($soon)){
//            $params['activity_id'] = $soon['_id'];
//            $product = (new ProductModel())->selectActivityProduct($params);
//            if(empty($product['data'])){
//                $result['soonTime'] = '';
//            }
//        }
        //一级分类，产品数量列表
//        if(!empty($result['soonTime'])){
//            $result['soonClass'] = $this->getActivityCategoryCount($soon,$params['lang']);
//        }
        return apiReturn(['code'=>200, 'data'=>$result]);
    }

    /**
     * flashDeals活动 对应的产品分类数量
     * @param $activity
     * @param $lang
     * @return array|false|\PDOStatement|string|\think\Model
     */
    private function getActivityCategoryCount($activity,$lang){
        $class_list = array();
        $productModel = new ProductModel();
        if(!empty($activity)){
            $countData = $productModel->groupByProductCategory(null,'$FirstCategory',$activity['_id']);
            if(!empty($countData)){
                $countData = json_decode(json_encode($countData),true);
                //格式化
                $class_id = CommonLib::supportArray(CommonLib::getColumn('_id',$countData));
                //获取分类详情
                $class_list = (new ProductClassModel())->selectClass(['class_id' =>$class_id,'lang'=>$lang]);
                if(!empty($class_list)){
                    foreach($class_list as $key => $class){
                        $count = CommonLib::filterArrayByKey($countData,'_id',$class['id']);
                        //后面多语言判断，更换
                        $class_list[$key]['title'] = $class['title_en'];
                        $class_list[$key]['count'] = $count['count'];
                    }
                }
            }
        }
        return $class_list;
    }

    /**
     * 移动端首页FlashDeal数据  不够用市场价叠加，总共取300 --张勇需求
     */
    public function getHomeFlashDeals($params){

        $result = array();

        $cacheKey = CommonLib::getCacheKey($params);
        if(config('cache_switch_on')) {
            $result = $this->redis->get('APP_HOME_FLASHDATA_'.$cacheKey);
        }
        if(empty($result)){
            $request = doCurl(API_SHARE_URL . '/mall/productActivity/mobileHomeProducts', $params, [
                'access_token' => $this->getAccessToken()
            ]);

            if($request['code'] != 200){
               // logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,$params,MALL_API.'/mall/productActivity/mobileHomeProducts',$request);
                return array();
            }
            $result = $request['data'];
            if(!empty($result['data']) && is_array($result['data'])){
                $this->redis->set('APP_HOME_FLASHDATA_'.$cacheKey,$result,$result['remainingTime']);
            }
        }

        //币种切换
        if(!empty($result['data']) && is_array($result['data'])){

            //图片替换
            $result['data'] = handleProductImgBySize($result['data']);

            if($params['currency'] != DEFAULT_CURRENCY) {
                $currentRate = (new rateService())->getCurrentRate($params['currency']);
            }
            foreach($result['data'] as $key => $val){
                $result['data'][$key]['OriginalLowPrice'] = $val['OriginalPrice'];
                $result['data'][$key]['OriginalHightPrice'] = '';
                $result['data'][$key]['LowPrice'] = $val['SalesPrice'];
                $result['data'][$key]['HightPrice'] = '';
                if(!empty($val['Discount'])){
                    $result['data'][$key]['Discount'] = 1 - $val['Discount'];
                }
                //币种符号
                $result['data'][$key]['currencyCode'] = DEFAULT_CURRENCY;
                $result['data'][$key]['currencyCodeSymbol'] = DEFAULT_CURRENCY_CODE;
                if(self::DEFAULT_CURRENCY != $params['currency']) {
                    $result['data'][$key]['currencyCode'] = $params['currency'];
                    $result['data'][$key]['currencyCodeSymbol'] = $this->getCurrencyCode($params['currency']);
                }
                unset($result['data'][$key]['OriginalPrice'],$result['data'][$key]['SalesPrice']);
                if($params['currency'] != DEFAULT_CURRENCY) {
                    if (isset($val['OriginalPrice']) && !empty($val['OriginalPrice']) && $val['OriginalPrice'] != '0.00') {
                        $result['data'][$key]['OriginalLowPrice'] = sprintf("%01.2f", (double)$val['OriginalPrice'] * $currentRate);
                    }
                    if (isset($val['SalesPrice']) && !empty($val['SalesPrice']) && $val['SalesPrice'] != '0.00') {
                        $result['data'][$key]['LowPrice'] = sprintf("%01.2f", (double)$val['SalesPrice'] * $currentRate);
                    }
                }
            }

        }else{
            $result['data'] = array();
        }

        //打乱产品
        shuffle($result['data']);
        return $result;
    }

    /**
     * 获取当前商品可用的coupon列表
     */
    public function getSellerCoupon($params){
        $result['auto'] = $result['manual'] = array();
        $request = doCurl(API_SHARE_URL . '/mall/coupon/getCouponList',$params, [
            'access_token' => $this->getAccessToken()
        ]);

        if($request['code'] != 200){
            return $result;
        }
        if(!empty($request['data'])){
            $result['auto'] = isset($request['data']['auto']) ? array_values($request['data']['auto']) : [];
            $result['manual'] = isset($request['data']['manual']) ? array_values($request['data']['manual']) : [];
        }
        return $result;
    }

    /**
     * 根据coupoid 获取coupon列表
     */
    public function getIndexCouponByIds($params){
        $result = doCurl(API_SHARE_URL.'/mall/coupon/getCouponListByIds',$params,[
            'access_token' => $this->getAccessToken()
        ]);
        //请求后日志
        if($result['code'] != 200){
            //logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,$params,MALL_API.'/mall/coupon/getHomeCouponList',$result);
        }
        return $result;
    }
    
    /**
     * 新增优惠券
     */
    public function addCustomerCoupon($params){
        $request = doCurl(API_SHARE_URL . '/mall/coupon/addCoupon',$params, [
            'access_token' => $this->getAccessToken()
        ]);
        if($request['code'] != 5010001){
            $request['msg'] = lang('tips_'.$request['code']);
            $request['code'] = 200;
            return $request;
        }
        $request['msg'] = lang('tips_'.$request['code']);
        $request['code'] = 200;
        return $request;
    }

    /**
     * 活动产品列表
     */
    public function getActivityProductList($params){
        $result = array();
        $cacheKey = CommonLib::getCacheKey($params);
        if(config('cache_switch_on')) {
            $result = $this->redis->get('APP_ACTIVITY_PRODUCT_LIST_'.$cacheKey);
        }
        if(empty($result)){
            $request = doCurl(MALL_API . '/mall/productActivity/getActivityProductList', $params, [
                'access_token' => $this->getAccessToken()
            ]);
            if($request['code'] != 200){
                //logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,$params,MALL_API.'/mall/productActivity/getActivityProductList',$request);
                return array();
            }
            $result = $request['data'];
            if(!empty($result['data']) && is_array($result['data'])){
                $this->redis->set('APP_ACTIVITY_PRODUCT_LIST_'.$cacheKey,$result,CACHE_HOUR);
            }
        }
        $currency=$params['currency'];
        //币种切换
        if(!empty($result['data']) && is_array($result['data'])){
            $currentRate =  $this->getCurrencyRate($params['currency']);
            $currencyCodeSymbol=$this->getCurrencyCode($currency);
            //币种符号
            if($params['currency'] != DEFAULT_CURRENCY){
                foreach($result['data'] as $key => $val){
                    if(isset($val['OriginalPrice']) && !empty($val['OriginalPrice']) && $val['OriginalPrice'] != '0.00'){
                        $result['data'][$key]['OriginalPrice'] = sprintf("%01.2f",(double)$val['OriginalPrice'] * $currentRate);
                    }
                    if(isset($val['SalesPrice']) && !empty($val['SalesPrice']) && $val['SalesPrice'] != '0.00'){
                        $result['data'][$key]['SalesPrice'] = sprintf("%01.2f",(double)$val['SalesPrice'] * $currentRate);
                    }
                    $result['data'][$key]['currencyCode'] = $currency;
                    $result['data'][$key]['currencyCodeSymbol'] =$currencyCodeSymbol;
                }
            }else{
                foreach($result['data'] as $key => $val){
                    $result['data'][$key]['currencyCode'] = $currency;
                    $result['data'][$key]['currencyCodeSymbol'] = $currencyCodeSymbol;
                }
            }
        }
        return $result;
    }

    /**
     * flashDeals 活动时间页面数据
     */
    public function getActivityTitle($params){
        $result = array();
        if(config('cache_switch_on')) {
            $result = $this->redis->get('APP_ALL_FLASHDEAL_DATA_'.$params['lang']);
        }
        if(empty($result)){
            $request = doCurl(MALL_API . '/mall/productActivity/getActivityTitle', $params, [
                'access_token' => $this->getAccessToken()
            ]);
            if($request['code'] != 200){
               // logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,null,MALL_API.'/mall/productActivity/getActivityTime',$request);
                return array();
            }
            if(!empty($request['data'])){
                $result = $request['data'];
                $this->redis->set('APP_ALL_FLASHDEAL_DATA_'.$params['lang'],$result,CACHE_FIVE_MIN);
            }
        }
        return $result;
    }
}
