<?php
namespace app\orderfrontend\controller;

use app\admin\dxcommon\BaseApi;
use app\common\helpers\OrderLib;
use app\common\helpers\RedisClusterBase;
use app\common\params\orderfrontend\SynOmsParams;
use app\demo\controller\Auth;
use app\orderfrontend\model\OrderModel;
use think\Log;
use think\Request;

/**
 * 订单与OMS、Payment等交互接口类【与OMS、Payment等交互专用类】
 * @author tinghu.liu 2018/06/14
 * @package app\orderfrontend\controller
 */
class SynOrder extends Auth
{
    public $base_api;
    public $order_model;
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $this->base_api = new BaseApi();
        $this->order_model = new OrderModel();
    }

    /**
     * 同步OMS订单状态至Order
     * @return mixed
     */
    public function status(){
        try{
            /**
             * <Notification>
             *    <ChangeID>474369</ChangeID>
             *    <OrderNumber>180626001042388898</OrderNumber>
             *    <Status>3</Status>
             *    <ChangeOn>2018-06-26T10:19:37.7377262</ChangeOn>
             *    <ChangeBy>Payment IPN</ChangeBy>
             *    <Notes></Notes>
             * </Notification>
             */

            //$post_data = htmlspecialchars_decode(file_get_contents("php://input"));
            Log::record('同步OMS订单至Order，接收的数据-php://input：'.print_r(file_get_contents("php://input"), true),'notice');
            //$data = simplexml_load_string($post_data);
            //$data_json = json_encode($data);

            //$data_json = htmlspecialchars_decode(file_get_contents("php://input"));
            $params = json_decode(file_get_contents("php://input"),true);
            Log::record('同步OMS订单至Order，接收的数据array：'.print_r($params, true),'notice');
            /** 参数校验 **/
            $validate = $this->validate($params,(new SynOmsParams())->orderStatusRules());
            if(true !== $validate){
                //return apiReturn(['code'=>2001, 'msg'=>$validate]);
                Log::record('同步OMS订单至Order，validate：'.$validate,'notice');
                echo '<Response>0</Response>';exit();
            }
            /** OMS订单状态和Order订单状态映射 **/
            $order_status = OrderLib::mappingOrderStatusByOMSStatus($params['Status']);
            \think\Log::pathlog('APIRequest',$order_status,'mall_order_success_change_order_status');
            if ($order_status != -1){
                $create_on = isset($params['ChangeOn'])?strtotime($params['ChangeOn']):time();
                $create_by = isset($params['ChangeBy'])?$params['ChangeBy']:'orderfrontendAPI';
                $queue_data = json_encode(
                    [
                        'order_number'=>$params['OrderNumber'],
                        'order_status'=>$order_status,
                        /*'create_on'=>strtotime($params['ChangeOn']),
                        'create_by'=>$params['ChangeBy']*/
                        'create_on'=>$create_on,
                        'create_by'=>$create_by
                    ]
                );
                //写入队列，之后返回处理成功
                $redis_cluster = new RedisClusterBase();
                $res = $redis_cluster->lPush(
                    'mall_order_success_change_order_status',
                    $queue_data
                );
                \think\Log::pathlog('APIRequest','$redis_cluster:'.$res,'mall_order_success_change_order_status');
                if ($res){
                    //return apiReturn(['code'=>200, 'msg'=>'Success']);
                    echo '<Response>1</Response>';exit();
                }else{
                    //eturn apiReturn(['code'=>2002, 'msg'=>'Failure']);
                    echo '<Response>0</Response>';exit();
                }
                Log::record('同步OMS订单至Order，data-:'.$queue_data.', $res：'.$res,'notice');
                /**
                 * 更改订单状态
                 */
                /*$model = new OrderModel();
                $where = [
                    'order_number'=>$params['order_number']
                ];
                $up_data = [
                    'order_status'=>$order_status,
                ];
                //【消费队列处理】是否根据状态判断同步更新：完成时间、修改者、修改时间、支付时间、发货时间、发货完成时间
                if ($model->updateOrderByWhere($where, $up_data)){
                    return apiReturn(['code'=>200, 'msg'=>'Success']);
                }else{
                    return apiReturn(['code'=>2002, 'msg'=>'Failure']);
                }*/
            }else{
                Log::record('同步OMS订单至Order，状态更新无效。','notice');
                //return apiReturn(['code'=>200, 'msg'=>'Success']);
                echo '<Response>1</Response>';exit();
            }
        }catch (\Exception $e){
            //return apiReturn(['code'=>2003, 'msg'=>'Internal anomaly, '.$e->getMessage()]);
            Log::record('同步OMS订单至Order，异常：'.$e->getMessage(),'notice');
            echo '<Response>0</Response>';exit();
        }
    }

    /**
     * 同步交易明细至Order
     * [
            'order_number'=>'',
            'notification_id'=>0,
            'txn_data'=>'',
            'parent_txn_ref'=>'',
            'amount'=>0.00,
            'currency_code'=>'',
            'txn_type'=>'',
            'notes'=>'',
            'third_party_txn_id'=>'',
            'third_party_parent_txn_id'=>'',
            'third_party_method'=>'',
            'txn_result'=>'',
            'risk_control_status'=>'',
            'payment_method'=>'',
            'payment_txn_id'=>'',
            'payment_parent_txn_id'=>'',
            'refunding_amount'=>0.00,
        ]
     * @return mixed
     */
    public function salesDetail(){
        try{
            $params = json_decode(file_get_contents("php://input"), true);
            /** 参数校验 **/
            $validate = $this->validate($params,(new SynOmsParams())->salesDetailRules());
            if(true !== $validate){
                return apiReturn(['code'=>2001, 'msg'=>$validate]);
            }
            /** 记录交易明细数据 **/
            $model = new OrderModel();
            //拼装$data
            $order_id = 0;
            $order_data = $model->geOrderDataByWhere(['order_number'=>$params['order_number']]);
            if (!empty($order_data)){
                $order_id = isset($order_data[0]['order_id'])?$order_data[0]['order_id']:0;
            }
            $params['order_id'] = $order_id;
            $params['create_on'] = time();
            $res = $model->insertSalesTXN($params);
            if (true === $res){
                $this->refundResultHandle($params);
                return apiReturn(['code'=>200, 'msg'=>'Success']);
            }else{
                Log::record('salesDetail-error,params:'.json_encode($params).', res:'.$res);
                return apiReturn(['code'=>2002, 'msg'=>'Failure '.$res]);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>2003, 'msg'=>'Internal anomaly, '.$e->getMessage()]);
        }
    }

    /**
     * 退款&其他订单操作结果处理
     * @param $data
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    private function refundResultHandle($data){
//    public function refundResultHandle(){
        /*$data = [
            'order_number'=>'',
            'notification_id'=>0,
            'txn_data'=>'',
            'parent_txn_ref'=>'',
            'amount'=>0.00,
            'currency_code'=>'',
            'txn_type'=>'Refund',
            'notes'=>'',
            'third_party_txn_id'=>'',
            'third_party_parent_txn_id'=>'',
            'third_party_method'=>'',
            'txn_result'=>'',
            'risk_control_status'=>'',
            'payment_method'=>'',
            'payment_txn_id'=>'',
            'payment_parent_txn_id'=>'',
            'refunding_amount'=>0.00,
        ];*/
        //*** 类型&&结果 判断统一转为小写 ***
        $order_number = $data['order_number'];
        $txn_type = strtolower($data['txn_type']);
        $txn_result = strtolower($data['txn_result']);
        $txn_result_success = [strtolower('Success'),strtolower('SuccessWithWarning')];
        //退款类型
        $refund_type_arr = [strtolower('Reversed'), strtolower('RefundToSC'), strtolower('Refund'), strtolower('RemoteRefund')];
        //取消退款类型 TODO
        $cancel_refund_type_arr = [strtolower('CancelReversal'),strtolower('CancelRefundToSC')];
        //加锁类型
        $add_lock_type_arr = [strtolower('TemporaryHold'), strtolower('Reversed')];
        //解锁类型
        $cancel_lock_type_arr = [strtolower('CancelTemporaryHold'), strtolower('CancelReversal')];
        $order_info = $this->order_model->getOrderInfoByOrderNumber($order_number,'customer_id, affiliate');
        /** 订单退款处理 start **/
        if (in_array($txn_type, $refund_type_arr)){
            //1.如果是退款的数据，需要对相关数据进行处理
            if (in_array($txn_result, $txn_result_success)){
                //2.退款成功：1).修改订单正在退款金额，已退款金额，实收金额 2).返回赠送积分、佣金
                // 1).修改订单正在退款金额，已退款金额，实收金额
                $res = $this->order_model->handleOrderInfoForRefund($order_number, 1);
                // 2).返回赠送积分、佣金 TODO
                if ($res){
                    $p_res = $this->base_api->cancelOrderDecPoints(['CustomerID'=>$order_info['customer_id'], 'OrderNumber'=>$order_number]);
                    if (empty($p_res) || !isset($res['code']) || $res['code'] != 200){
                        Log::record('退款成功-扣减积分失败$order_number：'.$order_number.'->$p_res'.json_encode($p_res),'notice');
                    }
                    //20181029 如果是affiliate订单，需要调用“affiliate订单取消扣减推荐积分”接口
                    //20190114 kaiwen确定不用调
                    /*if (
                        isset($order_info['affiliate'])
                        && !empty($order_info['affiliate'])
                    ){
                        $_affiliate_params = [
                            'CustomerID'=>$order_info['customer_id'],
                            'OrderNumber'=>$order_number
                        ];
                        $affiliate_res = $this->base_api->CancelOrderDecReferralPoints($_affiliate_params);
                        if (empty($affiliate_res) || !isset($affiliate_res['code']) || $affiliate_res['code'] != 200){
                            Log::record('退款成功-affiliate订单取消扣减推荐积分失败$order_number：'.$order_number.'->params:'.json_encode($_affiliate_params).',$affiliate_res'.json_encode($affiliate_res),'notice');
                        }
                    }*/
                }else{
                    Log::record('退款成功-处理失败$order_number：'.$order_number,'notice');
                }
            }else{
                //3.退款失败：1).将订单状态修改为200（状态回退） 2).将退款中金额修改为0 3).已调的取消接口要回退？？？TODO
                $res = $this->order_model->handleOrderInfoForRefund($order_number, 2);
                if (!$res){
                    Log::record('退款失败-处理失败$order_number：'.$order_number,'notice');
                }
            }
        }
        /** 订单退款处理 end **/

        /** 加锁处理 start **/
        if (in_array($txn_type, $add_lock_type_arr)){
            if (in_array($txn_result, $txn_result_success)){
                $res = $this->order_model->updateOrderByWhere(['order_number'=>$order_number], ['lock_status'=>73]);
                if (!$res){
                    Log::record('加锁成功-处理失败$order_number：'.$order_number,'notice');
                }
            }
        }
        /** 加锁处理 end **/

        /** 解锁处理 start **/
        if (in_array($txn_type, $cancel_lock_type_arr)){
            if (in_array($txn_result, $txn_result_success)){
                $res = $this->order_model->updateOrderByWhere(['order_number'=>$order_number], ['lock_status'=>60]);
                if (!$res){
                    Log::record('解锁成功-处理失败$order_number：'.$order_number,'notice');
                }
            }
        }
        /** 解锁处理 end **/
    }

    /**
     * 获取已发货的产品销量
     * @return mixed
     */
    public function getProductSalesForFulfillment(){
        try{
            Log::record('getProductSalesForFulfillment:php://input:'.print_r(file_get_contents("php://input"), true),'notice');
            Log::record('getProductSalesForFulfillment:input:'.print_r(input(), true),'notice');
            Log::record('getProductSalesForFulfillment:request-post:'.print_r(request()->post(), true),'notice');
            $params = json_decode(file_get_contents("php://input"), true);
            //参数校验
            $validate = $this->validate($params,(new SynOmsParams())->getProductSalesRules());
            if(true !== $validate){
                return apiReturn(['code'=>2001, 'msg'=>$validate]);
            }
            /** 获取数据 **/
            $model = new OrderModel();
            $params['start_date'] = strtotime($params['start_date']);
            $params['end_date'] = strtotime($params['end_date']);
            $data = $model->getProductSalesWhenFulfillment($params);
            /** 拼装$data start **/
            //获取产品ID
            $product_id_arr = [];
            foreach ($data as $info){
                $product_id_arr[] = $info['product_id'];
            }
            $product_id_arr = array_unique($product_id_arr);
            //获取产品对应销量
            $rtn = [];
            foreach ($product_id_arr as $p){
                $tem = [];
                $sales_num = 0;
                foreach ($data as $val){
                    if ($val['product_id'] == $p){
                        $sales_num += $val['product_nums'];
                    }
                }
                $tem['spu_id'] = $p;
                $tem['sales_num'] = $sales_num;
                $rtn[] = $tem;
            }
            /** 拼装$data end **/
            if (!empty($rtn)){
                return apiReturn(['code'=>200, 'data'=>$rtn]);
            }else{
                return apiReturn(['code'=>1006]);
            }
        }catch (\Exception $e){
            return apiReturn(['code'=>2003, 'msg'=>'Internal anomaly, '.$e->getMessage()]);
        }
    }

}
