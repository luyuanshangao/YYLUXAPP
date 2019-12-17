<?php
namespace app\orderfrontend\controller;

use app\demo\controller\Auth;
use app\order\services\OrderService;

/**
 * 订单纠纷调用接口类
 * @author kevin
 * @version
 * 2018-04-23
 */
class OrderComplaint extends Auth
{
    /**
     * 订单退款列表
     */
    public function getOrderComplaintList(){
        $paramData = request()->post();
        $paramData = array_filter($paramData);
        //todo 参数校验
        //return $paramData;
        /*用户ID*/
        if(isset($paramData['customer_id'])){
            $where['customer_id'] = $paramData['customer_id'];
        }
        if(isset($paramData['after_sale_type'])){
            $where['after_sale_type'] = $paramData['after_sale_type'];
        }
        if(isset($paramData['complaint_status']) && $paramData['complaint_status']!==''){
            $where['complaint_status'] = $paramData['complaint_status'];
        }
        if(isset($paramData['is_platform_intervention']) && $paramData['is_platform_intervention']!==''){
            $where['is_platform_intervention'] = $paramData['is_platform_intervention'];
        }
        if(isset($paramData['create_on_start']) && isset($paramData['create_on_end'])){
            $where['add_time'] = ['between ',[strtotime($paramData['create_on_start']),strtotime($paramData['create_on_end'])]];
        }else{
            if(isset($paramData['create_on_start'])){
                $where['add_time'] = ['gt',strtotime($paramData['add_time'])];
            }
            if(isset($paramData['create_on_end'])){
                $where['add_time'] = ['lt',strtotime($paramData['add_time'])];
            }
        }
        /*订单评价状态*/
        $page_size = input("page_size",20);
        $page = input("page",1);
        $path = input("path");
        $order = isset($paramData['order'])?$paramData['order']:"complaint_id desc";
        $list = model("OrderAfterSaleApply")->getOrderComplaintList($where,$page_size,$page,$path,$order);
        return apiReturn(['code'=>200,'data'=>$list]);
    }

   /*
    * 添加订单纠纷接口
    * */
    public function saveOrderComplaint(){
        $paramData = request()->post();
        if(isset($paramData['store_id'])){
            $data['store_id'] = $paramData['store_id'];
        }
        if(isset($paramData['store_name'])){
            $data['store_name'] = $paramData['store_name'];
        }
        if(isset($paramData['customer_id'])){
            $data['customer_id'] = $paramData['customer_id'];
        }
        /*订单编号*/
        if(isset($paramData['order_number'])){
            $data['order_number'] = $paramData['order_number'];
        }
        if(isset($paramData['after_sale_id'])){
            $data['after_sale_id'] = $paramData['after_sale_id'];
        }
        if(isset($paramData['after_sale_type'])){
            $data['after_sale_type'] = $paramData['after_sale_type'];
        }
        if(isset($paramData['after_sale_status'])){
            $data['after_sale_status'] = $paramData['after_sale_status'];
        }
        if(isset($paramData['complaint_status'])){
            $data['complaint_status'] = $paramData['complaint_status'];
        }
        if(isset($paramData['complaint_imgs'])){
            $data['complaint_imgs'] = $paramData['complaint_imgs'];
        }
        if(isset($paramData['remarks'])){
            $data['remarks'] = $paramData['remarks'];
        }
        $res = model("OrderAfterSaleApply")->saveOrderComplaint($data);
        if($res>0){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1002]);
        }
    }

    public function getOrderComplaintInfo(){
        $paramData = request()->post();
        if(isset($paramData['complaint_id'])){
            $where['complaint_id'] = $paramData['complaint_id'];
        }else{
            return apiReturn(["code"=>1001]);
        }
        $res = model("OrderAfterSaleApply")->getOrderComplaintInfo($where);
        if($res>0){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1002]);
        }
    }
}
