<?php
namespace app\share\controller;

use app\common\controller\Base;
use app\common\helpers\CommonLib;
use app\share\model\DxRegion;
use app\share\model\OrderModel;
use think\Cache;

/**
 * 通用处理类
 * tinghu.liu 20190911
 */
class Handler extends Base
{
    public $orderModel;
    public function __construct()
    {
        parent::__construct();
        $this->orderModel = new OrderModel();
    }


    /**
     * 旧订单数据统一生成“支付Token”，为了兼容新三步走的支付流程，让用户repay时能正常使用新流程进行支付
     * api.localhost.com/share/Hanlder/payToken?start_date=2019-09-01 00:00:00&end_date=2019-10-01 00:00:00
     */
    public function payToken(){
        $start_date = input('start_date', '2019-09-01 00:00:00');
        $end_date = input('end_date', '2019-10-01 00:00:00');
        $start_time = strtotime($start_date);
        $end_time = strtotime($end_date);

        $create_on = time();
        $add_time = date('Y-m-d H:i:s', $create_on);

        $order_data = $this->orderModel->getOrderData(['start_time'=>$start_time,'end_time'=>$end_time]);

        foreach ($order_data as $k=>$v){
            if ($v['order_number'] == $v['order_master_number'] || $v['order_master_number'] == 0){
                $order_master_number = $v['order_number'];

                pr($order_master_number);

                $verify_data = $this->orderModel->getPayTypeDataByOrderMasterNumber($order_master_number);
                if (empty($verify_data)){
                    $pay_token = CommonLib::generatePayToken($order_master_number);
                    //支付Token数据
                    $_pay_token_params['order_master_number'] = $order_master_number;
                    $_pay_token_params['pay_token'] = $pay_token;
                    $_pay_token_params['create_on'] = $create_on;
                    $_pay_token_params['add_time'] = $add_time;
                    $res = $this->orderModel->insertPayTypeData($_pay_token_params);

                    pr('参数：');
                    pr($_pay_token_params);
                    pr('返回：');
                    pr($res);

                }else{
                    pr('主订单号（'.$order_master_number.'）已存在PayToken');
                }
                pr('================================================');
            }
        }
    }

}
