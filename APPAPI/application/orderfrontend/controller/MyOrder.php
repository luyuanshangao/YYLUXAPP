<?php
namespace app\orderfrontend\controller;

use app\common\helpers\RedisClusterBase;
use app\common\params\orderfrontend\OrderParams;
use app\demo\controller\Auth;
use app\common\services\CommonService;
use app\orderfrontend\model\OrderModel;
use app\orderfrontend\model\OrderPackageTrack;
use app\orderfrontend\services\OrderService;
use app\common\controller\Base;
use think\Log;

/**
 * 用户端调用接口类
 * @author kevin
 * @version
 * 2018-04-23
 */
class MyOrder extends Base
{
    //NOC订单记录
    const QueueMallOrderSuccessStatus_NOC = 'mall_order_success_change_order_status_noc';

    /**
     * 订单列表
     */
    public function getOrderList(){
        $paramData = request()->post();
        $validate = $this->validate($paramData,"MyOrder.getOrderList");
        if(true !== $validate){
            Log::record('$paramData'.json_encode($paramData));
            return apiReturn(['code'=>1002,"msg"=>$validate]);
        }
        $paramData = array_filter($paramData);
        //todo 参数校验
        //return $paramData;
        /*用户ID*/
        if(isset($paramData['customer_id'])){
            $where['customer_id'] = $paramData['customer_id'];
        }else{
            apiReturn(['code'=>1001]);
        }
        /*订单编号*/
        if(isset($paramData['order_number'])){
            $where['order_number'] = $paramData['order_number'];
        }
        //店铺ID
        if(isset($paramData['store_id'])){
            $where['store_id'] = $paramData['store_id'];
        }
        /*商品名称*/
        if(isset($paramData['product_name'])){
            $where['product_name'] = ['like',"%".$paramData['product_name']."%"];
        }
        if(isset($paramData['sku_num'])){
            $where['sku_num'] = $paramData['sku_num'];
        }
        /*订单状态*/
        if(isset($paramData['order_status'])){
            $where['order_status'] = $paramData['order_status'];
        }
        /*订单小状态*/
        if(isset($paramData['order_branch_status'])){
            if(is_array($paramData['order_branch_status'])){
                $where['order_branch_status'] = TrimArray($paramData['order_branch_status']);
            }else{
                $where['order_branch_status'] = ["IN",$paramData['order_branch_status']];
            }
        }
        /*支付状态*/
        if(isset($paramData['payment_status'])){
            $where['o.payment_status'] = $paramData['payment_status'];
        }
        /*支付状态*/
        if(isset($paramData['seller_name'])){
            $where['seller_name'] = ['like',"%".$paramData['seller_name']."%"];
        }
        if(isset($paramData['create_on_start']) && isset($paramData['create_on_end'])){
            $where['o.create_on'] = ["between",[strtotime($paramData['create_on_start']),strtotime($paramData['create_on_end'])]];
        }else{
            /*成交开始时间*/
            if(isset($paramData['create_on_start'])){
                $where['o.create_on'] = ['gt',strtotime($paramData['create_on_start'])];
            }

            /*成交结束时间*/
            if(isset($paramData['create_on_end'])){
                $where['o.create_on'] = ['lt',strtotime($paramData['create_on_end'])];
            }
        }
        if(isset($paramData['tracking_number'])){
            $where['order_number'] = model("OrderModel")->getOrderNumberByTrackingNumber($paramData['tracking_number']);
        }
        $where['delete_time'] = 0;
        /*订单评价状态*/
        $page_size = input("post.page_size",20);
        $page = input("post.page",1);
        $path = input("post.path");
        $order = isset($paramData['order'])?$paramData['order']:"o.order_id desc";
        $page_query = isset($paramData['page_query'])?$paramData['page_query']:'';
        if(isset($where['order_status']) && $where['order_status'] == 700){
            $where['order_status'] = ['in',[200,400]];
        }
        $list = model("OrderModel")->getOrderList($where,$page_size,$page,$path,$order,$page_query);
        return apiReturn(['code'=>200,'data'=>$list]);
    }

