<?php
namespace app\mall\controller;

use app\common\controller\Base;
use app\common\params\mall\AdvertisingParams;
use app\mall\services\AffiliateService;
use think\Monlog;


/**
 * 开发：钟宁
 * 功能：Affiliate js模板获取
 * 时间：2018-06-21
 */
class Affiliate extends Base
{
    public $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new AffiliateService();
    }

    /**
     * 根据id,获取Affiliate code
     * @return mixed
     */
    public function get(){
        $params = request()->post();

        //参数校验
        if(!isset($params['id'])){
            return apiReturn(['code'=>1002, 'msg'=>'id required']);
        }
        try{
            $data = $this->service->findAffiliate($params);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$params,null,$e->getMessage());

            return apiReturn(['code'=>1000000070, 'msg'=>$e->getMessage()]);
        }
    }

}
