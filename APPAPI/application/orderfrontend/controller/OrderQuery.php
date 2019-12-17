<?php
namespace app\orderfrontend\controller;

use app\demo\controller\Auth;
use app\order\services\OrderService;
use app\common\helpers\RedisClusterBase;
use think\Log;

/**
 * 清单查询专用接口类
 * @author heng.zhang
 * @version 1.0
 * 2018-06-07
 */
class OrderQuery extends Auth
{
    /**
     * 订单列表
     */
    public function getOrderList(){
        $paramData = request()->post();
        //var_dump($paramData);
        //die();
       //$paramData = array_filter($paramData);//因为有、0状态  该函数不能用
        foreach ($paramData as $key => $value){
            if(trim($paramData[$key]) ==''){
                unset($paramData[$key]);
            }
        }
        $where = [];
        $PageQuery  = [];
        //参数校验
        /*订单号*/
        if(isset($paramData['OrderNumber']) && $paramData['OrderNumber']!=''){
            $where['order_number'] = $paramData['OrderNumber'];
        }

        /*物流跟踪号 TODO
        if(isset($paramData['TrackingNumber'])){
        	$where['TrackingNumber'] = $paramData['TrackingNumber'];
        }
        */
        /*交易TxnID*/
        if(isset($paramData['ThirdPartyTxnID']) && $paramData['ThirdPartyTxnID'] !=''){
        $PageQuery['ThirdPartyTxnID'] =	$where['transaction_id'] = $paramData['ThirdPartyTxnID'];

        }
        /*下单时间*/
        if(isset($paramData['startTime']) && $paramData['startTime'] !=''){
        	if(isset($paramData['endTime']) && $paramData['endTime'] !=''){
        		  // $startTime = date('Y-m-d H:i:s', strtotime($paramData['endTime']));
            //       $endTime = date('Y-m-d H:i:s', strtotime($paramData['endTime']));
                  $PageQuery['startTime'] = $paramData['startTime'];
                  $PageQuery['endTime']   = $paramData['endTime'];
                  $startTime = strtotime($paramData['startTime']);
                  $endTime   = strtotime($paramData['endTime']);
                  // if(strtotime($paramData['startTime'])){
                  //     $PageQuery['startTime'] = $startTime = strtotime($paramData['startTime']);
                  //     $PageQuery['endTime']   = $endTime   = strtotime($paramData['endTime']);
                  // }else{
                  //     $PageQuery['startTime'] = $startTime = $paramData['startTime'];
                  //     $PageQuery['endTime']   = $endTime   = $paramData['endTime'];
                  // }

        		  $where['create_on'] = array('between',[$startTime,$endTime]);
        	}
        }
        if(empty($where['create_on'])){
            $startTime = strtotime('-3 month');
            $endTime   = time();
            $where['create_on'] = array('between',[$startTime,$endTime]);
        }
        /*用户ID*/
        if(isset($paramData['UserID']) && $paramData['UserID'] !=''){
        	$PageQuery['UserID'] = $where['customer_id'] = $paramData['UserID'];
        }
        /*订单状态*/
        if(isset($paramData['OrderStauts']) && $paramData['OrderStauts'] !=''){
        	$PageQuery['OrderStauts'] = $where['order_status'] = $paramData['OrderStauts'];
        }
        /*订单类型*/
        if(isset($paramData['OrderType']) && $paramData['OrderType'] !=''){
        	$PageQuery['OrderType'] = $where['order_type'] = $paramData['OrderType'];
        }
        /*是否是COD订单*/
        if(isset($paramData['COD_order']) && $paramData['COD_order'] !=''){
            $PageQuery['COD_order'] = $where['is_cod'] = $paramData['COD_order'];
        }
        /*是否是加锁*/
        if(isset($paramData['Lock']) && $paramData['Lock'] !=''){
            $PageQuery['Lock'] = $where['lock_status'] = $paramData['Lock'];
        }
        /*业务类型 TODO
        if(isset($paramData['BusinessType']) && $paramData['BusinessType'] !=''){
        	//$where[''] = $paramData['BusinessType'];
        }    */

        /*支付渠道*/
        if(isset($paramData['paymentMethod_name']) && $paramData['paymentMethod_name'] !=''){
        	$PageQuery['paymentMethod_name'] = $where['pay_channel'] = $paramData['paymentMethod_name'];
        }
        /*运输方式*/
        if(isset($paramData['ShippingMethod']) && $paramData['ShippingMethod'] !=''){
        	$PageQuery['ShippingMethod'] = $where['logistics_provider'] = $paramData['ShippingMethod'];
        }
        /*付款情况*/
        if(isset($paramData['PaymentStatus']) && $paramData['PaymentStatus'] !=''){
        	$PageQuery['PaymentStatus'] = $where['payment_status'] = $paramData['PaymentStatus'];
        }
        /*发货情况 30 订单收到，未处理; 31  备货中; 32 发货处理中; 33 部分发货; 34  已发货; 35  已完成; 36 已取消*/
        if(isset($paramData['FulfillmentStatus']) && $paramData['FulfillmentStatus'] !=''){
        	$PageQuery['FulfillmentStatus'] = $where['fulfillment_status'] = $paramData['FulfillmentStatus'];
        }
        /*收货国家 TODO */
        if(isset($paramData['ShippingCountryCode']) && $paramData['ShippingCountryCode'] !=''){
        	$PageQuery['ShippingCountryCode'] = $where['country_code'] = $paramData['ShippingCountryCode'];
        }
        /*商铺名 */
        if(isset($paramData['store_name']) && $paramData['store_name'] !=''){
            $PageQuery['store_name'] = $where['store_name'] = $paramData['store_name'];
        }
        /*商铺ID */
        if(isset($paramData['store_id']) && $paramData['store_id'] !=''){
            $PageQuery['store_id'] = $where['store_id'] = $paramData['store_id'];
        }
        $page_size = isset($paramData['page_size'])?$paramData['page_size']:20;//return $where;
        $page = isset($paramData['page'])?$paramData['page']:1;
        $path = isset($paramData['path'])?$paramData['path']:"";
        $order= isset($paramData['orderBy'])?$paramData['orderBy']:" create_on desc ";//pr($where);
        $list = model("OrderQuery")->getOrderList($where,$page_size,$page,$path,$order,$PageQuery);
        return apiReturn(['code'=>200,'data'=>$list]);
    }


