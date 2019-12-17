<?php
namespace app\app\controller;

use app\common\controller\AppBase;
use app\common\params\mall\BaseConfigParams;
use app\app\services\ConfigDataService;
use think\Exception;
use think\Monlog;

/**
 * 开发：钟宁
 * 功能：基础配置数据，如：topSell配置
 * 时间：2018-04-25
 *
 */
class BaseConfig extends AppBase
{
    public $baseConfigService;

    public function __construct()
    {
        parent::__construct();
        $this->baseConfigService = new ConfigDataService();
    }

    /**
     * 搜索栏下方位置，搜索热度词
     */
    public function getHotSearchWords(){
        try{
            $params = request()->post();
            //参数校验
//            $validate = $this->validate($params,(new BaseConfigParams())->getSearchWordRules());
//            if(true !== $validate){
//                return apiReturn(['code'=>1002, 'msg'=>$validate]);
//            }
            $data = $this->baseConfigService->getSearchHotWord($params);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            return apiReturn(['code'=>1000000060, 'msg'=>$e->getMessage()]);
        }
    }



    /**
     * 顶部广告
     */
    public function getTopBrands(){
        try{
            $data = $this->baseConfigService->getTopBanner();
            return $data;
        }catch (Exception $e){
            //错误日志
            return apiReturn(['code'=>1000000064, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * logo
     */
    public function getLogo(){
        try{
            $data = $this->baseConfigService->getLogo();
            return $data;
        }catch (Exception $e){
            return apiReturn(['code'=>1000000063, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * Google Code for Remarketing tag
     */
    public function getGoogleConversionLabel(){
        try{
            $data = $this->baseConfigService->getGoogleConversionLabel();
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,null,null,$e->getMessage());

            return apiReturn(['code'=>1000000063, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 品牌图标列表
     */
    public function getBrandsLogo(){
        try{
            $data = $this->baseConfigService->getBrandsLogo();
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,null,null,$e->getMessage());

            return apiReturn(['code'=>1000000065, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 基础配置key,获取配置的产品数据
     */
    public function getProductDataByKey(){
        $params = request()->post();
        //入口参数日志
        Monlog::write(LOGS_MALL_API,'info',__METHOD__,__FUNCTION__,$params);

        //参数校验
        $validate = $this->validate($params,(new BaseConfigParams())->getRule());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $products = $this->baseConfigService->getProductDataByKey($params);
            return apiReturn(['code'=>200, 'data'=>$products]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$params,null,$e->getMessage());

            return apiReturn(['code'=>1000000063, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * dx_mall_set 基础配置价格列表
     */
    public function getPriceList(){
        try{
            $data = $this->baseConfigService->getPriceList();
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,null,null,$e->getMessage());

            return apiReturn(['code'=>1000000063, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * dx_mall_set，payment配置
     */
    public function getPayment(){
        try{
            $params = request()->post();
            //入口参数日志
            Monlog::write(LOGS_MALL_API,'info',__METHOD__,__FUNCTION__,$params);

            $data = $this->baseConfigService->getPayment($params);
            return $data;
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$params,null,$e->getMessage());

            return apiReturn(['code'=>1000000060, 'msg'=>$e->getMessage()]);
        }
    }



}
