<?php
namespace app\datastatistics\controller;
use app\common\helpers\CommonLib;
use app\demo\controller\Auth;
use app\common\controller\Base;
use think\Db;
use app\common\helpers\RedisClusterBase;


/**
 * 订单统计
 * author: Wang
 * AddTime:2018-11-30
 */
class OrderStatistics extends Base
{
    const sales_order = 'sales_order';

    public function OrderStatistics(){
        $result = model("OrderStatistics")->OrderStatistics();
        return $result;
    }

}