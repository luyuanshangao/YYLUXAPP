<?php
namespace app\orderbackend\model;

use app\common\helpers\BaseApi;
use app\orderbackend\services\Base;
use think\Model;
use think\Db;
use think\Log;

/**
 * 订单退款模型
 * Class OrderRefundModel
 * @author tinghu.liu 2018/5/16
 * @package app\orderFront\model
 */
class OrderRefundModel extends Model{

    /**
     * 数据库连接对象
     * @var \think\db\Connection
     */
	private $db;
    /**
     * 订单主表
     * @var string
     */
    protected $table_sales_order = "dx_sales_order";
    /**
     * 售后处理主表
     * @var string
     */
	protected $table = "dx_order_after_sale_apply";
    /**
     * 售后处理详情表
     * @var string
     */
	protected $table_item = "dx_order_after_sale_apply_item";
    /**
     * 售后处理记录表
     * @var string
     */
	protected $table_log = "dx_order_after_sale_apply_log";
    /**
     * 售后纠纷表
     * @var string
     */
	protected $table_complaint = "dx_order_complaint";
	protected $table_return_product_expressage = "dx_return_product_expressage";
    protected $table_order = "dx_sales_order";
    /*
     * 退款表
     * */
    protected $table_order_refund = "dx_order_refund";
    /*
     * 退款详情表
     * */
    protected $table_order_refund_item = "dx_order_refund_item";
    /*
     * 退款处理记录
     * */
    protected $table_order_refund_log = "dx_order_refund_log";
    /*
     * 订单收货地址
     * */
    protected $table_shipping_address = "dx_order_shipping_address";
    /*
     * 交易明细表
     * */
    protected $table_sales_txn = "dx_sales_txn";
    /*
     * 交易明细表
     * */
    protected $table_sales_order_refund_operation = "dx_sales_order_refund_operation";


    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_order');
    }

    /**
     * 获取列表数据【分页】
     * @param array $params 条件
     * @return array
     */
    public function getListDataForPage(array $params){
        $query = $this->db->table($this->table);
        //商家ID
        if (isset($params['store_id']) && !empty($params['store_id'])){
            $query->where('store_id', '=', $params['store_id']);
        }
        //售后类型
        if (isset($params['type']) && !empty($params['type'])){
            $query->where('type', '=', $params['type']);
        }
        //申请时间
        if (
            (isset($params['create_on_start']) && !empty($params['create_on_start']))
            && (isset($params['create_on_end']) && !empty($params['create_on_end']))
        ){
            $query->where('add_time', '>=', $params['create_on_start']);
            $query->where('add_time', '<=', $params['create_on_end']);
        }
        //售后申请状态
        if (isset($params['status']) && !empty($params['status'])){
            $query->where('status', '=', $params['status']);
        }
        //待处理倒计时 count_down_type：1-12天及以下，2-9天及以下，3-6天及以下，4-3天及以下，5-1天及以下  TODO
        /*
        //待买家发货 && (换货 || 退货) -15天倒计时
        if($val['status'] == 2 && ($val['type'] == 1 || $val['type'] == 2 )){
            $count_down = $flag1>0?$flag1:0;
        }
        //待卖家收货 30天倒计时
        if($val['status'] == 3){
            $count_down = $flag2>0?$flag2:0;
        }
        */
        //订单号
        if (isset($params['order_number']) && !empty($params['order_number'])){
            $query->where('order_number', '=', $params['order_number']);
        }
        //售后单号
        if (isset($params['after_sale_number']) && !empty($params['after_sale_number'])){
            $query->where('after_sale_number', '=', $params['after_sale_number']);
        }
        if (isset($params['count_down_type']) && !empty($params['count_down_type'])){
            $time = time();
            $flag_day = 0;
            $status_limit_day1 = config('order_after_sale_status_limit_day1');
            //只有在“待买家发货”和“待卖家收货”状态是才有倒计时
            $query->where('status=2 OR status=3');
            switch ($params['count_down_type']){
                case 1:
                    $flag_day = 12;
                    break;
                case 2:
                    $flag_day = 9;
                    break;
                case 3:
                    $flag_day = 6;
                    break;
                case 4:
                    $flag_day = 3;
                    break;
                case 5:
                    $flag_day = 1;
                    break;
            }
            $flag_time = $time + ($flag_day-$status_limit_day1)*24*60*60;
            $query->where('edit_time', '<=', $flag_time);
        }
        //平台是否介入 is_platform_intervention
        if (isset($params['is_platform_intervention']) && !empty($params['is_platform_intervention'])){
            $is_platform_intervention = $params['is_platform_intervention'];
            if ($is_platform_intervention == 1){
                $query->where('is_platform_intervention', '=', 1);
            }elseif ($is_platform_intervention == 2){
                $query->where('is_platform_intervention', '=', 0);
            }
        }
        //分页参数设置
        $page_size = isset($params['page_size']) && !empty($params['page_size']) ? (int)$params['page_size'] : 10;
        $page = isset($params['page']) && !empty($params['page']) ? (int)$params['page'] : 1;
        $path = isset($params['path']) && !empty($params['path']) ? $params['path'] : null;
        //排序
        $query->order('add_time', 'desc');
        //获取数据
        $response = $query->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path])
            ->each(function ($item, $key){
                //申请售后类型
                $type_arr = Base::getOrderAfterSaleType($item['type']);
                $item['type_str'] = isset($type_arr['name'])?$type_arr['name']:'-';
                //售后申请状态
                $after_sale_status = config('after_sale_status');
                $status_str = '';
                foreach ($after_sale_status as $status){
                    if ($item['status'] == $status['code']){
                        $status_str = $status['name'];
                        break;
                    }
                }
                $item['status_str'] = $status_str;
                //平台介入
                $item['is_platform_intervention_str'] = $item['is_platform_intervention'] == 1?'已介入':'未介入';
                //售后原因
                $after_sale_reason = config('after_sale_reason');
                $reason_str = '';
                foreach ($after_sale_reason as $reason){
                    if ($item['after_sale_reason'] == $reason['code']){
                        $reason_str = $reason['name'];
                        break;
                    }
                }
                $item['after_sale_reason_str'] = $reason_str;
                //订单售后管理状态限制天数
                $item['count_down_limit_day1'] = config('order_after_sale_status_limit_day1');
                $item['count_down_limit_day2'] = config('order_after_sale_status_limit_day2');
                //获取订单售后产品详情
                $item['item_info'] = $this->getApplyItemDataByWhere(['after_sale_id'=>$item['after_sale_id']]);
                $item['expressage_info'] = $this->db->table($this->table_return_product_expressage)->where(['after_sale_id'=>$item['after_sale_id']])->find();
                //获取订单币种
                $order_info = $this->db->table($this->table_order)->where(['order_number'=>$item['order_number']])->field('currency_code')->find();
                $item['currency_code'] = $order_info['currency_code'];
                $item['log'] = $this->db->table($this->table_log)->where(['after_sale_id'=>$item['after_sale_id']])->select();
                if(!empty($item['log'])){
                    foreach ($item['log'] as $k=>$v){
                        $item['log'][$k]['imgs'] = isset($v['imgs']) && !empty($v['imgs'])?json_decode(htmlspecialchars_decode($v['imgs']), true):[];
                    }
                }
                return $item;
            });
        $Page = $response->render();
        $data = $response->toArray();
        $data['Page'] = $Page;
        return $data;
    }

    /**
     * 获取退款列表数据【分页】
     * @param array $params 条件
     * @return array
     */
    public function getOrderListDataForPage(array $params){
        $query = $this->db->table($this->table);
        //商家ID
        if (isset($params['store_id']) && !empty($params['store_id'])){
            $query->where('store_id', '=', $params['store_id']);
        }
        //售后类型
        if (isset($params['type']) && !empty($params['type'])){
            $query->where('type', '=', $params['type']);
        }
        //申请时间
        if (
            (isset($params['create_on_start']) && !empty($params['create_on_start']))
            && (isset($params['create_on_end']) && !empty($params['create_on_end']))
        ){
            $query->where('add_time', '>=', $params['create_on_start']);
            $query->where('add_time', '<=', $params['create_on_end']);
        }
        //申请时间
        if (
            (isset($params['startTime']) && !empty($params['startTime']))
            && (isset($params['endTime']) && !empty($params['endTime']))
        ){
            $query->where('add_time', '>=', $params['startTime']);
            $query->where('add_time', '<=', $params['endTime']);
        }
        //售后申请状态
        if (isset($params['status']) && !empty($params['status'])){
            $query->where('status', '=', $params['status']);
        }
        //售后申请状态
        if (isset($params['after_sale_status']) && !empty($params['after_sale_status'])){
            $query->where('status', '=', $params['after_sale_status']);
        }
        //退换货状态
        if (isset($params['refunded_type']) && !empty($params['refunded_type'])){
            $query->where('refunded_type', '=', $params['refunded_type']);
        }

        //订单号
        if (isset($params['order_number']) && !empty($params['order_number'])){
            $query->where('order_number', '=', $params['order_number']);
        }
        //售后单号
        if (isset($params['after_sale_number']) && !empty($params['after_sale_number'])){
            $query->where('after_sale_number', '=', $params['after_sale_number']);
        }
        //国家名字
        if (isset($params['customer_name']) && !empty($params['customer_name'])){
            $query->where('customer_name', '=', $params['customer_name']);
        }
        //店铺名字
        if (isset($params['store_name']) && !empty($params['store_name'])){
            $query->where('store_name', '=', $params['store_name']);
        }
        //时间 改动小,不用连表,但是性能差,excel导出使用还行
        if(isset($params['PlaceAnOrderStartTime']) && !empty($params['PlaceAnOrderStartTime']) && isset($params['PlaceAnOrderEndTime']) && !empty($params['PlaceAnOrderEndTime'])){
            $map['create_on']   = array(array('egt',$params['PlaceAnOrderStartTime']),array('elt',$params['PlaceAnOrderEndTime']));
            $order_id = $this->db->table($this->table_order)->where($map)->column('order_id');
            if($order_id){
                $query->where('order_id', 'in', $order_id);
            }
        }

        if (isset($params['count_down_type']) && !empty($params['count_down_type'])){
            $time = time();
            $flag_day = 0;
            $status_limit_day1 = config('order_after_sale_status_limit_day1');
            //只有在“待买家发货”和“待卖家收货”状态是才有倒计时
            $query->where('status=2 OR status=3');
            switch ($params['count_down_type']){
                case 1:
                    $flag_day = 12;
                    break;
                case 2:
                    $flag_day = 9;
                    break;
                case 3:
                    $flag_day = 6;
                    break;
                case 4:
                    $flag_day = 3;
                    break;
                case 5:
                    $flag_day = 1;
                    break;
            }
            $flag_time = $time + ($flag_day-$status_limit_day1)*24*60*60;
            $query->where('edit_time', '<=', $flag_time);
        }
        //平台是否介入 is_platform_intervention
        if (isset($params['is_platform_intervention']) && !empty($params['is_platform_intervention'])){
            $is_platform_intervention = $params['is_platform_intervention'];
            if ($is_platform_intervention == 1){
                $query->where('is_platform_intervention', '=', 1);
            }elseif ($is_platform_intervention == 2){
                $query->where('is_platform_intervention', '=', 0);
            }
        }
        //分页参数设置
        $page_size = isset($params['page_size']) && !empty($params['page_size']) ? (int)$params['page_size'] : 10;
        $page = isset($params['page']) && !empty($params['page']) ? (int)$params['page'] : 1;
        $path = isset($params['path']) && !empty($params['path']) ? $params['path'] : null;

        //排序
        $query->order('add_time', 'desc');
        //获取数据
        $response = $query->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path])
            ->each(function ($item, $key){
                //获取订单数据
                $order_info = $this->db->table($this->table_order)->where(['order_number'=>$item['order_number']])
                    ->field('goods_total,country,country_code,currency_code')->find();
                $item['goods_total'] = $order_info['goods_total'];
                $item['country'] = $order_info['country'];
                $item['country_code'] = $order_info['country_code'];
                $item['currency_code'] = $order_info['currency_code'];
                return $item;
            });
        $Page = $response->render();
        $data = $response->toArray();
        $data['Page'] = $Page;
        return $data;
    }

    /**
     * 获取纠纷列表（含分页）
     * @param $params 参数
     * @return array
     */
    public function getComplaintDataForPage($params){
        $query = $this->db->table($this->table_complaint)->alias('tc');
        $join = [
            [$this->table_item.' ti','tc.after_sale_id=ti.after_sale_id','LEFT'],
        ];
        $query->join($join);
        //商家ID
        if (isset($params['store_id']) && !empty($params['store_id'])){
            $query->where('tc.store_id', '=', $params['store_id']);
        }
        //纠纷类型
        if (isset($params['after_sale_type']) && !empty($params['after_sale_type'])){
            $query->where('tc.after_sale_type', '=', $params['after_sale_type']);
        }
        //纠纷状态
        if (isset($params['complaint_status']) && !empty($params['complaint_status'])){
            $query->where('tc.complaint_status', '=', $params['complaint_status']);
        }
        //申请时间
        if (
            (isset($params['create_on_start']) && !empty($params['create_on_start']))
            && (isset($params['create_on_end']) && !empty($params['create_on_end']))
        ){
            $query->where('add_time', '>=', $params['create_on_start']);
            $query->where('add_time', '<=', $params['create_on_end']);
        }
        //分页参数设置
        $page_size = isset($params['page_size']) ? (int)$params['page_size'] : 10;
        $page = isset($params['page']) ? (int)$params['page'] : 1;
        $path = isset($params['path']) ? $params['path'] : null;
        //获取数据
        $response = $query->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path])
            ->each(function ($item, $key){
                //纠纷类型
                $after_sale_type_arr = Base::getOrderAfterSaleType($item['after_sale_type']);
                $item['after_sale_type_str'] = isset($after_sale_type_arr['name'])?$after_sale_type_arr['name']:'-';
                //售后状态
                $after_sale_status = config('after_sale_status');
                $status_str = '-';
                foreach ($after_sale_status as $status){
                    if ($item['after_sale_status'] == $status['code']){
                        $status_str = $status['name'];
                        break;
                    }
                }
                $item['after_sale_status_str'] = $status_str;
                //平台介入
                $item['is_platform_intervention_str'] = $item['is_platform_intervention'] == 1?'已介入':'未介入';
                //纠纷状态
                $complaint_status = config('complaint_status');
                $complaint_status_str = '-';
                foreach ($complaint_status as $info){
                    if ($item['complaint_status'] == $info['code']){
                        $complaint_status_str = $info['name'];
                        break;
                    }
                }
                $item['complaint_status_str'] = $complaint_status_str;
                return $item;
            });
        $Page = $response->render();
        $data = $response->toArray();
        $data['Page'] = $Page;
        return $data;
    }

    /**
     * 根据条件获取订单售后申请详情数据
     * @param array $where 条件
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getApplyItemDataByWhere(array $where){
        $field = 'product_name,product_id,sku_id,sku_num,product_img,product_nums,product_price';
        return $this->db->table($this->table_item)->where($where)->field($field)->select();
    }

    /**
     * 根据条件更新 售后处理主表 信息
     * @param array $where 添加
     * @param array $up_data 要更新的数据
     * @return int|string
     */
    public function updateApplyDataByWhere(array $where, array $up_data){
        return $this->db->table($this->table)->where($where)->update($up_data);
    }

    /**
     * 增加“订单售后申请操作记录”数据
     * @param array $data 要增加的数据
     * @return int|string
     */
    public function addApplyLogData(array $data){
        return $this->db->table($this->table_log)->insert($data);
    }

    /**
     * 获取RAM订单数据
     * @param $after_sale_id 售后订单ID
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getRamPostData($after_sale_id){
        return $this->getApplyItemDataByWhere(['after_sale_id'=>$after_sale_id]);
    }

    /**
     * 获取售后订单数量
     * add 20190415 kevin
     * @return mixed
     */
    public function getUserAfterSaleCount($where){
        return $this->db->table($this->table)->where($where)->field("type,count(after_sale_id) type_number")->group("type")->select();
    }

    /**
     * 退款sku详情
     * [table_order_refund_item description]
     * @return [type] [description]
     */
    public function order_refund_item($data){
        return $this->db->table($this->table_order_refund_item)->insert($data);
    }

    /*
     * 添加订单退款
     * add 20190415 kevin
     * */
    public function saveOrderRefund($data,$where=''){
        if(!isset($data['refund_id']) && empty($where)){
            $tran = $this->db->transaction(function () use ($data,$where){
                /*获取退款中金额是否大于0，大于0不能再次提交退款 20190505 kevin*/
                $order_where['order_number'] = $data['order_number'];
                $refunding_amount = $this->db->table($this->table_order)->where($order_where)->value("refunding_amount");
                if($refunding_amount>0){
                    Log::record('订单'.$data['order_number'].'有退款payment未返回成功订单');
                    return -98;exit;
                }
                //如果已经提交了退款申请，状态为，1退款中，则不能再次提交审核 20190416 kevin
                $refund_where['order_number'] = $data['order_number'];
                $refund_where['status'] = ['in',1];
                $refund_data = $this->db->table($this->table_order_refund)->where($refund_where)->find();
                if (!empty($refund_data)){
                    Log::record('订单'.$data['order_number'].'重复提交退款申请');
                    return -99;exit;
                }
                $data['add_time'] = time();
                $data['refund_number'] = createNumner();
                $item = isset($data['item'])?$data['item']:'';
                unset($data['item']);
                unset($data['create_ip']);
                $res = $this->db->table($this->table_order_refund)->insertGetId($data);
                if($item){
                    foreach ($item as $key=>$value){
                        $value['refund_id'] = $res;
                        $this->db->table($this->table_order_refund_item)->insertGetId($value);
                    }
                }
                return $res;
            });
            return $tran;
        }else{
            $tran = $this->db->transaction(function () use ($data,$where){
                $data['edit_time'] = time();
                $item = $data['item'];
                unset($data['item']);
                $res = $this->db->table($this->table_order_refund)->where($where)->update($data);
                if($item){
                    $this->db->table($this->table_order_refund_item)->where('refund_id',$data['refund_id'])->delete();
                    foreach ($item as $key=>$value){
                        $value['refund_id'] = $data['refund_id'];
                        $this->db->table($this->table_order_refund_item)->insertGetId($value);
                    }
                }
                return $res;
            });
            return $tran;
        }
        return false;
    }

    /*获取退款详情
    *add 20190418 kevin
    */
    public function getOrderRefundInfo($where){
        $res = $this->db->table($this->table_order_refund)->where($where)->order("refund_id","DESC")->find();
        return $res;
    }

    /**
     * 根据条件获取退款数据
     * @param array $where
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getOrderRefundInfoByWhere(array $where){
        $query = $this->db->table($this->table_order_refund);
        if (isset($where['refund_id'])){
            $query->where(['refund_id'=>$where['refund_id']]);
        }
        return $query->select();
    }

    /**
     * 根据条件更新退款订单表数据
     * @param array $where 条件
     * @param array $up_data 要更新的数据
     * @return int|string
     */
    public function updateOrderRefundByParams(array $where, array $up_data){
        return $this->db->table($this->table_order_refund)->where($where)->update($up_data);
    }

    /*
     *获取订单退款列表
     * */
    public function getOrderRefundList($data){
        $where = array();
        if(!empty($data['order_number'])){
            $where['order_number'] = ['IN',$data['order_number']];
        }
        if(!empty($data['customer_name'])){
            $where['customer_name'] = ['IN',explode(",",$data['customer_name'])];
        }
        if(!empty($data['status'])){
            $where['status'] = $data['status'];
        }

        if(!empty($data['startTime']) && !empty($data['endTime'])){
            $where['add_time'] = ['BETWEEN',[strtotime($data['startTime']),strtotime($data['endTime'])]];
        }else{
            if(isset($data['startTime']) && !empty($data['startTime'])){
                $where['add_time'] = strtotime($data['startTime']);
            }
            if(isset($data['endTime']) && !empty($data['endTime'])){
                $where['add_time'] = strtotime($data['endTime']);
            }
        }
        if(!empty($data['is_page'])){
            $page_size = !empty($data['page_size'])?$data['page_size']:20;
            $page = !empty($data['page'])?$data['page']:1;
            $path = !empty($data['path'])?$data['path']:'';
            $order_list = $this->db->table($this->table_order_refund)
                ->where($where)
                ->order("refund_id","DESC")
                ->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path,'query'=>!empty($data)?$data:$where]);
            $Page = $order_list->render();
            $res = $order_list->toArray();
            $res['Page'] = $Page;
        }else{
            $res = $this->db->table($this->table_order_refund)->where($where)->order("refund_id","DESC")->select();
        }
        return $res;
    }

    /*
     * 保存退款详情
     * */
    public function save_order_refund_item($data){
        return $this->db->table($this->table_order_refund_item)->insert($data);
    }

    /*
    * 获取用户退款申请单
    * */
    public function getAdminOrderRefundList($where,$page_size=10,$page=1,$path='',$order='',$page_query=''){
        $res = $this->db->table($this->table_order_refund)
            ->alias("or")
            ->join($this->table_order." so","or.order_id = so.order_id")
            ->field("or.*,so.order_status,so.payment_status,lock_status,goods_count,currency_code,total_amount,grand_total,captured_amount_usd,refunded_amount,pay_time,shipments_time,shipments_complete_time,so.country,so.country_code,goods_total")
            ->where($where)->order($order)->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path,'query'=>!empty($page_query)?$page_query:$where]);
        $Page = $res->render();
        $data = $res->toArray();
        if(!empty($data)){
            foreach ($data['data'] as $key=>$value){
                //币种
                $data['data'][$key]['currency_value'] = getCurrency('',$value['currency_code']);
                $item_where['refund_id'] = $value['refund_id'];
                $data['data'][$key]['order_item'] = $this->db->table($this->table_order_refund_item)->where($item_where)->field("sku_id,sku_num,product_price,product_name,product_nums,product_img,product_attr_ids,product_attr_desc")->select();
            }
        }
        $data['Page'] = $Page;
        return $data;
    }

    /*
 * 后台获取用户退款申请详情
 * */
    public function getAdminOrderRefundInfo($where){
        $data = $this->db->table($this->table_order_refund)
            ->alias("or")
            ->join($this->table_sales_order." so","or.order_id = so.order_id")
            ->order("or.refund_id","DESC")
            ->field("or.*,so.order_status,so.payment_status,lock_status,goods_count,currency_code,total_amount,grand_total,captured_amount_usd,refunded_amount,pay_time,shipments_time,shipments_complete_time")
            ->where($where)->find();
        if(!empty($data)){
            $oa_item_where['refund_id'] = $data['refund_id'];
            $address_where['order_id'] = $data['order_id'];
            $data['shipping_address'] = $this->db->table($this->table_shipping_address)->where($address_where)->find();
            $data['item'] = $this->db->table($this->table_order_refund_item)->where($oa_item_where)->field("refund_item_id,refund_id,sku_id,sku_num,product_name,product_price,product_nums,product_img,product_attr_ids,product_attr_desc")->select();
            /*$log_where['refund_id'] = $data['refund_id'];
            $data['log'] = $this->getOrderRefundLog($log_where);*/
        }
        return $data;
    }

    /*
* 获取订单退款记录
* */
    public function getOrderRefundLog($where){
        $res = $this->db->table($this->table_order_refund_log)->where($where)->order(['add_time'=>'asc','log_id'=>'desc'])->select();
        return $res;
    }

    /**
     * 导出退款订单订单汇总信息
     * [OrderShutDown description]
     * @auther kevin  2019-10-22
     */
    public function getOrderRefundSummary($data = array()){
        $where['txn_type'] = array('in',['Refund','Reversed']) ;//只查退款
        $where['txn_result'] = 'Success';
        //退款状态
        if(!empty($data['currency_code'])){
            $where['ot.currency_code'] = $data['currency_code'];
        }
        if(!empty($data['order_number'])){
            $where['ot.order_number'] = ['IN',$data['order_number']];
        }
        if(!empty($data['payment_txn_id'])){
            $where['ot.payment_txn_id'] = ['IN',$data['payment_txn_id']];
        }
        if(!empty($data['startTime']) && !empty($data['endTime'])){
            $where['ot.create_on'] = ['BETWEEN',[strtotime($data['startTime']),strtotime($data['endTime'])]];
        }else{
            if(isset($data['startTime']) && !empty($data['startTime'])){
                $where['ot.create_on'] = strtotime($data['startTime']);
            }
            if(isset($data['endTime']) && !empty($data['endTime'])){
                $where['ot.create_on'] = strtotime($data['endTime']);
            }
        }
        $page_size = !empty($data['page_size'])?$data['page_size']:20;
        $page = !empty($data['page'])?$data['page']:1;
        $path = !empty($data['path'])?$data['path']:'';
        $order_list = $this->db->table($this->table_sales_txn)
            ->alias("ot")
            ->join($this->table_order_refund." or","ot.order_id=or.order_id","LEFT")
            ->join($this->table_sales_order_refund_operation." oro","oro.refund_id=or.refund_id","LEFT")
            ->where($where)
            ->field('txn_id,ot.order_id,notification_id,ot.order_number,ot.amount,ot.currency_code,ot.payment_txn_id,ot.payment_method,ot.create_on,txn_time,txn_type,or.remarks,or.initiator,oro.operator_type,oro.operator_id,oro.operator_name')
            ->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path,'query'=>!empty($data)?$data:$where]);
        $Page = $order_list->render();
        $data = $order_list->toArray();
        $data['Page'] = $Page;
        return $data;
    }

    //更改退款记录信息
    public function updateOrderRefund($data){
        if(!empty($data['refund_id'])){
            $where['refund_id'] = ['IN',$data['refund_id']];
        }
        if(!empty($data['status'])){
            $update_data['status'] = $data['status'];
        }
        if(!empty($data['audit_remarks'])){
            $update_data['audit_remarks'] = $data['audit_remarks'];
        }
        if(!empty($data['audit_admin'])){
            $update_data['audit_admin'] = $data['audit_admin'];
        }
        if(!empty($data['audit_admin_id'])){
            $update_data['audit_admin_id'] = $data['audit_admin_id'];
        }
        $update_data['edit_time'] = time();
        $res = $this->db->table($this->table_order_refund)->where($where)->update($update_data);
        return $res;
    }
}