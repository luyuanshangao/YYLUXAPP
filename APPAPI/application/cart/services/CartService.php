<?php
namespace app\cart\services;

use app\cart\model\CartModel;
use think\Cache;


/**
 * 购物车接口处理逻辑
 */
class CartService
{
    const CACHE_KEY = 'CART:';
    const CACHE_TIME = 360;

    protected $cartModel;

    public function __construct()
    {
        $this->cartModel = new CartModel();
    }

    public function getPayType($params){

        if(!empty($params)){
        	
            $ret = (new CartModel())->getPayType($params['Currency']);

            if($ret){
           		return $ret;
            }
            //return true;
        }
        //return false;

    }

}