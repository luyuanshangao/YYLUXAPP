<?php
namespace app\app\controller;

use app\app\services\ProductShippingService;
use app\common\controller\AppBase;
use app\common\params\mall\BaseConfigParams;
use think\Exception;
use think\Monlog;

/**
 * 开发：钟宁
 * 功能：运费
 * 时间：2018-09-06
 *
 */
class ProductShipping extends AppBase
{
    public $shippingService;

    public function __construct()
    {
        parent::__construct();
        $this->shippingService = new ProductShippingService();
    }

    /**
     * 获取产品运费
     */
    public function getShippingCost(){
        try{
            $params = request()->post();
            $data = $this->shippingService->countProductShipping($params);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            //错误日志
            return apiReturn(['code'=>1000000066, 'msg'=>$e->getMessage()]);
        }

    }

}
