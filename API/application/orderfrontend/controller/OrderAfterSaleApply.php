<?php
namespace app\orderfrontend\controller;

use app\common\params\orderfrontend\OrderParams;
use app\demo\controller\Auth;
use app\order\services\OrderService;
use think\Exception;
use think\Log;

/**
 * 订单售后调用接口类
 * @author kevin
 * @version
 * 2018-04-23
 */
class OrderAfterSaleApply extends Auth
{
    /**
     * 订单退款列表
     */
    public function getOrderAfterSaleApplyList(){
        $paramData = request()->post();
        $paramData = array_filter($paramData);
        //todo 参数校验
        //return $paramData;
        /*用户ID*/
        if(isset($paramData['customer_id'])){
            $where['oa.customer_id'] = $paramData['customer_id'];
        }
        /*订单编号*/
        if(isset($paramData['order_number'])){
            $where['oa.order_number'] = $paramData['order_number'];
        }
        //店铺ID
        if(isset($paramData['store_id'])){
            $where['oa.store_id'] = $paramData['store_id'];
        }
        if(isset($paramData['status']) && $paramData['status']!==''){
            $where['oa.status'] = $paramData['status'];
        }
        if(isset($paramData['type'])){
            $where['oa.type'] = $paramData['type'];
        }
        if(isset($paramData['order_status'])){
            $where['oa.order_status'] = $paramData['order_status'];
        }

        if(isset($paramData['create_on_start']) && isset($paramData['create_on_end'])){
            $where['oa.add_time'] = ['between',[strtotime($paramData['create_on_start']),strtotime($paramData['create_on_end'])]];
        }else{
            if(isset($paramData['create_on_start'])){
                $where['oa.add_time'] = ['gt',strtotime($paramData['add_time'])];
            }
            if(isset($paramData['create_on_end'])){
                $where['oa.add_time'] = ['lt',strtotime($paramData['add_time'])];
            }
        }
        /*订单评价状态*/
        $page_size = input("page_size",20);
        $page = input("page",1);
        $path = input("path");
        $order = isset($paramData['order'])?$paramData['order']:"after_sale_id desc";
        $list = model("OrderAfterSaleApply")->getOrderAfterSaleApplyList($where,$page_size,$page,$path,$order);
        return apiReturn(['code'=>200,'data'=>$list]);
    }

