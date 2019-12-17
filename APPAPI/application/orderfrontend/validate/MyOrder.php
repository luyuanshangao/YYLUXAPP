<?php
namespace app\orderfrontend\validate;
use think\Validate;

class MyOrder extends Validate{
    protected $rule = [
        ['customer_id',     'number|length:1,20',        "customer_id Must be a number|customer_id Invalid parameter length"],
        ['order_number',     'number|length:1,20',        "order_number Must be a number|order_number Invalid parameter length"],
        ['store_id',     'number|length:1,10',                    'store_id Must be a number|store_id Invalid parameter length'],
        ['order_id',     'number|length:1,10',                    'order_id Must be a number|order_id Invalid parameter length'],
        ['product_name',     'length:0,200',                    'product_name Invalid parameter length'],
        ['sku_num',     'length:1,20',                    'Street2 Invalid parameter length'],
        ['sku_id',     'number|length:1,20',                    'sku_id Must be a number|Street2 Invalid parameter length'],
        ['order_status',     'number|length:1,50',                    'order_status Must be a number|order_status Invalid parameter length'],
        /*['order_branch_status',     'number|length:1,50',                    'order_branch_status Must be a number|order_branch_status Invalid parameter length'],*/
        ['payment_status',    'number|length:1,50',                    'payment_status Must be a number|ProvinceCode Invalid parameter length'],
        ['seller_name',     'length:1,50',                    'Province Invalid parameter length'],
        ['create_on_start',     'length:0,50',                    'create_on_start Invalid parameter length'],
        ['create_on_end',     'length:0,50',                    'create_on_end Invalid parameter length'],
        ['tracking_number',     'length:0,50',    'tracking_number Invalid parameter length'],
        ['page_size',     'number|length:0,10',    'page_size Must be a number|page_size Invalid parameter length'],
        ['page',     'number|length:0,10',"page Must be a number|page Length must be between 1-10"],
        ['path',     'length:1,150',"path Length must be between 1-150"],
        ['change_reason_id',     'number|length:1,10',                    'change_reason_id Must be a number|change_reason_id Invalid parameter length'],
        ['tracking_number',     'alphaDash|length:1,50',                    'tracking_number data type error|tracking_number Invalid parameter length'],
    ];
    protected $scene = [
        'getOrderList'   =>  ['customer_id',"order_number","store_id","product_name","sku_num","order_status","payment_status","seller_name","create_on_start","create_on_end","tracking_number","page_size","page","path"],
        'getOrderInfo' => ['customer_id',"order_id",'order_number','sku_id'],
        'delOrder' =>   ['order_number',"customer_id"],
        'getOrderCount' => ['customer_id','order_number'],
        'updateOrderStatus' => ['order_id'],
        'getOrderBasics' => ['order_id','order_number','customer_id'],
        'refundOrder' => ['order_id','change_reason_id','customer_id'],
        'getLogisticsdetail'=> ['order_id','order_number','tracking_number'],
        'getOrderItem'=> ['order_id'],
        'logisticsdetail'=> ['order_id','tracking_number','customer_id','order_number'],
        'getRefundedAmount'=> ['customer_id'],
        'getPackageTrace'=> ['tracking_number'],
        ];
}