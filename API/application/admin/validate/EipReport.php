<?php
namespace app\admin\validate;
use think\Validate;

class EipReport extends Validate{
    protected $rule = [
        //['sales_time',     'require',       'create_on can not empty'],
        ['country_code',     'require',       'country_code can not empty'],
        ['first_category_id',     'number|length:1,15',        "FirstCategory Must be a number|FirstCategory Length must be between 1-15"],
        ['second_category_id',     'number|length:1,15',        "SecondCategory Must be a number|SecondCategory Length must be between 1-15"],
        ['third_category_id',     'number|length:1,15',        "ThirdCategory Must be a number|ThirdCategory Length must be between 1-15"],
        ['keyword',     'length:1,150',"Keywords Length must be between 1-150"],
        ['sale_rank',     'number|length:1,5',        "saleRank Must be a number|saleRank Length must be between 1-5"],
        ['rank_type',     'number|length:1,5',        "rankType Must be a number|saleRank Length must be between 1-5"],
        ['is_mvp',     'number|length:1,5',        "IsMVP Must be a number|IsMVP Length must be between 1-5"],
    ];
    protected $scene = [
        'getSkuSelection'   =>  ['create_on',"country_code","first_category_id","second_category_id","third_category_id","keyword","saleRasale_ranknk","rank_type","is_mvp"],
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