    public function getOrderInfo(){
        $paramData = request()->post();
        $validate = $this->validate($paramData,"MyOrder.getOrderInfo");
        if(true !== $validate){
            return apiReturn(['code'=>1002,"msg"=>$validate]);
        }
        /*用户ID*/
        if(!isset($paramData['customer_id'])){
            return apiReturn(['code'=>1001]);
        }
        if(isset($paramData['order_id']) || isset($paramData['order_number'])){
            $order_id =  isset($paramData['order_id'])?$paramData['order_id']:'';
            $order_number =  isset($paramData['order_number'])?$paramData['order_number']:'';
            $sku_id = isset($paramData['sku_id'])?$paramData['sku_id']:'';
            $order_item = model("OrderModel")->getOrderInfo($order_id,$sku_id,$paramData['customer_id'],$order_number);
            return apiReturn(['code'=>200,'data'=>$order_item]);
        }else{
            return apiReturn(['code'=>1001]);
        }
    }

    /*
     * 删除订单
     * */
    public function delOrder(){
        $paramData = request()->post();
        $validate = $this->validate($paramData,"MyOrder.delOrder");
        if(true !== $validate){
            return apiReturn(['code'=>1002,"msg"=>$validate]);
        }
        if(isset($paramData['order_number'])){
            $where['order_number'] = $paramData['order_number'];
            //$where['order_id'] = $paramData['order_id'];
            $where['customer_id'] = $paramData['customer_id'];
            $res = model("OrderModel")->delOrder($where);
            if($res!==false){
                return apiReturn(['code'=>200,'data'=>$res]);
            }else{
                Log::record('delOrder'.json_encode($paramData));
                return apiReturn(['code'=>1002]);
            }
        }else{
            return apiReturn(['code'=>1001]);
        }
    }

    /*
     * 获取订单数量
     * */
    public function getOrderCount(){
        $paramData = request()->post();
        $validate = $this->validate($paramData,"MyOrder.getOrderCount");
        if(true !== $validate){
            return apiReturn(['code'=>1002,"msg"=>$validate]);
        }
        $paramData = array_filter($paramData);
        //todo 参数校验
        //return $paramData;
        /*用户ID*/
        if(isset($paramData['customer_id'])){
            $where['customer_id'] = $paramData['customer_id'];
        }else{
            apiReturn(['code'=>1001]);
        }
        if(isset($paramData['order_number'])){
            $where['order_number'] = $paramData['order_number'];
        }else{
            apiReturn(['code'=>1001]);
        }
        $where['delete_time'] = 0;
        $where['order_master_number'] = ['neq',0];
        $res =  model("OrderModel")->getOrderCount($where);
        return apiReturn(['code'=>200,'data'=>$res]);
    }

    /*
     * 更改订单状态
     * */
    public function updateOrderStatus(){
        $paramData = request()->post();
        $validate = $this->validate($paramData,"MyOrder.updateOrderStatus");
        if(true !== $validate){
            return apiReturn(['code'=>1002,"msg"=>$validate]);
        }
        if(isset($paramData['order_id'])){
            $where['order_id'] = $paramData['order_id'];
            $res = model("OrderModel")->delOrder($paramData['order_id']);
            if($res){
                return apiReturn(['code'=>200,'data'=>$res]);
            }else{
                return apiReturn(['code'=>1002]);
            }
        }else{
            return apiReturn(['code'=>1001]);
        }
    }

    /*
     * 获取订单基础信息
     * */
    public function getOrderBasics(){
        $paramData = request()->post();
        $validate = $this->validate($paramData,"MyOrder.getOrderBasics");
        if(true !== $validate){
            return apiReturn(['code'=>1002,"msg"=>$validate]);
        }
        if((isset($paramData['order_id']) || isset($paramData['order_number'])) && isset($paramData['customer_id'])){
            if(isset($paramData['order_id'])){
                $where['order_id'] = $paramData['order_id'];
            }
            if(isset($paramData['order_number'])){
                $where['order_number'] = $paramData['order_number'];
            }
            if(isset($paramData['customer_id'])){
                $where['customer_id'] = $paramData['customer_id'];
            }
            $res = model("OrderModel")->getOrderBasics($where);
            if($res){
                return apiReturn(['code'=>200,'data'=>$res]);
            }else{
                return apiReturn(['code'=>1002]);
            }
        }else{
            return apiReturn(['code'=>1001]);
        }
    }

