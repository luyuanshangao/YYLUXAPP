<?php
namespace app\mall\controller;

use app\common\controller\Base;
use app\common\params\mall\BaseConfigParams;
use app\mall\services\ConfigDataService;
use think\Exception;
use think\Monlog;

/**
 * 开发：钟宁
 * 功能：基础配置数据，如：topSell配置
 * 时间：2018-04-25
 *
 */
class BaseConfig extends Base
{
    public $baseConfigService;

    public function __construct()
    {
        parent::__construct();
        $this->baseConfigService = new ConfigDataService();
    }

    /**
     * 根据一级分类，获取搜索栏下拉内的关键字
     */
    public function getCategoryHotWord(){
        $params = request()->post();
        $data = $this->baseConfigService->getCategoryHotWord($params);
        return apiReturn(['code'=>200, 'data'=>$data]);
    }

    /**
     * 搜索栏下方位置，搜索热度词
     */
    public function getSearchHotWord(){
        try{
            $params = request()->post();

            //参数校验
            $validate = $this->validate($params,(new BaseConfigParams())->getSearchWordRules());
            if(true !== $validate){
                return apiReturn(['code'=>1002, 'msg'=>$validate]);
            }
            $data = $this->baseConfigService->getSearchHotWord($params);
            return $data;
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$params,null,$e->getMessage());

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
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,null,null,$e->getMessage());

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
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,null,null,$e->getMessage());

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
            return $data;
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

        //参数校验
        $validate = $this->validate($params,(new BaseConfigParams())->getRule());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $data = $this->baseConfigService->getProductDataByKey($params);
            return $data;
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

            $data = $this->baseConfigService->getPayment($params);
            return $data;
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$params,null,$e->getMessage());

            return apiReturn(['code'=>1000000060, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     *  运费天数
     * @return mixed
     */
    public function getShippingConfig(){
        try{
            $data = $this->baseConfigService->getSystemConfigs('EstimatedDeliveryTime');
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,null,null,$e->getMessage());
            return apiReturn(['code'=>1000000060, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 移动端开关
     * @return mixed
     */
    public function getMobileJumpStatus(){
        try{
            $data = $this->baseConfigService->getSystemConfigs('MobileJumpStatus');
            if(isset($data['ConfigValue']) && !empty($data['ConfigValue'])){
                $status = json_decode(htmlspecialchars_decode($data['ConfigValue']),true);
            }
            return apiReturn(['code'=>200, 'data'=>empty($status) ? 0 : $status]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,null,null,$e->getMessage());
            return apiReturn(['code'=>1000000060, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 后台系统配置信息
     */
    public function getSystemConfigs(){
        $params = request()->post();
        if(empty($params['configKey'])){
            return apiReturn(['code'=>1001]);
        }
        $data = $this->baseConfigService->getSystemConfigs($params['configKey']);
        return apiReturn(['code'=>200, 'data'=>$data]);
    }

}
