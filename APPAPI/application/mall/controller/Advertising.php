<?php
namespace app\mall\controller;

use app\common\controller\Base;
use app\common\params\mall\AdvertisingParams;
use app\mall\services\AdvertisingService;
use think\Monlog;


/**
 * 广告获取
 */
class Advertising extends Base
{
    public $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new AdvertisingService();
    }


    /**
     * 根据key,获取广告详情信息
     * @return mixed
     */
    public function get(){
        $params = request()->post();

        //参数校验
        $validate = $this->validate($params,(new AdvertisingParams())->getRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $data = $this->service->getAdvertisingInfo($params);
            return $data;
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$params,null,$e->getMessage());

            return apiReturn(['code'=>1000000070, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 获取广告列表
     */
    public function lists(){
        $params = request()->post();

        //参数校验
        $validate = $this->validate($params,(new AdvertisingParams())->getRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $data = $this->service->getLists($params);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$params,null,$e->getMessage());

            return apiReturn(['code'=>1000000071, 'msg'=>$e->getMessage()]);
        }
    }

}
