<?php
namespace app\orderbackend\services;

use app\orderbackend\model\OrderModel;
use think\Cache;


/**
 * 订单接口处理逻辑
 * Class OrderService
 * @author tinghu.liu 2018/4/23
 * @package app\orderFront\services
 */
class OrderService
{
    const CACHE_KEY = 'Order:';
    const CACHE_TIME = 360;

    private $model;
    public function __construct(){
    	
    }
    

}