    /**
     * 获取售后订单配置数据
     * @return mixed
     */
    public function getAfterSaleConfig(){
        try{
            return apiReturn(['code'=>200,'data'=>config('after_sale_type')]);
        }catch (\Exception $e){
            return apiReturn(['code'=>1002,'msg'=>'System abnormality '.$e->getMessage()]);
        }
    }

    /**
     * 订单退款
     */
    public function refundOrder(){
        $paramData = request()->post();
        $validate = $this->validate($paramData,"MyOrder.refundOrder");
        if(true !== $validate){
            return apiReturn(['code'=>1002,"msg"=>$validate]);
        }
        try{
            $validate = $this->validate($paramData,(new OrderParams())->refundOrderRules());
            if(true !== $validate){
                return apiReturn(['code'=>1002, 'msg'=>$validate]);
            }
            //根据订单ID获取订单信息
            $order_id = $paramData['order_id'];
            $model = new OrderModel();
            $order_info = $model->getOrderInfoByOrderId($order_id);
            //进行退款操作
            if($order_info['captured_amount']>0){
                $service_post_data = array(
                    'DoRefund' => array(
                        'request' => array(
                            'CurrencyType' => 'Cash', //类型： Unknow (未知的) = 0,  Cash (现金) = 1, StoreCredit (虚拟货币) = 2,GiftCard (虚拟货币) = 3
                            'RefundAmount' => $order_info['captured_amount'], //退款金额
                            'TransactionID' => $order_info['transaction_id'], //交易唯一ID
                            // 'UniqueID' => $order_info['customer_id'], //用户ID
                            'ChildrenOrderNumber' => [$order_info['order_number']], //子单单号，数组类型
                        )
                    )
                );
                $service = new CommonService();
                $refund_res = $service->payment('DoRefund', $service_post_data);
                Log::record('退款结果：'.print_r($refund_res, true));
                if (
                    property_exists($refund_res, 'DoRefundResult')
                    && property_exists($refund_res->DoRefundResult, 'ResponseResult')
                ){
                    if ($refund_res->DoRefundResult->ResponseResult != 'Failure'){
                        /*增加订单退款中金额*/
                        $IncRefunding = model("OrderModel")->refundingAmount(['order_id'=>$order_id],$order_info['captured_amount']);
                        if(!$IncRefunding){
                            return apiReturn(['code'=>1002, 'msg'=>'Increase the amount of money in the refund']);
                        }
                        $cancel_post_data = array(
                            'CancelOrderRequest' => array(
                                'OrderNumber' => $order_info['order_number'], //订单号（字符串类型）
                                'Reason' => "Other", //取消订单原因（枚举类型）
                                'AdditionalReason' => $paramData['change_reason_id'], //退款原因
                                'RefundIfApplicable' => false, //是否退款（Bool类型，始终是false）
                                'RefundMethod' => 'Backtrack', //退款方式（为空）
                                'ReturnsOrderDetails' => false, //是否返回订单详情（Bool类型，始终是false）
                            )
                        );
                        $service = new CommonService();
                        $refund_res = $service->CancelOrder('CancelOrder', $cancel_post_data);
                        $redis_cluster = new RedisClusterBase();
                        foreach ($order_info['item_data'] as $item){
                            if (strtolower($item['shipping_model']) == 'nocnoc'){
                                $redis_cluster->lPush(
                                    self::QueueMallOrderSuccessStatus_NOC,
                                    $order_id.',1400'
                                );
                                break;
                            }
                        }
                        return apiReturn(['code'=>200, 'msg'=>'Refunds']);
                    }else{
                        if($refund_res->DoRefundResult->Error && property_exists($refund_res->DoRefundResult->Error,'ShortMessage')){
                           return apiReturn(['code'=>1003, 'msg'=>'Refund operation failure '.$refund_res->DoRefundResult->Error->ShortMessage]);
                        }else{
                            return apiReturn(['code'=>1003, 'msg'=>'Refund operation failure '.$refund_res->DoRefundResult->Error]);
                        }
                    }
                }else{
                    Log::record('退款结果（操作异常）-> request：'.json_encode($service_post_data).', -> response：'.print_r($refund_res, true));
                    return apiReturn(['code'=>1004, 'msg'=>'Failure of refunds operation and abnormal operation']);
                }
            }else{
                $cancel_post_data = array(
                    'CancelOrderRequest' => array(
                        'OrderNumber' => $order_info['order_number'], //订单号（字符串类型）
                        'Reason' => "Other", //取消订单原因（枚举类型）
                        'AdditionalReason' => $paramData['change_reason_id'], //退款原因
                        'RefundIfApplicable' => false, //是否退款（Bool类型，始终是false）
                        'RefundMethod' => 'Backtrack', //退款方式（为空）
                        'ReturnsOrderDetails' => false, //是否返回订单详情（Bool类型，始终是false）
                    )
                );
                $service = new CommonService();
                $refund_res = $service->CancelOrder('CancelOrder', $cancel_post_data);
            }
            return apiReturn(['code'=>200, 'msg'=>'Refunds']);
        }catch (\Exception $e){
            return apiReturn(['code'=>1002, 'msg'=>$e->getMessage()]);
        }
    }
/*
 * 记录订单改变日志
 * */
    public function order_status_change_log(){
        $data = request()->post();
        $model = new OrderModel();
        $res = $model->order_status_change_log($data);
        if($res){
            return apiReturn(['code'=>200]);
        }else{
            return apiReturn(['code'=>1002]);
        }
    }

