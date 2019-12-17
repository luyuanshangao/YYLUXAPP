<?php
namespace app\share\controller;

use app\common\controller\Base;
use app\common\helpers\CommonLib;
use app\share\model\DxRegion;
use think\Cache;

/**
 *订单状态
 */
class OrderConfig extends Base
{
    public function __construct()
    {
        parent::__construct();
    }

	//缓存时间：86400=1小时
	const CACHE_TIME =86400;
    /**
     * 获取订单状态列表
     */
    public function getOrderStatus(){
    	$order_status = config("order_status");
    	return apiReturn(['code'=>200,'data'=>$order_status]);
     }
    
    /*
     * 获取售后申请单状态
     * */
    public function getAfterSaleStatus(){
        $after_sale_status = config("after_sale_status");
        return apiReturn(['code'=>200,'data'=>$after_sale_status]);
    }

    /*
     * 获取订单配置值
     * */
    public function getOrderConfig(){
        $config_key = input("config_key");
        $config_value = config($config_key);
        return apiReturn(['code'=>200,'data'=>$config_value]);
    }
}
