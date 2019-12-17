<?php
namespace app\app\model;

use think\Model;
use think\Db;

/**
 * 订单统计模型
 * Class OrderRefundModel
 * @author tinghu.liu 2018/06/05
 * @package app\orderbackend\model
 */
class ProductTopsellerDay extends Model
{
    protected $connection='db_mongodb';
    protected $table = 'dx_product_topseller_day';

    /**
     * 获取用户消息列表
     * */
    public function getlatestData($order = 'id desc')
    {
        $data = $this
            ->order($order)
            ->limit(1)
            ->select();
        return $data;
    }

}