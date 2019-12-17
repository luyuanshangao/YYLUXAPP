<?php
namespace app\app\controller;

use app\common\controller\AppBase;
use app\common\params\mall\ActivityParams;
use app\app\services\ProductActivityService;
use think\Exception;
use think\Monlog;

/**
 * 开发：钟宁
 * 功能：Flash Deals第一场活动数据，下一场活动数据
 * 时间：2018-05-26
 */
class ProductActivity extends AppBase
{

    public function __construct()
    {
        parent::__construct();
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
        //入口参数日志
        Monlog::write(LOGS_MALL_API,'info',__METHOD__,__FUNCTION__,$params);
        try{
            $data = (new ProductActivityService())->getOnSaleList($params);
            return $data;
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
            $data = (new ProductActivityService())->getComingSoonLists($params);
            return $data;
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
            //入口参数日志
            Monlog::write(LOGS_MALL_API,'info',__METHOD__,__FUNCTION__,$params);

            $data = (new ProductActivityService())->getActivityTime($params);
            return $data;
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$params,null,$e->getMessage());

            return apiReturn(['code'=>2000000050, 'msg'=>$e->getMessage()]);
        }
    }

}
