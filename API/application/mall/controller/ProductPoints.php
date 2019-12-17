<?php
namespace app\mall\controller;

use app\common\controller\Base;
use app\mall\services\ProductPointService;
use think\Exception;

/**
 * 开发：钟宁
 * 功能：积分产品，获取积分产品列表
 * 时间：2018-05-26
 */
class ProductPoints extends Base
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 积分产品列表
     */
    public function lists(){
        try{
            $params = request()->post();
            $data = (new ProductPointService())->getPointsLists($params);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            return apiReturn(['code'=>1000000021, 'msg'=>$e->getMessage()]);
        }
    }

}
