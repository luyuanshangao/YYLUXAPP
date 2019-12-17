<?php
namespace app\common\params;

class ProductActivityParams
{

    public function getSellerCouponRules()
    {
        return[
            ['product_id','require|number','Productid Must be Required | Productid Must be a number'],
            ['lang','require|max:2','Lang Must be Required | Lang Invalid parameter length'],
            ['store_id','require|number','Storeid Must be Required | Storeid Must be a number'],
            ['brand_id','number','Brandid Must be a number'],
            ['categoryPath','require|max:100','CategoryPath Must be Required | CategoryPath Invalid parameter length'],
        ];
    }

    public function addCustomerCouponRules()
    {
        return[
            ['coupon_id','require|number','coupon_id Must be Required | coupon_id Must be a number'],
            ['lang','require|max:2','Lang Must be Required | Lang Invalid parameter length'],
            ['customer_id','require|number','Customer_id Must be Required | Customer_id Must be a number'],
        ];
    }

    public function getActivityProductRules()
    {
        return[
            ['activity_id','require|number|max:6','activityid Must be Required | activityid Must be a number | activityid Invalid parameter length '],
            ['lang','require|max:2','Lang Must be Required | Lang Invalid parameter length'],
            ['page','number','page Must be a number'],
        ];
    }
}