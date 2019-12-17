<?php
namespace app\admin\validate;
use think\Validate;

class Article extends Validate{
    protected $rule = [
        ['cate_id',     'number|length:1,15',       'cate_id Must be a number|cate_id Invalid parameter length'],
        ['article_id',     'number|length:1,15',       'article_id Must be a number|article_id Invalid parameter length'],
        ['article_title',     'length:0,200',       'article_title Invalid parameter length'],
        ['page_size',     'number|length:0,10',    'page_size Must be a number|page_size Invalid parameter length'],
        ['page',     'number|length:0,10',"page Must be a number|page Length must be between 1-10"],
        ['path',     'length:1,150',"path Length must be between 1-150"],
        ['status',     'in:1,2',"status must be 1 or 2"],
    ];
    protected $scene = [
        'getList'   =>  ['cate_id',"article_title","page_size","page","path",'status'],
        'getInfo'   =>  ['article_id',"article_title"],
        'getArticleCate'   =>  ['cate_id'],
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