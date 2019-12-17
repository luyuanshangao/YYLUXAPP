<?php
namespace app\common\params\app;

class CartParams
{
    /**
     * 添加购物车数据校验
     * @return array
     */
    public function addRules()
    {
        return[
            ['ProductID','require|integer', 'The ProductID is require.|The ProductID must be an integer.'],
            ['SkuID','require', 'The SkuID is require.'],
            ['Qty','require|integer', 'The Qty is require.|The Qty must be an integer.'],
            ['ShipTo','require', 'The ShipTo is require.'],
            ['Currency','require', 'The Currency is require.'],
            ['Lang','require', 'The Lang is require.'],

            ['CustomerId','require', 'The CustomerId is require.'],
            ['isGuest','require|integer', 'The isGuest is require.|The isGuest must be an integer.'],
            ['ShippingService','require', 'The ShippingService is require.'],
        ];
    }

    /**
     * 更新购物车数据校验
     * @return array
     */
    public function updateCartRules()
    {
        return[
            ['CustomerId','require|integer', 'The CustomerId is require.|The CustomerId must be an integer.'],
            ['CartData','require', 'The CartData is require.'],
        ];
    }

    /**
     * 获取购物车数据校验
     * @return array
     */
    public function getCartRules()
    {
        return[
            ['CustomerId','integer', 'The CustomerId must be an integer.'],
            //['CustomerName','require'],
            ['GuestId','require', 'The GuestId is require.'],
            //币种
            ['Currency','require', 'The Currency is require.'],
            //收货国家
            ['Country','require', 'The Country is require.'],
            //语种
            ['Lang','require', 'The Lang is require.'],
        ];
    }

    /**
     * 清空购物车数据校验
     * @return array
     */
    public function clearCartRules()
    {
        return[
            ['CustomerId','require|integer', 'The CustomerId is require.|The CustomerId must be an integer.'],
        ];
    }

    /**
     * 获取购物车数量数据校验
     * @return array
     */
    public function getCartCountRules()
    {
        return[
            ['CustomerId','require|integer', 'The CustomerId is require.|The CustomerId must be an integer.'],
        ];
    }

    /**
     * 获取购物车运费初始化数据校验
     * @return array
     */
    public function getInitShippingDataRules()
    {
        return[
            //币种
            ['Currency','require','The Currency is require.'],
            ['CustomerId','integer','The CustomerId must be an integer.'],
            //['CustomerName','require'],
            ['GuestId','require','The GuestId is require.'],
            //收货国家
            ['Country','require','The Country is require.'],
            //语种
            ['Lang','require','The Lang is require.'],
        ];
    }

    /**
     * 获取购物车运费初始化数据校验
     * @return array
     */
    public function removeFromCartRules()
    {
        return[
            ['CustomerId','integer','The CustomerId must be an integer.'],
            //['CustomerName','require'],
            ['GuestId','require','The GuestId is require.'],
            ['SkuInfo','require','The SkuInfo is require.'],
        ];
    }

    /**
     * 获取购物车运费初始化数据校验[产品数据校验]
     * @return array
     */
    public function removeFromCartSkuInfoRules()
    {
        return[
            ['ProductId','require','The ProductId is require.'],
            ['SkuId','require','The SkuId is require.'],
        ];
    }

    /**
     * 选中和不选中产品（包含全选）数据校验
     * @return array
     */
    public function isCheckRules()
    {
        return[
            ['CustomerId','integer','The CustomerId must be an integer.'],
            ['GuestId','require','The GuestId is require.'],
            //币种
            ['Currency','require','The Currency is require.'],
            //收货国家
            ['Country','require','The Country is require.'],
            //语种
            ['Lang','require','The Lang is require.'],
            ['Data','require','The Data is require.'],
        ];
    }

    /**
     * 选中和不选中产品（包含全选）数据校验【产品数据】
     * @return array
     */
    public function isCheckDataRules()
    {
        return[
            ['store_id','require|integer','The store_id is require.|The store_id must be an integer.'],
            ['product_id','require|integer','The product_id is require.|The product_id must be an integer.'],
            ['sku_id','require', 'The sku_id is require.'],
            //数量
            ['qty','require|integer','The qty is require.|The qty must be an integer.'],
            //是否选中，1-选中，2-不选中
            ['is_check','require|integer','The is_check is require.|The is_check must be an integer.'],
        ];
    }

    /**
     * 购物车产品数量变化数据校验【产品数据】
     * @return array
     */
    public function changeProductNumsRules()
    {
        return[
            ['CustomerId','integer', 'The CustomerId must be an integer.'],
            ['GuestId','require', 'The GuestId is require.'],

            ['ProductID','require', 'The ProductID is require.'],
            ['SkuID','require', 'The SkuID is require.'],
            ['ShipModel','require', 'The ShipModel is require.'],
            //['ShipTo','require'],
            ['Qty','require', 'The Qty is require.'],
            ['StoreID','require', 'The StoreID is require.'],

            //币种
            ['Currency','require', 'The Currency is require.'],
            //收货国家
            ['Country','require', 'The Country is require.'],
            //语种
            ['Lang','require', 'The Lang is require.'],
        ];
    }

