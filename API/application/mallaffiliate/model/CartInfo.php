<?php
namespace app\mallaffiliate\model;

use think\Log;
use think\Model;
use think\Db;

/**
 * 订单模型
 * Class OrderModel
 * @author tinghu.liu 2018/8/26
 * @package app\orderFront\model
 */
class CartInfo extends Model{
    protected $connection = 'db_mongodb_cart';
    protected $table='dx_cart';
}