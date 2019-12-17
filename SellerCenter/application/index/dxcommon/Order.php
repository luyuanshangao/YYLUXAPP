<?php
namespace app\index\dxcommon;


/**
 * Class Order
 * @author tinghu.liu
 * @date 2018-05-02
 * @package app\index\dxcommon
 */
class Order
{
    /**
     * 根据订单ID获取订单详情
     * @param $order_id 订单ID
     * @return array
     */
    public static function getOrderInfoByOrderId($order_id,$store_id=0){
        $order_info = [];
        $base_api = new BaseApi();
        $order_info_api = $base_api->getOrderInfo($order_id,$store_id);
        if (isset($order_info_api['data']) && !empty($order_info_api['data'])){
            $order_info = $order_info_api['data'];
            //订单状态
            $order_status_str = Base::getOrderStatus($order_info['order_status']);
            $order_info['order_status_str'] = isset($order_status_str['name'])?$order_status_str['name']:'-';
            //币种
            $order_info['currency_code_str'] = Base::getCurrencyCodeStr($order_info['currency_code']);



            //收货地址{$order_info.shipping_data.country} {$order_info.shipping_data.city} {$order_info.shipping_data.street1} {$order_info.shipping_data.street1}
            if (!empty($order_info['shipping_data'])){
                $shipping_address = $order_info['shipping_data']['country'].' '.$order_info['shipping_data']['city'].' '.$order_info['shipping_data']['street1'].' '.$order_info['shipping_data']['street1'];
            }else{
                $shipping_address = '';
            }
            $order_info['shipping_address'] = $shipping_address;
        }

        return $order_info;
    }



}
