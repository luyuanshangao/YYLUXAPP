<?php
namespace app\orderfrontend\validate;
use think\Validate;

class OrderMessage extends Validate{
    protected $rule = [
        ['order_id',     'number|length:1,10',                    'order_id Must be a number|order_id Invalid parameter length'],
        ['user_id',     'number|length:1,10',                    'user_id Must be a number|user_id Invalid parameter length'],
    ];
    protected $scene = [
        'solvedOrderMessage'   =>  ['order_id',"user_id"],
        ];
}