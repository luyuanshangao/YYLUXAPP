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
            $request_data = base64_encode(json_encode($params));
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
            $res = $model->addTrackingNumberByAllData($params,$request_data);
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
     * 先根据“package_number”判断是否有数据，若有则删除记录（包含item表数据），之后再同步数据
     *
     * （1）、type = 1 （正常同步追踪号（默认）时：
     * [
        'sign'=>'',
     *  'type'=>1, //类型：1-正常同步追踪号（默认），2-换单，3-正常同步追踪号（一个订单多个追踪号）
        'order_number'=>'',
     *  'is_delete'=>0, //是否删除其他的追踪号信息，只保存传的这个信息。0-不删除（默认），1-删除
     *
        'tracking_number'=>'',
        'shipping_channel_name'=>'',
     *
     *  'weight'=>'', //非必传
        'shipping_fee'=>'',//非必传
        'triff_fee'=>'',//非必传
        'service_per_charge'=>'',//非必传
        'service_charge'=>'',//非必传
        'total_amount'=>'',//非必传
        'pic_path_when_check'=>'',//非必传
        'pic_path_when_weigh'=>'',//非必传
        'package_number'=>'',//非必传
     *
        'item_info'=>[
                [
                    'sku_id'=>0,
                    'sku_qty'=>0
                ],
            ],
     *
        ],
     *
     * （2）、type = 2 （换单）时：
     * [
        'sign'=>'',
     *  'type'=>2, //类型：1-正常同步追踪号（默认），2-换单，3-正常同步追踪号（一个订单多个追踪号）
        'order_number'=>'',
        'tracking_number'=>'',
        'shipping_channel_name'=>'',
     *  'old_tracking_number'=>'', //换单时才会存在，为换单之前的追踪号
        ],
     *
     * （3）、type = 3 正常同步追踪号（一个订单多个追踪号）时：
     * [
            'sign'=>'',
     *      'type'=>3, //类型：1-正常同步追踪号（默认），2-换单，3-正常同步追踪号（一个订单多个追踪号）
            'order_number'=>'',
            'is_delete'=>0, //是否删除其他的追踪号信息，只保存传的这个信息。0-不删除（默认），1-删除
     *
     *      'data'=>[ //追踪号数据
     *          {
     *              'tracking_number'=>'',
     *              'shipping_channel_name'=>'',
     *
     *              'weight'=>'', //非必传
     *              'shipping_fee'=>'',//非必传
     *              'triff_fee'=>'',//非必传
     *              'service_per_charge'=>'',//非必传
     *              'service_charge'=>'',//非必传
     *              'total_amount'=>'',//非必传
     *              'pic_path_when_check'=>'',//非必传
     *              'pic_path_when_weigh'=>'',//非必传
     *              'package_number'=>'',//非必传
     *
     *              'item_info'=>[
                        {
                            'sku_id'=>0,
                            'sku_qty'=>0
                        },
                    ],
     *          },
     *      ]
        ],
     *
     * TrackingNumber/syncPost
     *
     */
    public function syncPost(){
        try{
            $params = json_decode(file_get_contents("php://input"), true);
            $request_data = base64_encode(json_encode($params));
            Log::record('syncPost同步追踪号到订单数据库，接收的数据：php://input'.json_encode($params));
            Log::record('syncPost同步追踪号到订单数据库，接收的数据：post'.json_encode(request()->post()));
            Log::record('syncPost同步追踪号到订单数据库，接收的数据：input'.json_encode(input()));
            if (empty($params) || !is_array($params)){
                return apiReturn(['code'=>1003]);
            }
            //类型：1-正常同步追踪号（默认），2-换单，3-正常同步追踪号（一个订单多个追踪号）
            $type = (isset($params['type']) && !empty($params['type']))?$params['type']:1;
            $params['type'] = $type;
            /** 参数校验 start **/
            if ($type == 1){ //正常同步追踪号（默认）
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
            }elseif ($type == 2){ //换单
                $validate = $this->validate($params,(new TrackingNumberParams())->syncPostForChangeOfOrderRules());
                if(true !== $validate){
                    return apiReturn(['code'=>2001, 'msg'=>$validate]);
                }
            }elseif($type == 3){ //正常同步追踪号（一个订单多个追踪号）
                //基本参数判断
                $validate = $this->validate($params,(new TrackingNumberParams())->syncPostForMultiRules());
                if(true !== $validate){
                    return apiReturn(['code'=>2001, 'msg'=>$validate]);
                }
                foreach ($params['data'] as $info){
                    //data基本参数判断
                    $validate_info = $this->validate($info,(new TrackingNumberParams())->syncPostItemForMultiRules());
                    if(true !== $validate_info){
                        return apiReturn(['code'=>2001, 'msg'=>$validate_info]);
                    }
                    //sku参数判断
                    foreach ($info['item_info'] as $i_info){
                        $validate_i = $this->validate($i_info,(new TrackingNumberParams())->syncPostItemRules());
                        if(true !== $validate_i){
                            return apiReturn(['code'=>2001, 'msg'=>$validate_i]);
                        }
                    }
                }
            }else{
                return apiReturn(['code'=>1004, 'msg'=>'请求错误']);
            }
            /** 参数校验 end **/
            /**
             * 因为有NOCNOC拆单情况，所以这里要判断是不是NOCNOC拆单后的订单号，如果是，需要转换为源订单号
             * 正常订单号 190412100138511822，长度为 18 位
             * NOCNOC拆单后是在正常订单好加 01（nocnoc订单） 或 02（非nocnoc订单），一共20位
             * tinghu.liu 20190415
             */
            $order_number = $params['order_number'];
            if (strlen($order_number) == 20){
                $order_number = substr($order_number, 0, 18);
                $params['order_number'] = $order_number;
            }
            $model = new OrderModel();
            $order_info = $model->getOrderInfoByOrderNumber($order_number, 'store_id,pay_time');
            if (empty($order_info)){
                return apiReturn(['code'=>1004, 'msg'=>'订单号有误，不存在的订单信息']);
            }
            //签名校验
            $sign_flag = 'syncPostTrackingNumber'.$order_info['store_id'].date('Y-m-d');
            if ($params['sign'] !== $this->makeSign($sign_flag)){
                return apiReturn(['code'=>1004, 'msg'=>'没有权限']);
            }
            $time = time();
            //最大填单时间校验，超过则不处理
            //去掉最大填单时间校验 BY tinghu.liu IN 20190215
            /*$max_delivery_time_config = config('max_delivery_time');
            if (
                $time > ($order_info['pay_time'] + $max_delivery_time_config)
            ){
                return apiReturn(['code'=>1005, 'msg'=>'已超过最大填单时间']);
            }*/
            //来源类型：1-OMS（默认），2-ERP
            $params['from_type'] = 2;
            $params['add_time'] = $time;
            //是否删除其他的追踪号信息，只保存传的这个信息。0-不删除（默认），1-删除
            $params['is_delete'] = isset($params['is_delete'])?$params['is_delete']:0;

            //同步追踪号
            $res = $model->addTrackingNumberByAllData($params, $request_data);
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

}
