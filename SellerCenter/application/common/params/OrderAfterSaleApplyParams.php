<?php
namespace app\common\params;

/**
 * 用户售后申请数据校验类
 * Class OrderAfterSaleApplyParams
 * @author tinghu.liu 2018/6/29
 * @package app\common\params
 */
class OrderAfterSaleApplyParams
{
    /**
     * 异步处理提交售后申请数据规则校验
     * @return array
     */
    public function async_submitApplyRules()
    {
        return[
            ['order_id', 'require|integer'],
            ['order_number', 'require'],
            /*['customer_id', 'require|integer'],
            ['customer_name', 'require'],*/
            /*['store_name', 'require'],
            ['store_id', 'require|integer'],
            ['payment_txn_id', 'require'],

            ['type', 'require|integer'],
            ['refunded_type', 'require|integer'],*/
            //['after_sale_reason', 'require|integer'],
            //['remarks', 'require'],
            //['item', 'require'],
        ];
    }

    /**
     * 异步处理提交售后申请数据规则校验-item数据
     * @return array
     */
    public function async_submitApplyItemsRules()
    {
        return[
            ['product_id', 'require|integer'],
            ['sku_id', 'require|integer'],
            ['sku_num', 'require'],
            ['product_name', 'require'],
            ['product_img', 'require'],
            /*['product_attr_ids', 'require'],
            ['product_attr_desc', 'require'],*/
            ['product_nums', 'require|integer'],
            ['product_price', 'require'],
        ];
    }

    /**
     * 异步处理取消仲裁数据规则校验
     * @return array
     */
    public function async_cancelArbitrationRules()
    {
        return [
            //['log_type','require|integer'],
            ['after_sale_id','require|integer'],
            //['title','require|integer'],
            //['user_type','require|integer'],
            //['user_id','require|integer'],
            //['user_name','require|integer'],
            //['content','require|integer'],
        ];
    }
}