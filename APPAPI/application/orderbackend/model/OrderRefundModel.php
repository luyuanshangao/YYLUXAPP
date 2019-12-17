<?php
namespace app\orderbackend\model;

use app\common\helpers\BaseApi;
use app\orderbackend\services\Base;
use think\Model;
use think\Db;

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

}