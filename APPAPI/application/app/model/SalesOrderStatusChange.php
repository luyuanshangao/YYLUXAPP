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
 * 订单日志消息模型
 * Class OrderRefundModel
 * @author tinghu.liu 2018/06/05
 * @package app\orderbackend\model
 */
class SalesOrderStatusChange extends Model
{
    protected $connection = 'db_order';
    protected $table= 'dx_sales_order_status_change';
    // 定义订单关联
    public function order()
    {
        return $this->hasOne('SalesOrder','order_id','order_id');
    }

    // 定义订单产品关联
    public function orderitem()
    {
        return $this->hasOne('SalesOrderItem','order_id','order_id');
    }

    public function getOrderChangeList($where = '',$page=10,$field = '*', $sort = 'id', $order = 'DESC')
    {
        $list = $this
            ->alias('o')
            ->field('o.change_reason as title,o.create_on as add_time,os.order_number,soi.product_name,soi.product_img as activity_img')
            ->join('dx_sales_order os','o.order_id=os.order_id','INNER')
            ->join('dx_sales_order_item soi','o.order_id=soi.order_id','INNER')
            ->where($where)
            ->order($sort, $order)
            ->paginate($page);
        return $list;
    }
}
