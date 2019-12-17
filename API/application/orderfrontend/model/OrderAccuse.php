<?php
namespace app\orderfrontend\model;
use think\Log;
use think\Model;
use think\Db;
/**
 * 订单投诉
 * @author
 * @version Kevin 2018/3/25
 */
class OrderAccuse extends Model{
    private $db;
    private $order = "dx_sales_order";
    private $order_item = "dx_sales_order_item";
    private $order_message = "dx_sales_order_message";
    private $shipping_address = "dx_order_shipping_address";
    private $order_after_sale_apply = "dx_order_after_sale_apply";
    private $order_after_sale_apply_item = "dx_order_after_sale_apply_item";
    private $order_after_sale_apply_log = "dx_order_after_sale_apply_log";
    private $return_product_expressage = "dx_return_product_expressage";
    private $order_complaint = "dx_order_complaint";
    private $order_accuse = "dx_order_accuse";
    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_order');
    }

    /*
     * 保存投诉订单
     * */
    public function saveOrderAccuse($data){
        if(!isset($data['accuse_id'])){
            $data['accuse_number'] = createNumner();
            $data['add_time'] = time();
            $res = $this->db->table($this->order_accuse)->insertGetId($data);
        }
        return $res;
    }

    /*
     * 获取投诉订单列表
     * */
    public function getOrderAccuseList($where,$page_size=10,$page=1,$path='',$order){
        $res = $this->db->table($this->order_accuse)->field("accuse_id,accuse_number,order_id,order_number,order_number,customer_id,customer_name,store_id,store_name,accuse_reason,accuse_status,imgs,remarks,add_time,edit_time")
            ->where($where)->order($order)->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path,'query'=>$where]);
        $Page = $res->render();
        $data = $res->toArray();
        $data['Page'] = $Page;
        return $data;
    }
}