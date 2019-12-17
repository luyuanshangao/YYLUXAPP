<?php
namespace app\orderbackend\services;

/**
 * 基础处理静态类
 * Class OrderService
 * @author tinghu.liu 2018/5/22
 * @package app\orderFront\services
 */

class Base
{

    /**
     * 获取订单售后类型
     * @param int $type 类型：1换货，2退货 3退款
     * @return array|mixed
     */
    public static function getOrderAfterSaleType($type=-1){
        $rtn = [
            ['id'=>1,'name'=>'换货'],
            ['id'=>2,'name'=>'退货'],
            ['id'=>3,'name'=>'退款'],
        ];
        if ($type != -1 && $type>=0){
            foreach ($rtn as $info){
                if ($info['id'] == $type){
                    $rtn = $info;
                    break;
                }
            }
        }
        return $rtn;
    }

}