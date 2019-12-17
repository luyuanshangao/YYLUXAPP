<?php
namespace app\admin\controller;

use app\demo\controller\Auth;
use app\order\services\OrderService;

/**
 * 订单投诉调用接口类
 * @author kevin
 * @version
 * 2018-04-23
 */
class OrderComplaint extends Auth
{
    /**
     * 订单投诉列表
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
        /*订单编号*/
        if(isset($paramData['order_number'])){
            $where['order_number'] = $paramData['order_number'];
        }
        //店铺ID
        if(isset($paramData['store_id'])){
            $where['store_id'] = $paramData['store_id'];
        }
        if(isset($paramData['store_id'])){
            $where['or.is_approve'] = $paramData['is_approve'];
        }
        if(isset($paramData['refunded_statue']) && $paramData['refunded_statue']!==''){
            $where['or.refunded_statue'] = $paramData['refunded_statue'];
        }
        if(isset($paramData['is_approve']) && $paramData['is_approve']!==''){
            $where['or.is_approve'] = $paramData['is_approve'];
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
        $order = isset($paramData['order'])?$paramData['order']:"complaint_id desc";
        $list = model("OrderComplaint")->getOrderComplaintList($where,$page_size,$page,$path,$order);
        return apiReturn(['code'=>200,'data'=>$list]);
    }

   /*
    * 添加订单退款接口
    * */
    public function saveOrderComplaint(){
        $paramData = request()->post();
        if(isset($paramData['complaint_id'])){
            $data['complaint_id'] = $paramData['complaint_id'];
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
        if(isset($paramData['complaint_type'])){
            $data['complaint_type'] = $paramData['complaint_type'];
        }
        if(isset($paramData['complaint_statue'])){
            $data['complaint_statue'] = $paramData['complaint_statue'];
        }
        if(isset($paramData['complaint_reason'])){
            $data['complaint_reason'] = $paramData['complaint_reason'];
        }
        if(isset($paramData['complaint_imgs'])){
            $data['complaint_imgs'] = $paramData['complaint_imgs'];
        }
        if(isset($paramData['remarks'])){
            $data['remarks'] = $paramData['remarks'];
        }
        $data = array_filter($data);
        $res = model("OrderComplaint")->saveOrderComplaint($data);
        if($res>0){
            return apiReturn(['code'=>200]);
        }else{
            return apiReturn(['code'=>1002]);
        }
    }

    public function getOrderComplaint(){
        $paramData = request()->post();
        if(isset($paramData['refunded_id'])){
            $where['refunded_id'] = $paramData['refunded_id'];
        }else{
            return apiReturn(["code"=>1001]);
        }
        $res = model("OrderRefunded")->getOrderRefundedInfo($where);
        if($res>0){
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
    /**
     * [RiskConfig description]
     *  @author wang 2018-08-03
     */
    public function RiskConfig(){
        $report_status['report_status'] = config('report_status');//售后类型
        $report_status['report_type']   = config('report_type');//售后类型
        $report_status['Currency']   = config('Currency');//币种
        return apiReturn(['code'=>200,'data'=>$report_status]);
    }
     /**
     * 根据条件获取配置信息
     * [RiskConfig description]
     *  @author wang 2019-02-20
     */
    public function ConfigurationInformation(){
        $data = request()->post();
        if(empty($data['config'])){
           return apiReturn(['code'=>100,'msg'=>'传参不能为空']);
        }
        $report_status = [];
        foreach ($data['config'] as $k => $v) {
            $report_status[$v] = config($v);//售后类型
        }
        return apiReturn(['code'=>200,'data'=>$report_status]);
    }
}
