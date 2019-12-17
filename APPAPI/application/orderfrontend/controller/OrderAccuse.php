<?php
namespace app\orderfrontend\controller;

use app\demo\controller\Auth;
use app\order\services\OrderService;

/**
 * 订单投诉调用接口类
 * @author kevin
 * @version
 * 2018-05-23
 */
class OrderAccuse extends Auth
{
    /**
     * 订单退款列表
     */
    public function getOrderAccuseList(){
        $paramData = request()->post();
        $paramData = array_filter($paramData);
        //todo 参数校验
        //return $paramData;
        /*用户ID*/
        if(isset($paramData['customer_id'])){
            $where['customer_id'] = $paramData['customer_id'];
        }
        if(isset($paramData['accuse_reason'])){
            $where['accuse_reason'] = $paramData['accuse_reason'];
        }
        if(isset($paramData['accuse_status'])){
            $where['accuse_status'] = $paramData['accuse_status'];
        }
        /*订单评价状态*/
        $page_size = input("page_size",20);
        $page = input("page",1);
        $path = input("path");
        $order = isset($paramData['order'])?$paramData['order']:"accuse_id desc";
        $list = model("OrderAccuse")->getOrderAccuseList($where,$page_size,$page,$path,$order);
        return apiReturn(['code'=>200,'data'=>$list]);
    }

   /*
    * 添加订单投诉接口
    * */
    public function saveOrderAccuse(){
        $paramData = request()->post();
        if(isset($paramData['accuse_id'])){
            $data['accuse_id'] = $paramData['accuse_id'];
        }
        /*订单号*/
        if(isset($paramData['order_id'])){
            $data['order_id'] = $paramData['order_id'];
        }else{
            return apiReturn(['code'=>1001]);
        }
        /*订单编号*/
        if(isset($paramData['order_number'])){
            $data['order_number'] = $paramData['order_number'];
        }
        if(isset($paramData['customer_id'])){
            $data['customer_id'] = $paramData['customer_id'];
        }else{
            return apiReturn(['code'=>1001]);
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
        if(isset($paramData['accuse_reason'])){
            $data['accuse_reason'] = $paramData['accuse_reason'];
        }
        if(isset($paramData['accuse_status'])){
            $data['accuse_status'] = $paramData['accuse_status'];
        }
        if(isset($paramData['imgs'])){
            $data['imgs'] = json_encode($paramData['imgs']);
        }
        if(isset($paramData['remarks'])){
            $data['remarks'] = $paramData['remarks'];
        }
        $data = array_filter($data);
        $res = model("OrderAccuse")->saveOrderAccuse($data);
        if($res>0){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1002]);
        }
    }

    public function getOrderAfterSaleApplyInfo(){
        $paramData = request()->post();
        if(isset($paramData['after_sale_id'])){
            $where['after_sale_id'] = $paramData['after_sale_id'];
        }else{
            return apiReturn(["code"=>1001]);
        }
        $res = model("OrderAfterSaleApply")->getOrderAfterSaleApplyInfo($where);
        if($res>0){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1002]);
        }
    }

}
