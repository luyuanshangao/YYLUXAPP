<?php
namespace app\orderbackend\model;

use app\admin\dxcommon\BaseApi;
use app\admin\model\OrderRemarks;
use app\common\helpers\CommonLib;
use think\Cache;
use think\Exception;
use think\Log;
use think\Model;
use think\Db;
use app\common\helpers\RedisClusterBase;

/**
 * 订单模型
 * Class OrderModel
 * @author tinghu.liu 2018/4/23
 * @package app\orderFront\model
 */
class OrderModel extends Model{
    /*执行同步订单到OMS redis队列,20190412 kevin*/
    const CREATE_ORDER_SYNC_OMS_KEY = "createOrderSyncOMS3";
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
     * 订单退款表
     * @var string
     */
    private $order_refund = "dx_order_refund";
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
    /**
     *【NOC拆单】订单表
     * @var string
     */
	private $nocsplit_sales_order = "dx_nocsplit_sales_order";
    /**
     *【NOC拆单】订单商品表
     * @var string
     */
	private $nocsplit_sales_order_item = "dx_nocsplit_sales_order_item";
    /**
     *【NOC拆单】订单优惠券使用记录表
     * @var string
     */
	private $nocsplit_sales_order_coupon = "dx_nocsplit_sales_order_coupon";
    /**
     *OMS推送订单状态记录表
     * @var string
     */
    private $sales_order_status_oms_record = "dx_sales_order_status_oms_record";
    /**
     *订单折扣异常记录表
     * @var string
     */
    private $sales_order_discount_exception = "dx_sales_order_exception";

    private $dx_order_pay_token = "dx_order_pay_token";
    /**
     * 订单附加表
     * @var string
     */
    private $order_other = "dx_sales_order_other";

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
        /*$join = [
            [$this->order_item.' oi','o.order_id=oi.order_id','LEFT'],
            [$this->order_message.' om','o.order_id=om.order_id','LEFT'],
        ];*/
        //join优化  added by wangyj in 20190218
        $join = [];
        if((isset($params['product_name']) && !empty($params['product_name'])) || (isset($params['product_id']) && !empty($params['product_id'])) || (isset($params['sku_num']) && !empty($params['sku_num']))){
            $query->join($this->order_item.' oi','o.order_id=oi.order_id',"LEFT");
        }
        if(isset($params['unread']) && !empty($params['unread']) && $params['unread'] == 1){
            $query->join($this->order_message.' om','o.order_id=om.order_id','LEFT');
        }
        /*是否已回复*/
        $reply_sql = '';//回复sql   added by wangyj in 20190218
        if(isset($params['is_reply']) && !empty($params['is_reply'])){
            $reply_sql = $this->db->table($this->order_message)->alias('om2')->field(['om2.*', 'sum(if(om2.`is_reply`=1, 1, 0))'=>'cc'])->group('order_id')->where("message_type","=",2)->buildSql();
            $query->join($reply_sql.' om','o.order_id=om.order_id');
        }
        if(!empty($reply_sql))$query->where('om.cc', ($params['is_reply']=='1'?'>':'='), 0);
        //$query->where('o.delete_time', '=', 0);
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

