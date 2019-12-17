<?php
namespace app\common\params\seller\seller;

class UpdateSellerExtensionParams
{
    public $company_name;
    public $company_phone;
    public $corporation_name;
    public $company_contact;
    public $company_contact_phone;
    public $operation_scope;
    public $registered_capital;
    public $social_credit_code;
    public $management_model;
    public $company_address;
    public $business_license_pic;
    public $corporation_idcard_facade;
    public $corporation_idcard_reverse;
    public $idcard_facade;
    public $idcard_reverse;
    public $op_name;
    public $op_time;

    public function rules()
    {
        return[
            ['op_name','require|max:100','op_name不能为空|最大长度100'],
        ];
    }
}