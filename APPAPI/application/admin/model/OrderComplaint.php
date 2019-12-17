<?php
namespace app\admin\model;
use think\Model;
use think\Db;
/**
 * 订单投诉模型
 * @author
 * @version Kevin 2018/3/25
 */
class OrderComplaint extends Model{
    private $db;
    private $order_complaint = "dx_order_complaint";
    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_admin');
    }
    /*
    * 保存订单投诉
    * */
    public function saveOrderComplaint($data,$where=''){
        if(!isset($data['complaint_id']) && empty($where)){
            $data['add_time'] = time();
            $data['complaint_number'] = createNumner();
            $res = $this->db->table($this->order_complaint)->insert($data);
        }else{
            $data['edit_time'] = time();
            $res = $this->db->table($this->order_complaint)->where($where)->update($data);
        }
        return $res;
    }

    /*
    * 获取用户投诉申请单
    * */
    public function getOrderComplaintList($where,$page_size=10,$page=1,$path='',$order){
        $res = $this->db->table($this->order_complaint)
            ->where($where)->order($order)->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path,'query'=>$where]);
        $Page = $res->render();
        $data = $res->toArray();
        $data['Page'] = $Page;
        return $data;
    }

    /*
     * 获取用户投诉申请详情
     * */
    public function getOrderComplaintInfo($where){
        $data = $this->db->table($this->order_complaint)
            ->where($where)->find();
        return $data;
    }
}