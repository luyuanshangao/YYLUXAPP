<?php
namespace app\orderfrontend\controller;

use app\common\params\orderfrontend\TrackingNumberParams;
use app\common\services\CommonService;
use app\demo\controller\Auth;
use app\orderfrontend\model\OrderModel;
use think\Log;

/**
 * 追踪号接口类
 * @author tinghu.liu 2018/06/08
 * @package app\orderfrontend\controller
 */
class TrackingNumber extends Auth
{
    /**
     * 接收追踪号数据，同步到订单数据库【与OMS同步专用】
     * [
        'order_number'=>'',
        'weight'=>'',
        'shipping_fee'=>0.00,
        'triff_fee'=>0.00,
        'service_per_charge'=>0.00,
        'service_charge'=>0.00,
        'total_amount'=>0.00,
        'pic_path_when_check'=>'',
        'pic_path_when_weigh'=>'',
        'package_number'=>'',
        'tracking_number'=>'',
        'item_info'=>[
                [
                    'sku_id'=>0,
                    'sku_qty'=>0
                ],
            ]
        ],
     * 'from_type'=>1, // 来源类型：1-OMS（默认），2-ERP
     * 'order_id'=>125 //订单ID（ERP时需要传）
     * 先根据“package_number”判断是否有数据，若有则删除记录（包含item表数据），之后再同步数据
     */
    public function post(){
        try{
            $params = json_decode(file_get_contents("php://input"), true);
            Log::record('同步追踪号到订单数据库，接收的数据：php://input'.json_encode($params));
            Log::record('同步追踪号到订单数据库，接收的数据：post'.json_encode(request()->post()));
            Log::record('同步追踪号到订单数据库，接收的数据：input'.json_encode(input()));
            if (empty($params) || !is_array($params)){
                return apiReturn(['code'=>1003]);
            }
            /** 参数校验 **/
            $time = time();
            $validate = $this->validate($params,(new TrackingNumberParams())->postRules());
            if(true !== $validate){
                return apiReturn(['code'=>2001, 'msg'=>$validate]);
            }
            foreach ($params['item_info'] as $item_info){
                $validate_item = $this->validate($item_info,(new TrackingNumberParams())->postItemRules());
                if(true !== $validate_item){
                    return apiReturn(['code'=>2001, 'msg'=>$validate_item]);
                }
            }
            $params['add_time'] = $time;
            //同步追踪号
            $model = new OrderModel();
            $res = $model->addTrackingNumberByAllData($params);
            if (true === $res){
                return apiReturn(['code'=>200, 'msg'=>'Success']);
            }else{
                Log::record('同步追踪号到订单数据库，接收的数据：失败：'.$res);
                return apiReturn(['code'=>2002, 'msg'=>'Failure '.$res]);
            }
        }catch (\Exception $e){
            Log::record('同步追踪号到订单数据库，接收的数据：失败：'.$e->getMessage());
            return apiReturn(['code'=>2003, 'msg'=>'Internal anomaly, '.$e->getMessage()]);
        }
    }

    /**
     * 接收追踪号数据，同步到订单数据库【接收ERP追踪号数据专用】【外网】
     * [
        'sign'=>'',
        'order_number'=>'',
        'tracking_number'=>'',
        'shipping_channel_name'=>'',
        'item_info'=>[
                [
                    'sku_id'=>0,
                    'sku_qty'=>0
                ],
            ]
        ],
     * 先根据“package_number”判断是否有数据，若有则删除记录（包含item表数据），之后再同步数据
     */
    public function syncPost(){
        try{
            $params = json_decode(file_get_contents("php://input"), true);
            Log::record('syncPost同步追踪号到订单数据库，接收的数据：php://input'.json_encode($params));
            Log::record('syncPost同步追踪号到订单数据库，接收的数据：post'.json_encode(request()->post()));
            Log::record('syncPost同步追踪号到订单数据库，接收的数据：input'.json_encode(input()));
            if (empty($params) || !is_array($params)){
                return apiReturn(['code'=>1003]);
            }
            /** 参数校验 **/
            $validate = $this->validate($params,(new TrackingNumberParams())->syncPostRules());
            if(true !== $validate){
                return apiReturn(['code'=>2001, 'msg'=>$validate]);
            }
            foreach ($params['item_info'] as $item_info){
                $validate_item = $this->validate($item_info,(new TrackingNumberParams())->syncPostItemRules());
                if(true !== $validate_item){
                    return apiReturn(['code'=>2001, 'msg'=>$validate_item]);
                }
            }
            $model = new OrderModel();
            $order_info = $model->getOrderInfoByOrderNumber($params['order_number'], 'store_id,pay_time');
            //签名校验
            $sign_flag = 'syncPostTrackingNumber'.$order_info['store_id'].date('Y-m-d');
            if ($params['sign'] !== $this->makeSign($sign_flag)){
                return apiReturn(['code'=>1004, 'msg'=>'没有权限']);
            }
            $time = time();
            //最大填单时间校验，超过则不处理
            $max_delivery_time_config = config('max_delivery_time');
            if (
                $time > ($order_info['pay_time'] + $max_delivery_time_config)
            ){
                return apiReturn(['code'=>1005, 'msg'=>'已超过最大填单时间']);
            }
            //来源类型：1-OMS（默认），2-ERP
            $params['from_type'] = 2;
            $params['add_time'] = $time;
            //同步追踪号
            $res = $model->addTrackingNumberByAllData($params);
            if (true === $res){
                return apiReturn(['code'=>200, 'msg'=>'Success']);
            }else{
                Log::record('syncPost同步追踪号到订单数据库，接收的数据：失败：'.$res);
                return apiReturn(['code'=>2002, 'msg'=>'Failure '.$res]);
            }
        }catch (\Exception $e){
            Log::record('syncPost同步追踪号到订单数据库，接收的数据：失败：'.$e->getMessage());
            return apiReturn(['code'=>2003, 'msg'=>'Internal anomaly, '.$e->getMessage()]);
        }
    }

    /**
     * 测试使用，不用提交
     * @return \think\response\Json
     */
    public function test(){
        $store_id = input('store_id', 333);
        $sign_flag = 'syncPostTrackingNumber'.$store_id.date('Y-m-d');
//        $sign_flag = 'syncPostTrackingNumber3332018-12-18';
        $sign = $this->makeSign($sign_flag);
        return json([
            'access_token'=>$this->makeSign(),
            'sign'=>$sign
        ]);
    }

}
