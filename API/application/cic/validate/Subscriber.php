<?php
namespace app\cic\validate;
use think\Validate;

class Subscriber extends Validate{
    protected $rule = [
        ['CustomerID',     'number',        "CustomerID Must be a number"],
        ['CustomerId',     'number',        "CustomerID Must be a number"],
        ['Email',     'require|email',                    'email can not null|email validation not passed'],
        ['Active',     'in:0,1',                    'Active validation not passed'],
        ['SiteId',     'number|length:1,100',                    'SiteId validation not passed'],
        ['ActiveCode',     'length:1,10',                    'ActiveCode Invalid parameter length'],
        ['CancelActiveCode',     'length:1,50',                    'CancelActiveCode validation not passed'],
        ['CancelReasonID',    'length:1,50',                    'CancelReasonID validation not passed'],
        ['OtherCancelReason',     'length:1,500',                    'OtherCancelReason  validation not passed'],
        ['CancelReasonIDs',     'length:1,50',                    'CancelReasonIDs validation not passed'],
        ['CountryCode',     'length:1,50',                    'CountryCode Invalid parameter length'],
        ['EndSendEmailTime',     'number|length:1,11',                    'EndSendCoupon Must be a number|EndSendCoupon Invalid parameter length'],
        ['limit',     'number|length:1,11',                    'limit Must be a number|limit Invalid parameter length'],
        ['siteId',     'number|length:1,11',                    'siteId Must be a number|siteId Invalid parameter length'],
        ['pageIndex',     'number|length:1,11',                    'pageIndex Must be a number|pageIndex Invalid parameter length'],
        ['totalRecord',     'number|length:1,11',                    'totalRecord Must be a number|totalRecord Invalid parameter length'],
        ['SendCouponNumber',     'number|length:1,11',                    'EndSendCoupon Must be a number|EndSendCoupon Invalid parameter length'],
    ];
    protected $scene = [
        'addSubscriber'   =>  ['CustomerID',"Email","Active","SiteId","ActiveCode","CancelActiveCode","CancelReasonID","OtherCancelReason","CancelReasonIDs","CountryCode"],
        'editSubscriberActive'=>['CustomerID'],
        'cancelSubscriber'=>['CustomerID','Email'],
        'checkSubscriber'=>['CustomerId','SiteID'],
        'getSubscriber'=>['CustomerId'],
        'getSubscriberCustomers'=>['Active','limit'],
        'updateSubscriberEndSendCoupon'=>['CustomerId'],
        'GetSimpleSubscribers'=>['siteId','pageIndex','totalRecord'],
        'getSubscriberCustomerIds'=>['siteId','pageIndex','totalRecord','SendCouponNumber'],
        'incSendCouponNumber'=>[],
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