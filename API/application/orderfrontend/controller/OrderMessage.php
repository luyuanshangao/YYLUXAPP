<?php
namespace app\orderfrontend\controller;

use app\demo\controller\Auth;
use app\order\services\OrderService;

/**
 * 用户端订单信息接口口类
 * @author kevin
 * @version
 * 2018-04-23
 */
class OrderMessage extends Auth
{
    /*
     * 增加信息
     * */
    public function addOrderMessage(){
        $paramData = request()->post();
        $paramData = array_filter($paramData);
        //todo 参数校验
        //return $paramData;
        /*订单ID*/
        if(isset($paramData['order_id'])){
            $data['order_id'] = $paramData['order_id'];
        }else{
            apiReturn(['code'=>1001]);
        }
        $data['order_id'] = $paramData['order_id'];
        $data['parent_id'] = isset($paramData['parent_id'])?$paramData['parent_id']:0;
        $data['user_id'] = isset($paramData['user_id'])?$paramData['user_id']:0;
        $data['user_name'] = isset($paramData['user_name'])?$paramData['user_name']:'';
        $data['message_type'] = isset($paramData['message_type'])?$paramData['message_type']:1;
        $data['first_category'] = isset($paramData['first_category'])?$paramData['first_category']:'';
        $data['second_category'] = isset($paramData['second_category'])?$paramData['second_category']:'';
        $data['message'] = isset($paramData['message'])?$paramData['message']:'';
        $data['file_url'] = isset($paramData['file_url'])?$paramData['file_url']:'';
        $data['statused'] = -1;
        $data['create_on'] = time();
        $res =  model("OrderModel")->addOrderMessage($data);
        if($res){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1002]);
        }
    }
    /*
     * 获取订单信息
     * */
    public function getOrderMessage(){
        $paramData = request()->post();
        if(isset($paramData['order_id'])){
            $data['order_id'] = $paramData['order_id'];
        }else{
            apiReturn(['code'=>1001]);
        }
        $res =  model("OrderModel")->getOrderMessage($data);
        if(!empty($res)){
            foreach ($res as $key=>$value){
                $res[$key]['message'] = htmlspecialchars_decode(htmlspecialchars_decode($value['message']));
            }
        }
        if($res){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1002]);
        }
    }

    /*
     * 获取订单信息列表
     * */
    public function getOrderMessageList(){
        $paramData = request()->post();
        //$paramData = array_filter($paramData);
        //todo 参数校验
        //return $paramData;
        if(!isset($paramData['user_id'])){
            apiReturn(['code'=>1001]);
        }else{
            $where['user_id'] = $paramData['user_id'];
        }
        if(isset($paramData['om.create_on'])){
            if(is_array($paramData['om.create_on'])){
                foreach ($paramData['om.create_on'] as $key=>$value){
                    $where['om.create_on'][$key] = trim($value);
                }
            }else{
                $where['om.create_on'] = $paramData['om.create_on'];
            }
        }
        if(isset($paramData['message_type'])  && empty($paramData['message_type'])){
            $where['message_type'] = $paramData['message_type'];
        }
        if(isset($paramData['month'])  && empty($paramData['month'])){
            $where['month'] = $paramData['month'];
        }

        $page_size = input("page_size",20);
        $page = input("page",1);
        $path = input("path");
        $query = isset($paramData['query'])?$paramData['query']:'';
        $res =  model("OrderMessage")->getOrderMessageList($where,$page_size,$page,$path,$query);
        if($res){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1002]);
        }
    }
    /*
     * 根据用户ID获取订单消息数量
     * */
    public function getOrderMessageCountByUserId(){
        $paramData = request()->post();
        if(isset($paramData['user_id'])){
            $data['user_id'] = $paramData['user_id'];
            $data['message_type'] = isset($paramData['message_type'])?$paramData['message_type']:2;
            $res =  model("OrderMessage")->getOrderMessageCountByUserId($data);
            return $res;
        }
    }

    /*
     * 一键阅读订单信息
     * */
    public function orderMessageFullRead(){
        $paramData = request()->post();
        if(!isset($paramData['user_id'])){
            return apiReturn(['code'=>1001]);
        }
        if(!isset($paramData['message_type'])){
            return apiReturn(['code'=>1001]);
        }
        $data['user_id'] = $paramData['user_id'];
        $data['message_type'] = $paramData['message_type'];
        $res =  model("OrderMessage")->orderMessageFullRead($data);
        return $res;
    }
    public function AddNotes(){
        $data = request()->post();
        // return $data;
        if(!empty($data['order_id']) &&  !empty($data['message']) && !empty($data['user_name']) && isset($data['status'])){
              $where = array();
              $where['order_id'] = $data['order_id'];
              $where['message']  = $data['message'];
              $where['user_id']  = isset($data['user_id'])?$data['user_id']:0;
              $where['user_name']= $data['user_name'];
              $where['message_type'] = 3;
              $where['file_url'] = 0;
              $where['create_on'] = time();
              $status = $data['status'];
              // $where['status'] = $data['status'];
        }
        if(!empty($where) && isset($status) ){
            $res =  model("OrderMessage")->AddNotes($where,$status);
            return $res;
        }else{
           return apiReturn(['code'=>100,'data'=>'数据参数出错']);;
        }

    }

    public function solvedOrderMessage(){
        $paramData = request()->post();
        $validate = $this->validate($paramData,"OrderMessage.solvedOrderMessage");
        if(true !== $validate){
            return apiReturn(['code'=>1002,"msg"=>$validate]);
        }
        if(isset($paramData['order_id']) && isset($paramData['user_id'])){
            $data['order_id'] = $paramData['order_id'];
            $data['user_id'] = $paramData['user_id'];
            $data['message_type'] = 2;
            $res =  model("OrderMessage")->solvedOrderMessage($data);
            if($res){
                return apiReturn(['code'=>200,'data'=>$res]);
            }else{
                return apiReturn(['code'=>1002]);
            }
        }else{
            apiReturn(['code'=>1001]);
        }
    }
}
