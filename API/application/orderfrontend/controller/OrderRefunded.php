<?php
namespace app\orderfrontend\controller;

use app\demo\controller\Auth;
use app\order\services\OrderService;

/**
 * 订单退款调用接口类
 * @author kevin
 * @version
 * 2018-04-23
 */
class OrderRefunded extends Auth
{
    /**
     * 订单退款列表
     */
    public function getOrderRefundedList(){
        $paramData = request()->post();
        $paramData = array_filter($paramData);
        //todo 参数校验
        //return $paramData;
        /*用户ID*/
        if(isset($paramData['customer_id'])){
            $where['or.customer_id'] = $paramData['customer_id'];
        }
        /*订单编号*/
        if(isset($paramData['order_number'])){
            $where['or.order_number'] = $paramData['order_number'];
        }
        //店铺ID
        if(isset($paramData['store_id'])){
            $where['or.store_id'] = $paramData['store_id'];
        }
        /*if(isset($paramData['is_approve'])){
            $where['or.is_approve'] = $paramData['is_approve'];
        }*/
        if(isset($paramData['refunded_statue']) && $paramData['refunded_statue']!==''){
            $where['or.refunded_statue'] = $paramData['refunded_statue'];
        }
        if(isset($paramData['refunded_type'])){
            $where['or.refunded_type'] = $paramData['refunded_type'];
        }
        if(isset($paramData['order_status'])){
            $where['order_status'] = $paramData['order_status'];
        }

        if(isset($paramData['add_time'])){
            $where['add_time'] = strtotime($paramData['add_time']);
        }

        if(isset($paramData['create_on_start']) && isset($paramData['create_on_end'])){
            $where['add_time'] = ['between ',[strtotime($paramData['create_on_start']),strtotime($paramData['create_on_end'])]];
        }else{

        }
        /*订单评价状态*/
        $page_size = input("page_size",20);
        $page = input("page",1);
        $path = input("path");
        $order = isset($paramData['order'])?$paramData['order']:"after_sale_id desc";
        $list = model("OrderRefunded")->getOrderRefundedList($where,$page_size,$page,$path,$order);
        return apiReturn(['code'=>200,'data'=>$list]);
    }

   /*
    * 添加订单退款接口
    * */
    public function saveOrderRefunded(){
        $paramData = request()->post();
        if(isset($paramData['refunded_id'])){
            $data['refunded_id'] = $paramData['refunded_id'];
        }
        /*订单号*/
        if(isset($paramData['order_id'])){
            $data['order_id'] = $paramData['order_id'];
        }
        /*订单编号*/
        if(isset($paramData['order_number'])){
            $data['order_number'] = $paramData['order_number'];
        }
        if(isset($paramData['customer_id'])){
            $data['customer_id'] = $paramData['customer_id'];
        }
        if(isset($paramData['customer_name'])){
            $data['customer_name'] = $paramData['customer_name'];
        }
        if(isset($paramData['store_name'])){
            $data['store_name'] = $paramData['store_name'];
        }
        if(isset($paramData['store_id'])){
            $data['store_id'] = $paramData['store_id'];
        }
        /*if(isset($paramData['payment_txn_id'])){
            $data['payment_txn_id'] = $paramData['payment_txn_id'];
        }*/
        if(isset($paramData['refunded_type'])){
            $data['refunded_type'] = $paramData['refunded_type'];
        }
        if(isset($paramData['refunded_statue'])){
            $data['refunded_statue'] = $paramData['refunded_statue'];
        }
        if(isset($paramData['refunded_reason'])){
            $data['refunded_reason'] = $paramData['refunded_reason'];
        }
        if(isset($paramData['expressage_company'])){
            $data['expressage_company'] = $paramData['expressage_company'];
        }
        if(isset($paramData['expressage_num'])){
            $data['expressage_num'] = $paramData['expressage_num'];
        }
        if(isset($paramData['refunded_fee'])){
            $data['refunded_fee'] = $paramData['refunded_fee'];
        }
        if(isset($paramData['is_approve'])){
            $data['is_approve'] = $paramData['is_approve'];
        }
        if(isset($paramData['approve_reason'])){
            $data['approve_reason'] = $paramData['approve_reason'];
        }
        if(isset($paramData['refunded_imgs'])){
            $data['refunded_imgs'] = $paramData['refunded_imgs'];
        }
        if(isset($paramData['remarks'])){
            $data['remarks'] = $paramData['remarks'];
        }
        $data = array_filter($data);
        if(isset($paramData['is_get_goods'])){
            $data['is_get_goods'] = $paramData['is_get_goods'];
        }
        if(isset($paramData['is_need_deliver_goods'])){
            $data['is_need_deliver_goods'] = $paramData['is_need_deliver_goods'];
        }
        $res = model("OrderRefunded")->saveOrderRefunded($data);
        if($res>0){
            return apiReturn(['code'=>200]);
        }else{
            return apiReturn(['code'=>1002]);
        }
    }

    public function getOrderRefundedInfo(){
        $paramData = request()->post();
        if(isset($paramData['after_sale_id'])){
            $where['after_sale_id'] = $paramData['after_sale_id'];
        }else{
            return apiReturn(["code"=>1001]);
        }
        $res = model("OrderRefunded")->getOrderRefundedInfo($where);
        if(!empty($res)){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1002]);
        }
    }

    /*
  * 保存订单退款记录
  * */
    public function addRefundedLog($data=''){
        $paramData = request()->post();
        $add_data = !empty($data)?$data:$paramData;
        if(!isset($add_data['refunded_id']) ){
            return apiReturn(['code'=>1001]);
        }
        $res = model("OrderRefunded")->addRefundedLog($add_data);
        if($res>0){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1002]);
        }
    }

    public function getRefundedLog(){
        $paramData = request()->post();
        if(!isset($paramData['refunded_id']) ){
            return apiReturn(['code'=>1001]);
        }
        $where['refunded_id'] = $paramData['refunded_id'];
        $res = model("OrderRefunded")->getRefundedLog($where);
        return apiReturn(['code'=>200,'data'=>$res]);

    }
}
