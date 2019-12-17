<?php
namespace app\orderbackend\controller;

use app\common\params\orderbackend\OrderParams;
use app\common\services\CommonService;
use app\demo\controller\Auth;
use app\orderbackend\model\OrderModel;
use app\orderbackend\services\OrderService;
use think\Log;

/**
 * 订单类
 * Class Order
 * @author tinghu.liu 2018/4/23
 * @package app\orderbackend\controller
 */
class Order extends Auth
{
    /**
     * 获取订单列表数据（含分页）
     * @return mixed
     */
    public function getOrderLists(){
        $param = request()->post();
        //参数校验
        $validate = $this->validate($param,(new OrderParams())->getOrderDataRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        $model = new OrderModel();
        $data = $model->getOrderDataForPage($param);
        if (empty($data)){
            return apiReturn(['code'=>1006]);
        }
        return apiReturn(['code'=>200, 'data'=>$data]);
    }

    /**
     * 获取订单状态对应数量
     * @return mixed
     */
    public function getOrderStatusNum(){
        $param = request()->post();
        //参数校验
        $validate = $this->validate($param,(new OrderParams())->getOrderStatusNumRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        $model = new OrderModel();
        $data = $model->getOrderStatusNum($param);
        if (empty($data)){
            return apiReturn(['code'=>1006]);
        }
        return apiReturn(['code'=>200, 'data'=>$data]);
    }

    /**
     * 修改价格
     * @return mixed
     */
    public function updateOrderPrice(){
        $param = request()->post();
        //参数校验
        $validate = $this->validate($param,(new OrderParams())->updateOrderPriceRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        $model = new OrderModel();
        if ($model->updateOrderPrice($param)){
            return apiReturn(['code'=>200]);
        }else{
            return apiReturn(['code'=>1002]);
        }
    }

    /**
     * 获取订单详情
     * @return mixed
     */
    public function getOrderInfo(){
        $param = request()->post();
        //参数校验
        $validate = $this->validate($param,(new OrderParams())->getOrderInfoRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        $model = new OrderModel();
        $data = $model->getOrderInfoByOrderId($param['order_id'], $param['seller_id']);
        if (empty($data)){
            return apiReturn(['code'=>90001, 'msg'=>'不存在的数据']);
        }
        return apiReturn(['code'=>200, 'data'=>$data]);
    }

    /**
     * 更新订单备注
     * @return mixed
     */
    public function updateOrderRemark(){
        $param = request()->post();
        //参数校验
        $validate = $this->validate($param,(new OrderParams())->updateOrderRemarkRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        $model = new OrderModel();
        if ($model->updateOrderInfoByWhere(
            ['order_id'=>$param['order_id']],
            ['remark'=>$param['remark']]
        )){
            return apiReturn(['code'=>200]);
        }else{
            return apiReturn(['code'=>1002]);
        }
    }

    /**
     * 增加订单留言信息
     * @return mixed
     */
    public function addOrderMessage(){
        $param = request()->post();
        //参数校验
        $validate = $this->validate($param,(new OrderParams())->addOrderMessageRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        $model = new OrderModel();
        if ($model->insertOrderMessageData($param)){
            return apiReturn(['code'=>200]);
        }else{
            return apiReturn(['code'=>1002]);
        }
    }

    /**
     * 修改订单留言信息状态
     * @return mixed
     */
    public function updateOrderMessageStatus(){
        $param = request()->post();
        //参数校验
        $validate = $this->validate($param,(new OrderParams())->updateOrderMessageStatusRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        $model = new OrderModel();
        $where['order_id'] = $param['order_id'];
        $data['statused'] = $param['statused'];
        if(isset($param['message_type']) && !empty($param['message_type'])){
            $where['message_type'] = $param['message_type'];
        }
        if ($model->updateOrderMessageByWhere($where,$data)){
            return apiReturn(['code'=>200]);
        }else{
            return apiReturn(['code'=>1002]);
        }
    }

    /**
     * 生成换货订单
     */
    public function createRmaOrder(){
    	$paramData = request()->post();
        //参数校验
        $validate = $this->validate($paramData,(new OrderParams())->createRmaOrderRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        foreach ($paramData['data'] as $info){
            $validate = $this->validate($info,(new OrderParams())->createRmaOrderProductRules());
            if(true !== $validate){
                return apiReturn(['code'=>1002, 'msg'=>$validate]);
            }
        }
    	$model = new OrderModel();
        $res = $model->createRmoOrder($paramData);
    	if (true === $res){
            return apiReturn(['code'=>200]);
        }else{
            return apiReturn(['code'=>1003, 'msg'=>'生成订单失败 '.$res]);
        }
    }

    /**
     * 订单退款
     */
    public function refundOrder(){
    	$paramData = request()->post();
    	try{
            $validate = $this->validate($paramData,(new OrderParams())->refundOrderRules());
            if(true !== $validate){
                return apiReturn(['code'=>1002, 'msg'=>$validate]);
            }
            //是否是售后判断
            $is_after_sales = false;
            if (isset($paramData['after_sale_id']) && !empty($paramData['after_sale_id'])){
                $is_after_sales = true;
            }
            //退款来源类型：1-后台退款，2-seller售后退款【默认】
            $type = isset($paramData['type'])?$paramData['type']:2;
            if ($type == 1){
                $validate = $this->validate($paramData,(new OrderParams())->refundOrderAdminRules());
                if(true !== $validate){
                    return apiReturn(['code'=>1002, 'msg'=>$validate]);
                }
            }
            //根据订单ID获取订单信息
            $order_id = $paramData['order_id'];
            $model = new OrderModel();
            $order_info = $model->getOrderInfoByOrderId($order_id);
            //获取交易ID
            $transaction_id = $model->getTransactionID(['order_number'=>$order_info['order_number']]);
            //退款金额
            $refund_grand_total = $order_info['captured_amount'];
            //售后退款
            if ($is_after_sales){
                $after_sale_id = $paramData['after_sale_id'];
                $after_data = $model->getAfterSaleApplyByWhere(['after_sale_id'=>$after_sale_id]);
                $refund_grand_total = $after_data[0]['captured_refunded_fee'];
            }
            switch ($type){
                case 1://后台退款
                    $refund_grand_total = $paramData['amount'];
                    break;
                default:break;
            }

            /*判断是否是整单退款，不是整单退款不需要改变订单状态*/
            $refund_order_number = array();
            if($refund_grand_total == $order_info['captured_amount']){
                $refund_order_number = [$order_info['order_number']];
            }
            //进行退款操作
            $service_post_data = array(
                'DoRefund' => array(
                    'request' => array(
                        'CurrencyType' => 'Cash', //类型： Unknow (未知的) = 0,  Cash (现金) = 1, StoreCredit (虚拟货币) = 2,GiftCard (虚拟货币) = 3
                        'RefundAmount' => $refund_grand_total, //退款金额
                        'TransactionID' => $transaction_id, //$order_info['transaction_id'], //交易唯一ID
                        //'UniqueID' => $order_info['customer_id'], //用户ID
                        'UniqueID' => NULL, //用户ID
                        'ChildrenOrderNumber' => $refund_order_number, //子单单号，数组类型
                    )
                )
            );
            $service = new CommonService();
            $refund_res = $service->payment('DoRefund', $service_post_data);
            Log::record('退款参数：'.print_r(json_encode($service_post_data), true));
            Log::record('退款结果：'.print_r($refund_res, true));
            if (
                property_exists($refund_res, 'DoRefundResult')
                && property_exists($refund_res->DoRefundResult, 'ResponseResult')
            ){
                if ($refund_res->DoRefundResult->ResponseResult != 'Failure'){
                    /**
                     * stdClass Object
                    (
                    [DoRefundResult] => stdClass Object
                    (
                    [Error] =>
                    [GiftCardCode] =>
                    [ParentTransactionID] => 78296
                    [ResponseResult] => Success
                    [ResponseTransactionInfo] => stdClass Object
                    (
                    [Timestamp] => 2018-06-08T03:14:24.883303Z
                    [TransactionID] => 78324
                    )

                    [ThirdPartyTxnID] => 6GM73114PU7788101
                    )
                    )
                     */
                    // TODO 退款成功后调用取消订单接口（写入队列的形式）？？需和需求确认【不作处理】
                    //如果是售后订单，将售后订单状态修改为“退款完成”
                    if ($is_after_sales){
                        $model->updateAfterSaleApplyByParams(['after_sale_id'=>$paramData['after_sale_id']], ['status'=>5]);
                        if($order_info['order_status']<500 && $refund_grand_total == $order_info['captured_amount']){
                            $model->updateOrderInfoByWhere(['order_id'=>$order_id], ['order_status'=>1900]);
                            /*订单退款，20181016下午跟恒总讨论加*/
                            $cancel_post_data = array(
                                'CancelOrderRequest' => array(
                                    'OrderNumber' => $order_info['order_number'], //订单号（字符串类型）
                                    'Reason' => "Other", //取消订单原因（枚举类型）
                                    'AdditionalReason' => isset($paramData['reason'])?$paramData['reason']:'', //退款原因
                                    'RefundIfApplicable' => false, //是否退款（Bool类型，始终是false）
                                    'RefundMethod' => 'Backtrack', //退款方式（为空）
                                    'ReturnsOrderDetails' => false, //是否返回订单详情（Bool类型，始终是false）
                                )
                            );
                            $service = new CommonService();
                            $refund_res = $service->CancelOrder('CancelOrder', $cancel_post_data);
                        }

                    }
                    //修改退款中金额数据
                    $model->updateOrderInfoByWhere(['order_id'=>$order_id], ['refunding_amount'=>$refund_grand_total]);
                    //记录操作日志
                    $model->insertOrderRefundOperationData([
                        'order_id'=>$order_id,
                        //退款来源:1-seller售后退款；2-my退款；3-admin退款
                        'refund_from'=>$paramData['refund_from'],
                        //退款类型：1-陪保退款；2-售后退款；3-订单取消退款
                        'refund_type'=>$paramData['refund_type'],
                        //操作人类型：1-admin，2-seller，3-my
                        'operator_type'=>$paramData['operator_type'],
                        //操作人ID
                        'operator_id'=>$paramData['operator_id'],
                        //操作人名称
                        'operator_name'=>$paramData['operator_name'],
                        'refund_amount'=>$refund_grand_total,
                        'reason'=>isset($paramData['reason'])?$paramData['reason']:'',
                        'add_time'=>time(),
                    ]);
                    return apiReturn(['code'=>200, 'msg'=>'退款成功']);
                }else{
                    /**
                     * stdClass Object
                    (
                    [DoRefundResult] => stdClass Object
                    (
                    [Error] => stdClass Object
                    (
                    [Code] => 0x80040044
                    [LongMessage] => RepeatedApplicationForRefund.
                    [ShortMessage] => RepeatedApplicationForRefund.
                    )

                    [GiftCardCode] =>
                    [ParentTransactionID] => 0
                    [ResponseResult] => Failure
                    [ResponseTransactionInfo] =>
                    [ThirdPartyTxnID] =>
                    )

                    )
                     */
                    //如果是售后订单，将售后订单状态修改为“退款完成”
                    if ($is_after_sales){
                        $model->updateAfterSaleApplyByParams(['after_sale_id'=>$paramData['after_sale_id']], ['status'=>8]);
                    }
                    Log::record('退款结果（操作异常）-> request：'.$refund_res->DoRefundResult->Error->ShortMessage);
                    return apiReturn(['code'=>1003, 'msg'=>'退款操作失败 '.$refund_res->DoRefundResult->Error->ShortMessage]);
                }
            }else{
                Log::record('退款结果（操作异常）-> request：'.json_encode($service_post_data).', -> response：'.print_r($refund_res, true));
                return apiReturn(['code'=>1004, 'msg'=>'退款操作失败，操作异常']);
            }
        }catch (\Exception $e){
            Log::record('退款结果（操作异常）-> request：'.$e->getMessage());
            return apiReturn(['code'=>1002, 'msg'=>'请求异常：'.$e->getMessage()]);
        }
    }

    /**
     * 定时脚本专用 -买了又买
     * 获取一个月内所有成功支付订单产品关联数据
     */
    public function getBoughtAlsoBought(){
        $param = request()->post();
        //参数校验
        $validate = $this->validate($param,(new OrderParams())->getOrderTimeRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        $model = new OrderModel();
        $data = $model->getBoughtAlsoBought($param);
        if ($data){
            return apiReturn(['code'=>200,'data'=>$data]);
        }else{
            return apiReturn(['code'=>1002]);
        }
    }

    /**
     * 定时脚本专用
     * 根据产品，获取一个月内购买了这个产品，还买了其他产品的支付订单产品关联数据
     */
    public function getBoughtAlsoBoughtByProduct(){
        $param = request()->post();
        //参数校验
        $validate = $this->validate($param,(new OrderParams())->getOrderTimeRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        $model = new OrderModel();
        $data = $model->selectBoughtByProduct($param);
        if ($data){
            return apiReturn(['code'=>200,'data'=>$data]);
        }else{
            return apiReturn(['code'=>1002]);
        }
    }

    /**
     * 定时脚本专用
     * 获取一个月内成功支付的订单
     */
    public function getTaskOrder(){
        $param = request()->post();
        //参数校验
        $validate = $this->validate($param,(new OrderParams())->getOrderTimeRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        $model = new OrderModel();
        $data = $model->getTaskOrderData($param);
        if ($data){
            return apiReturn(['code'=>200,'data'=>$data]);
        }else{
            return apiReturn(['code'=>1002]);
        }
    }

    /**
     * 获取订单自动好评-订单数据【默认好评定时任务专用】
     * （状态为900||1000，并且状态变化时间 <= [time()-评论限制时间] ）
     * @return mixed
     */
    public function getOrderDataForAutomaticPraise(){
        try{
            $model = new OrderModel();
            $data = $model->getOrderDataForAutomaticPraise(2);
            return apiReturn(['code'=>200,'data'=>$data]);
        }catch (\Exception $e){
            return apiReturn(['code'=>1002,'msg'=>'系统异常 '.$e->getMessage()]);
        }
    }

    /**
     * 改变订单状态【默认好评定时任务专用】
     * Array
        (
            [to_order_status] => 1100
            [order_ids] => Array
                (
                    [0] => 1153
                 )
            [order_status_change_arr] => Array
            (
            [0] => Array
                (
                    [order_id] => 1153
                    [order_number] => 180610017778507749
                    [order_status_from] => 100
                )
            )
     * )
     * @return mixed
     * @throws \Exception
     * @throws \think\exception\PDOException
     */
    public function updateOrderStatusForAutomaticPraise(){
        try{
            $param = request()->post();
            Log::record('updateOrderStatusForAutomaticPraise-params:'.print_r($param, true));
            //参数校验
            $validate = $this->validate($param,(new OrderParams())->updateOrderStatusForAutomaticPraiseRules());
            if(true !== $validate){
                return apiReturn(['code'=>1002, 'msg'=>$validate]);
            }
            $model = new OrderModel();
            $data = $model->updateOrderStatusForAutomaticPraise($param);
            if ($data){
                return apiReturn(['code'=>200,'data'=>$data]);
            }else{
                return apiReturn(['code'=>1002]);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1002,'msg'=>'系统异常 '.$e->getMessage()]);
        }

    }

    /**
     * 根据父级订单ID获取订单数据
     * @return mixed
     */
    public function getOrderDataByOrderMasterNumber(){
        try{
            $param = request()->post();
            //参数校验
            $validate = $this->validate($param,(new OrderParams())->getOrderDataByOrderMasterNumberRules());
            if(true !== $validate){
                return apiReturn(['code'=>1002, 'msg'=>$validate]);
            }
            $model = new OrderModel();
            $data = $model->getOrderDataByWhere(['order_master_number'=>$param['order_master_number']]);
            if ($data){
                return apiReturn(['code'=>200,'data'=>$data]);
            }else{
                return apiReturn(['code'=>1002]);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1002,'msg'=>'系统异常 '.$e->getMessage()]);
        }
    }

    /**
     * 下载订单
     *
     * 只下载代发货状态的订单数据，增加时间选择
     * 1、注意数据隔离（增加sellerid对应sign的形式）
     * 2、下载后需要改订单状态？？
     * 3、seller发货后追踪号保存同步问题（考虑oms，fsc、my等方面）；
     *
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function downloadOrder(){
        $params = request()->post();
        //参数校验
        $validate = $this->validate($params,(new OrderParams())->downloadOrderRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $sign_flag = 'downloadOrder'.$params['store_id'].date('Y-m-d');
            //下载签名校验
            if ($params['download_sign'] !== $this->makeSign($sign_flag)){
                return apiReturn(['code'=>1002, 'msg'=>'没有下载权限']);
            }
            $model = new OrderModel();
            $data = $model->downloadOrder($params);
            return apiReturn(['code'=>200,'data'=>$data]);
        }catch (\Exception $e){
            return apiReturn(['code'=>1003,'msg'=>'程序异常，'.$e->getMessage()]);
        }
    }

    /*
     * 统计订单数量，用于后台用户管理
     * */
    public function getAdminCustomerOrder(){
        try{
            $params = request()->post();
            //参数校验
            $validate = $this->validate($params,(new OrderParams())->getAdminCustomerOrder());
            if(true !== $validate){
                return apiReturn(['code'=>1002, 'msg'=>$validate]);
            }
            $model = new OrderModel();
            $res =  $model->getAdminCustomerOrder($params);
            if ($res){
                return apiReturn(['code'=>200,'data'=>$res]);
            }else{
                return apiReturn(['code'=>1002]);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1003,'msg'=>'程序异常，'.$e->getMessage()]);
        }
    }
}