    /**
     * 获取运输方式数据校验
     * @return array
     */
    public function getShipModelRules()
    {
        return[
            ['ProductID','require', 'The ProductID is require.'],
            ['SkuID','require', 'The SkuID is require.'],
            ['ShipTo','require', 'The ShipTo is require.'],
            ['Qty','require', 'The Qty is require.'],
            //币种
            ['Currency','require', 'The Currency is require.'],
            //语种
            ['Lang','require', 'The Lang is require.'],


            //非必传参数（只有是checkou才传）
            //['PayType','require'],
            //['IsPaypalQuick','require'],
        ];
    }
    /**
     * 改变运输方式数据校验
     * @return array
     */
    public function changeShipModelRules()
    {
        return[
            ['GuestId','require', 'The GuestId is require.'],
            ['CustomerId','integer','The CustomerId must be an integer.'],
            //币种
            ['Currency','require','The Currency is require.'],
            ['ShipTo','require','The ShipTo is require.'],
            ['ShipModel','require','The ShipModel is require.'],
            ['StoreId','require','The StoreId is require.'],
            ['ProductID','require|integer','The ProductID is require.|The ProductID must be an integer.'],
            ['SkuID','require','The SkuID is require.'],
            ['Qty','require','The Qty is require.'],
            //语种
            ['Lang','require','The Lang is require.'],
        ];
    }

    /**
     * 使用coupon数据校验【sku级别】
     * @return array
     */
    public function useCouponRules()
    {
        return[
            ['GuestId','require','The GuestId is require.'],
            ['CustomerId','require|integer','The CustomerId is require.|The CustomerId must be an integer.'],


            ['StoreId','require|integer','The StoreId is require.|The StoreId must be an integer.'],
            ['ProductID','require|integer','The ProductID is require.|The ProductID must be an integer.'],
            ['SkuID','require','The SkuID is require.'],
            ['Qty','require','The Qty is require.'],
            //如果为2则表示是seller级别的coupon,如果为1则表示为sku级别的coupon
            ['DiscountLevel','require','The DiscountLevel is require.'],

            /** 非必传 start ，传了就是使用coupon，没传就是取消coupon的使用**/
            ['CouponId','integer','The CouponId is require.'],
            //非必传
            //['CouponCode',''],

            /** 非必传 end **/

            //币种
            ['Currency','require','The Currency is require.'], //币种
            ['ShipTo','require','The ShipTo is require.'], //国家
            //语种
            ['Lang','require','The Lang is require.'],
        ];
    }

    /**
     * 使用coupon数据校验【seller级别】
     * @return array
     */
    public function useCouponForSellerRules()
    {
        return[
            ['GuestId','require','The GuestId is require.'],
//            ['CustomerId','require|integer','The CustomerId is require.|The CustomerId must be an integer.'],
            //使用coupon不需要登录 tinghu.liu 20190424
            ['CustomerId','integer','The CustomerId must be an integer.'],


            ['StoreId','require|integer','The StoreId is require.|The StoreId must be an integer.'],
//            ['ProductID','require|integer','The ProductID is require.|The ProductID must be an integer.'],
//            ['SkuID','require','The SkuID is require.'],
//            ['Qty','require','The Qty is require.'],
            //如果为2则表示是seller级别的coupon,如果为1则表示为sku级别的coupon
            ['DiscountLevel','require','The DiscountLevel is require.'],

            /** 非必传 start ，传了就是使用coupon，没传就是取消coupon的使用**/
            ['CouponId','integer','The CouponId is require.'],
            //非必传
            //['CouponCode',''],

            /** 非必传 end **/

            //币种
            ['Currency','require','The Currency is require.'], //币种
            ['ShipTo','require','The ShipTo is require.'], //国家
            //语种
            ['Lang','require','The Lang is require.'],
        ];
    }

    /**
     * go to CheckOut数据校验
     * @return array
     */
    public function goToCheckOutRules()
    {
        return[
            ['GuestId','require','The GuestId is require.'],
            ['CustomerId','require|integer','The CustomerId is require.|The CustomerId must be an integer.'],
            ['Skus','require','The Skus is require.'], //sku数据
            ['ShippToCountry','require','The ShippToCountry is require.'], //国家
        ];
    }

    /**
     * go to CheckOut数据校验【Skus】
     * @return array
     */
    public function goToCheckOutSkusRules()
    {
        return[
            ['StoreID','require|integer','The StoreID is require.|The StoreID must be an integer.'],//StoreID
            ['ProductID','require|integer','The ProductID is require.|The ProductID must be an integer.'],
            ['SkuID','require', 'The SkuID is require.'],
            ['Qty','require', 'The Qty is require.'],
            ['ShipModel','require', 'The ShipModel is require.'],
        ];
    }

    /**
     * 清空购物车数据校验
     * @return array
     */
    public function getRecommend()
    {
        return[
            ['CustomerId','require|integer','The CustomerId is require.|The CustomerId must be an integer.'],
        ];
    }


}