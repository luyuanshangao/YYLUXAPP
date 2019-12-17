<?php
namespace app\common\params\mallextend\coupon;

/**
 * Coupon参数校验类
 * Class CouponParams
 * @author tinghu.liu 2018/5/11
 * @package app\common\params\mallextend\coupon
 */
class CouponParams
{
    /**
     * 增加coupon参数校验规则
     * @return array
     */
    public function getCouponListRules()
    {
        return[
            ['SellerId','require|integer','SellerId不能为空|SellerId必须为整型'],
            ['page_size','integer'],
            ['page','integer'],
        ];
    }

    /**
     * 增加coupon参数校验规则
     * @return array
     */
    public function getExchangeCouponListRules()
    {
        return[
            ['Type','integer'],
            ['TypeOne','integer'],
        ];
    }
    /**
     * 增加coupon参数校验规则
     * @return array
     */
    public function addCouponRules()
    {
        return[
            ['SellerId','require|integer','SellerId不能为空|SellerId必须为整型'],
            ['Name','require','Name不能为空'],
            ['LPUrl','require','LPUrl不能为空'],
            ['CreateBy','require','CreateBy不能为空'],
            ['CreateTime','require','CreateTime不能为空'],
        ];
    }

    /**
     * 增加coupon code参数校验规则
     * @return array
     */
    public function addCouponCodeRules()
    {
        return[
            ['CouponId','require|integer','CouponId不能为空|CouponId必须为整型'],
            //['coupon_code','require','coupon_code不能为空'],
            ['code_num','require|integer|>=:1','code_num不能为空|code_num必须为整型'],
            ['rules','require','rules不能为空'],
            ['CreateBy','require','CreateBy不能为空'],
            ['CreateTime','require','CreateTime不能为空'],
        ];
    }

    /**
     * 生成coupon code参数校验规则
     * @return array
     */
    public function getCouponCodeRules()
    {
        return[
            ['code_num','require|integer|>=:1','code_num不能为空|code_num必须为整型'],
            ['rules','require','rules不能为空'],
        ];
    }

    /**
     * 根据coupon ID获取coupon信息参数校验规则
     * @return array
     */
    public function getCouponByCouponIdRules()
    {
        return[
            ['CouponId','require|integer'],
        ];
    }

    /**
     * 根据coupon ID获取coupon code信息参数校验规则
     * @return array
     */
    public function getCouponCodeByCouponIdRules()
    {
        return[
            ['CouponId','require|integer'],
        ];
    }

    /**
     * 更新coupon信息参数校验规则
     * @return array
     */
    public function updateDataRules()
    {
        return[
            //更新标识：1-更新全部，2-更新coupon描述（含多语言），3-更新coupon状态
            ['flag','require|integer'],
            ['CouponId','require|integer'],
        ];
    }

    /**
     * 删除coupon Code参数校验规则
     * @return array
     */
    public function deleteCouponCodeRules()
    {
        return[
            ['CouponId','require|integer'],
            ['CouponCode','require'],
        ];
    }
}