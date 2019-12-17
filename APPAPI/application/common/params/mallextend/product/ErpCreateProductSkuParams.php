<?php
namespace app\common\params\mallextend\product;

class ErpCreateProductSkuParams
{
    public function rules()
    {
        return[
            //零售价
            ['SalesPrice','require|number|between:0.01,999999','SalesPrice 不能为空|SalesPrice 必须为数字|SalesPrice 金额请输入0.01~999999之间的数字'],
            //库存：maxlength="6"，大于0，不可输入非数字及点号
            ['Inventory','require|integer|between:0,999999','Inventory 不能为空|Inventory 必须为整数|Inventory 请输入0~999999之间的整数'],
            //商品编码：maxlength="20" ，英文字母+数字组合。过滤危险字符
            ['Code','require|max:50','Code 不能为空|Code 最大长度50'],
//            ['SalesAttrs','require','SalesAttrs 不能为空'],
        ];
    }

    public function Coderules()
    {
        return[
            ['Code','number|max:9','Code 必须是数字|Code 最大长度9'],
        ];
    }

    public function BulkRateRules()
    {
        return[
            ['BulkRateSet.SalesPrice','require|number|between:0.01,999999','批发价格不能为空|批发价格必须为数字|批发价格金额请输入0.01~999999之间的数字'],
            ['BulkRateSet.Discount','require|number','Discount 不能为空|Discount 是数字'],
            ['BulkRateSet.Batches','require|integer','Batches 不能为空|Batches 必须为整数'],
        ];
    }

    public function SalesAttrsRules()
    {
        return[
            ['_id','require','_id 不能为空'],
            ['Name','require','Name 不能为空'],
            ['Value','require','Value 不能为空'],
            ['OptionId','require','OptionId 不能为空'],
        ];
    }

    public function skuImgRules()
    {
        return[
            ['Image','require','Image 不能为空'],
        ];
    }
}