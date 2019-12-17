<?php
namespace app\common\params\cic;

/**
 * CIC相关验证类 tinghu.liu 20191113
 * Class MyCouponParams
 * @package app\common\params\cic
 */
class MyCouponParams
{
    /**
     * @return array
     */
    public function updateCouponForOrderRules()
    {
        return[
            ['coupon_id','require', 'The Coupon ID is require.'],
            ['coupon_code','require', 'The Coupon Code is require.'],
            ['order_number','require', 'The Order Number is require.'],
            ['new_order_number','require', 'The New Order Number is require.'],
        ];
    }

}