        if (isset($params['sku_num']) && !empty($params['sku_num'])){
            $query->where('oi.sku_num', $params['sku_num']);
        }
        if (isset($params['product_id']) && !empty($params['product_id'])){
            $query->where('oi.product_id', '=', $params['product_id']);
        }
        //订单号
        if (isset($params['order_number']) && !empty($params['order_number'])){
            //$params['order_number'] = str_replace('，',',', $params['order_number']);
            //$order_number_arr = explode(',', $params['order_number']);
            $order_number_arr = QueryFiltering($params['order_number']);
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
    public function getOrderInfoByOrderId($order_id='', $store_id=0,$order_number=''){
        //订单基本信息
        $query = $this->db->table($this->order);
        /*$order_info = $this->db->table($this->order)
            ->where(['order_id'=>$order_id])
            ->where(['delete_time'=>0])
            ->find();*/
        if ($store_id != 0 && !empty($store_id)){
            $query->where(['store_id'=>$store_id]);
        }
        if(!empty($order_id)){
            $where['order_id'] = $order_id;
        }elseif($order_number){
            $where['order_number'] = $order_number;
        }
        $order_info = $query->where($where)
            //->where(['delete_time'=>0])
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
            /*2019.01.15 kevin 处理留言内容*/
            $message['message'] = htmlspecialchars_decode(htmlspecialchars_decode($message['message']));
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
     * 获取订单号和金额的json
     * @param $order_id 订单ID
     * @param int $store_id seller ID
     * @return array|false|\PDOStatement|string|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getOrderJson($order_id='',$refund_grand_total)
    {
        //订单基本信息
        $query = $this->db->table($this->order);
        $where['order_id'] = $order_id;
        $order_info = $query->where($where)->find();
        $da=[];
        if (empty($order_info['order_number'])) {
            return;
        }
        $data[$order_info['order_number']]=$refund_grand_total;//退款金额

        if($order_info['order_number']!=$order_info['order_master_number']){
            $where1['order_number']=$order_info['order_master_number'];
            //获取主单
            Log::record('$where1'.json_encode($where1));
            $orders = $this->db->table($this->order)->field('order_number,captured_amount_usd')->where($where1)->find();
            Log::record('$orders'.json_encode($orders));
            if (empty($orders['order_number'])) {
                return;
            }
            $data1[$orders['order_number']]=$orders['captured_amount_usd'];//退款金额

            $data2=$data+$data1;
            return $data2;
        }
        return $data;
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
                    ->whereOr('o.order_status', '=', 1000);
            })
            ->where('o.complete_on', '<=', (time()-$order_review_limit_time))
            ->where("o.order_branch_status","<",1000)
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

            //Log::record('$order_ids'.print_r($order_ids, true));
            /*2019.1.14 如果是更改小状态*/
            if($to_order_status == 1100 || $to_order_status == 1200){
                $this->db->table($this->order)->where('order_id', 'in', $order_ids)->update(
                    [
                        'order_branch_status'=>$to_order_status,
                        'modify_on'=>$time,
                        'modify_by'=>'System Task Api',
                    ]
                );
            }else{
                //1、更新订单主表订单状态
                $this->db->table($this->order)->where('order_id', 'in', $order_ids)->update(
                    [
                        'order_status'=>$to_order_status,
                        'modify_on'=>$time,
                        'modify_by'=>'System Task Api',
                    ]
                );
            }
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
            //Log::record('$insert_status_change_arr'.print_r($insert_status_change_arr, true));
            $this->db->table($this->order_status_change)->insertAll($insert_status_change_arr);

            //3、更改admin库下的affiliate订单状态
            $base_api = new BaseApi();
            foreach ($order_status_change_arr as $val){
                $res = $base_api->updateAffiliateOrderStatus(['order_number'=>$val['order_number'], 'order_status'=>$to_order_status]);
                //Log::record('task修改订单状态系统-更新affiliate订单结果 '.json_encode($res));
            }
            // submit
            $this->db->commit();
        } catch (\Exception $e) {
            // roll
            $rtn = false;
            Log::record('执行改变订单状态【默认好评定时任务专用】事务失败:'.$e->getMessage().', '.$e->getFile().'['.$e->getLine().']');
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
     * @param boolean $is_download_order 是否是下载订单
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getOrderItemDataByWhere(array $where, $is_download_order=false){
        //下载订单增加购买产品数量大于0判断 tinghu.liu 20190515
        if ($is_download_order){
            return $this->db->table($this->order_item)->where($where)->where('product_nums','>',0)->select();
        }else{
            return $this->db->table($this->order_item)->where($where)->select();
        }
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
            ->group("om.order_id")
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
     * 增加订单状态变化
     * @param array $data
     * @return int|string
     */
    public function insertOrderStatusChange(array $data){
        return $this->db->table($this->order_status_change)->insert($data);
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
                /*获取后台用户，分配到后台用户*/
                $admin_user_where['group_id'] = 12;//客服主管
                $admin_user_where['status'] = 1;
                $admin_user = model("admin/User")->getUserInfo($admin_user_where);
                if($admin_user){
                    $update_data['distribution_admin_id'] = $admin_user['id'];
                    $update_data['distribution_admin'] = $admin_user['username'];
                }
                $update_where['order_id'] = $data['order_id'];
                $update_where['message_type'] = 2;
                $update_where['distribution_admin_id'] = ['eq',0];
                $update_data['is_reply'] = 2;
                $update_data['operator_admin_id'] = $data['user_id'];
                $update_data['operator_admin'] = "Seller-".$data['user_name'];
                $this->db->table($this->order_message)->where($update_where)->update($update_data);
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
        $orderModelFront = new \app\orderfrontend\model\OrderModel();

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
            $order_number = CommonLib::createOrderNumner();
            $order_data = [
                'parent_id'=>$old_order_id,
                'order_number'=>$order_number,
                'order_master_number'=>$order_number,
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
                //增加产品价格 tinghu.liu 20190523
                $sales_price = 0;
                foreach ($product_info['data']['Skus'] as $sku){
                    if ($sku['_id'] == $sku_id){
                        foreach ($sku['SalesAttrs'] as $sku_attr){
                            $product_attr_ids_arr[] = $sku_attr['_id'];
                            $product_attr_desc_arr[] = $sku_attr['Name'];
                        }
                        $sales_price = isset($sku['SalesPrice'])?$sku['SalesPrice']*$exchange_rate:0;
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
                    'product_price'=>$sales_price,
                    'product_unit'=>isset($product_info['data']['SalesUnitType'])?$product_info['data']['SalesUnitType']:'',
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
            /** RMA订单状态记录 tinghu.liu 20190524 **/
            $change_reason = 'Create RMA orders.';
            $create_by = 'ApiSystem';
            if ($order_status == 100){
                $up_status_params = [
                      'order_id'=>$order_id, //订单ID
                      'order_status_from'=>0, //修改前状态
                      'order_status'=>100, //修改后状态
                      'change_reason'=>$change_reason, //修改原因
                      'create_on'=>$time, //修改时间
                      'create_by'=>$create_by, //修改人
                      'create_ip'=>'', //创建者IP
                      'chage_desc'=>$change_reason, //修改描述
                      'is_start_trans'=>2, //是否开启事务：1-开启（默认），2-不开启
                 ];
                $orderModelFront->updateOrderStatus($up_status_params);
            }elseif ($order_status == 400)
            {
                $up_status_params_100 = [
                    'order_id'=>$order_id, //订单ID
                    'order_status_from'=>0, //修改前状态
                    'order_status'=>100, //修改后状态
                    'change_reason'=>$change_reason, //修改原因
                    'create_on'=>$time, //修改时间
                    'create_by'=>$create_by, //修改人
                    'create_ip'=>'', //创建者IP
                    'chage_desc'=>$change_reason, //修改描述
                    'is_start_trans'=>2, //是否开启事务：1-开启（默认），2-不开启
                ];
                $change_reason = 'Payment verified. Order is being processed.';
                $up_status_params_200 = [
                    'order_id'=>$order_id, //订单ID
                    'order_status_from'=>120, //修改前状态
                    'order_status'=>200, //修改后状态
                    'change_reason'=>$change_reason, //修改原因
                    'create_on'=>$time, //修改时间
                    'create_by'=>$create_by, //修改人
                    'create_ip'=>'', //创建者IP
                    'chage_desc'=>$change_reason, //修改描述
                    'is_start_trans'=>2, //是否开启事务：1-开启（默认），2-不开启
                ];
                $change_reason = 'Order is being prepared for shipment.';
                $up_status_params_400 = [
                    'order_id'=>$order_id, //订单ID
                    'order_status_from'=>200, //修改前状态
                    'order_status'=>400, //修改后状态
                    'change_reason'=>$change_reason, //修改原因
                    'create_on'=>$time, //修改时间
                    'create_by'=>$create_by, //修改人
                    'create_ip'=>'', //创建者IP
                    'chage_desc'=>$change_reason, //修改描述
                    'is_start_trans'=>2, //是否开启事务：1-开启（默认），2-不开启
                ];
                $orderModelFront->updateOrderStatus($up_status_params_100);
                $orderModelFront->updateOrderStatus($up_status_params_200);
                $orderModelFront->updateOrderStatus($up_status_params_400);
            }
            /** 原订单&&售后订单处理 **/
            //原订单状态修改为已关闭，增加状态记录
            $this->db->table($this->order)->where(['order_id'=>$old_order_id])->update(['order_status'=>1900,'modify_on'=>$time]);
            $change_reason = 'Close after sale.';
            $up_status_params_1900 = [
                'order_id'=>$old_order_id, //订单ID
                'order_status_from'=>$old_order_info['order_status'], //修改前状态
                'order_status'=>1900, //修改后状态
                'change_reason'=>$change_reason, //修改原因
                'create_on'=>$time, //修改时间
                'create_by'=>$create_by, //修改人
                'create_ip'=>'', //创建者IP
                'chage_desc'=>$change_reason, //修改描述
                'is_start_trans'=>2, //是否开启事务：1-开启（默认），2-不开启
            ];
            $orderModelFront->updateOrderStatus($up_status_params_1900);

            //售后订单状态不用处理
            // submit
            $this->db->commit();
            /*执行同步订单到OMS redis队列,20190412 kevin*/
            $redis_cluster = new RedisClusterBase();
            $oms_redis_data['order_number'] = $order_number;
            $oms_redis_data['risky'] = 0;
            $redis_cluster->lPush(
                self::CREATE_ORDER_SYNC_OMS_KEY,
                json_encode($oms_redis_data)
            );
        }catch (\Exception $e){
            $rtn = $e->getMessage();
            // roll
            $this->db->rollback();
        }
        return $rtn;
    }


    /**
     * 后台创建RMA订单数据订单数据
     * @param array $params
     * @return bool
     */
    public function createAdminRmaOrder(array $params){
        $orderModelFront = new \app\orderfrontend\model\OrderModel();
        $rtn = true;
        $result_data = [];
        // start
        $this->db->startTrans();
        try{
            $base_api = new BaseApi();
            $time = time();
            $add_time   = date('Y-m-d H:i:s', ($time+8*3600));   //订单创建时间(PRC) tinghu.liu 20191128
            $old_order_number = isset($params['order_number'])?$params['order_number']:'';
            $captured_amount = $params['captured_amount'];
            $product_data = $params['data'];
            /** 获取原订单号数据 **/
            $old_order_id = 0;
            if(!empty($old_order_number)){
                $old_order_info = $this->db->table($this->order)->where(['order_number'=>$old_order_number])->find();
                $old_order_id = $old_order_info['order_id'];
            }
            $exchange_rate = $params['exchange_rate'];
            /** 生成订单主表数据 **/
            $order_status = 100;//待付款
            if ($captured_amount == 0){
                $order_status = 400;//待发货
            }
            $order_number = CommonLib::createOrderNumner();
            $_pay_token = CommonLib::generatePayToken($order_number);

            $_shipping_fee = !empty($params['shipping_fee'])?$params['shipping_fee']:0;
            $order_data = [
                'parent_id'=>$old_order_id,
                'order_number'=>$order_number,
                'order_master_number'=>$order_number,
                'store_id'=>$params['store_id'],
                'store_name'=>$params['store_name'],
                'customer_id'=>$params['customer_id'],
                'customer_name'=>$params['customer_name'],
                'currency_code'=>$params['currency_code'],
                'exchange_rate'=>$exchange_rate,
                'order_status'=>$order_status,
                'fulfillment_status'=>$order_status,
                'country'=>$params['country'],
                'country_code'=>$params['country_code'],
                'goods_total'=>$params['goods_total'],//订单总价
                'total_amount'=>$captured_amount,//包含产品总金额、运费总金额、手续费等、含优惠的金额
                'grand_total'=>$captured_amount,//实收总金额
                'captured_amount_usd'=>$params['captured_amount_usd'],//以美元为单的实收总金额（如果退款，这个金额会变动）
                'captured_amount'=>$captured_amount,//实收金额（如果退款，这个金额会变动）
                'shipping_fee' => $_shipping_fee,//运费
                'receivable_shipping_fee' => $_shipping_fee,//运费
                'handling_fee' => !empty($params['handling_fee'])?$params['handling_fee']:0,//处理费
                'language_code'=>!empty($params['language_code'])?$params['language_code']:'en',
                'order_type'=>2,
                'remark'=>$params['remark'],
                'create_on'=>$time,
                'add_time'=>$add_time
            ];
            $order_id = $this->db->table($this->order)->insertGetId($order_data);

            /**** 支付Token数据 ******/
            $_pay_token_params['order_master_number'] = $order_number;
            $_pay_token_params['pay_token'] = $_pay_token;
            $_pay_token_params['create_on'] = $time;
            $_pay_token_params['add_time'] = $add_time;
            $this->db->table($this->dx_order_pay_token)->insert($_pay_token_params);

            /** 生成订单产品表数据 **/
            foreach ($product_data as $info){
                $product_id = $info['product_id'];
                $sku_id = $info['sku_id'];
                $sku_num = isset($info['sku_num'])?$info['sku_num']:'';
                //获取要添加的产品数据
                $product_info = $base_api->getProductInfoByID($product_id);
                if(empty($product_info['data']['Skus'])){
                    $rtn = "SPU:".$product_id."不存在";
                    return $rtn;
                }
                //获取对应SKU产品属性ID组/产品属性描述组
                $product_attr_ids_arr = [];
                $product_attr_desc_arr = [];
                $product_price = 0;
                foreach ($product_info['data']['Skus'] as $sku){
                    if ($sku['_id'] == $sku_id){
                        foreach ($sku['SalesAttrs'] as $sku_attr){
                            $product_attr_ids_arr[] = $sku_attr['_id'];
                            $product_attr_desc_arr[] = $sku_attr['Name'];
                        }
                        $product_price = $sku['SalesPrice'];
                        $sku_code = $sku['Code'];
                    }
                }
                //如果sku id匹配不到，則使用sku code進行匹配 tinghu.liu 20191210
                if(empty($sku_code)){
                    foreach ($product_info['data']['Skus'] as $sku_info){
                        if ($sku_info['Code'] == $sku_num){
                            foreach ($sku_info['SalesAttrs'] as $sku_attr){
                                $product_attr_ids_arr[] = $sku_attr['_id'];
                                $product_attr_desc_arr[] = $sku_attr['Name'];
                            }
                            $product_price = $sku_info['SalesPrice'];
                            $sku_code = $sku_info['Code'];
                            $sku_id = $sku_info['_id'];
                        }
                    }
                }
                if(empty($sku_code)){
                    $rtn = "sku_id:".$sku_id."不存在";
                    return $rtn;
                }
                $product_attr_ids = implode(',' , $product_attr_ids_arr);
                $product_attr_desc = implode(',' , $product_attr_desc_arr);
                $order_item_insert_data = [
                    'order_id'=>$order_id,
                    'product_id'=>$product_id,
                    'sku_id'=>$sku_id,
                    'sku_num'=>$sku_code,
                    'first_category_id'=>$product_info['data']['FirstCategory'],
                    'product_name'=>$product_info['data']['Title'],
                    'product_img'=>$product_info['data']['ImageSet']['ProductImg'][0],
                    'product_attr_ids'=>$product_attr_ids,
                    'product_attr_desc'=>$product_attr_desc,
                    'product_nums'=>$info['product_nums'],
                    'second_category_id'=>$product_info['data']['SecondCategory'],
                    'third_category_id'=>$product_info['data']['ThirdCategory'],
                    'order_item_type'=>1,
//                    'product_price'=>$product_price,
                    'product_price'=>$info['pruduct_price'],
                    'captured_price'=>$info['captured_price'],
                    'captured_price_usd'=>$info['captured_price_usd'],
                    'shipping_model'=>isset($info['shipping_model'])?$info['shipping_model']:'',
                    'product_unit'=> isset($product_info['data']['SalesUnitType'])?$product_info['data']['SalesUnitType']:'',
                    'remark'=>isset($info['remark'])?$info['remark']:'',
                    'create_on'=>$time
                ];
                $this->db->table($this->order_item)->insert($order_item_insert_data);
            }
            /** 生成订单邮寄地址表数据 **/
            $_cpf = isset($params['cpf'])?$params['cpf']:'';
            //获取原订单邮寄地址
            $order_shipping_address_insert_data = [
                'order_id'=>$order_id,
                'first_name'=>$params['first_name'],
                'last_name'=>$params['last_name'],
                'phone_number'=>!empty($params['phone_number'])?$params['phone_number']:'',
                'postal_code'=>$params['postal_code'],
                'street1'=>$params['street1'],
                'street2'=>$params['street2'],
                'city'=>$params['city'],
                'city_code'=>$params['city_code'],
                'country'=>$params['country'],
                'country_code'=>$params['country_code'],
                'email'=>$params['email'],
                'state'=>$params['state'],
                'state_code'=>$params['state_code'],
                'mobile'=>$params['mobile'],
                'cpf'=>$_cpf,
                'create_on'=>$time
            ];
            $this->db->table($this->order_shipping_address)->insert($order_shipping_address_insert_data);
            //增加other表记录 tinghu.liu 20191128
            $this->db->table($this->order_other)->insert([
                'order_id'=>$order_id,
                'cpf'=>$_cpf,
                'ref1'=>$params['add_user_name'].'('.$params['add_user_id'].')',
                'create_on'=>$time
            ]);
            //增加“订单备注”信息

            /**
             *
             *
             * `remarks` text COLLATE utf8mb4_general_ci NOT NULL COMMENT '备注信息',
            `` int(11) NOT NULL COMMENT '关联订单库订单表order_id',
            `` int(11) NOT NULL COMMENT '添加时间',
            `` int(11) DEFAULT NULL COMMENT '修改时间',
            `` varchar(255) COLLATE utf8mb4_general_ci NOT NULL COMMENT '状态1.用于后台订单详情备注',
            `` int(6) NOT NULL DEFAULT '0' COMMENT '操作人ID',
            `` varchar(50) COLLATE utf8mb4_general_ci NOT NULL COMMENT '操作人名',
            `` int(6) DEFAULT NULL COMMENT '修改操作人ID',
            `` varchar(50
             *
             *
             */
            (new OrderRemarks())->addRemarks([
                'remarks'=>$params['remark'],
                'order_id'=>$order_id,
                'status'=>1, //状态1.用于后台订单详情备注
                'edit_user_id'=>'',
                'edit_user_name'=>'',
                'edit_time'=>'',
                'add_user_id'=>$params['add_user_id'],
                'add_user_name'=>$params['add_user_name'],
                'add_time'=>$time,
            ]);

            /** RMA订单状态记录 tinghu.liu 20190524 **/
            $change_reason = 'Create RMA orders.';
            $create_by = !empty($params['create_by'])?$params['create_by']:'ApiSystem';
            if ($order_status == 100){
                $up_status_params = [
                    'order_id'=>$order_id, //订单ID
                    'order_status_from'=>0, //修改前状态
                    'order_status'=>100, //修改后状态
                    'change_reason'=>$change_reason, //修改原因
                    'create_on'=>$time, //修改时间
                    'create_by'=>$create_by, //修改人
                    'create_ip'=>'', //创建者IP
                    'chage_desc'=>$change_reason, //修改描述
                    'is_start_trans'=>2, //是否开启事务：1-开启（默认），2-不开启
                ];
                $orderModelFront->updateOrderStatus($up_status_params);
            }elseif ($order_status == 400) {

                /*执行同步订单到OMS redis队列,20190412 kevin*/
                $redis_cluster = new RedisClusterBase();
                $oms_redis_data['order_number'] = $order_number;
                $oms_redis_data['risky'] = 0;
                $redis_cluster->lPush(
                    self::CREATE_ORDER_SYNC_OMS_KEY,
                    json_encode($oms_redis_data)
                );

                $up_status_params_100 = [
                    'order_id' => $order_id, //订单ID
                    'order_status_from' => 0, //修改前状态
                    'order_status' => 100, //修改后状态
                    'change_reason' => $change_reason, //修改原因
                    'create_on' => $time, //修改时间
                    'create_by' => $create_by, //修改人
                    'create_ip' => '', //创建者IP
                    'chage_desc' => $change_reason, //修改描述
                    'is_start_trans' => 2, //是否开启事务：1-开启（默认），2-不开启
                ];
                $change_reason = 'Payment verified. Order is being processed.';
                $up_status_params_200 = [
                    'order_id' => $order_id, //订单ID
                    'order_status_from' => 120, //修改前状态
                    'order_status' => 200, //修改后状态
                    'change_reason' => $change_reason, //修改原因
                    'create_on' => $time, //修改时间
                    'create_by' => $create_by, //修改人
                    'create_ip' => '', //创建者IP
                    'chage_desc' => $change_reason, //修改描述
                    'is_start_trans' => 2, //是否开启事务：1-开启（默认），2-不开启
                ];
                $change_reason = 'Order is being prepared for shipment.';
                $up_status_params_400 = [
                    'order_id' => $order_id, //订单ID
                    'order_status_from' => 200, //修改前状态
                    'order_status' => 400, //修改后状态
                    'change_reason' => $change_reason, //修改原因
                    'create_on' => $time, //修改时间
                    'create_by' => $create_by, //修改人
                    'create_ip' => '', //创建者IP
                    'chage_desc' => $change_reason, //修改描述
                    'is_start_trans' => 2, //是否开启事务：1-开启（默认），2-不开启
                ];
                $orderModelFront->updateOrderStatus($up_status_params_100);
                $orderModelFront->updateOrderStatus($up_status_params_200);
                $orderModelFront->updateOrderStatus($up_status_params_400);
            }
            /*if($old_order_id>0){
                //原订单&&售后订单处理
                //原订单状态修改为已关闭，增加状态记录
                $this->db->table($this->order)->where(['order_id'=>$old_order_id])->update(['order_status'=>1900,'modify_on'=>$time]);
                $change_reason = 'Close after sale.';
                $up_status_params_1900 = [
                    'order_id'=>$old_order_id, //订单ID
                    'order_status_from'=>$old_order_info['order_status'], //修改前状态
                    'order_status'=>1900, //修改后状态
                    'change_reason'=>$change_reason, //修改原因
                    'create_on'=>$time, //修改时间
                    'create_by'=>$create_by, //修改人
                    'create_ip'=>'', //创建者IP
                    'chage_desc'=>$change_reason, //修改描述
                    'is_start_trans'=>2, //是否开启事务：1-开启（默认），2-不开启
                ];
                $orderModelFront->updateOrderStatus($up_status_params_1900);
            }*/
            $this->db->commit();

            $result_data['order_number'] = $order_number;
            $result_data['order_id'] = $order_id;
            $result_data['pay_token'] = $_pay_token;
        }catch (\Exception $e){
            $rtn = $e->getMessage();
            // roll
            $this->db->rollback();
        }
        return ['result'=>$rtn, 'data'=>$result_data];
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
        $sql = $this->db->table($this->order_sales_txn)->getLastSql();
        if(isset($TransactionData['Capture'])){
            return $TransactionData['Capture'];
        }elseif (isset($TransactionData['Purchase'])){
            return $TransactionData['Purchase'];
        }elseif (isset($TransactionData['Refund'])){
            return $TransactionData['Refund'];
        }else{
            Log::record('getTransactionID:params:'.json_encode($where).', res:'.json_encode($TransactionData).', sql:'.$sql);
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
        //拉取标识：0-拉取满足条件400状态的订单（默认），1-拉取满足条件400&407状态的订单 tinghu.liu 20190327
        $flag = isset($params['flag'])?$params['flag']:0;
        $shipping_model_config = config('shipping_model_except_exclusive');
        $max_delivery_time_config = config('max_delivery_time');
        $max_delivery_time_config_mvp = config('max_delivery_time_mvp');
        $front_order_model = new \app\orderfrontend\model\OrderModel();
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
        if ($flag == 1){
            $query->where('o.order_status', 'in', [400,407]);
        }elseif ($flag == 0){
            $query->where('o.order_status', '=', 400);
        }else{
            $query->where('o.order_status', '=', 400);
        }
        //排除锁住的订单（订单锁住状态(60:正常，未加锁,73:强制锁住，需手动解锁)） 20190320 tinghu.liu
        $query->where('o.lock_status', '=', 60);
        //排除FSC发货的产品（配合解决将FSC发货修改为ERP发货功能修改） 20190513 tinghu.liu
        $query->where('o.fsc_shipment', '=', 0);

        //TODO FSC发货转为ERP发货，【情况2-a】为666的单 处理 tinghu.liu 20190516
        // TODO 【订单下载完之后需要删掉】
        $fsc_order_number_arr = $this->getFsc2aOrderNumber()['order_number_arr'];
        $query->whereOr(function ($q) use ($fsc_order_number_arr, $params){
            $q->where('o.order_number', 'in', $fsc_order_number_arr);
            if (isset($params['store_id']) && !empty($params['store_id'])){
                $q->where('o.store_id', '=', $params['store_id']);
            }
        });

        $query->order('o.create_on', 'desc');
        //分页参数设置
        $page_size = isset($params['page_size']) && !empty($params['page_size']) ? (int)$params['page_size'] : 5;
        $page = isset($params['page']) && !empty($params['page']) ? (int)$params['page'] : 1;
        $path = isset($params['path']) && !empty($params['path']) ? $params['path'] : null;
        //下载的订单号集合，为了处理有NOCNOC拆单的情况 tinghu.liu 20190413
        $order_number_arr = [];
        //是nocnoc的订单号集合  tinghu.liu 20190415
        $order_number_noc_arr = [];
        $response = $query->field('o.order_id, o.parent_id, o.order_number, o.order_master_number, o.store_id, o.store_name, o.customer_id, o.customer_name, o.payment_status, o.order_status, o.order_branch_status, o.lock_status, o.goods_count, o.goods_total, o.discount_total, o.shipping_fee, o.handling_fee,o.total_amount,o.grand_total,o.captured_amount_usd,o.captured_amount,o.refunded_amount,o.refunding_amount,o.currency_code,o.shipping_count,o.shipped_count,o.shipped_amount,o.adjust_price,o.order_type,o.exchange_rate,o.language_code,o.create_on,o.complete_on,o.shipping_insurance_enabled,o.shipping_insurance_fee,o.bulk_rate_enabled,o.receivable_shipping_fee,o.shipping_fee_discount,o.logistics_provider,o.pay_type,o.pay_channel,o.affiliate,o.remark,o.pay_time,o.shipments_time,o.shipments_complete_time,o.tariff_insurance_enabled,o.tariff_insurance,o.transaction_id,o.sc_transaction_id,o.fulfillment_status,o.business_type,o.country,o.country_code,o.is_active,o.active_type,o.is_mvp,o.order_points,o.is_tariff_insurance,o.is_cod,o.coupon_id,o.order_from')
            ->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path])
            ->each(function ($item, $key) use ($shipping_model_config, $max_delivery_time_config, $front_order_model, &$order_number_arr, &$order_number_noc_arr, $fsc_order_number_arr, $max_delivery_time_config_mvp){
                $order_number_arr[] = $item['order_number'];
                $order_number = $item['order_number'];
                $currency_code = $item['currency_code'];
                $language_code = $item['language_code'];
                $country_code = $item['country_code'];
                $order_id = $item['order_id'];
                $is_mvp = $item['is_mvp'];
                /** 订单产品详情 **/
                //部分发货，或者删除商品的SKU不需要下载 tinghu.liu 20190515
                $item_data = $this->getOrderItemDataByWhere(['order_id' => $order_id], true);
                //是否是nocnoc订单
                $is_have_nocnoc = 0;
                //运输方式处理，如果是专线，需要将专线名称传过去，而不是具体哪个专线
                if (!empty($item_data)){
                    $temp_sku_num = [];
                    foreach ($item_data as &$info){
                        if(strtoupper($info['shipping_model']) == 'NOCNOC'){
                            $is_have_nocnoc = 1;
                        }
                        //初始化子运输方式，为了统一返回数据格式 tinghu.liu 20190411
                        $info['shipping_model_child'] = '';
                        if (
                            !empty($info['shipping_model'])
                            && !in_array(strtolower($info['shipping_model']), $shipping_model_config)
                        ){
                            $info['shipping_model_child'] = $info['shipping_model'];
                            $info['shipping_model'] = 'Exclusive';
                        }
                        /**
                         * 拼装产品地址 tinghu.liu 20191012
                         * https://www.dx.com/en/p/2015971?ta=US&tc=USD
                         * 因为海关限制了长度不能超过30，所以调整格式为 dx.com/p/2612078/1122709
                         */
//                        if (!empty($language_code)){
//                            $info['product_url'] = MALL_DOMAIN_URL.$language_code.'/p/'.$info['product_id'].'/'.$info['sku_num'].'.html?ta='.$country_code.'&tc='.$currency_code;
//                        }else{
//                            $info['product_url'] = MALL_DOMAIN_URL.'p/'.$info['product_id'].'/'.$info['sku_num'].'.html?ta='.$country_code.'&tc='.$currency_code;
//                        }
                        $info['product_url'] = MALL_DOMAIN_URL.'p/'.$info['product_id'].'/'.$info['sku_num'];
                        //拼装产品完整地址
                        $info['product_img'] = IMG_URL.'/'.$info['product_img'];
                        $temp_sku_num[] = $info['sku_num'];
                    }

                    //TODO FSC发货转为ERP发货，【情况2-a】处理 tinghu.liu 20190516
                    // TODO 【订单下载完之后需要删掉】
//                    $item_data = $this->getFsc2aHandle($order_number, $temp_sku_num, $item_data);
                }
                $item['item_data'] = $item_data;
                $item['is_have_nocnoc'] = $is_have_nocnoc;
                if ($is_have_nocnoc == 1){
                    $order_number_noc_arr[] = $item['order_number'];
                }
                /** 收货地址 **/
                $shipping_data = $this->getOrderShippingAddressDataByWhere(['order_id'=>$order_id]);
                $item['shipping_data'] = !empty($shipping_data)?$shipping_data[0]:[];
                //收货地址增加税号 tinghu.liu 20191126
                if (!isset($item['shipping_data']['cpf']) || empty($item['shipping_data']['cpf'])){
                    $cpf = '';
                    $order_other_info = $this->getOrderOtherInfoByOrderId($order_id);
                    if (!empty($order_other_info) && isset($order_other_info['cpf'])){
                        $cpf = $order_other_info['cpf'];
                    }
                    $item['shipping_data']['cpf'] = $cpf;
                }
                //增加最后发货时间（支付完成时间+指定时间）
                if ($is_mvp == 1){
                    $item['max_delivery_time'] = $item['pay_time'] + $max_delivery_time_config_mvp;
                }else{
                    $item['max_delivery_time'] = $item['pay_time'] + $max_delivery_time_config;
                }
                //是否是手机端订单 1-是，0-否
                // 订单来源：10-PC，20-Android，30-iOS，40-Pad，50-Mobile
                $item['is_cellphone'] = in_array($item['order_from'], [20,30,50])?1:0;
                //将下载的订单修改状态为407 - 开始配货 Configuring inventory (恒总讨论后) 20190320 tinghu.liu
                $up_status_data['is_start_trans'] = 1; //是否开启事务：1-开启（默认），2-不开启
                $up_status_data['order_id'] = $order_id;
                $up_status_data['order_status_from'] = $item['order_status'];
                $up_status_data['order_status'] = 407;
                $up_status_data['change_reason'] = 'Order picking completed.';
                $up_status_data['create_on'] = time();
                $up_status_data['create_by'] = 'APIsystemDownloadOrder';
                $up_status_data['create_ip'] = 0;
                $up_status_data['chage_desc'] = 'Order picking completed.';
                // -- 可选选项 --
                $up_status_data['fulfillment_status'] = 407;
                $up_status_data['is_record_change_info'] = 0; //是否记录状态改变日志，1-记录（默认），0-不记录
                //TODO 排除fsc换为erp发货的特殊订单不需要修改状态，因为已经是500 tinghu.liu 20190516
                // TODO 【订单下载完之后需要删掉】
                if (!in_array($order_number, $fsc_order_number_arr)){
                    $front_order_model->updateOrderStatus($up_status_data);
                }
                return $item;
            });
        $Page = $response->render();
        $data = $response->toArray();
        $data['Page'] = $Page;
        //处理NOCNOC订单拆单情况 tinghu.liu 20190413
        $data['data'] = $this->dataHandleForNocSplit($data['data'], $order_number_arr, $shipping_model_config, $max_delivery_time_config, $order_number_noc_arr, $max_delivery_time_config_mvp);
        $data['current_page_size'] = count($data['data']);
        return $data;
    }

    /**
     * // TODO 【订单下载完之后需要删掉】
     * FSC发货换为ERP发货 - 情况2-a 处理 【数据来源：4.xlsx】
     * 1.下载订单允许下载这10个状态为500的订单（ok）
     * 2.返回的item数据处理
     * 3.将已发货的sku去掉
     * @param $order_number
     * @param $temp_sku_num
     * @param $item_data
     * @return mixed
     */
    private function getFsc2aHandle($order_number, $temp_sku_num, $item_data){
        $fsc_order_number = $this->getFsc2aOrderNumber();
        $fsc_order_number_arr = $fsc_order_number['order_number_arr'];
        $fsc_order_number_skus = isset($fsc_order_number['all'][$order_number])?$fsc_order_number['all'][$order_number]:[];
        if (in_array($order_number, $fsc_order_number_arr)){
            //获取已发货的产品
            $diff_sku = array_diff($temp_sku_num, $fsc_order_number_skus);
            Log::record('getFsc2aHandle - 已发货sku:'.json_encode($diff_sku).', order_number:'.$order_number);
            if (!empty($diff_sku)){
                //去掉已发货的产品不返回给ERP
                foreach ($diff_sku as $k50=>$v50){
                    foreach ($item_data as $k51=>$v51){
                        if ($v51['sku_num'] == $v50){
                            unset($item_data[$k51]);
                        }
                    }
                }
            }
        }
        return $item_data;
    }
    private function getFsc2aOrderNumber(){
        return [
            //未发货的产品订单汇总
            'order_number_arr'=>[
                "190315100126671852",
                "190312100180159043",
                "190510100112770364",
                "181209100127697038",
                "190321100186548804",
                "190309100186417959",
                "190324100117052665",
                "190422100115914547",
                "190206100195905357",
                "190218100197407596"
            ],
            //未发货的产品
            'all'=>json_decode('
                    {
                        "190315100126671852":[
                             "137828", 
                             "502497", 
                             "4248", 
                             "626394", 
                             "405109", 
                             "438130", 
                             "625350", 
                             "484259", 
                             "150300", 
                             "6280"
                        ],
                        "190312100180159043":[
                            "462656",
                            "516126",
                            "517860"
                        ],
                        "190510100112770364":[
                            "591675", 
                            "549099"
                        ],
                        "181209100127697038":[
                            "492107", 
                            "488366", 
                            "258690"
                        ],
                        "190321100186548804":[
                             "480557",
                             "514961",
                             "422387"
                        ],
                        "190309100186417959":[
                            "509845",
                            "565535",
                            "570972",
                            "480994"
                        ],
                        
                        "190324100117052665":[
                            "617304"
                        ],
                        
                        "190422100115914547":[
                            "129389",
                            "308495",
                            "412746",
                            "162879",
                            "449901"
                        ],
                        
                        "190206100195905357":[
                            "594858",
                            "607317",
                            "513515",
                            "33438"
                        ],
                        
                        "190218100197407596":[
                            "304066", 
                            "367159", 
                            "570025" 
                        ]
                    }
                ', true)
        ];
    }

    /**
     * seller下载订单
     * @param array $params
     * @return array
     * @throws \think\exception\DbException
     */
    public function sellerDownloadOrder(array $params){
        //拉取标识：0-拉取满足条件400状态的订单（默认），1-拉取满足条件400&407状态的订单 tinghu.liu 20190327
        $flag = isset($params['flag'])?$params['flag']:0;
        $shipping_model_config = config('shipping_model_except_exclusive');
        $max_delivery_time_config = config('max_delivery_time');
        $front_order_model = new \app\orderfrontend\model\OrderModel();
        /*是否是分页*/
        $is_page = isset($params['is_page'])?$params['is_page']:1;
        foreach ($shipping_model_config as $k1=>$v2){
            $shipping_model_config[$k1] = strtolower($v2);
        }
        $where = array();
        $where['o.delete_time'] = 0;
        //排除锁住的订单（订单锁住状态(60:正常，未加锁,73:强制锁住，需手动解锁)） 20190320 tinghu.liu
        $where['o.lock_status'] = 60;
        //商家ID
        if (isset($params['store_id']) && !empty($params['store_id'])){
            $where['o.store_id'] = $params['store_id'];
        }
        //发货状态
        if (isset($params['fulfillment_status']) && !empty($params['fulfillment_status']) && $is_page == 1){
            $where['o.fulfillment_status'] = $params['fulfillment_status'];
            $where['o.order_status'] = $params['fulfillment_status'];
        }else{
            $where['o.fulfillment_status'] = 400;
            $where['o.order_status'] = 400;
        }
        //搜索订单支付时间，业务严慧敏要求将订单创建时间修改为支付时间，20190714 kevin
        if (!empty($params['create_on_start']) && !empty($params['create_on_end'])){
            $where['o.pay_time'] = ['BETWEEN',[strtotime($params['create_on_start']),strtotime($params['create_on_end'])]];
        }else{
            if(!empty($params['create_on_start'])){
                $where['o.pay_time'] = ['EGT',strtotime($params['create_on_start'])];
            }
            if(!empty($params['create_on_end'])){
                $where['o.pay_time'] = ['LT',strtotime($params['create_on_end'])];
            }
        }
        $order['o.create_on'] = 'desc';
        if($is_page == 1){
            //分页参数设置
            $page_size = isset($params['page_size']) && !empty($params['page_size']) ? (int)$params['page_size'] : 20;
            $page = isset($params['page']) && !empty($params['page']) ? (int)$params['page'] : 1;
            $path = isset($params['path']) && !empty($params['path']) ? $params['path'] : null;
            $query = isset($params['query']) && !empty($params['query']) ? $params['query'] : $where;
            //下载的订单号集合，为了处理有NOCNOC拆单的情况 tinghu.liu 20190413
            $order_number_arr = [];
            //是nocnoc的订单号集合  tinghu.liu 20190415
            $order_number_noc_arr = [];
            $response = $this->db->table($this->order_item)->alias('oi')->where($where)->order($order)->join($this->order.' o','oi.order_id=o.order_id','LEFT')
                ->join($this->order_package.' op','op.order_number=o.order_number','LEFT')
                ->join($this->order_package_item.' opi','opi.package_id=op.package_id','LEFT')
                ->join($this->order_shipping_address.' sa',"o.order_id=sa.order_id")
                ->field('oi.product_id,oi.sku_id,oi.sku_num,oi.product_nums,oi.shipping_model,oi.product_name,oi.captured_price_usd,oi.product_price,oi.shipping_model,
                  o.order_id, o.parent_id, o.order_number, o.order_master_number, o.store_id, o.store_name,
                  o.customer_id, o.customer_name, o.order_status, o.lock_status,
                  o.goods_count, o.goods_total, o.discount_total, o.shipping_fee, o.handling_fee,o.total_amount,o.grand_total,
                  o.captured_amount_usd,o.captured_amount,o.currency_code,o.shipping_count,
                  o.shipped_count,o.shipped_amount,o.exchange_rate,o.create_on,o.complete_on,
                  o.shipping_insurance_enabled,o.shipping_insurance_fee,o.shipping_fee_discount,
                  o.logistics_provider,o.pay_type,o.pay_channel,o.affiliate,o.remark,o.shipments_time,o.shipments_complete_time,o.tariff_insurance_enabled,
                  o.tariff_insurance,o.transaction_id,o.fulfillment_status,o.business_type,o.country,o.country_code,o.is_tariff_insurance,
                  o.order_from,sa.first_name,sa.last_name,sa.phone_number,sa.postal_code,
                  sa.street1,sa.street2,sa.city,sa.city_code,sa.state,sa.state_code,sa.country,sa.country_code,sa.email,sa.mobile,op.package_number,
                  op.tracking_number,op.shipping_channel_name,op.shipping_channel_name_cn,opi.item_id opi_item_id,opi.package_id,opi.sku_id opi_sku_id,opi.sku_qty opi_sku_qty')
                ->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path,'query'=>$query]);
                $Page = $response->render();
                $data = $response->toArray();
                $data['Page'] = $Page;
                //处理NOCNOC订单拆单情况 tinghu.liu 20190413
                //$data['data'] = $this->dataHandleForNocSplit($data['data'], $order_number_arr, $shipping_model_config, $max_delivery_time_config, $order_number_noc_arr);
                return $data;
        }else{
            $response = $this->db->table($this->order_item)->alias('oi')->where($where)->order($order)->join($this->order.' o','oi.order_id=o.order_id','LEFT')
                ->join($this->order_package.' op','op.order_number=o.order_number','LEFT')
                ->join($this->order_package_item.' opi','opi.package_id=op.package_id','LEFT')
                ->join($this->order_shipping_address.' sa',"o.order_id=sa.order_id")
                ->field('oi.product_id,oi.sku_id,oi.sku_num,oi.product_nums,oi.shipping_model,oi.product_name,oi.captured_price_usd,oi.product_price,oi.shipping_model,
                  o.order_id, o.parent_id, o.order_number, o.order_master_number, o.store_id, o.store_name,
                  o.customer_id, o.customer_name, o.order_status, o.lock_status,
                  o.goods_count, o.goods_total, o.discount_total, o.shipping_fee, o.handling_fee,o.total_amount,o.grand_total,
                  o.captured_amount_usd,o.captured_amount,o.currency_code,o.shipping_count,
                  o.shipped_count,o.shipped_amount,o.exchange_rate,o.create_on,o.complete_on,
                  o.shipping_insurance_enabled,o.shipping_insurance_fee,o.shipping_fee_discount,
                  o.logistics_provider,o.pay_type,o.pay_channel,o.affiliate,o.remark,o.shipments_time,o.shipments_complete_time,o.tariff_insurance_enabled,
                  o.tariff_insurance,o.transaction_id,o.fulfillment_status,o.business_type,o.country,o.country_code,o.is_tariff_insurance,
                  o.order_from,sa.first_name,sa.last_name,sa.phone_number,sa.postal_code,
                  sa.street1,sa.street2,sa.city,sa.city_code,sa.state,sa.state_code,sa.country,sa.country_code,sa.email,sa.mobile,op.package_number,
                  op.tracking_number,op.shipping_channel_name,op.shipping_channel_name_cn,opi.item_id opi_item_id,opi.package_id,opi.sku_id opi_sku_id,opi.sku_qty opi_sku_qty')
                ->select();
            //将下载的订单修改状态为407 - 开始配货 Configuring inventory (恒总讨论后) 20190320 tinghu.liu
            foreach ($response as $key=>$value){
                $up_status_data['is_start_trans'] = 0; //是否开启事务：1-开启（默认），2-不开启
                $up_status_data['order_id'] = $value['order_id'];
                $up_status_data['order_status_from'] = $value['order_status'];
                $up_status_data['order_status'] = 407;
                $up_status_data['change_reason'] = 'Seller Download Order';
                $up_status_data['create_on'] = time();
                $up_status_data['create_by'] = 'SellsystemDownloadOrder';
                $up_status_data['create_ip'] = 0;
                $up_status_data['chage_desc'] = 'Seller Download Order';
                // -- 可选选项 --
                $up_status_data['fulfillment_status'] = 407;
                $up_status_data['is_record_change_info'] = 0; //是否记录状态改变日志，1-记录（默认），0-不记录
                // TODO 【订单下载完之后需要删掉】
                //Log::write("up_status_data:".json_encode($up_status_data));
                $update_order_status_res = $front_order_model->updateOrderStatus($up_status_data);
                if(!$update_order_status_res){
                    unset($response[$key]);
                }
            }
            //Log::write("response:".json_encode($response));
            return $response;
        }
    }

    /**
     * 下载订单处理NOCNOC拆单情况 tinghu.liu 20190413
     * @param $data
     * @param $order_number_arr
     * @param $shipping_model_config
     * @param $max_delivery_time_config
     * @param $order_number_noc_arr
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private function dataHandleForNocSplit($data, $order_number_arr, $shipping_model_config, $max_delivery_time_config, $order_number_noc_arr,$max_delivery_time_config_mvp){
        $rtn = [];
        //少的字段：o.parent_id,多的字段：o.source_order_id, o.source_order_number
        $source_data = $this->db->table($this->nocsplit_sales_order)->alias('o')->field('o.source_order_id, o.source_order_number, o.order_id,  o.order_number, o.source_order_master_number as order_master_number, o.store_id, o.store_name, o.customer_id, o.customer_name, o.payment_status, o.order_status, o.order_branch_status, o.lock_status, o.goods_count, o.goods_total, o.discount_total, o.shipping_fee, o.handling_fee,o.total_amount,o.grand_total,o.captured_amount_usd,o.captured_amount,o.refunded_amount,o.refunding_amount,o.currency_code,o.shipping_count,o.shipped_count,o.shipped_amount,o.adjust_price,o.order_type,o.exchange_rate,o.language_code,o.create_on,o.complete_on,o.shipping_insurance_enabled,o.shipping_insurance_fee,o.bulk_rate_enabled,o.receivable_shipping_fee,o.shipping_fee_discount,o.logistics_provider,o.pay_type,o.pay_channel,o.affiliate,o.remark,o.pay_time,o.shipments_time,o.shipments_complete_time,o.tariff_insurance_enabled,o.tariff_insurance,o.transaction_id,o.sc_transaction_id,o.fulfillment_status,o.business_type,o.country,o.country_code,o.is_active,o.active_type,o.is_mvp,o.order_points,o.is_tariff_insurance,o.is_cod,o.coupon_id,o.order_from')->where('source_order_number', 'in', $order_number_arr)->select();
        //NOCNOC已经拆过单的订单
        if (!empty($source_data)){
            //已经拆单的订单号
            $split_order_number = [];
            /** 组装拆单的数据 **/
            foreach ($source_data as $k=>$v){
                //补充字段，为了返回数据格式一致性
                $source_data[$k]['parent_id'] = 0;
                $noc_order_id = $v['order_id'];
                $noc_order_number = $v['order_number'];
                $source_order_id = $v['source_order_id'];
                $source_order_number = $v['source_order_number'];

                $currency_code = $v['currency_code'];
                $language_code = $v['language_code'];
                $country_code = $v['country_code'];
                $is_mvp = $v['is_mvp'];

                $split_order_number[] = $source_order_number;
                //去掉多余的key
                unset($source_data[$k]['source_order_id']);
                unset($source_data[$k]['source_order_number']);
                //1. item数据
                $item_data = $this->db->table($this->nocsplit_sales_order_item)->where([
                    'order_id'=>$noc_order_id
                ])->select();
                //运输方式处理，如果是专线，需要将专线名称传过去，而不是具体哪个专线
                if (!empty($item_data)){
                    foreach ($item_data as $k2=>$v2){
                        //初始化子运输方式，为了统一返回数据格式 tinghu.liu 20190411
                        $item_data[$k2]['shipping_model_child'] = '';
                        if (
                            !empty($v2['shipping_model'])
                            && !in_array(strtolower($v2['shipping_model']), $shipping_model_config)
                        ){
                            $item_data[$k2]['shipping_model_child'] = $v2['shipping_model'];
                            $item_data[$k2]['shipping_model'] = 'Exclusive';
                        }
                        //拼装产品地址 tinghu.liu 20191012
                        $item_data[$k2]['product_url'] = MALL_DOMAIN_URL.'p/'.$v2['product_id'].'/'.$v2['sku_num'].'.html';

                        /**
                         * 拼装产品地址 tinghu.liu 20191012
                         * https://www.dx.com/en/p/2015971?ta=US&tc=USD
                         * 因为海关限制了长度不能超过30，所以调整格式为 dx.com/p/2612078/1122709
                         */
//                        if (!empty($language_code)){
//                            $item_data[$k2]['product_url'] = MALL_DOMAIN_URL.$language_code.'/p/'.$v2['product_id'].'/'.$v2['sku_num'].'.html?ta='.$country_code.'&tc='.$currency_code;
//                        }else{
//                            $item_data[$k2]['product_url'] = MALL_DOMAIN_URL.'p/'.$v2['product_id'].'/'.$v2['sku_num'].'.html?ta='.$country_code.'&tc='.$currency_code;
//                        }
                        $item_data[$k2]['product_url'] = MALL_DOMAIN_URL.'p/'.$v2['product_id'].'/'.$v2['sku_num'];
                        //拼装产品完整地址
                        $item_data[$k2]['product_img'] = IMG_URL.'/'.$v2['product_img'];
                    }
                }
                $source_data[$k]['item_data'] = $item_data;
                //2. 收货地址数据
                $shipping_data = $this->getOrderShippingAddressDataByWhere(['order_id'=>$source_order_id]);
                $source_data[$k]['shipping_data'] = !empty($shipping_data)?$shipping_data[0]:[];
                //收货地址增加税号 tinghu.liu 20191126
                if (!isset($source_data[$k]['shipping_data']['cpf']) || empty($source_data[$k]['shipping_data']['cpf'])){
                    $cpf = '';
                    $order_other_info = $this->getOrderOtherInfoByOrderId($source_order_id);
                    if (!empty($order_other_info) && isset($order_other_info['cpf'])){
                        $cpf = $order_other_info['cpf'];
                    }
                    $source_data[$k]['shipping_data']['cpf'] = $cpf;
                }
                //增加最后发货时间（支付完成时间+指定时间）
                if ($is_mvp == 1){
                    $source_data[$k]['max_delivery_time'] = $source_data[$k]['pay_time'] + $max_delivery_time_config_mvp;
                }else{
                    $source_data[$k]['max_delivery_time'] = $source_data[$k]['pay_time'] + $max_delivery_time_config;
                }
                //是否是手机端订单 1-是，0-否
                // 订单来源：10-PC，20-Android，30-iOS，40-Pad，50-Mobile
                $source_data[$k]['is_cellphone'] = in_array($source_data[$k]['order_from'], [20,30,50])?1:0;
                $rtn[] = $source_data[$k];
            }
            $split_order_number = array_unique($split_order_number);
            /** 将没有拆单的订单数据重新拼装 **/
            foreach ($data as $k3=>$v3){
                if (!in_array($v3['order_number'], $split_order_number)){
                    $rtn[] = $v3;
                }
            }
            //避免是nocnoc但是没有拆单成功的情况，只有在nocnoc拆单成功的情况下才允许下载nocnoc订单 tinghu.liu 20190415
            $order_number_noc_diff_arr = array_diff($order_number_noc_arr, $split_order_number);
            if (!empty($order_number_noc_diff_arr)){
                foreach ($order_number_noc_diff_arr as $k10=>$v10){
                    foreach ($rtn as $k11=>$v11){
                        if ($v11['order_number'] == $v10){
                            unset($rtn[$k11]);
                        }
                    }
                }
            }
        }else{
            $rtn = $data;
        }
        return $rtn;
    }

    public function getAdminCustomerOrder($param){
        $data['order_total'] = $this->db->table($this->order)->where($param)->count();
        $complete_where = $param;
        $complete_where['order_status'] = ["IN","900,1000,1100,1200,1300"];
        $data['complete_order'] = $this->db->table($this->order)->where($complete_where)->count();
        $processing_where = $param;
        $processing_where['order_status'] = 300;
        $data['processing_order'] = $this->db->table($this->order)->where($processing_where)->count();
        $chargeback_where = $param;
        $data['refunded_order'] = $this->db->table($this->order_refund)->where($chargeback_where)->count();
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

    /**
     * 根据订单号获取订单状态
     * @param $params
     * @return array|false|\PDOStatement|string|Model
     */
    public function getOrderStatusByOrderNum($params){
        //order_num
        return $this->db->table($this->order)
            ->where('order_number','=', $params['order_num'])
            ->field('order_number as order_num,order_status as status,order_branch_status as branch_status,lock_status')
            ->find();
    }
    /**
     * 根据条件逻辑删除或修改订单详情表订单
     *
     */
    public function update_sku($data = array()){
        if(!empty($data['delete_sku'][1])){
             $where = [];
             $where_oms = [];//Log::record('11111111111111111111111111111111111111111111111');
             foreach ($data['delete_sku'][1] as $k => $v) {
                 $list = $this->db->table($this->order_item)->where(['order_id'=>$data["order_id"],'sku_num'=>$v[0]])->find();
                 if(!empty($list)){
                     $udate_result = '';
                     $where_oms['lines'][] =  ["Sku"=>$v[0],"Num"=>$v[1], "UnitPrice"=>$list["captured_price"]];

                     if($v[1] <= $list['product_nums']){
                             $product_nums = $list['product_nums'] - $v[1];
                             $udate_result = $this->db->table($this->order_item)->where(['order_id'=>$data["order_id"],'sku_num'=>$v[0]])->update(['product_nums'=>$product_nums]);
                             if(!empty($udate_result)){
                                   Log::record('部分退款sku修改成功：'.'订单表order_id：'.$data["order_id"].'SKU:'.$v[0].'把购买数量从'.$list['product_nums'].'退为'.$product_nums);
                             }else{
                                   Log::record('部分退款sku修改  error：'.'订单表order_id：'.$data["order_id"].'SKU:'.$v[0].'把购买数量从'.$list['product_nums'].'退为'.$product_nums);
                             }
                     }
                     // else if($v[1] == $list['product_nums']){
                     //         //改为1表示退款退货
                     //         $udate_result = $this->db->table($this->order_item)->where(['order_id'=>$data["order_id"],'sku_num'=>$v[0]])->update(['order_product_status'=>1]);
                     //         if(!empty($udate_result)){
                     //               Log::record('订单退款sku修改成功：'.'订单表order_id：'.$data["order_id"].'SKU:'.$v[0].'已逻辑删除');
                     //               // $log['reason'] = '订单表order_id：'.$data["order_id"].'SKU:'.$v[0].'把购买数量从'.$list['product_nums'].'退为'.$product_nums;
                     //         }else{
                     //               Log::record('订单退款sku修改失败  error：'.'订单表order_id：'.$data["order_id"].'SKU:'.$v[0]);
                     //               Log::record('mysql'. $this->db->table($this->order_item)->getLastSql());
                     //         }
                     // }
                 }
             }

             $where_oms['OrderNumber'] = $data['delete_sku'][0];
             $post_config = config('CancelOrderSKU');
             // $post_config['user_name'] = 'admin';
             // $post_config['pass_word'] = '123456';
             $header = array(
                "Content-Type: application/json",
                "Authorization: Basic ".base64_encode($post_config['user_name'].":".$post_config['pass_word'])
                );

             //把需要删除或者减少的产品数量推送OMS
             $result = doCurl($post_config['URL'],$where_oms,$options = null,true,$header);
             if($result['IsSuccess'] == false){
                 Log::record('OMS结果ERROR-> request：'.json_encode($result));
             }else{
                 Log::record('OMS结果成功-> request：'.json_encode($result));
             }
             // Log::record('OMS结果-> request11：'.json_encode($where_oms));

             //删除订单sku后需要检查是否为全部发货，是则修改为“全部发货”状态，且记录状态变化日志
             $this->orderStatusCheckForAdminRefundDeleteSkus($data['delete_sku'][0], $data["order_id"]);
             return $result;
        }

    }

    /**
     * 订单发货状态修改
     * @param $order_number
     * @param $order_id
     * @throws \Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    private function orderStatusCheckForAdminRefundDeleteSkus($order_number, $order_id){
        //获取已发货数量 a
        $package_data = $this->db->table($this->order_package)->where(['order_number'=>$order_number])->select();
        if (!empty($package_data)){
            $package_id_arr = array_column($package_data, 'package_id');
            $shipped_count = $this->db->table($this->order_package_item)->where('package_id', 'in', $package_id_arr)->sum('sku_qty'); //获取已发货数量 a
            $product_count = $this->db->table($this->order_item)->where(['order_id'=>$order_id])->sum('product_nums'); //获取修改产品数量后的产品总数 b

            //a >= b ,则修改订单状态为“部分发货”的状态修改为“全部发货”且记录状态变化日志
            if ($shipped_count >= $product_count){
                $order_info = $this->db->table($this->order)->where(['order_id'=>$order_id])->field('order_status')->find();
                if ($order_info['order_status'] == 500){

                    $_change_status_data['order_id'] = $order_id;
                    $_change_status_data['order_status_from'] = $order_info['order_status'];
                    $_change_status_data['order_status'] = 600;
                    $_change_status_data['change_reason'] = 'After the SKU refund, all remaining SKUs have been shipped.';
                    $_change_status_data['create_on'] = time();
                    $_change_status_data['create_by'] = 'ADMIN-API System';
                    $_change_status_data['create_ip'] = 0;
                    $_change_status_data['chage_desc'] = 'All items have been shipped.';
                    $res = (new \app\orderfrontend\model\OrderModel())->updateOrderStatus($_change_status_data);
                    Log::record('后台退款导致订单发货状态变化操作返回：'.$res?'（成功）':'（失败）');
                }
            }
        }
    }

    /**
     * 获取退款操作记录
     * @param $params
     * @return array|false|\PDOStatement|string|Model
     */
    public function getOrderRefundOperation($params){
        return $this->db->table($this->order_sales_order_refund_operation)
            ->where($params)
            ->order("id","DESC")
            ->select();
    }


    /**
     * 获取OMS推送订单状态记录
     * @param $params
     * @return array|false|\PDOStatement|string|Model
     */
    public function getOrderStatusOmsRecord($params,$page_size,$page,$path='',$page_query=''){
        $response = $this->db->table($this->sales_order_status_oms_record)
            ->where($params)
            ->order("record_id","DESC")
            ->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path,'query'=>!empty($page_query)?$page_query:$params]);
        $Page = $response->render();
        $data = $response->toArray();
        $data['Page'] = $Page;
        return $data;
    }

    /**
     * 获取订单折扣异常记录
     * @param $params
     * @return array|false|\PDOStatement|string|Model
     */
    public function getOrderDiscountExceptionList($params,$page_size,$page,$path='',$page_query=''){
        $response = $this->db->table($this->sales_order_discount_exception)
            ->where($params)
            ->order("exception_id","DESC")
            ->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path,'query'=>!empty($page_query)?$page_query:$params]);
        $Page = $response->render();
        $data = $response->toArray();
        $data['Page'] = $Page;
        return $data;
    }

    /**
     * 海外仓发货单导出 20190715 kevin
     * @param $params
     * @return array|false|\PDOStatement|string|Model
     */
    public function getDeliveryOrder($params,$is_export,$page_size,$page,$path='',$page_query=''){
        /*判断是否是导出数据，导出数据不需要分页*/
        $params['op.is_delete'] = 0;
        if($is_export == 0){
            $response = $this->db->table($this->order)
                ->alias("o")
                //->join($this->order_item." oi","o.order_id=oi.order_id")
                ->join($this->order_package." op","o.order_number=op.order_number")
                ->join($this->order_package_item." opi","op.package_id=opi.package_id")
                ->join($this->order_shipping_address." osa","osa.order_id=o.order_id")
                ->group("opi.item_id")
                //->join($this->order_package_item." opi","oi.sku_num=opi.sku_id","RIGHT")
                ->where($params)
                /*20190910 kevin 将发货时间o.shipments_time改为包裹op.add_time shipments_time*/
                ->field("o.order_id,o.order_number,opi.sku_id,o.currency_code,opi.sku_qty,
                    o.create_on,osa.street1,osa.street2,osa.city,osa.state,osa.state,
                    osa.country,osa.country_code,osa.first_name,osa.last_name,op.shipping_channel_name,op.shipping_channel_name_cn,op.tracking_number,op.add_time shipments_time,o.exchange_rate")
                ->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path,'query'=>!empty($page_query)?$page_query:$params])
                ->each(function ($item,$key){
                    $sku_data_where['order_id'] = $item['order_id'];
                    $sku_data_where['sku_num'] = $item['sku_id'];
                    $sku_data = $this->db->table($this->order_item)->where($sku_data_where)->field("captured_price,captured_price_usd,shipping_fee,discount_total,first_category_id")->find();
                    if(!empty($sku_data)){
                        $item['captured_price'] = $sku_data['captured_price'];
                        $item['captured_price_usd'] = $sku_data['captured_price_usd'];
                        $item['shipping_fee'] = $sku_data['shipping_fee'];
                        $item['discount_total'] = $sku_data['discount_total'];
                        $item['first_category_id'] = $sku_data['first_category_id'];
                    }
                    return $item;
                });
            $Page = $response->render();
            $data = $response->toArray();
            $data['Page'] = $Page;
        }elseif($is_export == 1){
            $data = $this->db->table($this->order)
                ->alias("o")
                //->join($this->order_item." oi","o.order_id=oi.order_id")
                ->join($this->order_package." op","o.order_number=op.order_number")
                ->join($this->order_package_item." opi","op.package_id=opi.package_id")
                ->join($this->order_shipping_address." osa","osa.order_id=o.order_id")
                //->join($this->order_package_item." opi","oi.sku_num=opi.sku_id","RIGHT")
                ->where($params)
                ->field("o.order_id,o.order_number,opi.sku_id,o.currency_code,opi.sku_qty,
                    o.create_on,o.shipments_time,osa.street1,osa.street2,osa.city,osa.state,osa.state,
                    osa.country,osa.country_code,osa.first_name,osa.last_name,op.shipping_channel_name,op.shipping_channel_name_cn,op.tracking_number,o.exchange_rate")
                ->select();
                foreach ($data as $key=>&$item){
                    $sku_data_where['order_id'] = $item['order_id'];
                    $sku_data_where['sku_num'] = $item['sku_id'];
                    $sku_data = $this->db->table($this->order_item)->where($sku_data_where)->field("captured_price,captured_price_usd,shipping_fee,discount_total,first_category_id")->find();
                    if(!empty($sku_data)){
                        $item['captured_price'] = $sku_data['captured_price'];
                        $item['captured_price_usd'] = $sku_data['captured_price_usd'];
                        $item['shipping_fee'] = $sku_data['shipping_fee'];
                        $item['discount_total'] = $sku_data['discount_total'];
                        $item['first_category_id'] = $sku_data['first_category_id'];
                    }
                }
        }elseif($is_export == 2){
            $data = $this->db->table($this->order)
                ->alias("o")
                ->join($this->order_package." op","o.order_number=op.order_number")
                ->where($params)
                ->group("o.order_number")
                ->field("o.order_id,o.order_number,o.create_on,o.shipments_time,currency_code,tariff_insurance,country,country_code")
                ->select();
        }
        return $data;
    }

    /**
     * 获取订单主表信息 20190930 kevin
     * @param $params
     * @return array|false|\PDOStatement|string|Model
     */
    public function getOrderByInfoWhere($where = array()){
        $data = $this->db->table($this->order)->where($where)->find();
        return $data;
    }

    /**
     * 根据订单ID获取订单扩展表信息
     * @param $order_id
     * @return array|false|\PDOStatement|string|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getOrderOtherInfoByOrderId($order_id){
        return $this->db->table($this->order_other)->where(['order_id'=>$order_id])->find();
    }
}