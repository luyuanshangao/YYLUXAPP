<?php
namespace app\orderfrontend\services;

use app\orderfrontend\model\OrderModel;
use think\Cache;
/**
 * 订单接口处理逻辑
 * @author gz
 * 2018-05-01
 */
class OrderService
{
    const CACHE_KEY = 'Order:';
    const CACHE_TIME = 360;

    private $model;
    public function __construct(){
    	
    }
    
    /**
     * 提交订单
     * @param unknown $params
     * @return Ambigous <\app\orderfrontend\model\true/false, boolean, multitype:NULL , multitype:string >|boolean
     */
    public function submitOrder($params){
//     	$ret = (new OrderModel())->submitOrder($params);
//     	exit;
    	if(!empty($params)){
    		 
    		$ret = (new OrderModel())->submitOrder($params);

    		if($ret){
    			return $ret;
    		}else{
    			return false;
    		}
    		
    	}
    	return false;
    
    }
    
    /**
     * 根据订单ID获取订单的编号
     * @param array $params
     * @return unknown|boolean
     */
    public function getOrderNumberByOrderId($params){
    	 
    	if(!empty($params)){
    		 //数组转成字符串
    		$params = implode(',',$params);

    		$ret = (new OrderModel())->getOrderNumberByOrderId($params);
    		if($ret){
    			return $ret;
    		}else{
    			return false;
    		}
    
    	}
    	return false;
    
    }
    
    /**
     * 获取待支付的订单信息
     * @param unknown $params
     * @return unknown|boolean
     */
    public function getPayOrderInfo($params){
    	if(!empty($params)){
    		$ret = (new OrderModel())->getPayOrderInfo($params);
    		//return '123456789';
    		if($ret){
    			return $ret;
    		}else{
    			return false;
    		}
    
    	}
    	//return '999';
    	return false;
    
    }

    public function getOrderNumberByOrderMasterNumber($params){
        if(!empty($params)){
            $ret = (new OrderModel())->getOrderNumberByOrderMasterNumber($params);
            if($ret){
                return $ret;
            }else{
                return false;
            }

        }
        //return '999';
        return false;

    }
    
    /**
     * 获取订单的收货信息
     * @param array $params
     * @return unknown|boolean
     */
    public function getOrderShippingAddress($params){
    
    	if(!empty($params)){
    		$ret = (new OrderModel())->getOrderShippingAddress($params);
    		if($ret){
    			return $ret;
    		}else{
    			return false;
    		}
    
    	}
    	return false;
    
    }
    
    /**
     * 根据订单编号获取订单的收货信息
     * @param array $params
     * @return unknown|boolean
     */
    public function getOrderAddressByOrderNumber($params){
    
    	if(!empty($params)){
    		$ret = (new OrderModel())->getOrderAddressByOrderNumber($params);
    		if($ret){
    			return $ret;
    		}else{
    			return false;
    		}
    
    	}
    	return false;
    
    }
    
    /**
     * 改变订单状态
     * @param array $params
     * @return unknown|boolean
     */
    public function changeOrderStatus($params){
    
    	if(!empty($params)){
    		$ret = (new OrderModel())->changeOrderStatus($params);
    		if($ret){
    			return $ret;
    		}else{
    			return false;
    		}
    
    	}
    	return false;
    
    }

    /**
     * 根据订单编号获取订单的收货信息
     * @param array $params
     * @return unknown|boolean
     */
    public function getOrderInfoByOrderMasterNumber($params){
    	if(!empty($params)){
    		$ret = (new OrderModel())->getOrderInfoByOrderMasterNumber($params);
    		if($ret){
    			return $ret;
    		}else{
    			return false;
    		}
    	}else{
    		return false;
    	}
    }

    /**
     * 根据订单编号获取订单的收货信息
     * @param array $params
     * @return unknown|boolean
     */
    public function transactionIdProcess($params){
        if(!empty($params)){
            $ret = (new OrderModel())->transactionIdProcess($params);
            if($ret){
                return $ret;
            }else{
                return false;
            }
        }else{
            return false;
        }
    }
}
