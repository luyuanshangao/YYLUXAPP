<?php
namespace app\common\params\seller\product;

class UpdateProductStatusParams
{
    public function rules()
    {
        return[
            ['id','require','id不能为空'],
            ['status','require|integer','状态不能为空|必须为整数'],
        ];
    }

    public function statusRule(){
        return[
            ['id','require','id不能为空'],
            ['status','require','状态不能为空'],
        ];
    }
}