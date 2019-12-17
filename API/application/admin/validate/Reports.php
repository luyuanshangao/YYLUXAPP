<?php
namespace app\admin\validate;
use think\Validate;

class Reports extends Validate{
    protected $rule = [
        ['customer_id',     'require|number|length:1,60',       'customer_id can not empty|customer_id Must be a number|customer_id Invalid parameter length'],
        ['seller_id',     'number|length:1,60',       'seller_id Must be a number|seller_id Invalid parameter length'],
        ['report_type',     'number|length:1,5',        "report_type Must be a number|report_type Length must be between 1-5"],
        ['report_small_type',     'number|length:1,5',        "report_small_type Must be a number|report_small_type Length must be between 1-5"],
        ['report_status',     ['Between:1,1000'],                    'report_status Must be between 1-10000'],
        ['page_size',     'number|length:0,10',    'page_size Must be a number|page_size Invalid parameter length'],
        ['page',     'number|length:0,10',"page Must be a number|page Length must be between 1-10"],
        ['path',     'length:1,150',"path Length must be between 1-150"],
        ['status',     ['in:1,2,3,4,5'],                    'status Must be between 1-5'],
        ['email',     ['require','regex'=>"/^(?:[a-zA-Z0-9!#$%&'*+=?\/^_`{|}~-]+(?:\.[a-zA-Z0-9!#$%&'*+=?\/^_`{|}~-]+)*|'(?:[\x01-\x08\x0b\x0c\x0e-\x1f\x21\x23-\x5b\x5d-\x7f]|\\[\x01-\x09\x0b\x0c\x0e-\x7f])*')@(?:(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]*[a-zA-Z0-9])?\.)+[a-zA-Z0-9](?:[a-zA-Z0-9-]*[a-zA-Z0-9])?|\[(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?|[a-zA-Z0-9-]*[a-zA-Z0-9]:(?:[\x01-\x08\x0b\x0c\x0e-\x1f\x21-\x5a\x53-\x7f]|\\[\x01-\x09\x0b\x0c\x0e-\x7f])+)\])$/"],                    "email can not empty|Wrong Mail format Or Email's Length Is Invalid."],
        ['customer_name',     'length:1,30',"customer_name Length must be between 1-30"],
        ['seller_name',     'chsDash|length:1,30',"seller_name Is not Valid|seller_name Length must be between 1-30"],
        ['product_url',     'url',"product_url Is not Valid"],
        ['reason',     'length:1,1000',"reason Length must be between 1-1000"],
        ['enclosure',     'length:0,300',"reason Length must be between 0-300"],
        ['email',     'email',"email Is not Valid"],
        ['phone',     'length:0,20',"phone Is not Valid"],
        ['order_number',     'number|length:0,20',"order_number Is not Valid"],
        ['phone',     'length:0,20',"phone Is not Valid"],
        ['currency_code',     'length:0,10',"currency_code Is not Valid"],
        ['amount',     'float|length:0,10',"amount Is not Valid"],
        ['SPU',      'number|length:0,20',"SPU Is not Valid"],
        ['report_status',      'number|length:0,20',"report_status Is not Valid"],
        ['id',      'require|number|length:1,20',"id can not empty|id Is not Valid"],
    ];
    protected $scene = [
        'getList'   =>  ['customer_id',"report_type","report_status","page_size","page","path"],
        'getListForSeller'   =>  ['seller_id',"report_status","page_size","page","path"],
        'addReports'   =>  ['customer_id',"customer_name","report_type","seller_id","seller_name",'report_small_type','product_url','reason','enclosure','email','phone','order_number','currency_code','amount','SPU','report_status'],
        'deleteReport'   =>  ['id','customer_id'],
        'getAdminReportList'   =>  ["report_type","report_status","page_size","page","path"],
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