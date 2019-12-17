<?php
namespace app\app\controller;

use app\common\controller\AppBase;
use app\common\helpers\RedisClusterBase;
use app\common\params\mall\ActivityParams;
use app\app\services\ProductActivityService;
use app\common\params\ProductActivityParams;
use think\Exception;
use think\Log;
use think\Monlog;
use app\common\services\CategoryService;
use app\common\services\IndexService;
/**
 * 开发：钟宁
 * 功能：Flash Deals第一场活动数据，下一场活动数据
 * 时间：2018-05-26
 */
class ProductActivity extends AppBase
{
    public $productActivityService;
    public function __construct()
    {
        parent::__construct();
        $this->productActivityService = new ProductActivityService();
    }
    /**
     * 首页 -- 活动商品数据
     */
    public function getHomeFlashList(){
        $params = input();
        try{
            $data = (new ProductActivityService())->getHomeFlash($params);
            return $data;
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$params,null,$e->getMessage());

            return apiReturn(['code'=>1000000060, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 活动进行中的产品列表
     * @return array|mixed
     */
    public function onSaleLists(){
        $params = request()->post();
        try{
            $result = (new ProductActivityService())->getOnSaleList($params);
            return apiReturn(['code'=>200, 'data'=>empty($result) ? getDefaultData($result) : $result]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$params,null,$e->getMessage());

            return apiReturn(['code'=>1000000061, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 下一场活动
     * @return array|mixed
     */
    public function comingSoonLists(){
        try{
            $params = request()->post();
            $result = (new ProductActivityService())->getComingSoonLists($params);
            return apiReturn(['code'=>200, 'data'=>empty($result) ? getDefaultData($result) : $result]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$params,null,$e->getMessage());

            return apiReturn(['code'=>1000000061, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 第一场次的剩余时间
     * 第二场次的开始时间
     * @return mixed
     */
    public function getActivityTime(){
        try{
            $params = request()->post();

            $data = (new ProductActivityService())->getActivityTime($params);
            return $data;
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$params,null,$e->getMessage());

            return apiReturn(['code'=>2000000050, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 根据sku_id sellerid 获取coupon
     * @return \think\response\Json
     */
    public function getSellerCoupon(){
        try {
            $params = input();
            $paramsRequest = [
                'lang' => isset($params['lang']) ? $params['lang'] : DEFAULT_LANG,//当前语种
                'store_id' => isset($params['store_id']) ? $params['store_id'] : null,
                'categoryPath' => isset($params['categoryPath']) ? $params['categoryPath'] : null,
                'brand_id' => isset($params['brand_id']) ? $params['brand_id'] : null,
                'product_id' => isset($params['spu']) ? $params['spu'] : null,
                'CouponStrategy' => [1, 2],//手动，自动
                'CouponChannels' => [1, 4],//优惠渠道：1-全站、2-Web站、
                'DiscountLevel' => 1,//1-单品级别优惠，2-订单级别优惠
            ];

            //参数校验
//            $validate = $this->validate($paramsRequest, (new ProductActivityParams())->getSellerCouponRules());
//            if (true !== $validate) {
//                Log::record('getSellerCoupon1'.json_encode($params));
//                return apiReturn(['code' => 1002, 'msg' => $validate]);
//            }
            $resData = $this->productActivityService->getSellerCoupon($paramsRequest);

            return apiReturn(['code' => 200, 'data' => $resData]);
        }catch(Exception $e){
            //logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,null,null,$e->getMessage());
            return apiReturn(['code' => 1099, 'msg' => $e->getMessage()]);
        }

    }

    /**
     * 添加coupon
     * @return \think\response\Json
     */
    public function addCoupon(){
        try {
//            $this->addHeader();
            $params = input();
            $paramsRequest = [
                'lang' => isset($params['lang']) ? $params['lang'] : DEFAULT_LANG,//当前语种
                'coupon_id' => isset($params['coupon_id']) ? $params['coupon_id'] : null,
                'customer_id' => isset($params['customer_id']) ? $params['customer_id'] : null,
            ];
            //参数校验
            $validate = $this->validate($paramsRequest, (new ProductActivityParams())->addCustomerCouponRules());
            if (true !== $validate) {
                return json(['code' => 1002, 'msg' => $validate]);
            }
            $resData = $this->productActivityService->addCustomerCoupon($paramsRequest);

            return json($resData);
        }catch(Exception $e){
            //logService::write(LOGS_MALL,'error',__METHOD__,__FUNCTION__,$params,null,$e->getMessage());
            return apiJosn(['code' => 1099, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * flashdeal页面数据接口
     */
    public function getActivityProduct(){
        $params = input();
        $paramsRequest = [
            'lang' =>  isset($params['lang']) ? $params['lang'] : DEFAULT_LANG,//当前语种
            'currency' => isset($params['currency']) ? $params['currency'] : DEFAULT_CURRENCY,
            'country' => isset($params['country']) ? $params['country'] : 'US',
            'page' => isset($params['page']) ? $params['page'] : 1,
            'salesCounts' => isset($params['salesCounts']) ? $params['salesCounts'] : null,
            'addTimeSort' => isset($params['addTimeSort']) ? $params['addTimeSort'] : null,
            'reviewCount' => isset($params['reviewCount']) ? $params['reviewCount'] : null,
            'priceSort' => isset($params['priceSort']) ? $params['priceSort'] : null,
//          'type' => isset($params['type']) ? $params['type'] : 5,//活动类型:1专题活动;2定期活动;3节日活动;4促销活动;5Falsh Deals活动
//          'status' => isset($params['status']) ? $params['status'] : 3,//1:报名中,2:活动未开始,3:活动进行中,4:活动结束,5:下一场活动

            'activity_id' => isset($params['activity_id']) ? $params['activity_id'] : 0,
            'firstCategory' => isset($params['firstCategory']) ? $params['firstCategory'] : 0,
            'product_id' => !empty($params['pid']) ? $params['pid'] : "",
        ];
        //参数校验
        $validate = $this->validate($paramsRequest,(new ProductActivityParams())->getActivityProductRules());
        if(true !== $validate){
            return json(['code'=>1002, 'msg'=>$validate]);
        }
        $resData = $this->productActivityService->getActivityProductList($paramsRequest);
        return json($resData);
    }

    //获取FalshDeal所有场次展示
    public function getActivityTitle(){
        $params = input();
        $lang= $params['lang']= isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;//当前语种
        $currency =$params['currency']= isset($params['currency']) ? $params['currency'] : DEFAULT_CURRENCY;
        $country =$params['country']= isset($params['country']) ? $params['country'] : 'US';
        $activityData = $this->productActivityService->getActivityTitle(['lang' => $lang]);
        if(!empty($activityData)){
            foreach($activityData as $key => $val){
                $activityData[$key]['start_time'] = date('n/j H:i',$val['activity_start_time']);
                if($val['status'] == 3){
                    $defaultId = $val['_id'];
                    $activityData[$key]['start_time'] = $val['activity_end_time'] - time();
                }
            }
        }else{
            $activityData=[];
        }
        $categories = (new CategoryService())->getCatetoryProductCount(['key' => 'FlashDeal','lang' => $lang]);
        $pageConfig = (new IndexService())->ActivityPageConfig('flashDeals',$lang,$currency,$country);
        $coupon=!empty($pageConfig['coupon'])?$pageConfig['coupon']:[];
        $data=['title' => $activityData, 'coupon' => $coupon, 'categories' => $categories];
        return apiJosn(['code' => 200, 'data' => $data]);
    }

    //获取FalshDeal所有场次展示
    public function getCatetory(){
        $params = input();
        $lang=  isset($params['lang']) ? $params['lang'] : DEFAULT_LANG;//当前语种
        $categories = (new CategoryService())->getCatetoryProductCount(['key' => 'FlashDeal','lang' => $lang]);
        return apiJosn(['code' => 200, 'data' => $categories]);
    }





}
