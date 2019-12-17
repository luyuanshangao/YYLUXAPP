<?php
namespace app\share\controller;

use app\common\controller\Base;
use app\common\helpers\CommonLib;
use app\share\model\DxRegion;
use think\Cache;

/**
 *获取配置
 */
class BaseConfig extends Base
{
    public function __construct()
    {
        parent::__construct();
    }
    /*
     * 获取订单配置值
     * */
    public function getBaseConfig(){
        $config_key = input("config_key");
        $config_value = config($config_key);
        return apiReturn(['code'=>200,'data'=>$config_value]);
    }
}
