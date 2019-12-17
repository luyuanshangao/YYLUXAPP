<?php
namespace app\orderbackend\controller;
use app\common\controller\Base;
use think\Log;
use app\common\params\orderbackend\OrderParams;
use app\common\services\CommonService;
use app\demo\controller\Auth;
use app\orderbackend\model\OrderModel;
use app\orderbackend\services\OrderService;
use app\orderbackend\model\OrderRefundModel;
use think\Monlog;


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
        if(!isset($param['order_id']) && !isset($param['order_number'])){
            return apiReturn(['code'=>1002, 'msg'=>"order_id和order_number必须填写一个"]);
        }

        $model = new OrderModel();
        $order_id = isset($param['order_id'])?$param['order_id']:'';
        $order_number = isset($param['order_number'])?$param['order_number']:'';
        $data = $model->getOrderInfoByOrderId($order_id, $param['seller_id'],$order_number);
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
     * 生成换货订单
     */
    public function createAdminRmaOrder(){
        $paramData = request()->post();
        //参数校验
        $validate = $this->validate($paramData,(new OrderParams())->createAdminRmaOrderRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        foreach ($paramData['data'] as $info){
            $validate = $this->validate($info,(new OrderParams())->createAdminRmaOrderProductRules());
            if(true !== $validate){
                return apiReturn(['code'=>1002, 'msg'=>$validate]);
            }
        }
        $model = new OrderModel();
        $res = $model->createAdminRmaOrder($paramData);

        if (isset($res['result']) && true === $res['result']){
            return apiReturn(['code'=>200, 'data'=>isset($res['data'])?$res['data']:[]]);
        }else{
            return apiReturn(['code'=>1003, 'msg'=>'生成订单失败, '.$res]);
        }
    }


    /**
     * 订单售后退款
     * add 20190416
     */
    public function afterSaleRefundOrder(){
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
            if(isset($order_info['refunding_amount']) && $order_info['refunding_amount']>0){
                return apiReturn(['code'=>1002, 'msg'=>'payment有未返回成功订单，请稍后再试']);
            }
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
                        //if(($order_info['order_status'] < 500 || $order_info['order_status'] == 1700) && $refund_grand_total == $order_info['captured_amount']){
                        $change_reason = isset($paramData['change_reason'])?$paramData['change_reason']:"";
                            $model->updateOrderInfoByWhere(['order_id'=>$order_id], ['order_status'=>1900]);
                            $OrderStatusData['order_id'] = $order_id;
                            $OrderStatusData['order_status_from'] = $order_info['order_status'];
                            $OrderStatusData['order_status'] = 1900;
                            $OrderStatusData['change_reason'] = $change_reason;
                            $OrderStatusData['create_on'] = time();
                            //参数判断 - 为了解决退款成功，但提示失败导致多次退款问题 tinghu.liu 20190507
                            $OrderStatusData['create_by'] = isset($paramData['create_by'])?$paramData['create_by']:'API-afterSaleRefundOrder';
                            $OrderStatusData['create_ip'] = isset($paramData['create_ip'])?$paramData['create_ip']:'';
                            $OrderStatusData['chage_desc'] = isset($paramData['chage_desc'])?$paramData['chage_desc']:$change_reason;
                            $model->insertOrderStatusChange($OrderStatusData);
                        //}

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
            $data = $model->getOrderDataForAutomaticPraise(500);
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
            //Log::record('updateOrderStatusForAutomaticPraise-params:'.print_r($param, true));
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


    /**
     * seller下载订单
     * @return mixed
     * @throws \think\exception\DbException
     */
    public function sellerDownloadOrder(){
        $params = request()->post();
        //参数校验
        $validate = $this->validate($params,(new OrderParams())->sellerDownloadOrderRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        try{
            $model = new OrderModel();
            $data = $model->sellerDownloadOrder($params);
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
            unset($params['access_token']);
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

    /**
     * 【ERP查询状态用】根据订单号获取订单状态
     * @return mixed
     */
    public function getOrderStatusByOrderNum(){
        try{
            $params = request()->post();
            //参数校验
            $validate = $this->validate($params,(new OrderParams())->getOrderStatusByOrderNumRules());
            if(true !== $validate){
                return apiReturn(['code'=>1002, 'msg'=>$validate]);
            }
            $model = new OrderModel();
            $res =  $model->getOrderStatusByOrderNum($params);
            if ($res){
                return apiReturn(['code'=>200,'data'=>$res]);
            }else{
                return apiReturn(['code'=>1002]);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>1003,'msg'=>'程序异常，'.$e->getMessage()]);
        }
    }

    /**
     * 后台订单退款(退款不推货)
     * @author wang   2019-03-05
     */
    public function AdminrefundOrder(){
        $paramData = request()->post();
        try{
            $validate = $this->validate($paramData,(new OrderParams())->refundOrderRules());
            if(true !== $validate){
                return apiReturn(['code'=>1002, 'msg'=>$validate]);
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
            $order_info = $model->getOrderByInfoWhere(['order_id'=>$order_id]);
            if(!$order_info){
                return apiReturn(['code'=>1002, 'msg'=>'订单ID信息有误，请确认后再试']);
            }
            //获取交易ID
            $transaction_id = $model->getTransactionID(['order_number'=>$order_info['order_number']]);
            //退款金额
            //$refund_grand_total = $order_info['captured_amount'];
            //后台退款
            $refund_id = $paramData['refund_id'];
            $refund_data = (new OrderRefundModel())->getOrderRefundInfo(['refund_id'=>$refund_id]);
            if(isset($order_info['refunding_amount']) && $order_info['refunding_amount']>0 && isset($refund_data['status']) && $refund_data['status'] == 1){
                return apiReturn(['code'=>1002, 'msg'=>'payment有未返回成功订单，请稍后再试']);
            }
            //支付系统，1旧版，2新版
            $payment_system = $order_info['payment_system'];
            $refund_grand_total = $refund_data['captured_refunded_fee'];
            /*传入退款子单单号，不传的话payment回传不了信息 20190620 kevin*/
            $refund_order_number = [$order_info['order_number']];
            $service = new CommonService();
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
            if($payment_system==2){//新版退款
                Log::record('refundOrderNew');
                $params['TransactionId']=$transaction_id;
                $params['RefundAmount']=$refund_grand_total;
                $params['Note']='refundOrderNew';
                $params['order_number']=$order_info['order_number'];
                $order_json = $model->getOrderJson($order_id,$refund_grand_total);
                if(empty($order_json)){
                    return apiReturn(['code'=>1002, 'msg'=>'order_json有未返回数据，请稍后再试']);
                }
                $params['json']=json_encode($order_json);
                Log::record('退款参数1：'.print_r(json_encode($params), true));
                $refund_res = $service->refund($params, $order_info['customer_id'], $order_info['order_master_number']);
                Log::record('退款结果9：'.print_r($refund_res, true));
                if (
                    !empty($refund_res['code'])
                    && ($refund_res['code']==200)
                ){
                    if ($refund_res['data']['status'] == 'failure'){
                        $updata_refund_res = (new OrderRefundModel())->updateOrderRefundByParams(['refund_id'=>$paramData['refund_id']], ['status'=>3]);
                        if(!$updata_refund_res){
                            Log::record('退款失败（修改退款订单失败）-> refund_id：'.$paramData['refund_id']);
                        }
                        Log::record('退款结果（操作异常3）-> request：'.$refund_res['data']['error_info']);
                        return apiReturn(['code'=>1003, 'msg'=>'退款操作失败 '.$refund_res['data']['error_info']]);
                    }
                }else{//接口返回失败
                    $updata_refund_res = (new OrderRefundModel())->updateOrderRefundByParams(['refund_id'=>$paramData['refund_id']], ['status'=>3]);
                    if(!$updata_refund_res){
                        Log::record('退款失败（修改退款订单失败）-> refund_id：'.$paramData['refund_id']);
                    }
                    Log::record('退款结果（操作异常2）-> request：'.json_encode($service_post_data).', -> response：'.print_r($refund_res, true));
                    $res_msg = !empty($refund_res['msg'])?$refund_res['msg']:'无';
                    return apiReturn(['code'=>1004, 'msg'=>'退款操作失败，操作异常,第三方结果：'.$res_msg]);
                }
            }else{//旧版退款
                $refund_res = $service->payment('DoRefund', $service_post_data);
                Log::record('退款参数：'.print_r(json_encode($service_post_data), true));
                Log::record('退款结果：'.print_r($refund_res, true));

                Monlog::write(LOGS_MALL_CART.'_payment','info',__METHOD__,'refund',$service_post_data,'DoRefund',json_encode($refund_res), $order_info['customer_id'], $order_info['order_master_number'], $order_info['order_number']);
                
                if (
                    property_exists($refund_res, 'DoRefundResult')
                    && property_exists($refund_res->DoRefundResult, 'ResponseResult')
                ){
                    if ($refund_res->DoRefundResult->ResponseResult == 'Failure'){
                        $updata_refund_res = (new OrderRefundModel())->updateOrderRefundByParams(['refund_id'=>$paramData['refund_id']], ['status'=>3]);
                        if(!$updata_refund_res){
                            Log::record('退款失败（修改退款订单失败）-> refund_id：'.$paramData['refund_id']);
                        }
                        Log::record('退款结果（操作异常）-> request：'.$refund_res->DoRefundResult->Error->ShortMessage);
                        return apiReturn(['code'=>1003, 'msg'=>'退款操作失败 '.$refund_res->DoRefundResult->Error->ShortMessage]);
                    }else{
                        //如果是退款订单，将退款订单状态修改为“退款完成”,并且保存退款交易号到退款订单中 20190702 kevin
                        if(
                            property_exists($refund_res->DoRefundResult, 'ResponseTransactionInfo')
                            &&  property_exists($refund_res->DoRefundResult->ResponseTransactionInfo, 'TransactionID')
                        ){
                            $update_order_refund_data['payment_txn_id'] = $refund_res->DoRefundResult->ResponseTransactionInfo->TransactionID;
                        }else{
                            Log::write("TransactionID Is Empty!");
                        }
                    }
                }else{
                    $updata_refund_res = (new OrderRefundModel())->updateOrderRefundByParams(['refund_id'=>$paramData['refund_id']], ['status'=>3]);
                    if(!$updata_refund_res){
                        Log::record('退款失败（修改退款订单失败）-> refund_id：'.$paramData['refund_id']);
                    }
                    Log::record('退款结果（操作异常）-> request：'.json_encode($service_post_data).', -> response：'.print_r($refund_res, true));
                    return apiReturn(['code'=>1004, 'msg'=>'退款操作失败，操作异常']);
                }
            }
            //退款成功处理
            $update_order_refund_data['status'] = 2;
            $updata_refund_res = (new OrderRefundModel())->updateOrderRefundByParams(['refund_id'=>$paramData['refund_id']], $update_order_refund_data);
            if(!$updata_refund_res){
                Log::record('退款成功（修改退款订单失败）-> refund_id：'.$paramData['refund_id']);
            }
            if(($order_info['order_status'] < 500 || $order_info['order_status'] == 1700) && $refund_grand_total == $order_info['captured_amount']){
                $model->updateOrderInfoByWhere(['order_id'=>$order_id], ['order_status'=>1900]);
                $OrderStatusData['order_id'] = $order_id;
                $OrderStatusData['order_status_from'] = $order_info['order_status'];
                $OrderStatusData['order_status'] = 1900;
                $OrderStatusData['change_reason'] = $paramData['change_reason'];
                $OrderStatusData['create_on'] = time();
                $OrderStatusData['create_by'] = $paramData['create_by'];
                $OrderStatusData['create_ip'] = $paramData['create_ip'];
                $OrderStatusData['chage_desc'] = isset($paramData['chage_desc'])?$paramData['chage_desc']:$paramData['change_reason'];
                $model->insertOrderStatusChange($OrderStatusData);
            }
            //修改退款中金额数据
            $model->updateOrderInfoByWhere(['order_id'=>$order_id], ['refunding_amount'=>$refund_grand_total]);
            //记录操作日志
            $model->insertOrderRefundOperationData([
                'order_id'=>$order_id,
                'refund_id'=>$paramData['refund_id'],
                //退款来源:1-seller售后退款；2-my退款；3-admin退款
                'refund_from'=>$paramData['refund_from'],
                //退款类型：1-陪保退款；2-售后退款；3-订单取消退款;4-订单后台退款
                'refund_type'=>4,
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
            if(!empty($paramData['delete_sku'])){
                $model->update_sku($paramData);//OMS删除对应产品
            }
            return apiReturn(['code'=>200, 'msg'=>'退款成功']);
        }catch (\Exception $e){
            Log::record('退款结果（操作异常）-> request：'.$e->getMessage());
            return apiReturn(['code'=>1002, 'msg'=>'请求异常：'.$e->getMessage()]);
        }
    }


    /*
     * 获取订单退款记录
     * add 20190418 kevin
     * */
    public function getOrderRefundOperation(){
        $paramData = request()->post();
        $validate = $this->validate($paramData,(new OrderParams())->getOrderRefundOperationRules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        $model = new OrderModel();
        $where['order_id'] = $paramData['order_id'];
        $res = $model->getOrderRefundOperation($where);
        return apiReturn(['code'=>200, 'data'=>$res]);
    }

    /*
     *获取OMS推送订单状态记录
     * add 2019050525
     * */
    public function getOrderStatusOmsRecord(){
        $paramData = request()->post();
        $validate = $this->validate($paramData,(new OrderParams())->getOrderStatusOmsRecord());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        $where = array();
        $page_size = !empty($paramData['page_size'])?$paramData['page_size']:20;
        $page = !empty($paramData['page'])?$paramData['page']:1;
        $path = !empty($paramData['path'])?$paramData['path']:'';
        $page_query = !empty($paramData['page_query'])?$paramData['page_query']:'';
        $model = new OrderModel();
        if(!empty($paramData['order_number'])){
            $where['order_number'] = trim($paramData['order_number']);
        }
        $res = $model->getOrderStatusOmsRecord($where,$page_size,$page,$path,$page_query);
        return apiReturn(['code'=>200, 'data'=>$res]);
    }

    /*
     *获取OMS推送订单状态记录
     * add 20190619 kevin
     * */
    public function getOrderDiscountExceptionList(){
        $paramData = request()->post();
        $validate = $this->validate($paramData,(new OrderParams())->getOrderDiscountExceptionList());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        $where = array();
        $page_size = !empty($paramData['page_size'])?$paramData['page_size']:20;
        $page = !empty($paramData['page'])?$paramData['page']:1;
        $path = !empty($paramData['path'])?$paramData['path']:'';
        $page_query = !empty($paramData['page_query'])?$paramData['page_query']:'';
        $model = new OrderModel();
        if(!empty($paramData['startCreateOn']) && !empty($paramData['endCreateOn'])){
            $where['create_on'] = ['BETWEEN',[$paramData['startCreateOn'],$paramData['endCreateOn']]];
        }else{
            if(isset($paramData['startCreateOn']) && !empty($paramData['startCreateOn'])){
                $where['create_on'] = ["EGT",$paramData['startCreateOn']];
            }
            if(isset($paramData['endCreateOn']) && !empty($paramData['endCreateOn'])){
                $where['create_on'] = ["ELT",$paramData['endCreateOn']];
            }
        }
        if(!empty($paramData['order_number'])){
            $where['order_number'] = trim($paramData['order_number']);
        }
        $res = $model->getOrderDiscountExceptionList($where,$page_size,$page,$path,$page_query);
        return apiReturn(['code'=>200, 'data'=>$res]);
    }

    /*
     * 获取海外订单发货数据
     * add by 20190711 kevin
     * */
    public function getDeliveryOrder(){
        $paramData = request()->post();
        $validate = $this->validate($paramData,(new OrderParams())->getDeliveryOrder());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'msg'=>$validate]);
        }
        $where = array();
        $page_size = !empty($paramData['page_size'])?$paramData['page_size']:20;
        $page = !empty($paramData['page'])?$paramData['page']:1;
        $path = !empty($paramData['path'])?$paramData['path']:'';
        $page_query = !empty($paramData['page_query'])?$paramData['page_query']:'';
        /*判断是否导出，导出不需要分页*/
        $is_export = !empty($paramData['is_export'])?$paramData['is_export']:0;
        $model = new OrderModel();
        $where['o.store_id'] = trim($paramData['store_id']);
        if(!empty($paramData['startTime']) && !empty($paramData['endTime'])){
            $where['op.add_time'] = ['BETWEEN',[strtotime($paramData['startTime']),strtotime($paramData['endTime'])]];
        }else{
            if(isset($paramData['startTime']) && !empty($paramData['startTime'])){
                $where['op.add_time'] = ["EGT",strtotime($paramData['startTime'])];
            }
            if(isset($paramData['endTime']) && !empty($paramData['endTime'])){
                $where['op.add_time'] = ["ELT",strtotime($paramData['endTime'])];
            }
        }
        $res = $model->getDeliveryOrder($where,$is_export,$page_size,$page,$path,$page_query);
        if(!empty($res)){
            /*获取出货单信息*/
            if($is_export){
                $delivery_order_data = $res;
            }else{
                $delivery_order_data = $res['data'];
            }
            if($is_export != 2){
                $sku_data = array();
                foreach ($delivery_order_data as $key=>$value){
                    if(!in_array($value['sku_id'],$sku_data)){
                        $sku_data[] = $value['sku_id'];
                    }
                }
                $product_purchase_price = controller("mallextend/Product")->getProductPurchasePrice(['skus'=>$sku_data]);
                if($product_purchase_price['code'] == 200 && !empty($product_purchase_price['data'])){
                    foreach ($product_purchase_price['data'] as $key=>$value){
                        $sku_unit_cost[$value['SKU']] = $value['UnitCost'];
                    }
                    foreach ($delivery_order_data as $k => $v) {
                        $delivery_order_data[$k]['unit_cost'] = isset($sku_unit_cost[$v['sku_id']]) ? $sku_unit_cost[$v['sku_id']] : 0;
                        if(isset($v['sku_id']) && isset($v['exchange_rate']) && isset($sku_unit_cost[$v['sku_id']])){
                            $delivery_order_data[$k]['total_cost'] = sprintf("%.2f",$sku_unit_cost[$v['sku_id']]/$v['exchange_rate']*$v['sku_qty']);
                        }else{
                            $delivery_order_data[$k]['total_cost'] = 0;
                        }
                        if(isset($v['sku_id']) && isset($v['exchange_rate']) && isset($sku_unit_cost[$v['sku_id']])){
                            $delivery_order_data[$k]['total_cost_usd'] = sprintf("%.2f",$sku_unit_cost[$v['sku_id']]*$v['sku_qty']);
                        }else{
                            $delivery_order_data[$k]['total_cost_usd'] = 0;
                        }
                    }
                }
            }
            /*获取出货单信息*/
            if($is_export){
                $res = $delivery_order_data;
            }else{
                $res['data'] = $delivery_order_data;
            }
        }
        return apiReturn(['code'=>200, 'data'=>$res]);
    }

}
