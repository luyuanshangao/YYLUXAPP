<?php
namespace app\mall\controller;

use app\common\controller\Base;
use app\common\params\mall\ActivityParams;
use app\mall\services\ProductActivityService;
use app\mall\services\ProductClassService;
use think\Exception;
use think\Monlog;

/**
 * 开发：钟宁
 * 功能：Flash Deals第一场活动数据，下一场活动数据
 * 时间：2018-05-26
 */
class ProductActivity extends Base
{

    public function __construct()
    {
        parent::__construct();
    }
    /**
     * 首页 -- 活动商品数据
     */
    public function getHomeFlashList(){
        $params = request()->post();

        //参数校验
        $validate = $this->validate($params,(new ActivityParams())->getFlashDataRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
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

        //参数校验
        $validate = $this->validate($params,(new ActivityParams())->getFlashDataRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
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
        $params = request()->post();

        //参数校验
        $validate = $this->validate($params,(new ActivityParams())->getFlashDataRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
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

            $data = (new ProductActivityService())->getActivityTime($params);
            return $data;
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$params,null,$e->getMessage());

            return apiReturn(['code'=>2000000050, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 移动端首页FlashDeal 产品
     * @return array|mixed
     */
    public function mobileHomeProducts(){
        $params = request()->post();

        //参数校验
        $validate = $this->validate($params,(new ActivityParams())->getFlashDataRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $data = (new ProductActivityService())->mobileHomeProducts($params);
            return $data;
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$params,null,$e->getMessage());

            return apiReturn(['code'=>1000000061, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 活动产品列表 falshdeal重构 addbyzhongning 20190902
     * @return array|mixed
     */
    public function getActivityProductList(){
        $params = request()->post();
        try{
            $data = (new ProductActivityService())->getActivityProductList($params);
            return $data;
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$params,null,$e->getMessage());

            return apiReturn(['code'=>1000000061, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 获取flash活动标题 falshdeal重构 addbyzhongning 20190902
     * @return array|mixed
     */
    public function getActivityTitle(){
        try{
            $data = (new ProductActivityService())->getActivityTitle();
            return $data;
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,null,null,$e->getMessage());

            return apiReturn(['code'=>1000000061, 'msg'=>$e->getMessage()]);
        }
    }

}
