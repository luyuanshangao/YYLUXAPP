<?php
namespace app\common\params\mall;

/**
 * Coupon相关校验
 * @author tinghu.liu
 * 2018-12-22
 */
class CouponParams
{
    /**
     * 通过coupon code获取coupon信息数据校验
     * @return array
     */
    public function getCouponInfoByCouponCodeRule()
    {
        return [
            ['StoreId','require','StoreId require.'],
            ['CouponCode','require','CouponCode require.'],
            ['ActivityStrategy','require','ActivityStrategy require.'],
        ];
    }
}