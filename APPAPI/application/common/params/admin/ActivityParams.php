<?php
namespace app\common\params\admin;

/**
 * 活动接口参数校验
 * Class Activity
 * @author tinghu.liu 2018/4/19
 * @package app\seller\controller
 */
class ActivityParams
{

    /**
     * 获取活动数据校验
     * @return array
     */
    public function getActivityRules()
    {
        return[
            ['tab_type','require|integer','tab_type不能为空|tab_type必须为整型'],
            ['seller_id','require|integer','seller_id不能为空|seller_id必须为整型'],
            ['time','require','time不能为空'],
            ['activity_type','integer','activity_type必须为整型'],
            ['activity_status','integer','activity_status必须为整型'],
        ];
    }

    /**
     * 根据活动ID获取单条活动详情数据参数校验
     * @return array
     */
    public function getActivityByActivityIDRules()
    {
        return[
            ['activity_id','require|integer','activity_id不能为空|activity_id必须为整型'],
        ];
    }

    /**
     * 增加报名数据参数校验
     * @return array
     */
    public function enrollActivityRules()
    {
        return[
            ['activity_id','require|integer','activity_id不能为空|activity_id必须为整型'],
            ['seller_id','require|integer','seller_id不能为空|seller_id必须为整型'],
            //['status','require|integer','status不能为空|status必须为整型'],
            ['add_time','require|integer','add_time不能为空|add_time必须为整型'],
            ['add_user_name','require','add_user_name不能为空'],
        ];
    }

    /**
     * 退出活动数据参数校验
     * @return array
     */
    public function quitActivityRules()
    {
        return[
            ['activity_id','require|integer','activity_id不能为空|activity_id必须为整型'],
            ['seller_id','require|integer','seller_id不能为空|seller_id必须为整型'],
            ['edit_time','require|integer','edit_time不能为空|edit_time必须为整型'],
            ['edit_user_name','require','edit_user_name不能为空'],
        ];
    }

    /**
     * 增加活动SKU参数校验
     * @return array
     */
    public function addActivitySKURules(){
        return[
            ['activity_id','require|integer','活动ID不能为空|活动ID必须为整型'],
            ['seller_id','require|integer','SellerID不能为空|SellerID必须为整型'],
            ['seller_name','require','Seller名称不能为空'],
            ['product_id','require|integer','产品ID不能为空|产品ID必须为整型'],
            ['sku','require|integer','sku不能为空|sku必须为整型'],
            ['code','require','code不能为空'],
            ['sales_price','require|>=:activity_price','产品价格不能为空|活动价格必须小于等于产品价格'],
            ['activity_price','require','活动价格不能为空'],

            ['set_type','require','set_type不能为空'],
            ['discount','>=:0|<=:100','活动折扣价必须大于0|活动折扣价必须小于100'],

            ['activity_inventory','require','产品活动库存不能为空'],
            ['add_time','require|integer','新增时间不能为空|新增时间必须为整型'],
        ];
    }

    /**
     * 更新活动SKU参数校验
     * @return array
     */
    public function updateActivitySKURules(){
        return[
            ['id','require|integer','id不能为空|id必须为整型'],

            ['product_id','require|integer'],
            ['activity_inventory','require|integer'],
            ['set_type','require|integer'],
            ['spu_id','require|integer'],

            ['edit_time','require|integer','edit_time不能为空|edit_time必须为整型'],
            ['edit_user_name','require','edit_user_name不能为空'],
        ];
    }

    /**
     * 获取活动SKU数据【列表页分页】参数校验
     * @return array
     */
    public function getActivitySKUDataForListRules(){
        return[
            ['activity_id','require|integer','activity_id不能为空|activity_id必须为整型'],
            ['seller_id','require|integer','seller_id不能为空|seller_id必须为整型'],
        ];
    }
}