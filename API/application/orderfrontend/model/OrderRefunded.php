<?php
namespace app\orderfrontend\model;
use think\Log;
use think\Model;
use think\Db;
/**
 * 订单退款模型
 * @author
 * @version Kevin 2018/3/25
 */
class OrderRefunded extends Model{
    private $db;
    private $order = "dx_sales_order";
    private $order_item = "dx_sales_order_item";
    private $order_message = "dx_sales_order_message";
    private $shipping_address = "dx_order_shipping_address";
    private $order_refunded = "dx_order_after_sale_apply";
    private $refunded_log = "dx_refunded_log";
    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_order');
    }
    /*
    * 保存订单退款
    * */
    public function saveOrderRefunded($data,$where=''){
        if(!isset($data['refunded_id']) && empty($where)){
            $data['add_time'] = time();
            $data['refunded_number'] = createNumner();
            $res = $this->db->table($this->order_refunded)->insert($data);
        }else{
            $data['edit_time'] = time();
            $res = $this->db->table($this->order_refunded)->where($where)->update($data);
        }
        return $res;
    }

    /*
    * 获取用户退款申请单
    * */
    public function getOrderRefundedList($where,$page_size=10,$page=1,$path='',$order){
        $res = $this->db->table($this->order_refunded)
            ->alias("or")
            ->join($this->order." so","or.order_id = so.order_id")
            ->field("or.*,so.order_status,so.payment_status,lock_status,goods_count,currency_code,total_amount,grand_total,captured_amount_usd,refunded_amount,pay_time,shipments_time,shipments_complete_time")
            ->where($where)->order($order)->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path,'query'=>$where]);
        $Page = $res->render();
        $data = $res->toArray();
        if(!empty($data)){
            foreach ($data['data'] as $key=>$value){
                //售后状态
                $after_sale_status = config('after_sale_status');
                $status_str = '-';
                foreach ($after_sale_status as $status){
                    if ($value['status'] == $status['code']){
                        $status_str = $status['en_name'];
                        break;
                    }
                }
                $data['data'][$key]['status_str'] = $status_str;
                //币种
                $data['data'][$key]['currency_value'] = getCurrency('',$value['currency_code']);
                $item_where['order_id'] = $value['order_id'];
                $data['data'][$key]['order_item'] = $this->db->table($this->order_item)->where($item_where)->field("sku_id,sku_num,product_price,product_name,product_nums,product_img,product_attr_ids,product_attr_desc")->select();
            }
        }
        $data['Page'] = $Page;
        return $data;
    }

    /*
     * 获取用户退款申请详情
     * */
    public function getOrderRefundedInfo($where){
        $data = $this->db->table($this->order_refunded)
            ->alias("or")
            ->join($this->order." so","or.order_id = so.order_id")
            ->field("or.*,so.order_status,so.payment_status,lock_status,goods_count,currency_code,total_amount,grand_total,captured_amount_usd,refunded_amount,pay_time,shipments_time,shipments_complete_time,modify_on,shipping_insurance_enabled,goods_total,create_on")
            ->where($where)->find();
            $item_where['or.after_sale_id'] = $data['after_sale_id'];
            $data['order_item'] = $this->db->table($this->order_item)->where($item_where)->field("after_sale_id,sku_id,sku_num,product_price,product_name,product_img,product_attr_ids,product_attr_desc")->select();
        return $data;
    }

    /*
   * 保存订单退款记录
   * */
    public function addRefundedLog($data=''){
        $data['add_time'] = time();
        $res = $this->db->table($this->refunded_log)->insertGetId($data);
        return $res;
    }

    /*
  * 保存订单退款记录
  * */
    public function getRefundedLog($where){
        $res = $this->db->table($this->refunded_log)->where($where)->order("log_id desc")->select();
        return $res;
    }
}