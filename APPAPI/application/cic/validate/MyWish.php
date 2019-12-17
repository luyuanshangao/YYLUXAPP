<?php
namespace app\cic\validate;
use think\Validate;

class MyWish extends Validate{
    protected $rule = [
        ['CustomerID',     'number|length:1,15',        "customer_id Must be a number|customer_id Invalid parameter length"],
        ['CategoryID',     'number|length:1,15',        "CategoryID Must be a number|CategoryID Invalid parameter length"],
        // ['Username',     'length:0,30',    'Username Invalid parameter length'],
        ['SPU',     'number|length:0,10',    'SPU Must be a number|SPU Invalid parameter length'],
        ['Source',     'length:0,10',    'Source Invalid parameter length'],
        ['page_size',     'number|length:0,10',    'page_size Must be a number|page_size Invalid parameter length'],
        ['page',     'number|length:0,10',"page Must be a number|page Length must be between 1-10"],
        ['path',     'length:1,150',"path Length must be between 1-150"],
        ['PriceWhenAdded',     'float|length:0,10',    'page Must be a float|Username Invalid parameter length'],
        ['ShippingWhenAdded',     'float|length:0,10',    'ShippingWhenAdded Must be a float|Username Invalid parameter length'],
        ['Comments',     'length:0,255',    'Comments Invalid parameter length'],
        ['Tags',     'length:0,50',    'Tags Invalid parameter length'],
        ['CategoryID',     'length:0,10',    'page Must be a float|CategoryID Invalid parameter length'],
        ['CategoryName',     'length:0,60',    'CategoryName Invalid parameter length'],

    ];
    protected $scene = [
        'getWishList'   =>  ['CustomerID',"CategoryID","Tags","page_size","page","path"],
        'addWish'   =>  ['CustomerID',"SPU","Source","PriceWhenAdded","ShippingWhenAdded",'Comments','Tags','CategoryID','CategoryName'],
    ];
	 /*protected $rule = [
         ['nickname',     'require|length:1,100',                    '昵称长度需在6-21个字符之间'],
        ['account',     'require|unique:Admin|alphaDash|length:6,30|regex:^[a-zA-z]+\w+',                        '帐号不能为空|帐号已存在|帐号只允许字母、数字和下划线 破折号|帐号长度为5-50个字符|帐号必须以字母开头'],
        ['nickname',     'length:6,30',                    '昵称长度需在6-21个字符之间'],
        ['password',    'require|length:6,30',                          '密码不能为空','密码长度需在6-21个字符之间'],
        ['phone',       ['regex'=>'/^1[3|4|5|7|8][0-9]{9}$/','unique:Admin','require'],    '手机格式错误|手机号已存在|手机号不能为空'],
        ['email',       'email',                       '邮箱格式错误'],
        ['AccountName',       ['regex'=>'/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[$@$!%*?&._-])[A-Za-z\d$@$!%*?&._-]{6,20}/','require'],    'Incorrect payment password format|Payment password can not be empty'],
    ];    */
}