    /*
     * 获取物流跟踪号
     * */
    public function getLogisticsdetail(){
        $paramData = request()->post();
        $validate = $this->validate($paramData,"MyOrder.getLogisticsdetail");
        if(true !== $validate){
            return apiReturn(['code'=>1002,"msg"=>$validate]);
        }
        $order_id = $paramData['order_id'];
        $where['order_number'] = $paramData['order_number'];
        if(isset($paramData['package_id']) && !empty($paramData['package_id'])){
            $where['package_id'] = $paramData['package_id'];
        }
        $model = new OrderModel();
        $data['order_tracking_number'] = $model->getTrackingNumber($where);
        $data['order_shipping_address'] = $model->getOrderShippingAddressDataByWhere(['order_id'=>$order_id]);
        if(!empty($data)){
            return apiReturn(['code'=>200,'data'=>$data]);
        }else{
            return apiReturn(['code'=>1002]);
        }
    }

    /*
    * 获取物流跟踪号
     * kevin 20190525
    * */
    public function getPackageTrace(){
        $paramData = request()->post();
        $validate = $this->validate($paramData,"MyOrder.getPackageTrace");
        if(true !== $validate){
            return apiJosn(['code'=>1002,"msg"=>$validate]);
        }
        if(isset($paramData['tracking_number']) && !empty($paramData['tracking_number'])){
            $where['tracking_number'] = $paramData['tracking_number'];
        }else{
            return apiJosn(['code'=>1002]);
        }
        $model = new OrderPackageTrack();
        $data = $model->get($where);
        $list=[];
        if(!empty($data['raw_data'])){
            $Logisticsdetail=json_decode($data['raw_data'],true);
            if(!empty($Logisticsdetail['track']['z1'])){
                $list=$Logisticsdetail['track']['z1'];
            }
        };
        return apiJosn(['code'=>200,'data'=>$list]);
    }

