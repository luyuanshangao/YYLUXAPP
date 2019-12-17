<?php
namespace app\common\params\seller\seller;

class UpdateSellerParams
{
    public $true_name;
    public $seller_code;
    public $phone_num;
    public $email;
    public $province;
    public $city;
    public $country_town;
    public $address;
    public $status;
    public $op_name;
    public $op_desc;
    public $op_time;
    public $is_delete;

    public function rules()
    {
        return[
            ['user_id','require','user_id不能为空'],
            ['op_name','require|max:100','op_name不能为空|最大长度100'],
        ];
    }
}