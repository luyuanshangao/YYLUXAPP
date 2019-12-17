<?php
namespace app\orderbackend\model;

use app\admin\dxcommon\BaseApi;
use app\common\helpers\CommonLib;
use think\Cache;
use think\Exception;
use think\Log;
use think\Model;
use think\Db;

/**
 * 订单模型
 * Class OrderModel
 * @author tinghu.liu 2018/4/23
 * @package app\orderFront\model
 */
class OrderModel extends Model{

    /**
     * 数据库连接对象
     * @var \think\db\Connection
     */
	private $db;
    /**
     * 订单主表
     * @var string
     */
	private $order = "dx_sales_order";
    /**
     * 订单子表
     * @var string
     */
	private $order_item = "dx_sales_order_item";
    /**
     * dx_sales_order_message表
     * @var string
     */
	private $order_message = "dx_sales_order_message";
    /**
     * 订单价格的更改记录表
     * @var string
     */
	private $order_price_change = "dx_order_price_change";
    /**
     * 订单邮寄地址记录表
     * @var string
     */
	private $order_shipping_address = "dx_order_shipping_address";
    /**
     * 订单退款退货换货表（售后单主表）
     * @var string
     */
	private $order_after_sale_apply = "dx_order_after_sale_apply";
    /**
     * 追踪号信息表
     * @var string
     */
	private $order_package = "dx_order_package";
    /**
     * 追踪号信息表
     * @var string
     */
	private $order_package_item = "dx_order_package_item";
    /**
     * 订单状态日志表
     * @var string
     */
	private $order_status_change = "dx_sales_order_status_change";
    /**
     * 交易明细表表
     * @var string
     */
	private $order_sales_txn = "dx_sales_txn";
    /**
     * 退款操作明细表表
     * @var string
     */
	private $order_sales_order_refund_operation = "dx_sales_order_refund_operation";

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_order');
    }

    /**
     * 获取订单数据【分页】
     * @param array $params 条件
     * @return array
     */
    public function getOrderDataForPage(array $params){
        $query = $this->db->table($this->order)->alias('o');
        $join = [
            [$this->order_item.' oi','o.order_id=oi.order_id','LEFT'],
            [$this->order_message.' om','o.order_id=om.order_id','LEFT'],
        ];
        $query->join($join);
        $query->where('o.delete_time', '=', 0);
        //去掉主单号数据，只显示子订单数据
        //$query->where('o.order_master_number', '<>', 0);
        $query->where('o.order_type', 'in', [0, 2, 3]);
        //商家ID
        if (isset($params['store_id']) && !empty($params['store_id'])){
            $query->where('o.store_id', '=', $params['store_id']);
        }
        //产品名称
        if (isset($params['product_name']) && !empty($params['product_name'])){
            $query->where('oi.product_name', 'LIKE', $params['product_name'].'%');
        }
        if (isset($params['product_id']) && !empty($params['product_id'])){
            $query->where('oi.product_id', '=', $params['product_id']);
        }
        //订单号
        if (isset($params['order_number']) && !empty($params['order_number'])){
            $params['order_number'] = str_replace('，',',', $params['order_number']);
            $order_number_arr = explode(',', $params['order_number']);
            $query->where('o.order_number', 'in', $order_number_arr);
        }
        //搜索订单创建时间
        if (
            isset($params['create_on_start']) && !empty($params['create_on_start'])
            && isset($params['create_on_end']) && !empty($params['create_on_end'])
        ){
            $query->where('o.create_on','>=', $params['create_on_start']);
            $query->where('o.create_on','<=', $params['create_on_end']);
        }
        //买家名称
        if (isset($params['customer_name']) && !empty($params['customer_name'])){
            $query->where('o.customer_name', '=', $params['customer_name']);
        }
        if(isset($params['customer_id']) && !empty($params['customer_id'])){
            $query->whereOr('o.customer_id', '=', $params['customer_id']);
        }
        //订单状态
        if (isset($params['order_status']) && !empty($params['order_status'])){
            $query->where('o.order_status', '=', $params['order_status']);
        }
        //订单留言状态
        if (isset($params['unread']) && !empty($params['unread']) && $params['unread'] == 1){
            $query->where("om.message_type","=",2);
            $query->where('om.statused', '=', -1);
        }
        /*是否已回复*/
        if(isset($params['is_reply']) && !empty($params['is_reply'])){
            if($params['is_reply'] == 1){
                $query->where("om.message_type","=",2);
                $query->where("om.is_reply","=",1);
            }elseif($params['is_reply'] == 2){
                $query->where("om.message_type","=",2);
                $query->where("om.is_reply","=",2);
            }
        }
        $query->order('o.create_on', 'desc');
        //分页参数设置
        $page_size = isset($params['page_size']) && !empty($params['page_size']) ? (int)$params['page_size'] : 5;
        $page = isset($params['page']) && !empty($params['page']) ? (int)$params['page'] : 1;
        $path = isset($params['path']) && !empty($params['path']) ? $params['path'] : null;
        $response = $query->field('o.*')
            ->group('o.order_id')
            ->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path])
            ->each(function ($item, $key){
                $order_id = $item['order_id'];
                $order_status = $item['order_status'];
                //订单产品详情
                $item['item_data'] = $this->getOrderItemDataByWhere(['order_id'=>$order_id]);
                //订单未读消息
                $message_data = $this->getOrderMessageDataByWhere([
                    'order_id'=>$order_id,
                    'message_type'=>2
                ]);
                $item['message_unread_count'] = 0;
                $item['no_reply_count'] = 0;
                $item['message_count'] = 0;
                if(!empty($message_data)){
                    foreach ($message_data as $key=>$value){
                        $item['message_count'] ++;
                        if($value['is_reply'] == 1){
                            $item['no_reply_count']++;
                        }
                        if($value['statused'] == -1){
                            $item['message_unread_count']++;
                        }
                    }
                }
                $after_where['order_number'] = $item['order_number'];
                $item['is_after'] = $this->db->table($this->order_after_sale_apply)->where($after_where)->count();
                //追踪号数据
                $package_data = $this->getTrackingNumberByWhere(['order_number'=>$item['order_number']]);
                $tracking_number_arr = [];
                $tracking_number_arr_new = [];
                foreach ($package_data as $info){
                    $tracking_number_arr[$info['package_id']] = $info['tracking_number'];
                }
                $tracking_number_arr = array_unique($tracking_number_arr);
                foreach ($tracking_number_arr as $k=>$t_info){
                    $tracking_number_arr_new[$k]['tracking_number'] = $t_info;
                }
                $item['tracking_number_data'] = $tracking_number_arr_new;
                //$item['tracking_number_data'] = $this->getTrackingNumberByWhere(['order_number'=>$item['order_number']]);
                //订单主状态
                /*$config_status = config('order_status');
                $order_status_str = '';
                foreach ($config_status as $key=>$val) {
                    if ($val['code'] == $order_status){
                        $order_status_str = $val['name'];
                        break;
                    }
                }
                $item['order_status_str'] = $order_status_str;*/
                return $item;
            });
        $Page = $response->render();
        $data = $response->toArray();
        $data['Page'] = $Page;
        return $data;
    }

    /**
     * 根据条件获取追踪号数据
     * @param array $where 条件
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getTrackingNumberByWhere(array $where){
        $data = $this->db->table($this->order_package)->where($where)->select();
        foreach ($data as &$info){
            $info['item_data'] = $this->db->table($this->order_package_item)->where(['package_id'=>$info['package_id']])->select();
        }
        return $data;
    }

    /**
     * 获取订单详情
     * @param $order_id 订单ID
     * @param int $store_id seller ID
     * @return array|false|\PDOStatement|string|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getOrderInfoByOrderId($order_id, $store_id=0){
        //订单基本信息
        $query = $this->db->table($this->order);
        /*$order_info = $this->db->table($this->order)
            ->where(['order_id'=>$order_id])
            ->where(['delete_time'=>0])
            ->find();*/
        if ($store_id != 0 && !empty($store_id)){
            $query->where(['store_id'=>$store_id]);
        }
        $order_info = $query->where(['order_id'=>$order_id])
            ->where(['delete_time'=>0])
            ->find();
        if (empty($order_info)){
            return;
        }
        //订单产品信息
        $order_info['item_data'] = $this->getOrderItemDataByWhere(['order_id'=>$order_info['order_id']]);
        //订单留言信息
        $message_data = $this->getOrderMessageDataByWhere(['order_id'=>$order_info['order_id']]);
        foreach ($message_data as &$message){
            //地址处理
            $file_real_url = $message['file_url'];
            $message_real_name = '';
            if (!empty($message['file_url'])){
                $file_real_url = config('cdn_url').'/'.$message['file_url'];
            }
            $message['file_real_url'] = $file_real_url;
            //留言人姓名,message_type：1表示卖家留言或回复，2表示买家留言或回复
            if ($message['message_type'] == 1){
                $message_real_name = $order_info['store_name'];
            }elseif ($message['message_type'] == 2){
                $message_real_name = $order_info['customer_name'];
            }
            //$message['message_real_name'] = $message_real_name;
            $message['message_real_name'] = $message['user_name'];
        }
        $order_info['message_data'] = $message_data;
        //订单收货地址信息
        $shipping_info = [];
        $shipping_data = $this->getOrderShippingAddressDataByWhere(['order_id'=>$order_info['order_id']]);
        if (!empty($shipping_data)){
            $shipping_info = $shipping_data[0];
        }
        $after_where['order_number'] = $order_info['order_number'];
        $order_info['is_after'] = $this->db->table($this->order_after_sale_apply)->where($after_where)->count();
        $order_info['shipping_data'] = $shipping_info;
        //物流追踪号信息
        $order_info['package_data'] = $this->getTrackingNumberByWhere(['order_number'=>$order_info['order_number']]);

        // 重新划分订单状态（可通过判断状态区间来显示）以及相关倒计时提示功能。为了配合前端，1-买家下单、2-买家付款、3-卖家发货、4-订单完成
        $order_status = $order_info['order_status'];
        //状态
        $order_show_status = 1;
        //订单完成时，倒计时类型标识
        $count_down_finish_flag = 0;
        //倒计时秒数
        $count_down_time = 0;
        $time = time();
        if (
            ($order_status > 0 && $order_status < 200)
            || $order_status == 300
        ){
            $order_show_status = 1;
            //完成对本订单的付款剩余时间，倒计时从订单提交完成的时间起，开始倒数5天（可配置）的倒计时。该5天为工作日
            $order_pay_expire_time = config('order_pay_expire_day')*24*60*60;
            $create_on = $order_info['create_on'];
            $flag_time = ($create_on + $order_pay_expire_time) - $time;
            $count_down_time = $flag_time>0?$flag_time:0;
        }elseif (
            $order_status == 400
            || $order_status == 200
        ){
            $order_show_status = 2;
            //付款完成后开始可发货倒数时间，倒计时从付款完成的时间起，开始倒数5天（可配置）的倒计时。该5天为工作日
            $delivery_time_limit_time = config('delivery_time_limit_day')*24*60*60;
            $pay_time = $order_info['pay_time'];
            $flag_time = ($pay_time + $delivery_time_limit_time) - $time;
            $count_down_time = $flag_time>0?$flag_time:0;
        }elseif (
            $order_status > 400
            && $order_status <= 800
        ){
            $order_show_status = 3;
            //提醒买家确认收货的倒计时，倒计时从发货完成的时间起，开始倒数60天（可配置）的倒计时。该60天为工作日
            $buyer_confirm_time = config('buyer_confirm_take_delivery_limit_day')*24*60*60;
            $shipments_complete_time = $order_info['shipments_complete_time'];
            $flag_time = ($shipments_complete_time + $buyer_confirm_time) - $time;
            $count_down_time = $flag_time>0?$flag_time:0;
        }else{
            $order_show_status = 4;
            ///** 未评价 **/ 订单已完成，可及时对订单进行评价。买家还有 0天00小时00分钟00秒 //TODO进行评价。
            $order_status_info = $this->getOrderStatusInfoByWhere([
                'order_id'=>$order_info['order_id'],
                'order_status'=>$order_status
            ]);
            $order_status_time = $order_status_info['create_on'];//订单状态变化时间
            //订单交易完成后可评价限制（单位：天）
            $order_review_limit_day = config('order_review_limit_day')*24*60*60;
            //订单交易完成后可追加评价限制（单位：天）（未评价）
            $append_review_limit_day = config('append_review_limit_day')*24*60*60;
            //订单交易完成后可追加评价限制（单位：天）（已评价）
            $append_have_review_limit_day = config('append_have_review_limit_day')*24*60*60;
            switch ($order_status){
                case 900://已完成
                case 1000://待评价
                    //在评价期内，提醒买家评价。注释语：订单已完成，可及时对订单进行评价。买家还有（钟表控件）14天22小时15分钟31秒进行评价
                    if (
                        ($order_status_time + $order_review_limit_day) > $time
                    ){
                        $flag_time = ($order_status_time + $order_review_limit_day) - $time;
                        $count_down_time = $flag_time>0?$flag_time:0;
                        $count_down_finish_flag = 1;//待评价，但在评价期内的倒计时
                    }else{
                        //如果订单已过评价期，但未过追评期
                        if (
                            ($order_status_time + $append_review_limit_day) > $time
                        ){
                            $flag_time = ($order_status_time + $append_review_limit_day) - $time;
                            $count_down_time = $flag_time>0?$flag_time:0;
                            $count_down_finish_flag = 2;//待评价，超过评价期但在追评期内的倒计时
                        }
                    }
                    break;
                case 1100://已评价。则注释语为：订单已评价，仍可进行追评。买家还有（钟表控件）14天22小时15分钟31秒进行追评
                //case 1200://待追评
                    $flag_time = ($order_status_time + $append_have_review_limit_day) - $time;
                    $count_down_time = $flag_time>0?$flag_time:0;
                    $count_down_finish_flag = 3;//已评价，追评倒计时
                    break;
            }
        }
        $order_after_query['order_id'] = $order_info['order_id'];
        $after_sale_apply = $this->db->table($this->order_after_sale_apply)
            ->field('*') //TODO
            ->where($order_after_query)
            ->select();
        if(!empty($after_sale_apply)){
            $after_sale_status = config('after_sale_status');
            foreach ($after_sale_apply as $key=>$value){
                foreach ($after_sale_status as $status){
                    if ($value['status'] == $status['code']){
                        $after_sale_apply[$key]['status_name'] = $status['name'];
                        $after_sale_apply[$key]['status_en_name'] = $status['en_name'];
                        break;
                    }
                }
            }
        }
        $order_info['after_sale_apply'] = $after_sale_apply;
        $order_info['order_show_status'] = $order_show_status;
        $order_info['count_down_time'] = $count_down_time;
        $order_info['count_down_finish_flag'] = $count_down_finish_flag;
        return $order_info;
    }

    /**
     * 获取订单自动好评-订单数据【默认好评定时任务专用】
     * 订单完成超过指定时间（配置15天）未评价的数据，默认好评
     * （状态为900||1000，并且状态变化时间 <= [time()-评论限制时间] ）
     * @param int $limit
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getOrderDataForAutomaticPraise($limit = 50){
        $order_review_limit_time = config('order_review_limit_day')*24*60*60;
        $join = [
            [$this->order_status_change.' os','o.order_id=os.order_id','LEFT'],
        ];
        $query = $this->db->table($this->order)->alias('o');
        $data = $query->join($join)
            ->where(function ($query) {
                $query->where('o.order_status', '=', 900)
                    ->whereOr('o.order_status', '=', 100);
            })
            ->where('os.create_on', '<=', (time()-$order_review_limit_time))
            ->field('o.order_id,o.order_number,o.store_id,o.customer_id,o.customer_name,o.country_code,o.complete_on,o.order_status')
            ->group('o.order_id')->limit($limit)
            ->select();
        //获取订单产品数据
        foreach ($data as &$info){
            $info['item_data'] = $this->db->table($this->order_item)->where(['order_id'=>$info['order_id']])->select();
        }
        return $data;
    }

    /**
     * 改变订单状态【默认好评定时任务专用】
     * @param array $params
     * @return bool
     * @throws \Exception
     * @throws \think\exception\PDOException
     */
    public function updateOrderStatusForAutomaticPraise(array $params){
        $rtn = true;
        // start

        $this->db->startTrans();
        try{
            $time = time();
            $order_ids = $params['order_ids'];
            $to_order_status = $params['to_order_status'];
            $order_status_change_arr = $params['order_status_change_arr'];

            Log::record('$order_ids'.print_r($order_ids, true));

            //1、更新订单主表订单状态
            $this->db->table($this->order)->where('order_id', 'in', $order_ids)->update(
                [
                    'order_status'=>$to_order_status,
                    'modify_on'=>$time,
                    'modify_by'=>'System Task Api',
                ]
            );
            //2、记录订单状态变化信息
            //组装订单状态变化数据
            $insert_status_change_arr = [];

            $change_str = 'System Automatic Praise For Orders.';
            foreach ($order_status_change_arr as $info){
                $tem = [];
                $tem['order_id'] = $info['order_id'];
                $tem['order_status_from'] = $info['order_status_from'];
                $tem['order_status'] = $to_order_status;
                $tem['change_reason'] = $change_str;
                $tem['create_on'] = $time;
                $tem['create_by'] = 'System Task Api';
                $tem['chage_desc'] = $change_str;
                $insert_status_change_arr[] = $tem;
            }
            Log::record('$insert_status_change_arr'.print_r($insert_status_change_arr, true));
            $this->db->table($this->order_status_change)->insertAll($insert_status_change_arr);

            //3、更改admin库下的affiliate订单状态
            $base_api = new BaseApi();
            foreach ($order_status_change_arr as $val){
                $res = $base_api->updateAffiliateOrderStatus(['order_number'=>$val['order_number'], 'order_status'=>$to_order_status]);
                Log::record('task修改订单状态系统-更新affiliate订单结果 '.json_encode($res));
            }
            // submit
            $this->db->commit();
        } catch (\Exception $e) {
            // roll
            $rtn = false;
            Log::record('执行修改订单价格事务失败 '.$e->getMessage());
            $this->db->rollback();
        }
        return $rtn;
    }

    /**
     * 根据条件获取订单状态数据
     * @param array $where
     * @return array|false|\PDOStatement|string|Model
     */
    public function getOrderStatusInfoByWhere(array $where){
        return $this->db->table($this->order_status_change)->where($where)->order(['create_on'=>'desc'])->find();
    }


    /**
     * 根据条件获取订单item详情
     * @param array $where 条件
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getOrderItemDataByWhere(array $where){
        return $this->db->table($this->order_item)->where($where)->select();
    }

    /**
     * 根据条件获取订单留言信息
     * @param array $where 条件
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getOrderMessageDataByWhere(array $where){
        return $this->db->table($this->order_message)->where($where)->order("id","DESC")->select();
    }

    /**
     * 根据条件获取订单邮寄信息
     * @param array $where 条件
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getOrderShippingAddressDataByWhere(array $where){
        return $this->db->table($this->order_shipping_address)->where($where)->select();
    }

    /**
     * 获取订单状态数量信息
     */
    public function getOrderStatusNum($params){
        $store_id = $params['store_id'];
        //获取“今日新订单数”
        $start_time = strtotime(date('Y-m-d 00:00:00'));
        $end_time = strtotime(date('Y-m-d 23:59:59'));
        $data['today_num'] = $this->db->table($this->order)
            ->where('store_id', '=', $store_id)
            ->where('create_on','>=', $start_time)
            ->where('create_on','<=', $end_time)
            ->count();
        //等待您发货 数量
        $data['waiting_delivery_num'] =
            $this->db->table($this->order)
                ->where('store_id', '=', $store_id)
                ->where('order_status','=', 400)
                ->count();
        // 买家申请取消订单 数量
        $data['cancelled_order_num'] =
            $this->db->table($this->order)
                ->where('store_id', '=', $store_id)
                ->where('order_status','=', 1400)
                ->count();
        // 有纠纷的订单 数量
        $data['dispute_order_num'] =
            $this->db->table($this->order)
                ->where('store_id', '=', $store_id)
                ->where('order_status','=', 1700)
                ->count();

        // 未读留言 TODO
        $join = [
            [$this->order_message.' om','o.order_id=om.order_id','LEFT'],
        ];
        $data['unread_message_order_num'] = $this->db->table($this->order)->alias('o')
            ->join($join)
            ->where('o.store_id', '=', $store_id)
            ->where('om.statused', '=', -1)
            ->where('om.message_type', '=', 2)
            ->count();
        //等待买家付款 数量
        $data['wait_payment_order_num'] =
            $this->db->table($this->order)
                ->where('store_id', '=', $store_id)
                ->where('order_status','=', 100)
                ->count();
        // 等待确认收货订单 数量
        $data['waiting_confirm_receipt_order_num'] =
            $this->db->table($this->order)
                ->where('store_id', '=', $store_id)
                ->where('order_status','=', 800)
                ->count();
        return $data;
    }

    /**
     * 根据条件更新订单信息
     * @param array $where 条件
     * @param array $up_data 要更新的字段
     * @return int|string
     */
    public function updateOrderInfoByWhere(array $where, array $up_data){
        return $this->db->table($this->order)->where($where)->update($up_data);
    }

    /**
     * 根据条件更新订单留言信息
     * @param array $where 条件
     * @param array $up_data 要更新的字段
     * @return int|string
     */
    public function updateOrderMessageByWhere(array $where, array $up_data){
        return $this->db->table($this->order_message)->where($where)->update($up_data);
    }

    /**
     * 增加订单价格修改信息
     * @param array $data
     * @return int|string
     */
    public function insertOrderPriceChangeData(array $data){
        return $this->db->table($this->order_price_change)->insert($data);
    }

    /**
     * 增加订单退款信息
     * @param array $data
     * @return int|string
     */
    public function insertOrderRefundOperationData(array $data){
        return $this->db->table($this->order_sales_order_refund_operation)->insert($data);
    }

    /**
     * 增加订单留言信息
     * @param array $data
     * @return int|string
     */
    public function insertOrderMessageData(array $data){
        $res = $this->db->transaction(function() use ($data) {
            $res = $this->db->table($this->order_message)->insert($data);
            /*如果是卖家回复，则更改买家留言回复状态*/
            if(isset($data['order_id']) && !empty($data['order_id']) && isset($data['message_type']) && $data['message_type']==1){
                $update_where['order_id'] = $data['order_id'];
                $update_where['message_type'] = 2;
                $this->db->table($this->order_message)->where($update_where)->update(['is_reply'=>2]);
            }
            return $res;
        });
        return $res;
    }

    /**
     * 修改订单价格
     * @param $param
     * @return bool
     */
    public function updateOrderPrice($param){
        $rtn = true;
        $order_info = $this->getOrderInfoByOrderId($param['order_id']);
        //只有在“等待买家付款”下才允许修改订单价格
        if($order_info['order_status'] == 100){
            // start
            $this->db->startTrans();
            try{
                //更新订单价格数据
                $this->updateOrderInfoByWhere(
                    ['order_id'=>$param['order_id']],
                    [
                        /*'grand_total'=>$param['grand_total_changed'],
                        'captured_amount_usd'=>$param['USD_captured_amount_changed'],*/
                        'adjust_price'=>$param['adjust_price'],
                    ]
                );
                //记录修改信息
                $this->insertOrderPriceChangeData(
                    [
                        'order_id'=>$param['order_id'],
                        'change_user_name'=>$param['change_user_name'],
                        'change_user_id'=>$param['change_user_id'],
                        'change_user_ip'=>$param['change_user_ip'],
                        'change_reason'=>$param['change_reason'],
                        'change_time'=>time(),
                        'change_from'=>$param['grand_total'],
                        'change_to'=>$param['grand_total_changed'],
                    ]
                );
                // submit
                $this->db->commit();
            } catch (\Exception $e) {
                // roll
                $rtn = false;
                Log::record('执行修改订单价格事务失败 '.$e->getMessage());
                $this->db->rollback();
            }
        }else{
            Log::record('执行修改订单价格失败:只有在“等待买家付款”下才允许修改订单价格');
            $rtn = false;
        }
        return $rtn;
    }

    /**
     * 定时脚本 -- 通过一个月的订单数据筛选出所有产品信息，组合如下数据
     * Key_SKU	Relative_SKU    num
     * 9	    176746	        3
     * 14	    1212	        36
     * 14	    4319	        2
     * 14	    5639	        24
     *
     * @param array $params 条件
     * @return array
     */
    public function getBoughtAlsoBought(array $params){

        $query = $this->db->table($this->order)->alias('o');
        $join = [
            [$this->order_item.' oi','o.order_id=oi.order_id','LEFT'],
        ];
        $query->join($join);
        $query->where('o.delete_time', '=', 0);

        //搜索订单创建时间
        if (
            isset($params['create_on_start']) && !empty($params['create_on_start'])
            && isset($params['create_on_end']) && !empty($params['create_on_end'])
        ){
            $query->where('o.create_on','>=', $params['create_on_start']);
            $query->where('o.create_on','<=', $params['create_on_end']);
        }

        if (isset($params['order_status']) && !empty($params['order_status'])){
            $query->where(['o.order_status'=>['in',$params['order_status']]]);
        }
        $baseSubQuery = $query->field('o.order_id,o.customer_id,oi.product_id,oi.sku_num')
            ->buildSql();
        $distinctSpu = $this->db->table($baseSubQuery . ' a')->distinct(true)->field('a.product_id')->buildSql();
        $query = $this->db->table($distinctSpu . ' ds')
            ->join($baseSubQuery .' t' ,'t.product_id = ds.product_id')
            ->join($baseSubQuery .' t1','t1.order_id = t.order_id')
            ->where('ds.product_id != t1.product_id')
            ->group('ds.product_id, t1.product_id')
            ->field('ds.product_id AS Key_SKU,t1.product_id AS Relative_SKU,COUNT(t.product_id) as num');
        return $query->select();
    }

    /**
     * 根据产品ID，查询买了又买数据
     * @param array $params 条件
     * @return array
     */
    public function selectBoughtByProduct(array $params){
        $order_ids = array();
        $query = $this->db->table($this->order)->alias('o');
        $join = [
            [$this->order_item.' oi','o.order_id=oi.order_id','LEFT'],
        ];
        $query->join($join);
        $query->where('o.delete_time', '=', 0);

        //搜索订单创建时间
        if (
            isset($params['create_on_start']) && !empty($params['create_on_start'])
            && isset($params['create_on_end']) && !empty($params['create_on_end'])
        ){
            $query->where('o.create_on','>=', $params['create_on_start']);
            $query->where('o.create_on','<=', $params['create_on_end']);
        }

        if (isset($params['order_status']) && !empty($params['order_status'])){
            $query->where(['o.order_status'=>['in',$params['order_status']]]);
        }
        if(isset($params['product_id'])){
            $query->where(['oi.product_id'=>$params['product_id']]);
        }
        $query->group('o.order_id');

        //查找出买过这个产品的所有订单ID
        $baseSubQuery = $query->field('o.order_id')->select();

        if(!empty($baseSubQuery)){
            $order_ids = CommonLib::getColumn('order_id',$baseSubQuery);
        }
        //找出这些订单中，买了其他产品的产品ID
        if(!empty($order_ids)){
            $order_query = $this->db->table($this->order_item)
                ->field('product_id,COUNT(product_id) AS p_num')
                ->where(['order_id'=>['in',$order_ids],'product_id'=>['<>',$params['product_id']]])
                ->group('product_id')->order('p_num','desc')->limit(50);
            $data = $order_query->select();
            return $data;
        }
        return array();
    }


    /**
     * 获取订单数据
     * @param array $params
     * type = 1按销量排序
     * type = 2按价格排序
     * @return array
     */
    public function getTaskOrderData(array $params){

        $query = $this->db->table($this->order_item)->alias('oi');

        $join = [
            [$this->order.' o','o.order_id=oi.order_id','LEFT'],
        ];
        $query->join($join);
        $query->where('o.delete_time', '=', 0);

        //搜索订单创建时间
        if (
            isset($params['create_on_start']) && !empty($params['create_on_start'])
            && isset($params['create_on_end']) && !empty($params['create_on_end'])
        ){
            $query->where('o.create_on','>=', $params['create_on_start']);
            $query->where('o.create_on','<=', $params['create_on_end']);
        }

        if (isset($params['order_status']) && !empty($params['order_status'])){
            $query->where(['o.order_status'=>['in',$params['order_status']]]);
        }

        //按销量排序
        if($params['type'] == 1){
            $data = $query->field(['oi.product_id','COUNT(oi.product_id) AS p_num'])->group('oi.product_id')->order('p_num','desc')->limit(1000)->select();
        }else{
            //按价格
            $baseSubQuery = $query->field(['product_id,product_price'])->buildSql();

            //此做法是因为goupby 报错 sql_mode=only_full_group_by
            $data = $this->db->table($baseSubQuery . ' a')->field('a.product_id,a.product_price')->distinct('a.product_id')->order('a.product_price',$params['price_order'])->buildSql();
            $data = $this->db->table($data . ' b')->field('b.product_id')->group("b.product_id")->limit(1000)->select();
        }
        return $data;

    }

    /**
     * 创建换货订单数据
     * [
        'after_sale_id'=>4, //售后单号ID
        'order_id'=>1, //原订单表ID
        'price'=>20, //订单金额:订单金额为0时，订单状态直接变更为“待发货”;订单金额大于0时，订单状态变更为“待支付”
        'data'=>
            [
                [
                    'product_id'=>91, //SPU id
                    'sku_id'=>86, //SKU id
                    'sku_code'=>1, //SKU 编码
                    'sku_nums'=>4, //SKU 数量
                ],
                [
                    'product_id'=>172,
                    'sku_id'=>466,
                    'sku_code'=>20,
                    'sku_nums'=>3,
                ],
            ]
        ]
     * 提交成功后，原售后单关闭
     * @param array $params
     * @return bool
     */
    public function createRmoOrder(array $params){
        $rtn = true;
        // start
        $this->db->startTrans();
        try{
            $base_api = new BaseApi();
            $time = time();
            $old_order_id = $params['order_id'];
            $price = $params['price'];//美元
            $product_data = $params['data'];
            /** 获取原订单号数据 **/
            $old_order_info = $this->db->table($this->order)->where(['order_id'=>$old_order_id])->find();
            $exchange_rate = $old_order_info['exchange_rate'];
            $price_other = $price * $exchange_rate;
            /** 生成订单主表数据 **/
            $order_status = 100;//待付款
            if ($price == 0){
                $order_status = 400;//待发货
            }
            $order_data = [
                'parent_id'=>$old_order_id,
                'order_number'=>CommonLib::createOrderNumner(),
                'store_id'=>$old_order_info['store_id'],
                'store_name'=>$old_order_info['store_name'],
                'customer_id'=>$old_order_info['customer_id'],
                'customer_name'=>$old_order_info['customer_name'],
                'currency_code'=>$old_order_info['currency_code'],
                'exchange_rate'=>$exchange_rate,
                'order_status'=>$order_status,
                'goods_total'=>$price_other,//订单总价
                'total_amount'=>$price_other,//包含产品总金额、运费总金额、手续费等、含优惠的金额
                'grand_total'=>$price_other,//实收总金额
                'captured_amount_usd'=>$price,//以美元为单的实收总金额（如果退款，这个金额会变动）
                'captured_amount'=>$price_other,//实收金额（如果退款，这个金额会变动）
                'order_type'=>2,
                'create_on'=>$time,
            ];
            $order_id = $this->db->table($this->order)->insertGetId($order_data);
            /** 生成订单产品表数据 **/
            foreach ($product_data as $info){
                $product_id = $info['product_id'];
                $sku_id = $info['sku_id'];
                //获取要添加的产品数据
                $product_info = $base_api->getProductInfoByID($product_id);
                //获取对应SKU产品属性ID组/产品属性描述组
                $product_attr_ids_arr = [];
                $product_attr_desc_arr = [];
                foreach ($product_info['data']['Skus'] as $sku){
                    if ($sku['_id'] == $sku_id){
                        foreach ($sku['SalesAttrs'] as $sku_attr){
                            $product_attr_ids_arr[] = $sku_attr['_id'];
                            $product_attr_desc_arr[] = $sku_attr['Name'];
                        }
                    }
                }
                $product_attr_ids = implode(',' , $product_attr_ids_arr);
                $product_attr_desc = implode(',' , $product_attr_desc_arr);
                $order_item_insert_data = [
                    'order_id'=>$order_id,
                    'product_id'=>$product_id,
                    'sku_id'=>$sku_id,
                    'sku_num'=>$info['sku_code'],
                    'first_category_id'=>$product_info['data']['FirstCategory'],
                    'product_name'=>$product_info['data']['Title'],
                    'product_img'=>$product_info['data']['ImageSet']['ProductImg'][0],
                    'product_attr_ids'=>$product_attr_ids,
                    'product_attr_desc'=>$product_attr_desc,
                    'product_nums'=>$info['sku_nums'],
                    'second_category_id'=>$product_info['data']['SecondCategory'],
                    'third_category_id'=>$product_info['data']['ThirdCategory'],
                    'order_item_type'=>1,
                ];
                $this->db->table($this->order_item)->insert($order_item_insert_data);
            }
            /** 生成订单邮寄地址表数据 **/
            //获取原订单邮寄地址
            $old_shipping_data_all = $this->getOrderShippingAddressDataByWhere(['order_id'=>$old_order_id]);
            $old_shipping_data = !empty($old_shipping_data_all)?$old_shipping_data_all[0]:[];
            $order_shipping_address_insert_data = [
                'order_id'=>$order_id,
                'first_name'=>$old_shipping_data['first_name'],
                'last_name'=>$old_shipping_data['last_name'],
                'phone_number'=>$old_shipping_data['phone_number'],
                'postal_code'=>$old_shipping_data['postal_code'],
                'street1'=>$old_shipping_data['street1'],
                'street2'=>$old_shipping_data['street2'],
                'city'=>$old_shipping_data['city'],
                'country'=>$old_shipping_data['country'],
                'country_code'=>$old_shipping_data['country_code'],
                'email'=>$old_shipping_data['email'],
                'state'=>$old_shipping_data['state'],
                'mobile'=>$old_shipping_data['mobile'],
            ];
            $this->db->table($this->order_shipping_address)->insert($order_shipping_address_insert_data);
            /** 原订单&&售后订单处理 **/
            //原订单状态修改为已关闭
            $this->db->table($this->order)->where(['order_id'=>$old_order_id])->update(['order_status'=>1900,'modify_on'=>$time]);
            //售后订单状态不用处理
            // submit
            $this->db->commit();
        }catch (\Exception $e){
            $rtn = $e->getMessage();
            // roll
            $this->db->rollback();
        }
        return $rtn;
    }

    /**
     * 根据条件更新售后订单表数据
     * @param array $where 条件
     * @param array $up_data 要更新的数据
     * @return int|string
     */
    public function updateAfterSaleApplyByParams(array $where, array $up_data){
        return $this->db->table($this->order_after_sale_apply)->where($where)->update($up_data);
    }

    /**
     * 根据条件获取售后数据
     * @param array $where
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getAfterSaleApplyByWhere(array $where){
        $query = $this->db->table($this->order_after_sale_apply);
        if (isset($where['after_sale_id'])){
            $query->where(['after_sale_id'=>$where['after_sale_id']]);
        }
        return $query->select();
    }

    /**
     * 根据条件获取订单数据
     * @param array $where
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getOrderDataByWhere(array $where){
        $query = $this->db->table($this->order);
        if (isset($where['order_master_number']) && !empty($where['order_master_number'])){
            $query->where(['order_master_number'=>$where['order_master_number']]);
        }
        return $query->select();
    }

    /**
     * 根据订单编号获取交易唯一ID
     * @param $where
     * @return string
     */
    public function getTransactionID($where){
        $TransactionData = $this->db->table($this->order_sales_txn)->where($where)->column("payment_txn_id","txn_type");
        if(isset($TransactionData['Capture'])){
            return $TransactionData['Capture'];
        }elseif (isset($TransactionData['Purchase'])){
            return $TransactionData['Purchase'];
        }else{
            return '';
        }
    }

    /**
     * 下载订单
     * @param array $params
     * @return array
     * @throws \think\exception\DbException
     */
    public function downloadOrder(array $params){
        $shipping_model_config = config('shipping_model_except_exclusive');
        $max_delivery_time_config = config('max_delivery_time');
        foreach ($shipping_model_config as $k1=>$v2){
            $shipping_model_config[$k1] = strtolower($v2);
        }
        $query = $this->db->table($this->order)->alias('o');
        $query->where('o.delete_time', '=', 0);
        //商家ID
        if (isset($params['store_id']) && !empty($params['store_id'])){
            $query->where('o.store_id', '=', $params['store_id']);
        }
        //搜索订单创建时间
        if (
            isset($params['create_on_start']) && !empty($params['create_on_start'])
            && isset($params['create_on_end']) && !empty($params['create_on_end'])
        ){
            $query->where('o.create_on','>=', $params['create_on_start']);
            $query->where('o.create_on','<=', $params['create_on_end']);
        }
        //订单状态，只下载状态为“待发货”的订单【20181029，根据订单状态来判断，因为fulfillment_status在下载订单后面才更新（同步追踪号时）】
        //$query->where('o.fulfillment_status', '=', 400);
        $query->where('o.order_status', '=', 400);
        $query->order('o.create_on', 'desc');
        //分页参数设置
        $page_size = isset($params['page_size']) && !empty($params['page_size']) ? (int)$params['page_size'] : 5;
        $page = isset($params['page']) && !empty($params['page']) ? (int)$params['page'] : 1;
        $path = isset($params['path']) && !empty($params['path']) ? $params['path'] : null;
        $response = $query->field('o.order_id, o.parent_id, o.order_number, o.order_master_number, o.store_id, o.store_name, o.customer_id, o.customer_name, o.payment_status, o.order_status, o.order_branch_status, o.lock_status, o.goods_count, o.goods_total, o.discount_total, o.shipping_fee, o.handling_fee,o.total_amount,o.grand_total,o.captured_amount_usd,o.captured_amount,o.refunded_amount,o.refunding_amount,o.currency_code,o.shipping_count,o.shipped_count,o.shipped_amount,o.adjust_price,o.order_type,o.exchange_rate,o.language_code,o.create_on,o.complete_on,o.shipping_insurance_enabled,o.shipping_insurance_fee,o.bulk_rate_enabled,o.receivable_shipping_fee,o.shipping_fee_discount,o.logistics_provider,o.pay_type,o.pay_channel,o.affiliate,o.remark,o.pay_time,o.shipments_time,o.shipments_complete_time,o.tariff_insurance_enabled,o.tariff_insurance,o.transaction_id,o.sc_transaction_id,o.fulfillment_status,o.business_type,o.country,o.country_code,o.is_active,o.active_type,o.is_mvp,o.order_points,o.is_tariff_insurance,o.is_cod,o.coupon_id')
            ->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path])
            ->each(function ($item, $key) use ($shipping_model_config, $max_delivery_time_config){
                $order_id = $item['order_id'];
                /** 订单产品详情 **/
                $item_data = $this->getOrderItemDataByWhere(['order_id' => $order_id]);
                //运输方式处理，如果是专线，需要将专线名称传过去，而不是具体哪个专线
                if (!empty($item_data)){
                    foreach ($item_data as &$info){
                        if (
                            !empty($info['shipping_model'])
                            && !in_array(strtolower($info['shipping_model']), $shipping_model_config)
                        ){
                            $info['shipping_model_child'] = $info['shipping_model'];
                            $info['shipping_model'] = 'Exclusive';
                        }
                        //拼装产品完整地址
                        $info['product_img'] = IMG_URL.'/'.$info['product_img'];
                    }
                }
                $item['item_data'] = $item_data;
                /** 收货地址 **/
                $shipping_data = $this->getOrderShippingAddressDataByWhere(['order_id'=>$order_id]);
                $item['shipping_data'] = !empty($shipping_data)?$shipping_data[0]:[];
                //增加最后发货时间（支付完成时间+指定时间）
                $item['max_delivery_time'] = $item['pay_time'] + $max_delivery_time_config;
                return $item;
            });
        $Page = $response->render();
        $data = $response->toArray();
        $data['Page'] = $Page;
        return $data;
    }

    public function getAdminCustomerOrder($param){
        $data['order_total'] = $this->db->table($this->order)->where($param)->count();
        $complete_where = $param;
        $complete_where['complete_on'] = ["GT",0];
        $data['complete_order'] = $this->db->table($this->order)->where($complete_where)->count();
        $processing_where = $param;
        $processing_where['order_status'] = 300;
        $data['processing_order'] = $this->db->table($this->order)->where($processing_where)->count();
        $chargeback_where = $param;
        $chargeback_where['refunded_type'] = ['GT',0];
        $data['refunded_order'] = $this->db->table($this->order_after_sale_apply)->where($chargeback_where)->count();
        $dispute_where = $param;
        $dispute_where['order_status'] = 1800;
        $data['dispute_order'] = $this->db->table($this->order)->where($dispute_where)->count();
        $rma_where = $param;
        $data['rma_order'] = $this->db->table($this->order_after_sale_apply)->where($rma_where)->count();
        $fraud_adjusted_where = $param;
        $fraud_adjusted_where['order_branch_status'] = 105;
        $data['fraud_adjusted_order'] = $this->db->table($this->order)->where($fraud_adjusted_where)->count();
        $first_order_where = $param;
        $data['first_order_time'] = $this->db->table($this->order)->where($first_order_where)->order("order_id","ASC")->value("create_on");
        $last_order_where = $param;
        $data['last_order_time'] = $this->db->table($this->order)->where($last_order_where)->order("order_id","DESC")->value("create_on");
        return $data;
    }
}