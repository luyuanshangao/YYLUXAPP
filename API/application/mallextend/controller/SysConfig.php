<?php
namespace app\mallextend\controller;

use app\common\controller\Base;

class SysConfig extends Base
{
    public function __construct()
    {
        parent::__construct();
    }
    /**
     * 根据后台配置
     */
    public function getSysCofig($paramData = '')
    {
        $paramData = !empty($paramData)?$paramData:request()->post();
        $ConfigName = $paramData["ConfigName"];
        $SysCofig = model("mallextend/SysConfig")->getSysCofig($ConfigName);
        $size = json_decode($SysCofig['ConfigValue'],true);
        return apiReturn(['code'=>200, 'data'=>$size]);
    }

    public function getSysCofigValue($paramData = '')
    {
        $paramData = !empty($paramData)?$paramData:request()->post();
        $ConfigName = $paramData["ConfigName"];
        $SysCofig = model("mallextend/SysConfig")->getSysCofig($ConfigName);
        if(!is_null(json_decode($SysCofig['ConfigValue']))){
            $SysCofig['ConfigValue'] = json_decode($SysCofig['ConfigValue']);
        }
        return apiReturn(['code'=>200, 'data'=>$SysCofig['ConfigValue']]);
    }

    public function getOrderStatusView($paramData = ''){
        $paramData = !empty($paramData)?$paramData:request()->post();
        $ConfigName = isset($paramData["ConfigName"])?$paramData["ConfigName"]:"OrderStatusView";
        $SysCofig = model("mallextend/SysConfig")->getSysCofig($ConfigName);
        if(!empty($SysCofig['ConfigValue'])){
            $OrderStatusViewStr = explode(";",$SysCofig['ConfigValue']);
            foreach ($OrderStatusViewStr as $key=>$value){
                $OrderStatusViewArr[$key] = explode(":",$OrderStatusViewStr[$key]);
                if($OrderStatusViewArr){
                    $getOrderStatusData[$key]['code'] = $OrderStatusViewArr[$key][0];
                    $NameValue = explode('-',$OrderStatusViewArr[$key][1]);
                    $getOrderStatusData[$key]['en_name'] = $NameValue[0];
                    $getOrderStatusData[$key]['name'] = $NameValue[1];
                }
            }
        }
        return apiReturn(['code'=>200, 'data'=>$getOrderStatusData]);
    }
}
