<?php
namespace app\common\params\orderbackend;

/**
 * 订单参数校验类
 * Class OrderParams
 * @author tinghu.liu 2019/7/10
 * @package app\common\params\orderbackend
 */
class OrderExtendParams
{
    /**
     * 根据OrderNumber获取订单数据数据校验
     *{
        "order_number":"", //订单号（子单）
        "load_order_lines":"", //是否返回订单明细：0-不返回，1-返回
        "load_order_status_history":"", //是否返回订单状态变更历史：0-不返回，1-返回
     * }
     * @return array
     */
    public function getByOrderNumberRules(){
        return [
            ['order_number','require','订单号错误'],
            ['load_order_lines','integer|in:0,1','参数错误'],
            ['load_order_status_history','integer|in:0,1','参数错误'],
        ];
    }

    /**
     * 根据用户ID获取用户订单数据校验
     *  {
            "customer_id":"34",
            "page_size":"10",
            "page":"3",
            "path":""
        }
     * @return array
     */
    public function getByCustomerIdRules(){
        return [
            ['customer_id','require','参数错误'],
            ['page_size','integer','page_size必须为整型'],
            ['page','integer','page必须为整型'],
            ['path','url','path必须为url格式'],
        ];
    }

    /**
     * 根据多个OrderNumber获取订单数据数据校验
     *  {
            "order_numbers":["15163136136","1356493812131"]
        }
     * @return array
     */
    public function getByOrderNumbersRules(){
        return [
            ['order_numbers','require','参数错误']
        ];
    }

}