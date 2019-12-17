<?php
namespace app\common\params\seller\product;

class CreateProductSkuParams
{
    public function rules()
    {
        return[
            //零售价
            ['SalesPrice','require|number|between:0.01,999999','零售价不能为空|零售价必须为数字|零售价金额请输入0.01~999999之间的数字'],
            //库存：maxlength="6"，大于0，不可输入非数字及点号
            ['Inventory','require|integer|between:0,999999','库存不能为空|库存必须为整数|库存请输入0~999999之间的整数'],
            //商品编码：maxlength="20" ，英文字母+数字组合。过滤危险字符
            ['Code','require|max:50','商品编码不能为空|商品编码最大长度50'],
//            ['SalesAttrs','require','SalesAttrs 不能为空'],
        ];
    }

    public function Coderules()
    {
        return[
            ['Code','number|max:9','商品编码必须是数字|商品编码最大长度9'],
        ];
    }
}