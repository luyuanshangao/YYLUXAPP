<?php
namespace app\mall\controller;

use app\common\controller\Base;
use app\common\params\mall\ActivityParams;
use app\common\params\mall\ElectronicCoilParams;
use app\mall\services\ElectronicCoilService;
use app\mall\services\ProductActivityService;
use app\mall\services\ProductClassService;
use think\Exception;
use think\Monlog;

/**
 * 开发：钟宁
 * 功能：电子券
 * 时间：2018-05-26
 */
class ElectronicCoil extends Base
{

    public function __construct()
    {
        parent::__construct();
    }
    /**
     * 获取电子券
     */
    public function getCoil(){
        try{
            $params = request()->post();

            $data = (new ElectronicCoilService())->getCoil($params);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$params,null,$e->getMessage());

            return apiReturn(['code'=>1000000060, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 电子券绑定邮箱
     */
    public function bind(){
        try{
            $params = request()->post();
            //参数校验
            $validate = $this->validate($params,(new ElectronicCoilParams())->Rules());
            if(true !== $validate){
                return apiReturn(['code'=>1002, 'msg'=>$validate]);
            }
            $result = (new ElectronicCoilService())->bindCoil($params);
            if($result){
                return apiReturn(['code'=>200]);
            }else{
                return apiReturn(['code'=>1000000060, 'msg'=>'bing error']);
            }
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$params,null,$e->getMessage());

            return apiReturn(['code'=>1000000060, 'msg'=>$e->getMessage()]);
        }
    }
}
