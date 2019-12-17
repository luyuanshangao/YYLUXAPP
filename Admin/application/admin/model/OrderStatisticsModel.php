<?php
namespace app\admin\model;

use think\Model;
use think\Db;

/**
 * 订单统计模型
 * Class OrderRefundModel
 * @author tinghu.liu 2018/06/05
 * @package app\orderbackend\model
 */
class OrderStatisticsModel extends Model{
    //protected $connection='db_admin';
    protected $table='dx_order_statistics';
}