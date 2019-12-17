<?php
namespace app\share\validate;
use think\Validate;

class EmailHandle extends Validate{
    protected $rule = [
        ['title',     'length:1,300',                    'Title Invalid parameter length'],
        ['content',     'length:1,50000',                    'Content Invalid parameter length'],
    ];
    protected $scene = [
        'sendEmail'   =>  ['title',"content"],
        ];
}