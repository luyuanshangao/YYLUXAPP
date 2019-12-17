<?php
namespace app\orderfrontend\controller;

use app\common\params\orderfrontend\OrderParams;
use app\demo\controller\Auth;
use app\orderfrontend\model\OrderModel;
use app\orderfrontend\services\OrderService;
use think\Log;

/**
 * 购物车接口类
 * @author gz
 * @version
 * 2018-04-09
 */
class Order extends Auth
{
    public $OrderService;

    public function _initialize()
    {
        $this->OrderService = new OrderService();
    }

    /**
     * 提交订单处理方法
     */
    public function submitOrder(){
    	$paramData = request()->post();
    	//todo 参数校验
    	//return $paramData;
    	$data = $this->OrderService->submitOrder($paramData);

    	if(false == $data){
    		return apiReturn(['code'=>1000000021, 'msg'=>'请求失败']);
    	}
    	return apiReturn(['code'=>200, 'data'=>$data]);
    }
    
    /**
     * 根据订单ID获取订单编号
     */
    public function getOrderNumberByOrderId(){
    	$paramData = request()->post();
    	//todo 参数校验
    	//return $paramData;
    	$data = $this->OrderService->getOrderNumberByOrderId($paramData);
   	
    	if(false == $data){
    		return apiReturn(['code'=>1000000021, 'msg'=>'请求失败']);
    	}
    	return apiReturn(['code'=>200, 'data'=>$data]);
    }
    
    /**
     * 获取订单信息
     * @return multitype:
     */
    public function getPayOrderInfo(){
    	$paramData = request()->post();
    	//$paramData['order_master_number'] = input('order_master_number');
    	//todo 参数校验
    	//return $paramData;
    	$data = $this->OrderService->getPayOrderInfo($paramData);
 	
    	if(false == $data){
    		return apiReturn(['code'=>1000000021, 'msg'=>'请求失败']);
    	}
    	return apiReturn(['code'=>200, 'data'=>$data]);
    }

    /**
     * 根据order_master_number 获取order_number
     * @return multitype:
     */
    public function getOrderNumberByOrderMasterNumber(){
        $paramData = request()->post();
        //$paramData['order_master_number'] = input('order_master_number');
        //todo 参数校验
        //return $paramData;
        $data = $this->OrderService->getOrderNumberByOrderMasterNumber($paramData);

        if(false == $data){
            return apiReturn(['code'=>1000000021, 'msg'=>'请求失败']);
        }
        return apiReturn(['code'=>200, 'data'=>$data]);
    }
    
    /**
     * 获取订单收信息
     * @return multitype:
     */
    public function getOrderShippingAddress(){
    	$paramData = request()->post();
    	//todo 参数校验
    	//return $paramData;
    	
    	$data = $this->OrderService->getOrderShippingAddress($paramData);
    
    	if(false == $data){
    		return apiReturn(['code'=>1000000021, 'msg'=>'请求失败']);
    	}
    	return apiReturn(['code'=>200, 'data'=>$data]);
    }
    /**
     * 根据订单编号获取订单收信息
     * @return multitype:
     */
    public function getOrderAddressByOrderNumber(){
    	$paramData = request()->post();
    	//todo 参数校验    	 
    	$data = $this->OrderService->getOrderAddressByOrderNumber($paramData);
    
    	if(false == $data){
    		return apiReturn(['code'=>1000000021, 'msg'=>'请求失败']);
    	}
    	return apiReturn(['code'=>200, 'data'=>$data]);
    }

    /**
     * 改变订单状态
     * @return multitype:
     */
	public function changeOrderStatus(){
    	$paramData = request()->post();
    	//todo 参数校验    	 
    	$data = $this->OrderService->changeOrderStatus($paramData);
    	if(false == $data){
    		return apiReturn(['code'=>1000000021, 'msg'=>'请求失败']);
    	}

        if($paramData['log']){
            $OrderModel = new OrderModel();
            $log_res = $OrderModel->order_status_change_log($paramData['log']);
        }
    	return apiReturn(['code'=>200, 'data'=>$data]);
    }

    /**
     * 改变订单状态
     * @return mixed
     */
	public function realChangeOrderStatus(){
    	$params = request()->post();
        $validate = $this->validate($params,(new OrderParams())->realChangeOrderStatusRules());
        if(true !== $validate){
            return apiReturn(['code'=>2001, 'msg'=>$validate]);
        }
        try{
            $orderModel = new OrderModel();
            $res = $orderModel->updateOrderStatus($params);
            if ($res){
                return apiReturn(['code'=>200]);
            }else{
                return apiReturn(['code'=>1001, 'msg'=>'修改状态失败']);
            }
        }catch (\Exception $e){
            $msg = $e->getMessage();
            Log::record('realChangeOrderStatus异常：'.$msg);
            return apiReturn(['code'=>1002, 'msg'=>$msg]);
        }
    }

    /*
     * 记录订单状态
     * */
    public function order_status_change_log(){
        $paramData = request()->post();
        //todo 参数校验
        $data = $this->OrderService->changeOrderStatus($paramData);
    }
    
    /**
     * 根据订单编号获取订单收信息
     * @return multitype:
     */
    public function getOrderInfoByOrderMasterNumber(){
    	$paramData = request()->post();
    	$paramData['order_master_number'] = input('order_master_number');
    	//todo 参数校验
    	//return $paramData;
    	$data = $this->OrderService->getOrderInfoByOrderMasterNumber($paramData);
    
    	if(false == $data){
    		return apiReturn(['code'=>1000000021, 'msg'=>'请求失败']);
    	}
    	return apiReturn(['code'=>200, 'data'=>$data]);
    }

    /**
     * 更新订单状态
     * @return multitype:
     */
    public function transactionIdProcess(){
        $paramData = request()->post();
        //todo 参数校验
        //return $paramData;
        $data = $this->OrderService->transactionIdProcess($paramData);

        if(false == $data){
            return apiReturn(['code'=>1000000021, 'msg'=>'请求失败']);
        }
        return apiReturn(['code'=>200, 'data'=>$data]);
    }

    /**
     * 根据订单号获取订单数据
     * @return mixed
     */
    public function getOrderInfoByOrderNumber(){
        $params = request()->post();
        $validate = $this->validate($params,(new OrderParams())->getOrderInfoByOrderNumberRules());
        if(true !== $validate){
            return apiReturn(['code'=>2001, 'msg'=>$validate]);
        }
        try{
            $orderModel = new OrderModel();
            $res = $orderModel->getOrderInfoByOrderNumber($params['order_number'], 'order_id');
            if (!empty($res)){
                return apiReturn(['code'=>200, 'data'=>$res]);
            }else{
                return apiReturn(['code'=>1001, 'msg'=>'订单数据为空']);
            }
        }catch (\Exception $e){
            $msg = $e->getMessage();
            Log::record('getOrderInfoByOrderNumber异常：'.$msg);
            return apiReturn(['code'=>1002, 'msg'=>$msg]);
        }
    }
}
