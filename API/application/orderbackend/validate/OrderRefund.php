<?php
namespace app\orderbackend\validate;
use think\Validate;

class OrderRefund extends Validate{
    protected $rule = [
        ['customer_id',     'number|length:1,20',        "customer_id Must be a number|customer_id Invalid parameter length"],
        ['order_number',     'number|length:1,20',        "order_number Must be a number|order_number Invalid parameter length"],
        ['customer_name',     'length:1,100',        "customer_name Invalid parameter length"],
        ['store_id',     'number|length:1,20',        "store_id Must be a number|store_id Invalid parameter length"],
        ['status',     'number|length:1,20',        "status Must be a number|status Invalid parameter length"],
        //['add_time',     'number|length:1,20',        "add_time Must be a number|add_time Invalid parameter length"],
    ];
    protected $scene = [
        'getUserAfterSaleCount'=> ['customer_id'],
        'getOrderRefundInfo'=> ['order_number'],
        'getOrderRefundList'=> [''],
        'getAdminOrderRefundList'=> ['order_number','customer_name','customer_id','store_id','status'],
        ];
}