    /**
     * 获取买家订单数量
     * @return mixed
     */
    public function getOrderNumForUser(){
        try{
            $paramData = request()->post();
            /** 参数校验 **/
            $validate = $this->validate($paramData,(new OrderParams())->getOrderNumForUserRules());
            if(true !== $validate){
                return apiReturn(['code'=>2001, 'msg'=>$validate]);
            }
            $data = (new OrderModel())->getOrderNumForUser($paramData);
            if(!empty($data)){
                return apiReturn(['code'=>200,'data'=>$data]);
            }else{
                return apiReturn(['code'=>1002]);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1003,'msg'=>$e->getMessage()]);
        }
    }

    /*
     * 获取订单产品信息
     * */
    public function getOrderItem(){
        try{
            $paramData = request()->post();
            $validate = $this->validate($paramData,"MyOrder.getOrderItem");
            if(true !== $validate){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            if(!isset($paramData['order_id'])){
                return apiReturn(['code'=>1001]);
            }
            $where['order_id'] = $paramData['order_id'];
            $order_item = (new OrderModel())->getOrderItem($where);
            if(!empty($order_item)){
                return apiReturn(['code'=>200,'data'=>$order_item]);
            }else{
                return apiReturn(['code'=>1002]);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1003,'msg'=>$e->getMessage()]);
        }
    }

    /*
     * 订单物流详情
     * */
    public function logisticsdetail(){
        try{
            $paramData = request()->post();
            $validate = $this->validate($paramData,"MyOrder.logisticsdetail");
            if(true !== $validate){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            $order_id = $paramData["order_id"];
            $tracking_number = $paramData["tracking_number"];
            $customer_id = $paramData['customer_id'];
            $baseApi = new BaseApi();
            $OrderBasics = $baseApi->getOrderInfo(['order_id'=>$order_id,'customer_id'=>$customer_id]);
            if($OrderBasics['code'] == 200){//是否存在订单
                $Logisticsdetail = $baseApi->getLogisticsdetail(['order_id'=>$order_id,'order_number'=>$OrderBasics['data']['order_number'],'tracking_number'=>$tracking_number]);
                $tracking_nos = !empty($tracking_number)?$tracking_number:(isset($Logisticsdetail['data']['order_tracking_number'][0]['tracking_number'])?$Logisticsdetail['data']['order_tracking_number'][0]['tracking_number']:'');
                //根据物流编号，获取物流详情
                if(!empty($tracking_nos)){
                    $package_trace_list = $this->get_package_trace_list($tracking_nos);
                }else{
                    $package_trace_list = "";
                }

                return $this->fetch('',['OrderBasics'=>$OrderBasics['data'],'package_trace_list'=>$package_trace_list,'Logisticsdetail'=>$Logisticsdetail['data']]);
            }else{
                $res['msg'] = "Order does not exist";
                return $res;
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1003,'msg'=>$e->getMessage()]);
        }
    }

    public function get_package_trace_list($tracking_nos){
        $params = array(
            'GetPackageTrace' => array(
                'request' => array(
                    'HasAll' => true,
                    'TrackingNos' => array(
                        //RI256778026CN
                        'string'=>array(
                            $tracking_nos,
                        )
                    )
                )
            )
        );
        try{
            $_rate = $this->CommonService->dxSoap('GetPackageTrace', $params);
            if(gettype($_rate) == "object"){
                $package_trace_list = '';
            }else{
                $package_trace_list = isset($_rate['GetPackageTraceResult']->PackageList->Package) && !empty($_rate['GetPackageTraceResult']->PackageList->Package)?$_rate['GetPackageTraceResult']->PackageList->Package->PackageTraceList->PackageTrace:'';
                if(!empty($package_trace_list)){
                    foreach ($package_trace_list as $key=>&$value){
                        $value=(array)$value;
                    }
                }else{
                    $package_trace_list = '';
                }
            }

        }catch (\Exception $e){
            return $e->getMessage();
        }

        return $package_trace_list;
    }

    /*
         * 获取订单操作历史记录
         * */
    public function getOrderStatusChange($where){
        try{
            $paramData = request()->post();
            if(!isset($paramData['order_id'])){
                return apiReturn(['code'=>1001]);
            }
            $res = $this->db->table($this->order_status_change)->where($where)->order(" id desc")->select();
            if(!empty($res)){
                return apiReturn(['code'=>200,'data'=>$res]);
            }else{
                return apiReturn(['code'=>1002]);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1003,'msg'=>$e->getMessage()]);
        }
    }

    /*
     * 获取订单退款总数
     * */
    public function getRefundedAmount(){
        try{
            $paramData = request()->post();
            $validate = $this->validate($paramData,"MyOrder.getRefundedAmount");
            if(true !== $validate){
                return apiReturn(['code'=>1002,"msg"=>$validate]);
            }
            $where['customer_id'] = $paramData['customer_id'];
            $refunded_amount = (new OrderModel())->getRefundedAmount($where);
            if(!empty($refunded_amount)){
                foreach ($refunded_amount as $key=>$value){
                    if($value['sum_refunded_amount'] <= 0){
                        unset($refunded_amount[$key]);
                    }
                }
                return apiReturn(['code'=>200,'data'=>$refunded_amount]);
            }else{
                return apiReturn(['code'=>1002]);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1003,'msg'=>$e->getMessage()]);
        }
    }
}
