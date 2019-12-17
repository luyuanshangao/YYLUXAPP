<?php
namespace app\common\params\mallextend\product;

class ErpCreateProductParams
{

    /**
     * erp 新增商品接口
     * @return array
     */
    public function productCreateRule()
    {
        return[
            //品牌ID
            //['BrandId','number','BrandId 必须为数字'],
            //品牌名称
            //['BrandName','require','BrandName 不能为空'],
            //店铺ID
            ['StoreID','require|number','StoreID 不能为空|StoreID 必须为数字'],
            //店铺名称
            //['StoreName','require','StoreName 不能为空'],
            //分组ID
            //['GroupId','number','GroupId 必须为数字'],
            //分组名称
            //['GroupName','require','GroupName 不能为空'],
            //末级分类ID
            ['LastCategory','require|number','LastCategory 不能为空|LastCategory 必须为数字'],

            ['ImageSet.ProductImg','require','ProductImg 不能为空'],
            //产品标题：长度128字符，英文字符
            ['Title','require|max:128','Title 不能为空|Title 只能输入128字符'],
            ['Keywords','require','Keywords 不能为空'],
            ['Descriptions','require','Descriptions 不能为空'],

            ['PackingList.Weight','require','Weight 不能为空'],
            ['PackingList.Dimensions','require','产品包装后的尺寸不能为空'],
            ['PackingList.UseCustomWeight','require','UseCustomWeight 不能为空'],

            ['LogisticsLimit','require','物流属性不能为空'],
            ['SalesUnitType','require','SalesUnitType 不能为空'],
            ['LogisticsTemplateId','require','物流运费模板不能为空'],
            ['AllowBulkRate','require','AllowBulkRate 不能为空'],
//            ['FilterOptions','require','FilterOptions 不能为空'],

            ['Skus','require','Skus 不能为空'],
        ];
    }


    /**
     * erp 新增商品接口
     * @return array
     */
    public function productWeightRule()
    {
        return[
            ['CustomeWeightInfo.Qty','require','Qty 不能为空'],
            ['CustomeWeightInfo.IncreaseQty','require','IncreaseQty 不能为空'],
            ['CustomeWeightInfo.IncreaseWeight','require','IncreaseWeight 不能为空'],
        ];
    }

}