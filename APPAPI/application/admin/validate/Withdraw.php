<?php
namespace app\admin\validate;
use think\Validate;

class Withdraw extends Validate{
    protected $rule = [
        ['customer_id',     'require|number|length:1,60',       'customer_id can not empty|customer_id Must be a number|customer_id Invalid parameter length'],
        ['customer_name',     'require|length:1,150',        "customer_name can not empty|customer_name Length must be between 1-150"],
        ['amount',     ['require','Between:1,10000'],                    'amount can not empty|amount Must be between 1-10000'],
        ['status',     ['in:1,2,3,4,5'],                    'status Must be between 1-5'],
        ['email',     'require|email',                    "email can not empty|Wrong Mail format Or Email's Length Is Invalid."],
    ];
    protected $scene = [
        'addWithdraw'   =>  ['customer_id',"customer_name","amount","status","email"],
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