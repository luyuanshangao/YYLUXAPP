<?php
namespace app\common\params\admin;

/**
 * Affiliate接口参数校验
 * Class Activity
 * @author tinghu.liu 2018/5/26
 * @package app\common\params\admin
 */
class AffiliateParams
{

    /**
     * 获取分类佣金配置列表数据校验
     * @return array
     */
    public function getClassCommissionListRules()
    {
        return[
            ['seller_id','require|integer','SellerID不能为空|SellerID必须为整型'],
            ['type','integer','类型必须为整型'],
            ['status','integer','状态必须为整型'],
        ];
    }

    /**
     * 增加产品数据校验
     * [class_id] => 1
    [spu] => 129
    [commission] => 0.22
    [effect_time] => 2018-06-01
    [type] => 2
    [seller_id] => 18
    [add_time] => 1527500958
     * @return array
     */
    public function addAffiliateProductRules()
    {
        return[
            ['seller_id','require|integer','seller_id不能为空|seller_id必须为整型'],
            ['class_id','require|integer','分类ID不能为空|分类ID必须为整型'],
            ['spu','require|integer','产品ID不能为空|产品ID必须为整型'],
            ['commission','require|float|>=:0.05|<=:0.5','佣金比例不能为空|佣金比例必须为浮点数|佣金比例大于等于0.05|佣金比例必须小于等于0.5'],
            ['effect_time','require|integer'],
            ['type','require|integer'],
            ['add_time','require|integer'],
        ];
    }

    /**
     * 更新联盟营销产品数据数据校验
     * @return array
     */
    public function updateAffiliateProductRules()
    {
        return[
            ['id','require|integer'],
            ['class_id','require|integer','分类不能为空|分类必须为整型'],
            ['commission','require|float|>=:0.05|<=:0.5','佣金比例不能为空|佣金比例必须为浮点数|佣金比例必须大于等于5%|佣金比例必须小于等于50%'],
            ['effect_time','require|integer', '生效时间不能为空|生效时间必须是整型'],
            ['update_time','require|integer'],
        ];
    }

    /**
     * 获取主推产品数量情况数据校验
     * @return array
     */
    public function getMainProductNumRules()
    {
        return[
            ['seller_id','require|integer','SellerID不能为空|SellerID必须为整型'],
        ];
    }

    /**
     * 判断seller是否已经加入联盟营销数据校验
     * @return array
     */
    public function judgeIsJoinRules()
    {
        return[
            ['seller_id','require|integer','SellerID不能为空|SellerID必须为整型'],
        ];
    }

    /**
     * 获取联盟营销产品列表数据校验
     * @return array
     */
    public function getAffiliateProductListRules()
    {
        return[
            ['seller_id','require|integer','SellerID不能为空|SellerID必须为整型'],
            //数据类型:1 非主推产品; 2 主推产品;
            ['type','require|integer'],
            //审核状态:0 待审核; 1 审核通过; 2 审核不通过;
            ['status','require|integer'],
            //spu ID
            ['spu','integer'],
            //分页参数
            ['page_size','integer','page_size必须为整型'],
            ['page','integer','page必须为整型'],
            ['path','url','path必须为url格式'],
        ];
    }

    /**
     * 获取affiliate订单列表数据校验
     * @return array
     */
    public function getAffiliateOrderListRules()
    {
        return[
            //商家ID
            ['store_id','require|integer'],
            //订单状态
            ['order_status','integer'],
            //添加时间
            ['create_on_start','integer'],
            ['create_on_end','integer'],
            //分页参数
            ['page_size','integer','page_size必须为整型'],
            ['page','integer','page必须为整型'],
            ['path','url','path必须为url格式'],
        ];
    }

    /**
     * 根据affiliate订单ID获取affiliate订单详情数据校验
     * @return array
     */
    public function getAffiliateOrderInfoByIdRules()
    {
        return[
            //affiliate订单ID
            ['affiliate_order_id','require|integer'],
        ];
    }

}