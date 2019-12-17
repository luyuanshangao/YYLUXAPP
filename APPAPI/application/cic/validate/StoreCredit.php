<?php
namespace app\cic\validate;
use think\Validate;

class StoreCredit extends Validate{
    protected $rule = [
        ['CustomerID',     'require|number',        "CustomerID can not null|CustomerID Must be a number"],

    ];
    protected $scene = [
        'getAllStoreCarditBasicInfo'   =>  ['CustomerID']
    ];
}