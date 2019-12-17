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

    /**
     * 改变订单状态(新)
     * add 20190411 kevin
     * @return mixed
     */
    public function realChangeOrderStatusNew($params=''){
        $params = !empty($params)?$params:request()->post();
        $validate = $this->validate($params,(new OrderParams())->realChangeOrderStatusNewRules());
        if(true !== $validate){
            return apiReturn(['code'=>2001, 'msg'=>$validate]);
        }
        try{
            $orderModel = model("OrderModel");
            $order_where["customer_id"] = $params['customer_id'];
            $order_where["order_number"] = $params['order_number'];
            $order_basics = $orderModel->getOrderBasics($order_where);
            if($order_basics){
                $update['order_status_from'] = $order_basics['order_status'];
                $update['order_id'] = $order_basics['order_id'];
                $update['create_on'] = time();
                $update['create_by'] = !empty($params["create_by"])?$params["create_by"]:"customer,username:".$order_basics['customer_name'];
                if($params['order_status']>900 && $params['order_status']<1400){
                    $update['order_branch_status'] = $params['order_status'];
                    $update['order_status'] = $order_basics['order_status'];
                }else{
                    $update['order_status'] = $params['order_status'];
                }
                $update['change_reason'] = $params['change_reason'];
                $update['order_status'] = $params['order_status'];
                $update['chage_desc'] = $params['change_reason'];
                $update['create_ip'] = $params['create_ip'];
            }else{
                return apiReturn(['code'=>1002, 'msg'=>'Order does not exist']);
            }
            $res = $orderModel->updateOrderStatus($update);
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


    /**
     * 售后订单拒绝回退订单最后一个订单状态
     * add 20190411 kevin
     * @return mixed
     */
    public function rollbackApplyOrderStatus($params=''){
        $params = !empty($params)?$params:request()->post();
        $validate = $this->validate($params,(new OrderParams())->rollbackOrderStatus());
        if(true !== $validate){
            return apiReturn(['code'=>2001, 'msg'=>$validate]);
        }
        try{
            $orderModel = model("OrderModel");
            $order_after_where["after_sale_id"] = $params['after_sale_id'];
            $order_after_sale_apply_data = $orderModel->getOrderAfterSaleApply($order_after_where);
            if($order_after_sale_apply_data){
                $OrderStatusWhere['order_id'] = $order_after_sale_apply_data['order_id'];
                $OrderStatusInfo = $orderModel->getOrderStatusInfoByWhere($OrderStatusWhere);
                if(empty($OrderStatusInfo)){
                    return apiReturn(['code'=>1001, 'msg'=>'订单记录获取失败']);
                }
                $update['order_status_from'] = $OrderStatusInfo['order_status'];
                $update['order_status'] = $OrderStatusInfo['order_status_from'];
                $update['order_id'] = $OrderStatusInfo['order_id'];
                $update['create_on'] = time();
                $update['create_by'] = !empty($params["create_by"])?$params["create_by"]:"";
                $update['change_reason'] = "Api Rollback Order Status";
                $update['chage_desc'] = !empty($params['change_reason'])?$params['change_reason']:"Api after sale refusal Rollback Order Status";
                $update['create_ip'] = $params['create_ip'];
            }else{
                return apiReturn(['code'=>1002, 'msg'=>'After sale apply order does not exist']);
            }
            $res = $orderModel->updateOrderStatus($update);
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
    /**
     * 客服统计报表获取订单信息
     * [OrderInformation description]
     *  auther wang  2019-02-20
     */
    public function OrderInformation(){
            // $params = request()->post();
            $params = $_POST;
            $model = new OrderModel();
            $order_info = $model->OrderInformation($params);
            return $order_info;
    }

    /**
     * 根据主单号获取全部订单数据【包含主单数据】
     * @return mixed
     */
    public function getAllOrderDataByMasterNumber(){
        $params = request()->post();
        $validate = $this->validate($params,(new OrderParams())->getAllOrderDataByMasterNumberRules());
        if(true !== $validate){
            return apiReturn(['code'=>2001, 'msg'=>$validate]);
        }
        try{
            $orderModel = new OrderModel();
            $res = $orderModel->getAllOrderDataByMasterNumber($params['order_master_number']);
            if (!empty($res)){
                return apiReturn(['code'=>200, 'data'=>$res]);
            }else{
                return apiReturn(['code'=>1001, 'msg'=>'订单数据为空']);
            }
        }catch (\Exception $e){
            $msg = $e->getMessage();
            Log::record('getAllOrderDataByMasterNumber异常：'.$msg);
            return apiReturn(['code'=>1002, 'msg'=>$msg]);
        }
    }
    /**
     * 关闭订单
     * [OrderShutDown description]
     * @auther wang  2019-03-18
     */
    // public function OrderShutDown(){
    //      $params = request()->post();
    //      if(empty($params['order_id']) || !is_numeric($params['order_id'])  || strlen($params['order_id']) >20 || empty($params['order_status'])){
    //          return apiReturn(['code'=>1002, 'msg'=>'获取数据出异常']);
    //      }
    //      $orderModel = new OrderModel();
    //      $result = $orderModel->OrderShutDown(['order_id'=>$params['order_id']]);
    //      if(!empty($result)){

    //         return apiReturn(['code'=>200, 'msg'=>'数据修改成功']);
    //      }else{
    //         return apiReturn(['code'=>1002, 'msg'=>'数据修改失败']);
    //      }
    // }
     /**
     *
     * [ExportRefundOrder description]
     * @auther wang  2019-03-19
     */
    public function ExportRefundOrder(){
         $params = request()->post();
         $validate = $this->validate($params,(new OrderParams())->getExportRefundOrder());
         if(true !== $validate){
            return apiReturn(['code'=>2001, 'msg'=>$validate]);
         }
         $orderModel = new OrderModel();
         $result = $orderModel->ExportRefundOrder($params);
         return apiReturn(['code'=>200,'data'=>$result]);
    }


    /**
     * 根据主订单更新支付方式和支付渠道
     * @return mixed
     */
    public function updateOrderPaytypeAndChannel(){
        $params = request()->post();
        $validate = $this->validate($params,(new OrderParams())->updateOrderPaytypeAndChannelRules());
        if(true !== $validate){
            return apiReturn(['code'=>2001, 'msg'=>$validate]);
        }
        try{
            $orderModel = new OrderModel();
            //增加支付时使用的支付系统标识 tinghu.liu 20190813
            $payment_system = isset($params['payment_system'])?$params['payment_system']:'';
            //增加CPF（税号）数据字段 tinghu.liu 20191125
            $cpf = isset($params['cpf'])?$params['cpf']:'';
            $res = $orderModel->updateOrderPaytypeAndChannel($params['order_master_number'], $params['pay_type'], $params['pay_channel'], $payment_system, $cpf);
            if ($res){
                return apiReturn(['code'=>200, 'data'=>$res]);
            }else{
                return apiReturn(['code'=>1001, 'msg'=>'更新失败']);
            }
        }catch (\Exception $e){
            $msg = $e->getMessage();
            Log::record('updateOrderPaytypeAndChannel异常：'.$msg);
            return apiReturn(['code'=>1002, 'msg'=>$msg]);
        }
    }

    /**
     * 更新订单币种
     * @return mixed
     */
    public function updateOrderCurrency(){
        $params = request()->post();
        $validate = $this->validate($params,(new OrderParams())->updateOrderCurrencyRules());
        if(true !== $validate){
            return apiReturn(['code'=>2001, 'msg'=>$validate]);
        }
        try{
            $orderModel = new OrderModel();
//
//            ['order_master_number', 'require'],
//            ['old_currency', 'require'],
//            ['currency', 'require'],

            $order_master_number = $params['order_master_number'];
            $to_currency = $params['to_currency'];
            $res = $orderModel->updateOrderCurrency($order_master_number, $to_currency);
            if ($res){
                return apiReturn(['code'=>200]);
            }else{
                return apiReturn(['code'=>1001, 'msg'=>'更新失败']);
            }
        }catch (\Exception $e){
            $msg = $e->getMessage().', '.$e->getFile().'['.$e->getLine().']';
            Log::record('updateOrderCurrency异常：'.$msg);
            return apiReturn(['code'=>1002, 'msg'=>$msg]);
        }
    }

    /**
     * 根据主订单获取订单收货地址
     * @return mixed
     */
    public function getOrderAddressByOrderMasterNumber(){
        $params = request()->post();
        $validate = $this->validate($params,(new OrderParams())->getOrderAddressByOrderMasterNumberRules());
        if(true !== $validate){
            return apiReturn(['code'=>2001, 'msg'=>$validate]);
        }
        try{
            $orderModel = new OrderModel();
            $res = $orderModel->getOrderAddressByOrderMasterNumber($params['order_master_number']);
            if (!empty($res)){
                return apiReturn(['code'=>200, 'data'=>$res]);
            }else{
                return apiReturn(['code'=>1001, 'msg'=>'获取失败']);
            }
        }catch (\Exception $e){
            $msg = $e->getMessage();
            Log::record('getOrderAddressByOrderMasterNumber 异常：'.$msg);
            return apiReturn(['code'=>1002, 'msg'=>$msg]);
        }
    }

    public function getTransaction(){
        $params = request()->param();
        $orderModel = new OrderModel();
        if(empty($params['order_id'])){
            $msg='order_id 不能为空';
            return apiReturn(['code'=>1002, 'msg'=>$msg]);
        }
        $where['order_id']=$params['order_id'];
        if(!empty($params['txn_type'])){
            $where['txn_type']=$params['txn_type'];
        }
        $transaction_id=$orderModel->getTransactionID($where);
        return $transaction_id;
    }

    /**
     * 根据PayToken获取订单基本信息
     * orderfrontend/Order/getOrderBaseInfoByPayToken
     * @return mixed
     */
    public function getOrderBaseInfoByPayToken(){
        $params = request()->post();
        $validate = $this->validate($params,(new OrderParams())->getOrderBaseInfoByPayTokenRules());
        if(true !== $validate){
            return apiReturn(['code'=>2001, 'msg'=>$validate]);
        }
        try{
            $orderModel = new OrderModel();
            $res = $orderModel->getOrderBaseInfoByPayToken($params['pay_token']);
            if (!empty($res)){
                return apiReturn(['code'=>200, 'data'=>$res]);
            }else{
                return apiReturn(['code'=>1001, 'msg'=>'获取失败']);
            }
        }catch (\Exception $e){
            $msg = $e->getMessage();
            Log::record('getOrderAddressByOrderMasterNumber 异常：'.$msg);
            return apiReturn(['code'=>1002, 'msg'=>$msg]);
        }
    }

    /**
     * 根据主单号获取支付token信息
     * @return mixed
     */
    public function getPayTokenInfoByOrderMasterNumber(){
        $params = request()->post();
        $validate = $this->validate($params,(new OrderParams())->getPayTokenInfoByOrderMasterNumberRules());
        if(true !== $validate){
            return apiReturn(['code'=>2001, 'msg'=>$validate]);
        }
        try{
            $orderModel = new OrderModel();
            $res = $orderModel->getPayTokenInfoByOrderMasterNumber($params['order_master_number']);
            if (!empty($res)){
                return apiReturn(['code'=>200, 'data'=>$res]);
            }else{
                return apiReturn(['code'=>1001, 'msg'=>'获取失败']);
            }
        }catch (\Exception $e){
            $msg = $e->getMessage();
            Log::record('getOrderAddressByOrderMasterNumber 异常：'.$msg);
            return apiReturn(['code'=>1002, 'msg'=>$msg]);
        }
    }
    
    /**
     * 提交订单处理方法【区块链】
     */
    public function submitOrderForBlockChain(){
    	$paramData = request()->post();
    	//参数校验
    	$data = $this->OrderService->submitOrderForBlockChain($paramData);
    	if(false == $data){
    		return apiReturn(['code'=>101, 'msg'=>'创建订单失败']);
    	}
    	return apiReturn(['code'=>200, 'data'=>$data]);
    }
}
