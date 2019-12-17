<?php
namespace app\cic\validate;
use think\Validate;

class Address extends Validate{
    protected $rule = [
        ['AddressID',     'require|number',        "AddressID can not null|AddressID Must be a number"],
        ['CustomerID',     'require|number',        "CustomerID can not null|CustomerID Must be a number"],
        ['ContactName',     'length:1,50',                    'ContactName Invalid parameter length'],
        ['Street1',     'length:1,100',                    'Street1 Invalid parameter length'],
        ['Street2',     'length:1,100',                    'Street2 Invalid parameter length'],
        ['CityCode',     'length:1,50',                    'CityCode Invalid parameter length'],
        ['City',     'length:1,80',                    'City Invalid parameter length'],
        ['ProvinceCode',    'length:1,50',                    'ProvinceCode Invalid parameter length'],
        ['Province',     'length:1,50',                    'Province Invalid parameter length'],
        ['Country',     'require|length:1,50',                    'Country Invalid parameter length'],
        ['CountryCode',     'require|length:1,50',                    'CountryCode Invalid parameter length'],
        ['Mobile',     'length:0,20',    'Mobile Invalid parameter length'],
        ['Phone',     'length:0,20',    'Phone Invalid parameter length'],
        ['PostalCode',     'length:1,15',"PostalCode Length must be between 1-15"],
        ['FirstName',     'length:1,50',"FirstName Length must be between 1-50"],
        ['LastName',     'length:1,50',"LastName Length must be between 1-50"],
        ['CardID',     'length:1,20',        "CardID Must be a Number"],
        ['IsDefault',    'in:0,1',        "IsDefault must be 0 or 1"],
    ];
    protected $scene = [
        'saveAddress'   =>  ['CustomerID',"ContactName","Street1","Street2","CityCode","City","ProvinceCode","Province","Country","CountryCode","Mobile","Phone","PostalCode","FirstName","LastName","CardID","IsDefault"],
        'delAddress'    =>  ['CustomerID'],
        'setDefault'    =>  [],
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