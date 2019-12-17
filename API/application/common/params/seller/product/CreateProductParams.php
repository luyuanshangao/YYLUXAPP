<?php
namespace app\common\params\seller\product;

class CreateProductParams
{
    public function rules()
    {
        return[
            //产品标题：长度128字符，英文字符
            ['Title','require|max:128','Title 不能为空|Title 只能输入128字符'],
            //产品品牌
            ['BrandId','require|number','BrandId 不能为空|BrandId 必须为数字'],
            //产品的状态
//            ['ProductStatus','require|number','ProductStatus 不能为空','ProductStatus 必须为数字'],
            ['Skus','require','Skus 不能为空'],
//            ['Keywords','require','Keywords 不能为空'],
            ['ImageSet.ProductImg','require','ProductImg 不能为空'],
//            ['ImageSet.AttributeImg','require','AttributeImg 不能为空'],
            ['PackingList.Weight','require','产品包装后的重量不能为空'],
            ['PackingList.Dimensions','require','产品包装后的尺寸不能为空'],
//            ['LogisticsLimit','require','物流属性不能为空'],
            ['Descriptions','require','产品描述不能为空'],
            ['SalesUnitType','require','计量单位不能为空'],
            ['LogisticsTemplateId','require','物流运费模板不能为空'],
            ['StoreID','require','店铺ID不能为空'],
//            ['SalesMode','require','销售方式不能为空'],
        ];
    }


    public function updateProductRule(){
        return[
            ['id','require|integer','id 不能为空|id 只能是整型'],
        ];
    }

    /**
     * 根据StoreID更新产品联盟佣金数据校验
     * @return array
     */
    public function updateCommissionRules()
    {
        return[
            ['StoreID','require|integer'],
            ['CommissionType','require|integer'],
            ['Commission','require|float'],
        ];
    }

    /**
     * 根据StoreID更新产品联盟佣金数据校验
     * @return array
     */
    public function getProductByStoreIDRules()
    {
        return[
            ['StoreID','require|integer'],
            //['ProductStatus','integer'],
        ];
    }

    /**
     * 根据一级分类更新产品联盟佣金数据校验
     * @return array
     */
    public function updateCommissionByFirstCategoryRules()
    {
        return[
            ['StoreID','require|integer'],
            ['FirstCategory','require|integer'],
            ['CommissionType','require|integer'],
            ['Commission','require|float'],
        ];
    }

    /**
     * 根据一级分类更新产品联盟佣金数据校验
     * @return array
     */
    public function updateCommissionBySecondCategoryRules()
    {
        return[
            ['StoreID','require|integer'],
            ['FirstCategory','require|integer'],
            ['SecondCategory','require|integer'],
            ['CommissionType','require|integer'],
            ['Commission','require|float'],
        ];
    }

    public function updateGroup(){
        return[
            ['id','require'],
            ['GroupId','require'],
        ];
    }

    public function countSeller(){
        return[
            ['seller_id','require']
        ];
    }

    public function prolongExpiry(){
        return[
            ['id','require'],
            ['days','require'],

        ];
    }

    public function audit(){
        return[
            ['id','require'],
            ['status','require'],
        ];
    }


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
            ['Descriptions','require','产品描述不能为空'],

            ['PackingList.Weight','require','产品包装后的重量不能为空'],
            ['PackingList.Dimensions','require','产品包装后的尺寸不能为空'],
//            ['PackingList.UseCustomWeight','require','UseCustomWeight 不能为空'],

            ['LogisticsLimit','require','物流属性不能为空'],
            ['SalesUnitType','require','计量单位不能为空'],
            ['LogisticsTemplateId','require','物流运费模板不能为空'],
            ['AllowBulkRate','require','AllowBulkRate 不能为空'],
            ['FilterOptions','require','FilterOptions 不能为空'],

            ['Skus','require','Skus 不能为空'],
        ];
    }


    public function updatePrdouctmMultiLangs(){
        return[
            ['id','require|integer','id 不能为空|id 只能是整型'],
            ['lang','require','lang 不能为空'],
        ];
    }

    public function batchUpdateSalesRank(){
        return[
            ['spus','require'],
            ['sales_rank','require'],
        ];
    }
    public function batchUpdateProductStatus(){
        return[
            ['spus','require'],
            ['status','require'],
        ];
    }
}