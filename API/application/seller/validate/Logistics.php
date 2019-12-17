<?php
namespace app\seller\validate;
use think\Validate;

class Logistics extends Validate{
    protected $rule = [
        ['countryCode',     'require|number|length:1,20',        "countryCode can not null|countryCode Must be a number|countryCode Invalid parameter length"],
        ['isCharged',     'require|number|length:1,10',                    'isCharged can not null|isCharged Must be a number|isCharged Invalid parameter length'],
        ['shippingServiceID',     'require|number|length:0,200',                    'shippingServiceID can not null|shippingServiceID Must be a number|shippingServiceID Invalid parameter length'],
    ];
    protected $scene = [
        'getLogisticsManagement'   =>  ['countryCode',"isCharged","shippingServiceID"],
        ];
}