    /**
     * 根据订单号码获取订单明细数据
     * @param string $orderNumber
     * @return 订单明细数据
     */
    public function getOrderDetail(){
    	try{
	    	$paramData = request()->post();
            if(!empty($paramData['orderNumber']) ){
                $orderNumber= $paramData['orderNumber'];
                //当subset存在时是获取子订单号
                $res = model("OrderQuery")->getOrderDetail($orderNumber);
                return apiReturn(['code'=>200,'data'=>$res]);
            }
            return apiReturn(['code'=>201,'msg'=>'参数传递为空']);

            // if($paramData['subset'] == true){
            //    $res = model("OrderQuery")->getOrderSubset($orderNumber);
            // }else{
            //    $res = model("OrderQuery")->getOrderDetail($orderNumber);
            // }

    	}catch(Exception $ex){
    		$msg = $ex -> getMessage();
    		Log::write('getOrderDetail-根据订单号码获取订单明细数据查询数据库异常，错误信息:'.$msg);
    		return apiReturn(['code'=>201,'msg'=>$msg]);
    	}
    }
    /**
     * 加锁解锁11
     * [holdAndUnhold description]
     * @return [type] [description]
     * @author wang   2018-06-21
     */
    public function holdAndUnhold(){
       $data = request()->post();
       $result = model("OrderQuery")->holdAndUnhold($data["data"]);//return $result ;
       if($result){
            $order = $data["data"];
            $redis = new RedisClusterBase();
            $redis->LPUSH('ORDER_LOCK_STATUS_CHANGE_FOR_OMS',json_encode(array('order_number'=>$order['order_number'],'lock_status'=>$order['status']),true));
            return apiReturn(['code'=>200,'data'=>'操作成功']);
       }else{
            return apiReturn(['code'=>100,'data'=>'操作失败']);
       }
    }
    /**
     * 投诉管理
     * [orderAccuse description]
     * @return [type] [description]
     * @author wang   2018-06-23
     */
    public function orderAccuse(){
       $data = request()->post();
       $result = model("OrderQuery")->orderAccuse($data);
       return $result;
    }
    /**
     * 售后管理
     * [orderRefund description]
     * @return [type] [description]
     * @author wang   2018-06-25
     */
    public function orderRefund(){
       $data = request()->post();
       $result = model("OrderQuery")->orderRefund($data);
       return $result;
    }
    /**
     * 获取配置信息
     * [apiConfig description]
     * @return [type] [description]
     * @author wang   2018-06-26
     */
    public function apiConfig(){
         $data = request()->post();
         $apiConfig['after_sale_type']   = config('after_sale_type');//售后类型
         $apiConfig['after_sale_status'] = config('after_sale_status');//售后状态
         $apiConfig['refunded_type']     = config('refunded_type');//退款类型
         $apiConfig['accuse_reason']     = config('accuse_reason');//投诉原因
         $apiConfig['sdd']     = 1;
         return $apiConfig;
    }
     /**
     * 售后详情
     * [afterSaleDetails description]after_sale_type
     * @return [type] [description]
     * @author wang   2018-06-27
     */
    public function afterSaleDetails(){
         $data = request()->post();
         $result = model("OrderQuery")->afterSaleDetails($data);
         return $result;
    }
    public function arbitration(){
        $data = request()->post();//return $data;
        $result = model("OrderQuery")->arbitration($data);
        return $result;
        return apiReturn(['code'=>100,'data'=>$result]);


    }
    /**
     * 仲裁回复
     * [applyLog description]
     * @return [type] [description]
     * @author wang   2018-08-21
     */
    public function applyLog(){
        $data = request()->post();
        if(!$data){
            return apiReturn(['code'=>100,'data'=>'传输数据有误']);
        }
        $result = model("OrderQuery")->applyLog($data);
        return $result;
    }
    /**
     * 产品举报
     * [CustomsInsurance description]
     * @author wang   2018-09-08
     */
    public function CustomsInsurance(){
        $data = request()->post();
        if(!$data){
            return apiReturn(['code'=>100,'data'=>'传输数据有误']);
        }
        $result = model("OrderQuery")->CustomsInsurance($data);
        return $result;
    }
    /**
     * 根据条件查询
     * [order description]
     * @return [type] [description]
     * @author wang   addtime 2018-09-27
     */
    public function order_query(){
        $data = $_POST;
        // file_put_contents ('../runtime/log/201812/rukou.log',json_encode($data).'----', FILE_APPEND|LOCK_EX);
        if(!$data){
            return apiReturn(['code'=>100,'data'=>'传输数据有误']);
        }
        $result = model("OrderQuery")->order_query($data);
        return $result;
    }
}
