<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/3/16
 * Time: 13:45
 */
return[
  "apicode" =>[
      200   => "Success",//操作成功
      //缺少参数
      1001  => "Lack of parameters",
      //内部错误
      1002  => "Internal error",
      //请求参数不能为空
      1003  => "The request parameter can not null",
      //数组太长
      1004  => "The Array Is Longer",
      //参数的长度太长
      1005  => "The Parameter's Length Is Longer",
      //结果为空，没有数据
      1006  => "The Result Is Null",
      //错误的邮件格式或电子邮件的长度无效
      1007  => "Wrong Mail format Or Email's Length Is Invalid( EmailUsername[1, 80),EmailDomainname[1,50]).",
      //用户名必须填写
      1008  => "UserName is required",
      //密码必须填写
      1009  => "Password is required",
      //错误的原始密码
      1010  => "Wrong original password",
      //电子邮件有已经存在
      1011  => "Email hava already existed",
      //有已经存在的用户名
      1012  => "UserName hava already existed",
      //用户名禁止更改
      1013  => "Username forbid update",
      //账号不存在
      1014  => "Account password error",
      //站点不存在
      1015  => "Site does not exist",
      //密码错误
      1016  => "password is error.",
      //用户名称为空
      1017  => "UserNameIsNullOrEmpty",
      //AnonymousCustomer没有激活
      1018  => "AnonymousCustomer does not activate",
      //帐户激活
      1019  => "Account Is Activate",
      //验证码无效
      1020  => "Verification code is invalid",
      //账户是无效的
      1021  => "Account is inactive",
      //地址不存在
      1022  => "Address does not exist",
      //地址模板为空
      1023  => "Address model is null",
      //Title必须填写
      1024  => "Title is required",
      //Body必需填写
      1025  => "Body is required",
      //信用卡不存在
      1026  => "CreditCard Is not Exist",
      //信用卡存在
      1027  => "CreditCard Is Exist",
      //tokenstatus是不存在的
      1028  => "TokenStatus Is not Exist",
      //成员不存在
      1029  => "Member Is not Exist",
      //成员无效
      1030  => "Member Is not Valid",
      //成员有已经存在
      1031  => "Member hava already existed",
      //实体关联的错误
      1032  => "entity relevance error",
      //成员级别不属于[1,10]
      1033  => "Member Level Is not belong to [1,10]",
      // CICService的Send，缺少发件人的提示信息。
      1034  => "Addresser is not exist for siteId",
      // 通过RCode查询Affiliate信息不存在的提示模板
      1035  => "affiliate is not exist for RCode {0}.",
      // 通过RCode查询Affiliate信息不存在的提示模板
      1036  => "affiliate is not exist for CICID {0}.",
      // 通过RCode查询Affiliate信息存在的提示模板
      1037  => "affiliate is exist for RCode {0}.",
      // 注册Affiliate的RCode已经存在
      1038  => "RCode has already existed.",
      // 已注册了Affiliate账户
      1039  => "Affiliate has aready existed.",
      #region TokenService
      // TokenValue 必填。
      1040  => "TokenValue is required.",
      // TokenValue 在数据库中不存在。
      1041  => "TokenValue is not exist.",
      // AccountInfo参数必填
      1042  => "AccountInfo is Required.",
      // token的过期时间不能小于当前时间
      1043  => "Token timeout should big then Now.",
      #region Thirdparty Customer
      //第三方站点不存在。
      1044  => "Third party site does not exist.",
      //第三方站点已绑定。
      1045  => "Third Party Site already bound.",
      //第三方电子邮件和帐号ID不能为空
      1046  => "Third Party Email and  AccountId can not be null ,at the same time.",
      //客户和第三方账户不匹配
      1047  => "Customer And Thrid Party Account don't match.",
      #endregion
      //不能等同于旧密码。
      1048  => "Cann't equal to the old password.",
      //参数的长度无效。
      1049  => "The Parameter's Length Is Invalid.",
      //不能等于登录密码
      1050  => "Cann't equal to the login Password .",
      //重置支付密码没有验证码！
      1051  => "The auth code is no exist for reset payment password!",
      //重置支付密码的验证码过期！
      1052  => "The auth code is expired for reset payment password!",
      // Token已过期。
      1053  => "The Token is expired !",
      // 用户地址最多10条
      1054  => "A maximum of 10 addresses !",
      //会员账号禁止登录
      1101  =>"Account account is forbidden to log in",
      //已经参加过活动
      1201  =>"You have already participated in the activities",
      //已经点过赞
      1301  =>"You  pro a good review.",
  ],
];