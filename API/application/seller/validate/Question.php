<?php
namespace app\seller\validate;
use think\Validate;

class Question extends Validate{
    protected $rule = [
        ['customer_id',     'number|length:1,20',        "customer_id Must be a number|customer_id Invalid parameter length"],
        ['name',     'length:1,50',        "name Invalid parameter length"],
        ['seller_id',     'number|length:1,10',                    'seller_id Must be a number|seller_id Invalid parameter length'],
        ['seller_name',     'length:0,50',                    'seller_name Invalid parameter length'],
        ['product_id',     'number|length:0,200',                    'product_id Must be a number|product_id Invalid parameter length'],
        ['product_img',     'length:0,300',                    'product_img Invalid parameter length'],
        ['product_name',     'length:0,500',                    'product_name Invalid parameter length'],
        ['product_attr_ids',     'length:1,100',                    'product_attr_ids order_status Invalid parameter length'],
        ['product_attr_desc',     'length:1,500',                    'product_attr_desc order_branch_status Invalid parameter length'],
        ['email',    'email',                    'email Incorrect format'],
        ['description',     'length:0,5000',                    'description Invalid parameter length'],
        ['type',     'number|length:0,5',                    'type Must be a number|type Invalid parameter length'],
        ['is_answer',     'in:0,1',                    'is_answer Must be 0 or 1'],
        ['question_id',     'number|length:1,20',        "question_id Must be a number|question_id Invalid parameter length"],
        ['user_id',     'number|length:1,10',                    'user_id Must be a number|user_id Invalid parameter length'],
    ];
    protected $scene = [
        'addQuestion'   =>  ['customer_id',"order_number","store_id","product_name","sku_num","order_status","order_branch_status","payment_status","seller_name","create_on_start","create_on_end","tracking_number","page_size","page","path"],
        'addAnswer'   =>  ['question_id',"product_id","name","user_id","description"],
        'getOneQuestion' =>['question_id'],
        ];
}