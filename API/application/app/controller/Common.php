<?php
namespace app\app\controller;

use think\Exception;
use think\Monlog;
use app\app\services\CommonService;
use app\common\controller\AppBase;
use app\common\params\app\CommonParams;
use think\Request;


/**
 * 整合APP所需要的综合业务
 * add by heng.zhang 2018-09-07
 */
class Common extends AppBase
{
    protected $commonService;

    public function __construct()
    {
        parent::__construct();
        $this -> commonService = new CommonService();
    }


    //TODO CRUD业务

    /**
     * 保存一键过滤
     */
    public function saveCustomerFilter(){
        $paramData = input();
        //入口参数日志
        Monlog::write(LOGS_MALL_API,'info',__METHOD__,__FUNCTION__,$paramData);
        //参数校验
        $validate = $this->validate($paramData,(new CommonParams())->saveCustomerFilterRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $data = $this-> commonService->saveCustomerFilter($paramData);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            //错误日志
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,$paramData,null,$e->getMessage());
            return apiReturn(['code'=>1000000021, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     *返回用户一键过滤数据
     */
    public function getCustomerFilter(){
        try {
            $paramData = input();
            if (empty($paramData) || !isset($paramData['CustomerID']) || (int)$paramData['CustomerID'] < 1) {
                return apiReturn(['code' => 1000000021, 'msg' => 'CustomerID is error']);
            }
            $data = $this->commonService->getCustomerFilter($paramData['CustomerID']);
            return apiReturn(['code' => 200, 'data' => $data]);
        }catch (Exception $e){
            return apiReturn(['code'=>1000000021, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * App接口 - 返回首页数据
     */
    public function getHomeData(){
        $params = request()->post();
        $params['key'] = 'app_top_banner';
        try{
            $data = $this->commonService->getAppBanner($params);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            return apiReturn(['code'=>1000000070, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * App接口 - 返回漂浮广告数据
     */
    public function getFloatBanners(){
        $params = request()->post();
        $params['key'] = 'float_banner';
        try{
            $data = $this->commonService->getAppBanner($params);
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            return apiReturn(['code'=>1000000070, 'msg'=>$e->getMessage()]);
        }
    }


    /**
     * App接口 - 返回版本号
     */
    public function getVersion(){
        try{
            $data = $this->commonService->getAppVersion();
            return apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            return apiReturn(['code'=>1000000070, 'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 获取头部信息
     */
    public function getHttpHeaderValue(){
        $country['currentCountry'] = 'US';
        //获取国家
        if(isset(Request::instance()->header()['regin']) && !empty(Request::instance()->header()['regin'])){
            $country['currentCountry'] = Request::instance()->header()['regin'];
        }else{
            if(isset($_SERVER['HTTP_REGION']) && !empty($_SERVER['HTTP_REGION'])){
                $country['currentCountry'] = $_SERVER['HTTP_REGION'];
            }
        }
        return apiReturn(['code'=>200, 'data'=>$country]);
    }
}
