<?php
namespace app\common\params;

/**
 * Index数据校验类
 * Class IndexParams
 * @author tinghu.liu 2018/5/15
 * @package app\common\params
 */
class IndexParams
{
    /**
     * 异步处理seller提交审核信息规则校验【个人】
     * @return array
     */
    public function async_submitSellerInfoRulesOne()
    {
        return[
            ['true_name', 'require', '真实姓名不能为空'],
            ['idcard_num', 'require', '身份证号码不能为空'],
            ['idcard_facade', 'require', '身份证照片（正）不能为空'],
            ['idcard_reverse', 'require', '身份证照片（反）不能为空'],
        ];
    }
    /**
     * 异步处理seller提交审核信息规则校验【企业】
     * @return array
     */
    public function async_submitSellerInfoRulesTwo()
    {
        return[
            ['company_name', 'require', '公司名称不能为空'],
            ['social_credit_code', 'require', '社会信用代码不能为空'],
            ['business_license_pic', 'require', '营业执照图片不能为空'],
            ['company_address','require', '公司地址不能为空'],
            ['operation_scope','require', '经营范围不能为空'],
            ['registered_capital','require|float', '注册资金不能为空|注册资金格式错误'],
            ['company_phone','require', '公司联系电话不能为空'],
            ['company_contact','require', '公司联系人不能为空'],
            ['company_contact_phone','require', '公司联系人电话不能为空'],
            ['corporation_name','require', '法人姓名不能为空'],
            ['corporation_idcard_facade','require', '法人手持身份证拍照（正）不能为空'],
            ['corporation_idcard_reverse','require', '法人手持身份证拍照（反）不能为空'],
        ];
    }

    /**
     * 添加子账号数据规则校验
     * @return array
     */
    public function async_addChildAcctRules(){
        return[
            ['email', 'require|email', '邮箱不能为空'],
            ['password', 'require', '密码不能为空'],
            ['confirm_password', 'require', '密码不能为空'],
            ['true_name', 'require', '真实姓名不能为空'],
            ['phone_num', 'require', '手机号码不能为空'],
            ['sex', 'require', '性别不能为空']
        ];
    }

}