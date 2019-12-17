<?php
namespace app\share\controller;

use app\common\controller\Base;
use app\common\helpers\CommonLib;
use app\share\model\DxRegion;
use think\Cache;

/**
 *订单状态
 */
class OrderStatus extends Base
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
    

}