   /*
    * 添加订单售后接口
    * */
    public function saveOrderAfterSaleApply(){
        $paramData = request()->post();
        try{
            if(isset($paramData['after_sale_id'])){
                $data['after_sale_id'] = $paramData['after_sale_id'];
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
            if(isset($paramData['payment_txn_id'])){
                $data['payment_txn_id'] = $paramData['payment_txn_id'];
            }
            if(isset($paramData['type'])){
                $data['type'] = $paramData['type'];
            }
            if(isset($paramData['refunded_type'])){
                $data['refunded_type'] = $paramData['refunded_type'];
            }
            if(isset($paramData['after_sale_reason'])){
                $data['after_sale_reason'] = $paramData['after_sale_reason'];
            }
            if(isset($paramData['expressage_company'])){
                $data['expressage_company'] = $paramData['expressage_company'];
            }
            if(isset($paramData['expressage_num'])){
                $data['expressage_num'] = $paramData['expressage_num'];
            }
            if(isset($paramData['expressage_fee'])){
                $data['expressage_fee'] = $paramData['expressage_fee'];
            }
            if(isset($paramData['refunded_fee'])){
                $data['refunded_fee'] = $paramData['refunded_fee'];
            }
            if(isset($paramData['imgs'])){
                $data['imgs'] = json_encode($paramData['imgs']);
            }
            if(isset($paramData['is_platform_intervention'])){
                $data['is_platform_intervention'] = $paramData['is_platform_intervention'];
            }
            if(isset($paramData['remarks'])){
                $data['remarks'] = $paramData['remarks'];
            }
            $data = array_filter($data);
            if(isset($paramData['status']) && isset($paramData['status'])){
                $data['status'] = $paramData['status'];
            }
            if(isset($paramData['captured_refunded_fee'])){
                $data['captured_refunded_fee'] = $paramData['captured_refunded_fee'];
            }
            if(isset($paramData['initiator'])){
                $data['initiator'] = $paramData['initiator'];
            }
            if(isset($paramData['item'])){
                $data['item'] = $paramData['item'];
            }
            if(isset($paramData['create_ip'])){
                $data['create_ip'] = $paramData['create_ip'];
            }
            $res = model("OrderAfterSaleApply")->saveOrderAfterSaleApply($data);
            if($res>0){
                return apiReturn(['code'=>200,'data'=>$res]);
            }else{
                if ($res == -99){
                    //重复提交售后申请情况
                    return apiReturn(['code'=>1002,'msg'=>'You have submitted.']);
                }else{
                    return apiReturn(['code'=>1002]);
                }
            }
        }catch (\Exception $e){
            Log::write("error:".$e->getMessage());
            Log::write("paramData:".json_encode($paramData));
            return apiReturn(['code'=>1002,'msg'=>$e->getMessage()]);
        }
    }

    /**
     * 获取售后订单数据
     * @return mixed
     */
    public function getOrderAfterSaleApplyInfo(){
        $paramData = request()->post();
        if(isset($paramData['order_number'])){
            $where['so.order_number'] = $paramData['order_number'];
        }elseif(isset($paramData['after_sale_id'])){
            $where['after_sale_id'] = $paramData['after_sale_id'];
        }else{
            return apiReturn(["code"=>1001]);
        }
        if(isset($paramData['status'])){
            $where['oa.status'] = $paramData['status'];
        }

        if(isset($paramData['type'])){
            $where['oa.type'] = $paramData['type'];
        }
        $res = model("OrderAfterSaleApply")->getOrderAfterSaleApplyInfo($where);
        if($res>0){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1002]);
        }
    }

    /**
     * 增加订单售后申请操作记录数据
     * @return mixed
     */
    public function addApplyLogData(){
        $param = request()->post();
        //参数校验
        $validate = $this->validate($param,(new OrderParams())->addApplyLogDataRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        $res = model("OrderAfterSaleApply")->addOrderAfterSaleApplyLog($param);
        if ($res){
            return apiReturn(['code'=>200]);
        }else{
            return apiReturn(['code'=>1002]);
        }
    }

    /**
     * 更新订单退款退货换货数据
     * @return mixed
     */
    public function updateApplyData(){
        $param = request()->post();
        //参数校验
        $validate = $this->validate($param,(new OrderParams())->updateApplyDataRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        $after_sale_id = $param['after_sale_id'];
        unset($param['after_sale_id']);
        $res = model("OrderAfterSaleApply")->updateApplyDataByWhere(['after_sale_id'=>$after_sale_id], $param);
        if ($res){
            return apiReturn(['code'=>200]);
        }else{
            return apiReturn(['code'=>1002]);
        }
    }

    /*
  * 保存订单售后记录
  * */
    public function addOrderAfterSaleApplyLog($data=''){
        $paramData = request()->post();
        $add_data = !empty($data)?$data:$paramData;
        if(!isset($add_data['after_sale_id']) ){
            return apiReturn(['code'=>1001]);
        }
        $res = model("OrderAfterSaleApply")->addOrderAfterSaleApplyLog($add_data);
        if($res>0){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1002]);
        }
    }

    /*
     * 添加退货快递单
     * */
    public function addReturnProductExpressage(){
        $paramData = request()->post();
        if(!isset($paramData['after_sale_id']) ){
            return apiReturn(['code'=>1001]);
        }
        $data['after_sale_id'] = $paramData['after_sale_id'];
        if(isset($paramData['expressage_company']) ){
            $data['expressage_company'] = $paramData['expressage_company'];
        }
        if(isset($paramData['expressage_num']) ){
            $data['expressage_num'] = $paramData['expressage_num'];
        }
        if(isset($paramData['expressage_fee']) ){
            $data['expressage_fee'] = $paramData['expressage_fee'];
        }
        if(isset($paramData['phone']) ){
            $data['phone'] = $paramData['phone'];
        }
        if(isset($paramData['explain']) ){
            $data['explain'] = $paramData['explain'];
        }
        if(isset($paramData['imgs']) ){
            $data['imgs'] = json_encode($paramData['imgs']);
        }
        $res = model("OrderAfterSaleApply")->addReturnProductExpressageForMy($data);
        if(true === $res){
            return apiReturn(['code'=>200]);
        }else{
            return apiReturn(['code'=>1002, 'msg'=>$res]);
        }
    }

    /*
     *获取退货快递单
     * */
    public function getReturnProductExpressage(){
        $paramData = request()->post();
        if(!isset($paramData['after_sale_id']) ){
            return apiReturn(['code'=>1001]);
        }
        $where['after_sale_id'] = $paramData['after_sale_id'];
        $res = model("OrderAfterSaleApply")->getReturnProductExpressage($where);
        return apiReturn(['code'=>200,'data'=>$res]);
    }


    public function getOrderAfterSaleApplyLog(){
        $paramData = request()->post();
        if(!isset($paramData['after_sale_id']) ){
            return apiReturn(['code'=>1001]);
        }
        $where['after_sale_id'] = $paramData['after_sale_id'];
        $res = model("OrderAfterSaleApply")->getOrderAfterSaleApplyLog($where);
        return apiReturn(['code'=>200,'data'=>$res]);

    }

    /**
     * 取消仲裁
     * @return mixed
     */
    public function cancelArbitration(){
        $param = request()->post();
        //参数校验
        $validate = $this->validate($param,(new OrderParams())->cancelArbitrationRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        $res = model("OrderAfterSaleApply")->cancelArbitration($param);
        Log::record('cancelArbitration:params-'.print_r($res, true));
        if($res){
            return apiReturn(['code'=>200,'data'=>$res]);
        }else{
            return apiReturn(['code'=>1002]);
        }
    }

    /*
     * 买家审核是否同意退款
     * */
    public function approved_refund(){

    }
    /**
     * 判断申请表是否有记录
     * [order_after_sale_apply description]
     * @return [type] [description]
     */
    public function order_after_sale_apply(){
         $data = request()->post();
         $res = model("OrderAfterSaleApply")->order_after_sale_apply($data);
         return $res;
    }
    /**
     * 新增退款产品详情
     * [order_after_sale_apply_item description]
     * @return [type] [description]
     */
    public function order_after_sale_apply_item(){
         if($data = request()->post()){
                $res = '';
                if(!empty($data["param_sku"])){
                     foreach ($data['param_sku'] as $k => $v) {
                            $where = [];
                            if(!empty($v['after_sale_id'])){
                                $where['after_sale_id'] = $v['after_sale_id'];
                            }
                            if(!empty($v['product_id'])){
                                $where['product_id'] = $v['product_id'];
                            }
                            if(!empty($v['sku_id'])){
                                $where['sku_id'] = $v['sku_id'];
                            }
                            if(!empty($v['sku_num'])){
                                $where['sku_num'] = $v['sku_num'];
                            }
                            if(!empty($v['product_name'])){
                                $where['product_name'] = $v['product_name'];
                            }
                            if(!empty($v['product_img'])){
                                $where['product_img'] = $v['product_img'];
                            }
                            if(!empty($v['product_nums'])){
                                $where['product_nums'] = $v['product_nums'];
                            }
                            if(!empty($v['product_price'])){
                                $where['product_price'] = $v['product_price'];
                            }
                            if(!empty($where)){
                               $res_1 = model("OrderAfterSaleApply")->order_after_sale_apply_item($where);
                               // return apiReturn(['code'=>100212,'msg'=>$res_1]);
                               if(empty($res_1)){
                                   Log::record('退款添加sku详情失败Error：'.json_encode($where));
                               }

                            }else{
                               $res = apiReturn(['code'=>1002,'msg'=>'传参出错']);
                            }
                     }

                }else{
                     $res = apiReturn(['code'=>1002,'msg'=>'传参出错']);
                }
                return $res;
         }
    }
     /**
     * 退款订单检测
     * [order_after_sale_apply_item description]
     * @return [type] [description]
     */
    public function OrderDetection(){
       if($data = request()->post()){
            $where = [];
            if(!empty($data["order"])){
                 $where['order_number'] = ['in',$data["order"]];
            }
            if(empty($where)){
              return apiReturn(['code'=>1002,'msg'=>'传参出错']);
            }

            if($data['status'] == 1){
                 $res = model("OrderAfterSaleApply")->OrderDetection($where);
            }else if($data['status'] == 2){
                 $res = model("OrderAfterSaleApply")->SomeSkuRefunds($where);
            }


            return $res;
       }
    }
}
