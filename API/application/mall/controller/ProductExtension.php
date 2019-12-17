<?php
namespace app\mall\controller;

use app\common\controller\Base;
use app\common\params\mall\ProductParams;
use app\mall\services\ProductExtensionService;
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

        //参数校验
        $validate = $this->validate($params,$this->productParams->getProductRules());
        if(true !== $validate){
            return (['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $result = $this->extensionService->getRelatedProducts($params);
            return apiReturn(['code'=>200, 'data'=>$result]);
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

        //参数校验
        $validate = $this->validate($params,$this->productParams->getProductRules());
        if(true !== $validate){
            return (['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $result = $this->extensionService->getAlsoViewed($params);
            return apiReturn(['code'=>200, 'data'=>$result]);
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

        //参数校验
        $validate = $this->validate($params,$this->productParams->getProductRules());
        if(true !== $validate){
            return (['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $result = $this->extensionService->getAlsoBought($params);
            return $result;
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$params,null,$e->getMessage());

            return apiReturn(['code'=>1000000011, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 产品详情页 --Recommendations Based On Your Recent History位置推荐
     * @return mixed
     */
    public function getRecentHistory(){
        $params = request()->post();

        //参数校验
        $validate = $this->validate($params,(new ProductParams())->getProductRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
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

        //参数校验
        $validate = $this->validate($params,(new ProductParams())->getViewHistoryRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $data = $this->extensionService->getProductViewHistory($params);
            return $data;
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
        try {
            $params = request()->post();

            $params['page_size'] = 50;
            $data = $this->extensionService->getRecommendations($params);
            return apiReturn(['code' => 200, 'data' => $data]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$params,null,$e->getMessage());

            return apiReturn(['code'=>1000000022, 'msg'=>$e->getMessage()]);
        }
    }
}
