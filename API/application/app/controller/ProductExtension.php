<?php
namespace app\app\controller;

use app\common\controller\Base;
use app\common\params\app\CartParams;
use app\common\params\mall\ProductParams;
use app\app\services\ProductExtensionService;
use think\Monlog;

/**
 * 产品推荐数据
 */
class ProductExtension extends Base
{
    public $extensionService;
    public $productParams;

    public function __construct()
    {
        parent::__construct();
        $this->extensionService = new ProductExtensionService();
        $this->productParams = new ProductParams();
    }

    /**
     * 产品详情页 --related products位置推荐
     * @return mixed
     */
    public function getRelatedProducts(){
        $params = request()->post();
        //入口参数日志
        Monlog::write(LOGS_MALL_API,'info',__METHOD__,__FUNCTION__,$params);

        //参数校验
        $validate = $this->validate($params,$this->productParams->getViewHistoryRules());
        if(true !== $validate){
            return (['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $params['product_id'] = $params['spu'];
            $result = $this->extensionService->getRelatedProducts($params);
            return $result;
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$params,null,$e->getMessage());

            return apiReturn(['code'=>1000000013, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 产品详情页 --Customers Who Viewed This Item Also Viewed位置推荐
     * @return mixed
     */
    public function getAlsoViewed(){
        $params = request()->post();
        //入口参数日志
        Monlog::write(LOGS_MALL_API,'info',__METHOD__,__FUNCTION__,$params);

        //参数校验
        $validate = $this->validate($params,$this->productParams->getViewHistoryRules());
        if(true !== $validate){
            return (['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $params['product_id'] = $params['spu'];
            $result = $this->extensionService->getAlsoViewed($params);
            return $result;
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$params,null,$e->getMessage());

            return apiReturn(['code'=>1000000012, 'msg'=>$e->getMessage()]);
        }

    }

    /**
     * 产品详情页 --Customers Who Bought This Item Also Bought位置推荐
     * @return mixed
     */
    public function getAlsoBought(){
        $params = request()->post();
        //入口参数日志
        Monlog::write(LOGS_MALL_API,'info',__METHOD__,__FUNCTION__,$params);

        //参数校验
        $validate = $this->validate($params,$this->productParams->getViewHistoryRules());
        if(true !== $validate){
            return (['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $params['product_id'] = $params['spu'];
            $result = $this->extensionService->getAlsoBought($params);
            return $result;
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$params,null,$e->getMessage());

            return apiReturn(['code'=>1000000011, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 下单成功后展示
     * @return mixed
     */
    public function getRecentHistory(){
        $params = request()->post();
        try{
            $result = $this->extensionService->getRecentHistory($params);
            return apiReturn(['code'=>200, 'data'=>$result]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$params,null,$e->getMessage());

            return apiReturn(['code'=>1000000021, 'msg'=>$e->getMessage()]);
        }
    }


    /**
     * 公共底部  -- 浏览历史数据推荐
     */
    public function getProductViewHistory(){
        $params = request()->post();
        //入口参数日志
        Monlog::write(LOGS_MALL_API,'info',__METHOD__,__FUNCTION__,$params);

        //参数校验
        $validate = $this->validate($params,(new ProductParams())->getRatingRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $data = $this->extensionService->getProductViewHistory($params);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$params,null,$e->getMessage());

            return apiReturn(['code'=>1000000022, 'msg'=>$e->getMessage()]);
        }

    }

    /**
     * 搜索页面推荐数据 --取staffPick数据随机展示
     */
    public function getRecommendations(){
        $params = request()->post();
        //入口参数日志
        Monlog::write(LOGS_MALL_API,'info',__METHOD__,__FUNCTION__,$params);

        $params +=[
            'isStaffPick' => true,
            'page_size' => 50
        ];
        $data = $this->extensionService->getRecommendations($params);
        if(false == $data){
            return apiReturn(['code'=>1000000021, 'msg'=>'请求失败']);
        }
        return apiReturn(['code'=>200, 'data'=>$data]);
    }

    /**
     * 推荐数据 --取salesRank数据随机展示
     */
    public function getCartboughtAlsoBought(){
        try{
            $params = request()->post();
            //参数校验
            $validate = $this->validate($params,$this->productParams->getProductRecommendations());
            if(true !== $validate){
                return apiReturn(['code'=>1002, 'msg'=>$validate]);
            }
            $data = $this->extensionService->getRecommendations($params);

            return apiReturn(['code'=>200,'data'=>$data]);
        }catch (Exception $e){
            return apiReturn(['code'=>20000000002, 'msg'=>$e->getMessage]);
        }
    }

    public function getRecommend(){
        try{
            $params = request()->post();
            //参数校验
            $validate = $this->validate($params,(new CartParams())->getRecommend());
            if (true !== $validate){
                return apiReturn(['code'=>1002, 'msg'=>$validate]);
            }
            $data = $this->extensionService->getRecommend($params);

            return apiReturn(['code'=>200,'data'=>$data]);
        }catch (Exception $e){
            return apiReturn(['code'=>20000000002, 'msg'=>$e->getMessage]);
        }
    }
}
