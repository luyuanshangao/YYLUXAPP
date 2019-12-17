<?php
namespace app\mallextend\controller;

use app\common\controller\Base;
use app\common\params\mallextend\product\ProductParams;
use app\mallextend\services\ProductExtensionService;
use think\Exception;

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
     * 推荐数据 --取salesRank数据随机展示
     */
    public function getboughtAlsoBought(){
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

    /**
     * 推荐数据 --取salesRank数据随机展示
     */
    public function getNewProducts(){
        try{
            $params['lang'] = DEFAULT_LANG;
            $params['salesRank'] = 'true';
            $data = $this->extensionService->getNewProductData($params);
            return apiReturn(['code'=>200,'data'=>$data]);
        }catch (Exception $e){
            return apiReturn(['code'=>20000000002, 'msg'=>$e->getMessage]);
        }
    }
}
