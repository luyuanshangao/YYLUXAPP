<?php
namespace app\common\helpers;

/**
 * 订单公用基本类
 * Class OrderLib
 * @author tinghu.liu 2018/06/14
 * @package app\common\helpers
 */
class OrderLib
{
    /**
     * OMS订单状态和order订单状态映射
     * @param $oms_order_status OMS订单状态
     * @return int
     * =============== 新平台订单状态配置：========================
     * ['code'=>100,'name'=>'等待付款','en_name'=>'Pending Payment'],
    ['code'=>101,'name'=>'进入事前风控','en_name'=>'Before Control'],
    ['code'=>102,'name'=>'通过事前风控','en_name'=>'Complete Control'],
    ['code'=>103,'name'=>'第三方支付验证','en_name'=>'Payment validation'],
    ['code'=>104,'name'=>'第三方支付验证通过','en_name'=>'Pending Payment'],
    ['code'=>105,'name'=>'进入事后风控','en_name'=>'After Control'],
    ['code'=>106,'name'=>'通过事后风控','en_name'=>'End Control'],
    ['code'=>107,'name'=>'进入人工风控','en_name'=>'Artificial Control'],
    ['code'=>108,'name'=>'通过人工风控','en_name'=>'Artificial through'],
    ['code'=>109,'name'=>'进入付款页面失败','en_name'=>'Payment Confirmed'],
    ['code'=>110,'name'=>'涉嫌欺诈','en_name'=>'FraudSuspected'],
    ['code'=>111,'name'=>'定性欺诈','en_name'=>'FraudAdjusted'],
    ['code'=>200,'name'=>'付款完成','en_name'=>'Payment Confirmed'],
    ['code'=>300,'name'=>'付款处理中','en_name'=>'Payment Processing'],
    ['code'=>400,'name'=>'待发货','en_name'=>'Shipment Processing'],//erp
    ['code'=>401,'name'=>'部分发货','en_name'=>'Seller Partial'],//erp
    ['code'=>402,'name'=>'已发货','en_name'=>'Seller Shipped'],//erp
    ['code'=>403,'name'=>'已收货','en_name'=>'Full Received'],//erp
    ['code'=>404,'name'=>'入库','en_name'=>'In Storage'],//erp
    ['code'=>405,'name'=>'发货超期','en_name'=>'Over Time'],
    ['code'=>406,'name'=>'订单超期','en_name'=>'Order Delay'],
    ['code'=>500,'name'=>'部分发货','en_name'=>'Partial Shipped'],
    ['code'=>600,'name'=>'已发货','en_name'=>'Full Shipped'],
    ['code'=>700,'name'=>'待收货','en_name'=>'Unreceived'],
    ['code'=>800,'name'=>'已到货','en_name'=>'Order Received'],
    ['code'=>900,'name'=>'已完成','en_name'=>'Completed'],
    ['code'=>901,'name'=>'申请退货','en_name'=>'Application back'],
    ['code'=>902,'name'=>'退货中','en_name'=>'In return'],
    ['code'=>903,'name'=>'确认收到退货','en_name'=>'Received return'],
    ['code'=>904,'name'=>'退货单完成','en_name'=>'Complete Return'],
    ['code'=>905,'name'=>'取消退货申请','en_name'=>'Cancel return'],
    ['code'=>906,'name'=>'申请换货','en_name'=>'Application exchange'],//换货
    ['code'=>907,'name'=>'退货中','en_name'=>'In Exchange'],//换货
    ['code'=>908,'name'=>'确认收到退货','en_name'=>'Received exchange'],//换货
    ['code'=>909,'name'=>'换货单发货','en_name'=>'New Order'],//换货
    ['code'=>910,'name'=>'取消换货申请','en_name'=>'Cancel Exchange'],//换货
    ['code'=>1000,'name'=>'待评价','en_name'=>'Not evaluated'],
    ['code'=>1100,'name'=>'已评价','en_name'=>'Complete evaluate'],
    ['code'=>1200,'name'=>'待追评','en_name'=>'Keep evaluate'],
    ['code'=>1300,'name'=>'已追评','en_name'=>'Keep evaluate'],
    ['code'=>1400,'name'=>'订单取消','en_name'=>'Cancelled'],
    ['code'=>1500,'name'=>'等待','en_name'=>'Hold'],
    ['code'=>1600,'name'=>'索赔','en_name'=>'Claim'],
    ['code'=>1700,'name'=>'纠纷中','en_name'=>'Disputes'],
    ['code'=>1800,'name'=>'争端订单','en_name'=>'Conflict'],
    ['code'=>1900,'name'=>'已关闭','en_name'=>'Closed'],
     *
     * =============== OMS订单状态枚举 ===========================
     *
     * /// <summary>
    /// 等待付款
    /// </summary>

    PendingPayment = 0,

    /// <summary>
    /// 部分付款
    /// </summary>

    PartialPayment = 1,

    /// <summary>
    /// 收到付款
    /// </summary>

    PaymentRecevied = 2,

    /// <summary>
    /// 付款确认
    /// </summary>

    PaymentConfirmed = 3,

    /// <summary>
    /// 订单收到，未处理
    /// </summary>

    OrderReceived = 30,

    /// <summary>
    /// 备货中
    /// </summary>

    BackOrder = 31,

    /// <summary>
    /// 发货处理中
    /// </summary>

    ShipmentProcessing = 32,

    /// <summary>
    /// 部分发货
    /// </summary>

    PartialShipped = 33,

    /// <summary>
    /// 已发货
    /// </summary>

    FullShipped = 34,

    /// <summary>
    /// 已完成
    /// </summary>

    Completed = 35,

    /// <summary>
    /// 已取消
    /// </summary>

    Cancelled = 36,

    /// <summary>
    /// 正常，未加锁
    /// </summary>

    Normal = 60,

    /// <summary>
    /// 客户正在修改，超时将自动解锁
    /// </summary>

    CustomerEditing = 61,

    /// <summary>
    /// 客服正在修改，超时将自动解锁
    /// </summary>

    CSEditing = 62,

    /// <summary>
    /// 正在联系客户，超时将自动解锁
    /// </summary>

    ContactingCustomer = 63,

    /// <summary>
    /// 正在生成发货批次，超时将自动解锁
    /// </summary>

    GeneratingShipment = 64,

    /// <summary>
    /// PayPal 纠纷事件，不超时，需手动解锁,纠纷事件,不区分PayPal或EGP,统一用 Dispute
    /// </summary>

    Dispute = 65,

    /// <summary>
    /// PayPal 索赔事件，不超时，需手动解锁,索赔事件,不区分PayPal或EGP,统一用 Claim
    /// </summary>

    Claim = 66,

    /// <summary>
    /// PayPal 退单事件，不超时，需手动解锁,退单事件,不区分PayPal或EGP,统一用 Chargeback
    /// </summary>

    Chargeback = 67,

    /// <summary>
    /// EGP 纠纷事件，不超时，需手动解锁
    /// </summary>
    [Obsolete("已废弃",false)]
    EGPRetrieval = 68,

    /// <summary>
    /// EGP 退单事件，不超时，需手动解锁
    /// </summary>
    [Obsolete("已废弃",false)]
    EGPChargeback = 69,

    /// <summary>
    /// 付款处理中
    /// </summary>

    PaymentProcessing = 70,

    /// <summary>
    /// 涉嫌欺诈
    /// </summary>

    FraudSuspected = 71,

    /// <summary>
    /// 定性欺诈
    /// </summary>

    FraudAdjusted = 72,

    /// <summary>
    /// 强制锁住，需手动解锁
    /// </summary>

    Hold = 73,
     */
    public static function mappingOrderStatusByOMSStatus($oms_order_status){
        $order_status = -1;
        //获取 CMS订单状态 和 新平台order订单状态 映射数据配置
        $cms_order_mapping_data = config('oms_order_mapping_data');
        foreach ($cms_order_mapping_data as $info){
            if ($info['oms_status'] == $oms_order_status){
                $order_status = $info['order_status'];
                break;
            }
        }
        return $order_status;
        /*
        $order_status = -1;
//        $order_status_arr = config('order_status');
        switch ($oms_order_status){
            case 0: //等待付款
                $order_status = 100;
                break;
            case 1: //部分付款  --新系统不需要
                //$order_status = -1;
                break;
            case 2: //收到付款  付款完成 200 ??  找刘迪
                $order_status = 105;
                break;
            case 3: //付款确认  找刘迪
                $order_status = 200;
                break;
            case 30: //订单收到，未处理   找刘迪
                $order_status = 100;
                break;
            case 31: //备货中  --新系统不需要
                //$order_status = -1;
                break;
            case 32: //发货处理中  --新系统的400
                $order_status = 400;
                break;
            case 33: //部分发货
                $order_status = 500;
                break;
            case 34: //已发货
                $order_status = 600;
                break;
            case 35: //已完成
                $order_status = 900;
                break;
            case 36: //已取消
                $order_status = 1400;
                break;
            case 60: //正常，未加锁 --新系统不需要，有了专门的字段标识
                //$order_status = -1;
                break;
            case 61: //客户正在修改，超时将自动解锁 --新系统不需要
                //$order_status = -1;
                break;
            case 62: //客服正在修改，超时将自动解锁 --新系统不需要
                //$order_status = -1;
                break;
            case 63: //正在联系客户，超时将自动解锁 --新系统不需要
                //$order_status = -1;
                break;
            case 64: //正在生成发货批次，超时将自动解锁  --新系统不需要
                //$order_status = -1;
                break;
            case 65: //PayPal 纠纷事件，不超时，需手动解锁,纠纷事件,不区分PayPal或EGP,统一用 Dispute --新系统不需要
                //$order_status = -1;
                break;
            case 66: //PayPal 索赔事件，不超时，需手动解锁,索赔事件,不区分PayPal或EGP,统一用 Claim --新系统不需要
                //$order_status = -1;
                break;
            case 67: //PayPal 退单事件，不超时，需手动解锁,退单事件,不区分PayPal或EGP,统一用 Chargeback --新系统不需要
                //$order_status = -1;
                break;
            case 68: //EGP 纠纷事件，不超时，需手动解锁[Obsolete("已废弃",false)] --新系统不需要
                //$order_status = -1;
                break;
            case 69: //EGP 退单事件，不超时，需手动解锁 --新系统不需要
                //$order_status = -1;
                break;
            case 70: //付款处理中
                $order_status = 300;
                break;
            case 71: //涉嫌欺诈
                $order_status = 110;
                break;
            case 72: //定性欺诈
                $order_status = 111;
                break;
            case 73: //强制锁住，需手动解锁--新系统不需要
                //$order_status = -1;
                break;
        }
        return $order_status;*/
    }
}