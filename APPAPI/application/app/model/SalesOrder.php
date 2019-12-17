<?php
/**
 * Created by PhpStorm.
 * User: pc
 * Date: 2019/10/22
 * Time: 14:04
 */
namespace app\app\model;

use think\Model;
use think\Db;

/**
 * 订单模型
 * Class OrderRefundModel
 * @author tinghu.liu 2018/06/05
 * @package app\orderbackend\model
 */
class SalesOrder extends Model
{
    protected $connection = 'db_order';
    protected $table= 'dx_sales_order';
}
